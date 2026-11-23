<?php

/**
 * Einmalige Übernahme einer Schule aus der früheren Mehrschul-Fassung
 * (MariaDB: zentrale DB + Schul-DB) in die SQLite-Datenbank der heutigen Fassung.
 *
 * Ziel ist eine FRISCH angelegte Datenbank (abiplaner:vorbereiten einmal gelaufen,
 * Ersteinrichtung NICHT durchlaufen). Für jede Tabelle des Ziels gilt:
 *   - gibt es sie in der Schul-DB, kommen die Zeilen von dort, sonst aus der
 *     zentralen DB; gibt es sie in keiner, bleibt der Inhalt des Ziels (Stammdaten);
 *   - übernommen werden die Spalten, die es auf beiden Seiten gibt;
 *   - Laufzeit-Tabellen (Sitzungen, Cache, Jobs, Migrationen) bleiben unberührt.
 * Absenzen ohne vorhandenen Schüler werden weggelassen (Entscheidung 23.11.2026).
 *
 *   php database/uebernahme/mariadb-nach-sqlite.php <ziel.sqlite> [--probe]
 *
 * Verbindung über Umgebungsvariablen: UEBERNAHME_HOST, UEBERNAHME_PORT (3306),
 * UEBERNAHME_BENUTZER, UEBERNAHME_KENNWORT, UEBERNAHME_ZENTRAL (abiplaner),
 * UEBERNAHME_SCHULNUMMER (Pflicht), UEBERNAHME_SCHULE (school_<Schulnummer>).
 * --probe: alles durchrechnen, am Ende zurückrollen.
 */

declare(strict_types=1);

$ziel = $argv[1] ?? '';
$probe = in_array('--probe', $argv, true);
if ($ziel === '' || ! is_file($ziel)) {
    fwrite(STDERR, "Aufruf: php mariadb-nach-sqlite.php <ziel.sqlite> [--probe]\n");
    exit(1);
}

$env = fn (string $name, string $vorgabe = ''): string => (string) (getenv($name) ?: $vorgabe);
$schulnummer = $env('UEBERNAHME_SCHULNUMMER');
if ($schulnummer === '') {
    fwrite(STDERR, "UEBERNAHME_SCHULNUMMER fehlt.\n");
    exit(1);
}

function mysql(string $datenbank, callable $env): PDO
{
    return new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env('UEBERNAHME_HOST', '127.0.0.1'), $env('UEBERNAHME_PORT', '3306'), $datenbank),
        $env('UEBERNAHME_BENUTZER', 'root'),
        $env('UEBERNAHME_KENNWORT'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

$quellen = [
    'schule' => mysql($env('UEBERNAHME_SCHULE', 'school_'.$schulnummer), $env),
    'zentral' => mysql($env('UEBERNAHME_ZENTRAL', 'abiplaner'), $env),
];
$sqlite = new PDO('sqlite:'.$ziel, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$UNBERUEHRT = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
    'password_reset_tokens', 'domains', 'sqlite_sequence'];

$tabellenQuelle = [];
foreach ($quellen as $name => $pdo) {
    $tabellenQuelle[$name] = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
}
$spaltenQuelle = fn (PDO $pdo, string $t): array => array_column($pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
$spaltenZiel = fn (string $t): array => array_column($sqlite->query("PRAGMA table_info(\"$t\")")->fetchAll(PDO::FETCH_ASSOC), 'name');

$zielTabellen = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$hinweise = [];
$summe = 0;

$sqlite->exec('PRAGMA foreign_keys = OFF');
$sqlite->beginTransaction();

printf("%-42s %-8s %8s %8s\n", 'Tabelle', 'Quelle', 'Quelle', 'Ziel');
foreach ($zielTabellen as $t) {
    if (in_array($t, $UNBERUEHRT, true)) {
        continue;
    }
    $inSchule = in_array($t, $tabellenQuelle['schule'], true);
    $inZentral = in_array($t, $tabellenQuelle['zentral'], true);
    if (! $inSchule && ! $inZentral) {
        $n = (int) $sqlite->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn();
        printf("%-42s %-8s %8s %8d\n", $t, '–', '–', $n);
        continue;
    }
    $herkunft = $inSchule ? 'schule' : 'zentral';
    $pdo = $quellen[$herkunft];
    if ($inSchule && $inZentral) {
        $nZ = (int) $quellen['zentral']->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        if ($nZ > 0) {
            $hinweise[] = "$t: auch in der zentralen DB ($nZ Zeilen) – genommen wurde die Schul-DB";
        }
    }

    $q = $spaltenQuelle($pdo, $t);
    $z = $spaltenZiel($t);
    $gemeinsam = array_values(array_intersect($z, $q));
    if ($fehlend = array_diff($z, $q)) {
        $hinweise[] = "$t: im Ziel ohne Quelle (Vorgabewert): ".implode(', ', $fehlend);
    }
    if ($entfallen = array_diff($q, $z)) {
        $hinweise[] = "$t: in der Quelle, im Ziel nicht mehr vorhanden: ".implode(', ', $entfallen);
    }

    $bedingung = '';
    if ($t === 'absences' && in_array('students', $tabellenQuelle['schule'], true)) {
        $bedingung = ' WHERE student_id IN (SELECT id FROM students)';
        $weg = (int) $pdo->query('SELECT COUNT(*) FROM absences WHERE student_id NOT IN (SELECT id FROM students)')->fetchColumn();
        $hinweise[] = "absences: $weg Absenzen ohne vorhandenen Schüler weggelassen";
    }

    $sqlite->exec("DELETE FROM \"$t\"");
    $liste = implode(', ', array_map(fn ($s) => "\"$s\"", $gemeinsam));
    $platz = implode(', ', array_fill(0, count($gemeinsam), '?'));
    $einfuegen = $sqlite->prepare("INSERT INTO \"$t\" ($liste) VALUES ($platz)");
    $zeilen = $pdo->query('SELECT '.implode(', ', array_map(fn ($s) => "`$s`", $gemeinsam))." FROM `$t`".$bedingung)->fetchAll(PDO::FETCH_NUM);
    foreach ($zeilen as $zeile) {
        $einfuegen->execute($zeile);
    }
    $n = count($zeilen);
    $summe += $n;
    $nQ = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    printf("%-42s %-8s %8d %8d%s\n", $t, $herkunft, $nQ, $n, $nQ !== $n && $t !== 'absences' ? '  ← ABWEICHUNG' : '');
}

// Benutzer gehören zur einen Schule dieser Installation.
if (in_array('school_id', $spaltenZiel('users'), true)) {
    $sqlite->prepare('UPDATE users SET school_id = ?')->execute([$schulnummer]);
}
$schulen = (int) $sqlite->query('SELECT COUNT(*) FROM schools')->fetchColumn();
if ($schulen !== 1) {
    $hinweise[] = "schools: $schulen Zeilen – erwartet genau eine Schule!";
}

$verstoesse = $sqlite->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
$jeTabelle = [];
foreach ($verstoesse as $v) {
    $jeTabelle[$v['table'].' → '.$v['parent']] = ($jeTabelle[$v['table'].' → '.$v['parent']] ?? 0) + 1;
}

echo "\nÜbernommen: $summe Zeilen.\n";
foreach ($hinweise as $h) {
    echo "  · $h\n";
}
echo $jeTabelle ? "\nFremdschlüssel-Verstöße:\n" : "\nFremdschlüssel: keine Verstöße.\n";
foreach ($jeTabelle as $wo => $anzahl) {
    echo "  ! $wo: $anzahl\n";
}

if ($probe) {
    $sqlite->rollBack();
    echo "\nPROBE: nichts gespeichert.\n";
    exit(0);
}
$sqlite->commit();
$sqlite->exec('PRAGMA foreign_keys = ON');
$ok = $sqlite->query('PRAGMA integrity_check')->fetchColumn();
echo "\nGespeichert. integrity_check: $ok\n";
exit($ok === 'ok' && ! $jeTabelle ? 0 : 2);
