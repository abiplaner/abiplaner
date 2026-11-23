<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesSchoolTenancy;
use App\Models\CourseTrack;
use App\Support\SchoolYear;
use Illuminate\Console\Command;

/**
 * Spielt die Zeitfenster der Schienen eines Schuljahres aus einer Datendatei ein.
 *
 * Hintergrund: Die Zeitfenster einer Schiene errechnet die Anwendung beim
 * ASV-Import aus den Stundenplänen der Lehrkräfte (`timetable_entries`). Liegen
 * die Lehrerstundenpläne des neuen Schuljahres noch nicht vor, bleiben die
 * Zeitfenster leer — der Klausurenplan zeigt dann „(flexible Zeiten)". Aus dem
 * Untis-Stundenplan der Oberstufe (PDF) lassen sie sich trotzdem ablesen; das
 * Ergebnis liegt in `database/data/schienen-zeitfenster-<Schuljahr>.json`.
 *
 * Der Befehl läuft standardmäßig nur als Vorschau; erst --apply schreibt.
 * Er ändert ausschließlich `course_tracks.time_slots` — Stundenpläne, Lehrkräfte
 * und Schienen selbst bleiben unberührt.
 *
 * Schutz vor veralteten Daten: Die Datei merkt sich je Schiene die Kurskürzel,
 * auf denen die Herleitung beruht. Weichen die Kürzel der gespeicherten Schiene
 * ab (z. B. nach einem neuen ASV-Import), wird sie übersprungen und gemeldet.
 *
 * Aufruf:
 *   php artisan tracks:zeitfenster-einspielen --year=2026/27 --school=<Schulnummer>
 *   php artisan tracks:zeitfenster-einspielen --year=2026/27 --school=<Schulnummer> --apply
 */
class SchienenZeitfensterEinspielen extends Command
{
    use ResolvesSchoolTenancy;

    protected $signature = 'tracks:zeitfenster-einspielen
                            {--year= : Schuljahr im Kurzformat, z. B. 2026/27 (Pflicht)}
                            {--school= : Schulnummer, wenn der Befehl außerhalb eines Mandanten läuft}
                            {--file= : Datendatei (Vorgabe: database/data/schienen-zeitfenster-<Schuljahr>.json)}
                            {--apply : Änderungen tatsächlich schreiben (sonst nur Vorschau)}';

    protected $description = 'Setzt die Zeitfenster der Schienen eines Schuljahres aus einer Datendatei (z. B. aus dem Oberstufen-Stundenplan)';

    private const DAYS = ['Mo' => 1, 'Di' => 2, 'Mi' => 3, 'Do' => 4, 'Fr' => 5];

    public function handle(): int
    {
        $schoolYear = trim((string) ($this->option('year') ?? ''));

        if (preg_match('#^\d{4}/\d{2}$#', $schoolYear) !== 1) {
            $this->error('Bitte das Schuljahr im Kurzformat angeben, z. B. --year=2026/27.');

            return self::FAILURE;
        }

        $file = trim((string) ($this->option('file') ?? ''));
        if ($file === '') {
            $file = database_path('data/schienen-zeitfenster-' . str_replace('/', '-', $schoolYear) . '.json');
        }

        $entries = $this->readEntries($file, $schoolYear);
        if ($entries === null) {
            return self::FAILURE;
        }

        $tenancyStarted = false;
        $exitCode = $this->resolveSchoolAndStartTenancy($this->option('school'), $tenancyStarted);
        if ($exitCode !== null) {
            return $exitCode;
        }

        try {
            $this->verarbeiten($schoolYear, $entries, (bool) $this->option('apply'));
        } finally {
            if ($tenancyStarted) {
                app(\App\Services\AktuelleSchule::class)->verlassen();
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function verarbeiten(string $schoolYear, array $entries, bool $apply): void
    {
        $rows = [];
        $counts = ['geändert' => 0, 'unverändert' => 0, 'übersprungen' => 0];
        $seen = [];

        foreach ($entries as $entry) {
            $grade = (string) $entry['grade'];
            $name = (string) $entry['name'];
            $seen[$grade . '|' . $name] = true;

            $track = CourseTrack::where('school_year', $schoolYear)
                ->where('grade', $grade)
                ->where('name', $name)
                ->first();

            if (!$track instanceof CourseTrack) {
                $rows[] = [$grade, $name, '', '', 'übersprungen: Schiene nicht vorhanden'];
                $counts['übersprungen']++;

                continue;
            }

            $expected = $this->sortedCodes($entry['course_codes'] ?? []);
            $actual = $this->sortedCodes($track->course_codes ?? []);

            if ($expected !== $actual) {
                $rows[] = [
                    $grade, $name, $this->format($track->time_slots), '',
                    'übersprungen: Kurse weichen ab (Datei: ' . implode(', ', $expected)
                        . ' — gespeichert: ' . implode(', ', $actual) . ')',
                ];
                $counts['übersprungen']++;

                continue;
            }

            $new = $this->normalize($entry['time_slots'] ?? null);
            $old = $this->normalize($track->time_slots);

            if ($old === $new) {
                $rows[] = [$grade, $name, $this->format($old), $this->format($new), 'unverändert'];
                $counts['unverändert']++;

                continue;
            }

            if ($apply) {
                $track->time_slots = $new;
                $track->save();
            }

            $rows[] = [$grade, $name, $this->format($old), $this->format($new), $apply ? 'geändert' : 'würde geändert'];
            $counts['geändert']++;
        }

        $this->info(($apply ? 'Schienen-Zeitfenster ' : 'Vorschau Schienen-Zeitfenster ') . $schoolYear . ':');
        $this->table(['Stufe', 'Schiene', 'bisher', 'neu', 'Ergebnis'], $rows);

        $unlisted = CourseTrack::where('school_year', $schoolYear)->get()
            ->filter(fn (CourseTrack $t): bool => !isset($seen[$t->grade . '|' . $t->name]));

        foreach ($unlisted as $track) {
            $this->line("Nicht in der Datei, bleibt unverändert: {$track->grade} {$track->name}");
        }

        $this->newLine();
        $this->info(sprintf(
            '%d %s, %d unverändert, %d übersprungen.',
            $counts['geändert'],
            $apply ? 'geändert' : 'würden geändert',
            $counts['unverändert'],
            $counts['übersprungen']
        ));

        if (!$apply) {
            $this->comment('Dies war nur eine Vorschau. Mit --apply werden die Zeitfenster tatsächlich geschrieben.');
        }
    }

    /**
     * @return list<array<string, mixed>>|null null bei unbrauchbarer Datei (Fehler ist ausgegeben)
     */
    private function readEntries(string $file, string $schoolYear): ?array
    {
        if (!is_file($file)) {
            $this->error("Datendatei nicht gefunden: {$file}");

            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        if (!is_array($data) || !isset($data['schienen']) || !is_array($data['schienen'])) {
            $this->error("Datendatei unlesbar oder ohne Liste \"schienen\": {$file}");

            return null;
        }

        if (($data['schuljahr'] ?? null) !== $schoolYear) {
            $this->error(sprintf(
                'Die Datei gilt für das Schuljahr %s, verlangt ist %s.',
                is_scalar($data['schuljahr'] ?? null) ? (string) $data['schuljahr'] : '(keines)',
                $schoolYear
            ));

            return null;
        }

        $entries = [];
        foreach ($data['schienen'] as $i => $entry) {
            if (!is_array($entry) || !isset($entry['grade'], $entry['name'])
                || !array_key_exists('time_slots', $entry) || !isset($entry['course_codes'])) {
                $this->error('Eintrag ' . ($i + 1) . ' der Datendatei ist unvollständig (grade, name, course_codes, time_slots).');

                return null;
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param mixed $codes
     * @return list<string>
     */
    private function sortedCodes(mixed $codes): array
    {
        $codes = is_array($codes) ? array_map(fn ($c): string => (string) $c, $codes) : [];
        sort($codes);

        return $codes;
    }

    /**
     * Einheitliche Form zum Vergleichen und Speichern: nach Wochentag und
     * Stunde sortiert; ein leeres Fenster ist null ("flexibel").
     *
     * @param mixed $slots
     * @return list<array{day_short: string, periods: list<int>}>|null
     */
    private function normalize(mixed $slots): ?array
    {
        if (!is_array($slots) || $slots === []) {
            return null;
        }

        $normalized = [];
        foreach ($slots as $slot) {
            if (!is_array($slot) || !isset($slot['day_short'])) {
                continue;
            }

            $periods = array_map('intval', (array) ($slot['periods'] ?? []));
            sort($periods);

            $normalized[] = ['day_short' => (string) $slot['day_short'], 'periods' => $periods];
        }

        usort($normalized, fn (array $a, array $b): int => [self::DAYS[$a['day_short']] ?? 9, $a['periods']]
            <=> [self::DAYS[$b['day_short']] ?? 9, $b['periods']]);

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @param mixed $slots
     */
    private function format(mixed $slots): string
    {
        $slots = $this->normalize($slots);

        if ($slots === null) {
            return 'flexibel';
        }

        return implode(', ', array_map(function (array $s): string {
            $p = $s['periods'];
            $range = count($p) > 1 ? $p[0] . '–' . $p[count($p) - 1] : (string) ($p[0] ?? '?');

            return $s['day_short'] . ' ' . $range;
        }, $slots));
    }
}
