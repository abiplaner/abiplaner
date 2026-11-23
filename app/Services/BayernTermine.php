<?php

namespace App\Services;

use App\Support\SchoolYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Die bayernweiten Termine (23.11.2026).
 *
 * Quelle sind die Dateien `database/data/termine/<schuljahr>.json`, je
 * Schuljahr eine: Ferien, Feiertage, Fristen der Oberstufe und die Termine
 * des Abiturs (schriftliche Prüfungstage je Slot, Kolloquiumswochen).
 *
 * **Wie neue Termine ankommen:** Für ein neues Schuljahr kommt eine neue
 * Datei ins Repo, für eine Berichtigung wird die vorhandene geändert. Beides
 * geht mit dem nächsten Programm-Update an alle Installationen, weil
 * `abiplaner:vorbereiten` bei jedem Start `termine:einspielen` aufruft.
 *
 * Der Abgleich ist idempotent und erkennt jeden Termin an seinem Schlüssel
 * („2026-27/pfingstferien", aus Schuljahr und Titel). Ein geändertes Datum
 * wird deshalb berichtigt statt verdoppelt, und ein aus der Datei entfernter
 * Termin verschwindet. Termine ohne Schlüssel (von Hand angelegt) bleiben
 * unberührt — mit einer Ausnahme: Ein Eintrag der früheren Seeder mit
 * gleichem Titel im selben Schuljahr wird übernommen (bekommt den Schlüssel).
 *
 * Prüfungszeiträume legt der Abgleich nur auf Wunsch an (Ersteinrichtung),
 * und nur bis zum laufenden Schuljahr: Ein neuer Zeitraum würde sofort zum
 * aktuellen (CurrentExamPeriodService nimmt den jüngsten) und einer Schule
 * mitten im Jahr die Planung unter den Füßen wegziehen. Für später gibt es
 * zeitraumVorschlag(), der die Maske „Neuer Prüfungszeitraum" vorbelegt.
 */
class BayernTermine
{
    /**
     * Feldwerte je Terminart. Übernommen aus den früheren Seedern
     * (GlobalCalendarEventsSeeder, Abitur2027Seeder, Kalender202627Seeder).
     *
     * @var array<string, array{event_type: string, color: string, is_recurring: bool, priority: int, stufen: list<int>|null}>
     */
    public const ARTEN = [
        'ferien' => ['event_type' => 'holiday', 'color' => '#A855F7', 'is_recurring' => false, 'priority' => 5, 'stufen' => null],
        'feiertag' => ['event_type' => 'public_holiday', 'color' => '#EF4444', 'is_recurring' => true, 'priority' => 3, 'stufen' => null],
        // Kein gesetzlicher Feiertag oder ein Sonntag.
        'freier_tag' => ['event_type' => 'public_holiday', 'color' => '#EF4444', 'is_recurring' => false, 'priority' => 2, 'stufen' => null],
        'gedenktag' => ['event_type' => 'info', 'color' => '#6B7280', 'is_recurring' => false, 'priority' => 3, 'stufen' => null],
        'unterrichtsbeginn' => ['event_type' => 'info', 'color' => '#10B981', 'is_recurring' => true, 'priority' => 5, 'stufen' => [11, 12, 13]],
        'schriftlich' => ['event_type' => 'deadline', 'color' => '#3B82F6', 'is_recurring' => true, 'priority' => 10, 'stufen' => [13]],
        'ausweichtermin' => ['event_type' => 'info', 'color' => '#93C5FD', 'is_recurring' => false, 'priority' => 6, 'stufen' => [13]],
        'kolloquium' => ['event_type' => 'deadline', 'color' => '#10B981', 'is_recurring' => true, 'priority' => 10, 'stufen' => [13]],
        'praktisch' => ['event_type' => 'deadline', 'color' => '#14B8A6', 'is_recurring' => false, 'priority' => 7, 'stufen' => [13]],
        'zusatzpruefung' => ['event_type' => 'deadline', 'color' => '#10B981', 'is_recurring' => true, 'priority' => 9, 'stufen' => [13]],
        'entlassung' => ['event_type' => 'administration', 'color' => '#059669', 'is_recurring' => true, 'priority' => 10, 'stufen' => [13]],
        'sonderpruefung' => ['event_type' => 'deadline', 'color' => '#6366F1', 'is_recurring' => false, 'priority' => 7, 'stufen' => [13]],
        'zeugnis_12' => ['event_type' => 'administration', 'color' => '#8B5CF6', 'is_recurring' => true, 'priority' => 8, 'stufen' => [12]],
        'zeugnis_13' => ['event_type' => 'administration', 'color' => '#8B5CF6', 'is_recurring' => true, 'priority' => 8, 'stufen' => [13]],
        'abiturfrist' => ['event_type' => 'deadline', 'color' => '#DC2626', 'is_recurring' => true, 'priority' => 10, 'stufen' => [13]],
        'seminararbeit' => ['event_type' => 'deadline', 'color' => '#DC2626', 'is_recurring' => true, 'priority' => 10, 'stufen' => [13]],
        'kurswahl_aenderung' => ['event_type' => 'deadline', 'color' => '#F59E0B', 'is_recurring' => false, 'priority' => 5, 'stufen' => [11, 12]],
        'kursprogramm' => ['event_type' => 'deadline', 'color' => '#F59E0B', 'is_recurring' => false, 'priority' => 7, 'stufen' => [11]],
        'bewerber' => ['event_type' => 'deadline', 'color' => '#F59E0B', 'is_recurring' => true, 'priority' => 8, 'stufen' => [13]],
        'vergabe' => ['event_type' => 'deadline', 'color' => '#F59E0B', 'is_recurring' => false, 'priority' => 7, 'stufen' => [13]],
    ];

    /** @var list<array<string, mixed>>|null */
    private ?array $jahre = null;

    public function verzeichnis(): string
    {
        return database_path('data/termine');
    }

    /**
     * Alle Schuljahre, aufsteigend. Eine fehlerhafte Datei bricht mit einer
     * Meldung ab, die Datei und Fehler nennt — lieber laut beim Start als
     * stillschweigend halbe Termine.
     *
     * @return list<array{datei: string, schuljahr: string, praefix: string, quellen: list<string>, hinweise: list<string>, abitur: array{jahr: int, name: string, kolloquium: list<array{0: string, 1: string}>, schriftlich: list<array{datum: string, titel: string, slots: list<int>}>}|null, termine: list<array<string, mixed>>}>
     */
    public function jahre(): array
    {
        if ($this->jahre !== null) {
            return $this->jahre;
        }

        $dateien = glob($this->verzeichnis() . '/*.json') ?: [];
        sort($dateien);
        $jahre = [];

        foreach ($dateien as $datei) {
            $daten = json_decode((string) file_get_contents($datei), true);
            $name = basename($datei);

            if (!is_array($daten)) {
                throw new RuntimeException("Termine: {$name} ist kein gültiges JSON (" . json_last_error_msg() . ').');
            }

            $schuljahr = (string) ($daten['schuljahr'] ?? '');

            if (!SchoolYear::isValid($schuljahr) || str_replace('/', '-', $schuljahr) . '.json' !== $name) {
                throw new RuntimeException("Termine: {$name} muss das Schuljahr „" . str_replace(['-', '.json'], ['/', ''], $name) . '" tragen.');
            }

            $jahre[] = [
                'datei' => $name,
                'schuljahr' => $schuljahr,
                'praefix' => str_replace('/', '-', $schuljahr),
                'quellen' => array_values($daten['quellen'] ?? []),
                'hinweise' => array_values($daten['hinweise'] ?? []),
                'abitur' => $daten['abitur'] ?? null,
                'termine' => array_values($daten['termine'] ?? []),
            ];
        }

        return $this->jahre = $jahre;
    }

    /**
     * Die Kalendereinträge eines Schuljahres: die Termine der Datei plus die
     * aus dem Abitur-Block erzeugten (schriftliche Tage, Kolloquiumswochen).
     *
     * @param array<string, mixed> $jahr
     * @return list<array{schluessel: string, art: string, titel: string, von: string, bis: string|null, beschreibung: string|null, stufen: list<int>|null, schultag: bool}>
     */
    public function eintraege(array $jahr): array
    {
        $roh = $jahr['termine'];

        foreach ($jahr['abitur']['schriftlich'] ?? [] as $tag) {
            $roh[] = ['art' => 'schriftlich', 'titel' => $tag['titel'], 'von' => $tag['datum']];
        }

        foreach ($jahr['abitur']['kolloquium'] ?? [] as $i => [$von, $bis]) {
            $roh[] = ['art' => 'kolloquium', 'titel' => ($i + 1) . '. Kolloquiumswoche', 'von' => $von, 'bis' => $bis];
        }

        $eintraege = [];

        foreach ($roh as $termin) {
            $art = (string) ($termin['art'] ?? '');
            $titel = trim((string) ($termin['titel'] ?? ''));
            $von = (string) ($termin['von'] ?? '');
            $bis = isset($termin['bis']) ? (string) $termin['bis'] : null;
            $wo = "Termine {$jahr['datei']}: „{$titel}\"";

            if (!isset(self::ARTEN[$art])) {
                throw new RuntimeException("{$wo} hat die unbekannte Art „{$art}\".");
            }

            if ($titel === '' || !$this->istDatum($von) || ($bis !== null && (!$this->istDatum($bis) || $bis < $von))) {
                throw new RuntimeException("{$wo} braucht Titel und gültige Daten (von ≤ bis, Format JJJJ-MM-TT).");
            }

            $schluessel = $jahr['praefix'] . '/' . Str::slug($titel);

            if (isset($eintraege[$schluessel])) {
                throw new RuntimeException("{$wo} steht doppelt im Schuljahr — Titel müssen je Schuljahr eindeutig sein.");
            }

            $eintraege[$schluessel] = [
                'schluessel' => $schluessel,
                'art' => $art,
                'titel' => $titel,
                'von' => $von,
                'bis' => $bis,
                'beschreibung' => isset($termin['beschreibung']) ? (string) $termin['beschreibung'] : null,
                'stufen' => isset($termin['stufen']) ? array_map('intval', $termin['stufen']) : self::ARTEN[$art]['stufen'],
                'schultag' => (bool) ($termin['schultag'] ?? false),
            ];
        }

        return array_values($eintraege);
    }

    /**
     * Plausibilitätsprobe eines Schuljahres. Findet Zahlendreher, bevor sie
     * in der Planung auffallen:
     * - jeder Termin liegt im Schuljahr (1. August bis 31. Juli),
     * - schriftliche Prüfungstage und als `schultag` markierte Fristen liegen
     *   auf einem Unterrichtstag (kein Wochenende, keine Ferien, kein Feiertag),
     * - Anfang und Ende der Kolloquiumswochen ebenso,
     * - kein schriftlicher Slot ist zweimal vergeben.
     *
     * @param array<string, mixed> $jahr
     * @return list<string>
     */
    public function pruefen(array $jahr): array
    {
        $eintraege = $this->eintraege($jahr);
        [$beginn, $ende] = SchoolYear::range($jahr['schuljahr']);
        $frei = array_filter($eintraege, fn (array $e) => in_array(self::ARTEN[$e['art']]['event_type'], ['holiday', 'public_holiday'], true));
        $warnungen = [];

        $unterrichtsfrei = function (string $tag) use ($frei): ?string {
            if (Carbon::parse($tag)->isWeekend()) {
                return 'ein Wochenende';
            }

            foreach ($frei as $f) {
                if ($tag >= $f['von'] && $tag <= ($f['bis'] ?? $f['von'])) {
                    return $f['titel'];
                }
            }

            return null;
        };

        foreach ($eintraege as $e) {
            if ($e['von'] < $beginn->toDateString() || $e['von'] > $ende->toDateString()) {
                $warnungen[] = "{$jahr['schuljahr']}: {$e['titel']} ({$e['von']}) liegt nicht im Schuljahr.";
            }

            $zuPruefen = match (true) {
                $e['schultag'], $e['art'] === 'schriftlich' => [$e['von']],
                $e['art'] === 'kolloquium' => [$e['von'], (string) $e['bis']],
                default => [],
            };

            foreach ($zuPruefen as $tag) {
                if (($grund = $unterrichtsfrei($tag)) !== null) {
                    $warnungen[] = "{$jahr['schuljahr']}: {$e['titel']} ({$tag}) fällt auf {$grund}.";
                }
            }
        }

        $vergeben = [];

        foreach ($jahr['abitur']['schriftlich'] ?? [] as $tag) {
            foreach ($tag['slots'] as $slot) {
                if (isset($vergeben[$slot])) {
                    $warnungen[] = "{$jahr['schuljahr']}: Slot {$slot} steht am {$vergeben[$slot]} und am {$tag['datum']}.";
                }

                $vergeben[$slot] = $tag['datum'];
            }
        }

        return $warnungen;
    }

    /**
     * Gleicht die Datenbank mit den Dateien ab. Mit `$probe` wird alles
     * gerechnet und am Ende zurückgerollt — der Bericht sagt, was geschähe.
     *
     * @return array{neu: int, geaendert: int, geloescht: int, unveraendert: int, schriftlich: int, zeitraeume: list<string>, warnungen: list<string>}
     */
    public function einspielen(bool $mitZeitraeumen = false, bool $probe = false): array
    {
        $bericht = ['neu' => 0, 'geaendert' => 0, 'geloescht' => 0, 'unveraendert' => 0, 'schriftlich' => 0, 'zeitraeume' => [], 'warnungen' => []];

        DB::beginTransaction();

        try {
            foreach ($this->jahre() as $jahr) {
                $bericht['warnungen'] = array_merge($bericht['warnungen'], $this->pruefen($jahr));
                $this->gleicheKalenderAb($jahr, $bericht);
                $this->gleicheSchriftlicheTermineAb($jahr, $bericht);

                if ($mitZeitraeumen) {
                    $this->legeZeitraumAn($jahr, $bericht);
                }
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $probe ? DB::rollBack() : DB::commit();

        return $bericht;
    }

    /**
     * @param array<string, mixed> $jahr
     * @param array{neu: int, geaendert: int, geloescht: int, unveraendert: int, schriftlich: int, zeitraeume: list<string>, warnungen: list<string>} $bericht
     */
    private function gleicheKalenderAb(array $jahr, array &$bericht): void
    {
        [$beginn, $ende] = SchoolYear::range($jahr['schuljahr']);
        $jetzt = now();
        $schluessel = [];

        foreach ($this->eintraege($jahr) as $e) {
            $schluessel[] = $e['schluessel'];
            $art = self::ARTEN[$e['art']];
            $soll = [
                'schluessel' => $e['schluessel'],
                'title' => $e['titel'],
                'description' => $e['beschreibung'],
                'event_date' => $e['von'],
                'end_date' => $e['bis'],
                'event_type' => $art['event_type'],
                'grade_levels' => $e['stufen'] === null ? null : json_encode($e['stufen']),
                'color' => $art['color'],
                'is_recurring' => $art['is_recurring'],
                'priority' => $art['priority'],
            ];

            $ist = DB::table('global_calendar_events')->where('schluessel', $e['schluessel'])->first()
                // Bestand der früheren Seeder: gleicher Titel im selben Schuljahr, noch ohne Schlüssel.
                ?? DB::table('global_calendar_events')->whereNull('schluessel')->where('title', $e['titel'])
                    ->whereBetween('event_date', [$beginn->toDateString(), $ende->toDateString() . ' 23:59:59'])
                    ->orderBy('id')->first();

            if ($ist === null) {
                DB::table('global_calendar_events')->insert($soll + [
                    'start_time' => null, 'end_time' => null, 'location' => null, 'created_by' => null,
                    'created_at' => $jetzt, 'updated_at' => $jetzt,
                ]);
                $bericht['neu']++;

                continue;
            }

            if ($this->gleich((array) $ist, $soll)) {
                $bericht['unveraendert']++;

                continue;
            }

            DB::table('global_calendar_events')->where('id', $ist->id)->update($soll + ['updated_at' => $jetzt]);
            $bericht['geaendert']++;
        }

        $bericht['geloescht'] += DB::table('global_calendar_events')
            ->where('schluessel', 'like', $jahr['praefix'] . '/%')
            ->whereNotIn('schluessel', $schluessel)
            ->delete();
    }

    /**
     * @param array<string, mixed> $ist
     * @param array<string, mixed> $soll
     */
    private function gleich(array $ist, array $soll): bool
    {
        foreach ($soll as $feld => $wert) {
            $vorhanden = $ist[$feld] ?? null;

            $gleich = match ($feld) {
                'event_date', 'end_date' => ($vorhanden === null ? null : substr((string) $vorhanden, 0, 10)) === $wert,
                'is_recurring', 'priority' => (int) $vorhanden === (int) $wert,
                'grade_levels' => json_decode((string) $vorhanden, true) === ($wert === null ? null : json_decode($wert, true)),
                default => $vorhanden === $wert,
            };

            if (!$gleich) {
                return false;
            }
        }

        return true;
    }

    /**
     * Datum je Slot des Katalogs `written_exam_slots`. Die Dateien nennen die
     * Slots über `sort_order`, nicht über die ID — die hängt an der
     * Einfügereihenfolge und ist nicht in jeder Installation dieselbe.
     *
     * @param array<string, mixed> $jahr
     * @param array{neu: int, geaendert: int, geloescht: int, unveraendert: int, schriftlich: int, zeitraeume: list<string>, warnungen: list<string>} $bericht
     */
    private function gleicheSchriftlicheTermineAb(array $jahr, array &$bericht): void
    {
        $abitur = $jahr['abitur'];

        if ($abitur === null || ($abitur['schriftlich'] ?? []) === []) {
            return;
        }

        /** @var array<int, int> $katalog sort_order => id */
        $katalog = DB::table('written_exam_slots')->pluck('id', 'sort_order')->map(fn ($id) => (int) $id)->all();

        if ($katalog === []) {
            $bericht['warnungen'][] = "{$jahr['schuljahr']}: Der Katalog der schriftlichen Slots ist leer — Prüfungstage übersprungen.";

            return;
        }

        $zugeordnet = [];
        $jetzt = now();

        foreach ($abitur['schriftlich'] as $tag) {
            foreach ($tag['slots'] as $sortOrder) {
                $zugeordnet[] = (int) $sortOrder;
                $slotId = $katalog[(int) $sortOrder] ?? null;

                if ($slotId === null) {
                    continue;
                }

                $vorhanden = DB::table('written_exam_year_dates')
                    ->where('exam_year', $abitur['jahr'])->where('written_exam_slot_id', $slotId)->first();

                if ($vorhanden === null) {
                    DB::table('written_exam_year_dates')->insert([
                        'exam_year' => $abitur['jahr'], 'written_exam_slot_id' => $slotId, 'exam_date' => $tag['datum'],
                        'created_at' => $jetzt, 'updated_at' => $jetzt,
                    ]);
                    $bericht['schriftlich']++;
                } elseif (substr((string) $vorhanden->exam_date, 0, 10) !== $tag['datum']) {
                    DB::table('written_exam_year_dates')->where('id', $vorhanden->id)
                        ->update(['exam_date' => $tag['datum'], 'updated_at' => $jetzt]);
                    $bericht['schriftlich']++;
                }
            }
        }

        $fehlend = array_diff(array_keys($katalog), $zugeordnet);
        $unbekannt = array_diff($zugeordnet, array_keys($katalog));

        if ($fehlend !== [] || $unbekannt !== []) {
            $bericht['warnungen'][] = "Abitur {$abitur['jahr']}: Slot-Zuordnung passt nicht zum Katalog (fehlend: "
                . (implode(',', $fehlend) ?: '–') . ', unbekannt: ' . (implode(',', $unbekannt) ?: '–') . ').';
        }
    }

    /**
     * @param array<string, mixed> $jahr
     * @param array{neu: int, geaendert: int, geloescht: int, unveraendert: int, schriftlich: int, zeitraeume: list<string>, warnungen: list<string>} $bericht
     */
    private function legeZeitraumAn(array $jahr, array &$bericht): void
    {
        $abitur = $jahr['abitur'];

        if ($abitur === null || (int) substr($jahr['schuljahr'], 0, 4) > (int) substr(SchoolYear::current(), 0, 4)) {
            return;
        }

        if (DB::table('exam_periods')->where('year', $abitur['jahr'])->exists()) {
            return;
        }

        $felder = $this->zeitraumFelder($abitur);
        DB::table('exam_periods')->insert($felder + ['created_at' => now(), 'updated_at' => now()]);
        $bericht['zeitraeume'][] = $felder['name'];
    }

    /**
     * Vorbelegung für „Neuer Prüfungszeitraum": das erste Abitur aus den
     * Dateien, das jünger ist als alle angelegten Zeiträume.
     *
     * @return array{name: string, year: int, week_1_start: string, week_1_end: string, week_2_start: string, week_2_end: string}|null
     */
    public function zeitraumVorschlag(): ?array
    {
        $juengstes = (int) DB::table('exam_periods')->max('year');

        foreach ($this->jahre() as $jahr) {
            if ($jahr['abitur'] !== null && (int) $jahr['abitur']['jahr'] > $juengstes) {
                return $this->zeitraumFelder($jahr['abitur']);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $abitur
     * @return array{name: string, year: int, week_1_start: string, week_1_end: string, week_2_start: string, week_2_end: string}
     */
    private function zeitraumFelder(array $abitur): array
    {
        [[$w1von, $w1bis], [$w2von, $w2bis]] = $abitur['kolloquium'];

        return [
            'name' => (string) $abitur['name'],
            'year' => (int) $abitur['jahr'],
            'week_1_start' => $w1von, 'week_1_end' => $w1bis,
            'week_2_start' => $w2von, 'week_2_end' => $w2bis,
        ];
    }

    private function istDatum(string $wert): bool
    {
        $datum = \DateTimeImmutable::createFromFormat('!Y-m-d', $wert);

        return $datum !== false && $datum->format('Y-m-d') === $wert;
    }
}
