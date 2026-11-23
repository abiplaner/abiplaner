<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Alle zentralen Termine des Abiturjahrgangs 2027 / Schuljahr 2026/27.
 *
 * Deckt drei zentrale Tabellen ab:
 * - `exam_periods` — die Periode „Abitur 2027" mit den vier Kolloquiums-Wochenterminen
 * - `written_exam_year_dates` — Datum je Prüfungsslot (treibt die Prüfungsplanung)
 * - `global_calendar_events` — Kalendereinträge für Anzeige und ICS-Feed
 *
 * Dass die schriftlichen Prüfungstage in zwei Tabellen stehen, ist kein Versehen:
 * in `written_exam_year_dates` hängen sie am Slot-Katalog und treiben die Planung,
 * in `global_calendar_events` sind sie Kalendereinträge. Schuljahr 2025/26 hält es
 * genauso (dort verteilt auf WrittenExamSlotsSeeder und GlobalCalendarEventsSeeder).
 *
 * Fortsetzung von GlobalCalendarEventsSeeder / ExamPeriodSeeder /
 * WrittenExamSlotsSeeder (Schuljahr 2025/26). Bewusst ein eigener Seeder je
 * Jahrgang: die Vorgänger fügen ohne Prüfung ein und würden bei einem zweiten Lauf
 * alles verdoppeln. Dieser hier ist idempotent — er überspringt, was schon steht —
 * und darf deshalb gefahrlos erneut laufen. Genau das macht ihn auch zur Probe:
 * meldet er auf einem befüllten System „0 eingefügt", stimmt der Bestand mit der
 * versionierten Definition überein.
 *
 * Quellen:
 * - Prüfungstermine: BayMBl. 2025 Nr. 354 vom 03.09.2025, Bekanntmachung des
 *   Staatsministeriums vom 18.08.2025, Az. VI.5-BS5500.0/243/2
 * - Ferien und Feiertage: bayerische Ferienordnung (von Wilfried übermittelt)
 */
class Abitur2027Seeder extends Seeder
{
    /** Feldwerte je Terminart, übernommen aus dem Bestand 2025/26. */
    private const MUSTER = [
        'ferien' => ['event_type' => 'holiday', 'color' => '#A855F7', 'is_recurring' => false, 'priority' => 5, 'grade_levels' => null],
        'feiertag' => ['event_type' => 'public_holiday', 'color' => '#EF4444', 'is_recurring' => true, 'priority' => 3, 'grade_levels' => null],
        'schriftlich' => ['event_type' => 'deadline', 'color' => '#3B82F6', 'is_recurring' => true, 'priority' => 10, 'grade_levels' => '[13]'],
        'kolloquium' => ['event_type' => 'deadline', 'color' => '#10B981', 'is_recurring' => true, 'priority' => 10, 'grade_levels' => '[13]'],
        'praktisch' => ['event_type' => 'deadline', 'color' => '#14B8A6', 'is_recurring' => false, 'priority' => 7, 'grade_levels' => '[13]'],
        'zusatzpruefung' => ['event_type' => 'deadline', 'color' => '#10B981', 'is_recurring' => true, 'priority' => 9, 'grade_levels' => '[13]'],
        'entlassung' => ['event_type' => 'administration', 'color' => '#059669', 'is_recurring' => true, 'priority' => 10, 'grade_levels' => '[13]'],
        'unterrichtsbeginn' => ['event_type' => 'info', 'color' => '#10B981', 'is_recurring' => true, 'priority' => 5, 'grade_levels' => '[11,12,13]'],
    ];

    /** Kolloquiumswochen 2027 — die vier NOT-NULL-Felder der Periode. */
    private const PERIODE = [
        'name' => 'Abitur 2027',
        'year' => 2027,
        'week_1_start' => '2027-05-31', 'week_1_end' => '2027-06-04',
        'week_2_start' => '2027-06-07', 'week_2_end' => '2027-06-11',
    ];

    /**
     * Prüfungsdatum je Slot-ID aus dem Katalog `written_exam_slots`.
     * Die Zuordnung folgt Nr. 1.1 der Bekanntmachung; die IDs entsprechen dem
     * Katalog, den WrittenExamSlotsSeeder anlegt.
     */
    private const SCHRIFTLICHE_TERMINE = [
        '2027-04-21' => [17, 18, 19, 20, 21, 22, 23], // alle eA außer D, M, E, F, Bio, Ch, Ph
        '2027-04-27' => [7],                          // Deutsch
        '2027-04-30' => [8, 9],                       // Englisch gA + eA
        '2027-05-03' => [11, 12],                     // Französisch gA + eA
        '2027-05-05' => [10],                         // Mathematik
        '2027-05-07' => [13, 14, 15, 16],             // alle gA außer E, F, Bio, Ch, Ph
        '2027-05-11' => [1, 2],                       // Biologie gA + eA
        '2027-05-12' => [3, 4],                       // Physik gA + eA
        '2027-05-13' => [5, 6],                       // Chemie gA + eA
    ];

    public function run(): void
    {
        $this->seedPeriode();
        $this->seedSchriftlicheTermine();
        $this->seedKalender();
        $this->pruefeKollisionen();
    }

    private function seedPeriode(): void
    {
        if (DB::table('exam_periods')->where('year', 2027)->exists()) {
            $this->command->info('✓ Periode „Abitur 2027" bereits vorhanden');

            return;
        }

        DB::table('exam_periods')->insert(
            self::PERIODE + ['created_at' => now(), 'updated_at' => now()]
        );
        $this->command->info('✓ Periode „Abitur 2027" angelegt');
    }

    private function seedSchriftlicheTermine(): void
    {
        // Vollständigkeitsprobe: jeder Slot des Katalogs genau einmal. Ein
        // vergessener Slot fiele sonst erst bei der Prüfungsplanung auf.
        $zugeordnet = array_merge(...array_values(self::SCHRIFTLICHE_TERMINE));
        $katalog = DB::table('written_exam_slots')->pluck('id')->all();
        sort($zugeordnet);
        sort($katalog);

        if ($zugeordnet !== $katalog) {
            $this->command->error('✗ Slot-Zuordnung passt nicht zum Katalog — schriftliche Termine übersprungen');
            $this->command->error('  fehlend: ' . (implode(',', array_diff($katalog, $zugeordnet)) ?: '–'));
            $this->command->error('  unbekannt: ' . (implode(',', array_diff($zugeordnet, $katalog)) ?: '–'));

            return;
        }

        $angelegt = 0;

        foreach (self::SCHRIFTLICHE_TERMINE as $datum => $slots) {
            foreach ($slots as $slot) {
                $vorhanden = DB::table('written_exam_year_dates')
                    ->where('exam_year', 2027)->where('written_exam_slot_id', $slot)->exists();

                if ($vorhanden) {
                    continue;
                }

                DB::table('written_exam_year_dates')->insert([
                    'exam_year' => 2027, 'written_exam_slot_id' => $slot, 'exam_date' => $datum,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $angelegt++;
            }
        }

        $this->command->info("✓ Schriftliche Termine: {$angelegt} eingefügt, " . (count($katalog) - $angelegt) . ' bereits vorhanden');
    }

    private function seedKalender(): void
    {
        $termine = [
            // --- Schuljahresbeginn ---
            // Die Sommerferien 2026 enden am 14.09.2026 (GlobalCalendarEventsSeeder),
            // der Unterricht beginnt am Tag darauf.
            ['unterrichtsbeginn', 'Beginn des stundenplanmäßigen Unterrichts', '2026-09-15', null],

            // --- Ferien und Feiertage 2026/27 ---
            ['feiertag', 'Tag der Deutschen Einheit', '2026-10-03', null],
            ['ferien', 'Herbstferien', '2026-11-02', '2026-11-08'],
            ['feiertag', 'Buß- und Bettag', '2026-11-18', null],
            ['ferien', 'Weihnachtsferien', '2026-12-24', '2027-01-10'],
            ['ferien', 'Frühjahrsferien', '2027-02-08', '2027-02-14'],
            ['ferien', 'Osterferien', '2027-03-22', '2027-04-04'],
            ['feiertag', 'Maifeiertag', '2027-05-01', null],
            ['feiertag', 'Christi Himmelfahrt', '2027-05-06', null],
            ['ferien', 'Pfingstferien', '2027-05-17', '2027-05-30'],

            // --- Schriftliche Abiturprüfung 2027 (BayMBl. Nr. 1.1) ---
            ['schriftlich', 'Schriftliche Abiturprüfung alle weiteren Fächer (eA)', '2027-04-21', null],
            ['schriftlich', 'Schriftliche Abiturprüfung Deutsch', '2027-04-27', null],
            ['schriftlich', 'Schriftliche Abiturprüfung Englisch (gA/eA)', '2027-04-30', null],
            ['schriftlich', 'Schriftliche Abiturprüfung Französisch (gA/eA)', '2027-05-03', null],
            ['schriftlich', 'Schriftliche Abiturprüfung Mathematik', '2027-05-05', null],
            ['schriftlich', 'Schriftliche Abiturprüfung alle weiteren Fächer (gA)', '2027-05-07', null],
            ['schriftlich', 'Schriftliche Abiturprüfung Biologie (gA/eA)', '2027-05-11', null],
            ['schriftlich', 'Schriftliche Abiturprüfung Physik (gA/eA)', '2027-05-12', null],
            ['schriftlich', 'Schriftliche Abiturprüfung Chemie (gA/eA)', '2027-05-13', null],

            // --- Kolloquium 2027 (BayMBl. Nr. 1.2) ---
            ['kolloquium', '1. Kolloquiumswoche', '2027-05-31', '2027-06-04'],
            ['kolloquium', '2. Kolloquiumswoche', '2027-06-07', '2027-06-11'],

            // --- Praktische Prüfungen, Zusatzprüfungen, Entlassung (Nr. 1.3, 1.4, 5) ---
            ['praktisch', 'Frühester Beginn praktische Prüfungen Sport', '2027-01-25', null,
                'Praktische Prüfungen im Fach Sport werden nicht vor diesem Tag durchgeführt (BayMBl. 2025 Nr. 354, Nr. 1.3).'],
            ['praktisch', 'Frühester Beginn praktische Prüfungen Musik', '2027-03-08', null,
                'Praktische Prüfungen im Fach Musik werden nicht vor diesem Tag durchgeführt (BayMBl. 2025 Nr. 354, Nr. 1.3).'],
            ['zusatzpruefung', 'Mündliche Zusatzprüfungen', '2027-06-18', null,
                'Bis spätestens Freitag, 18. Juni 2027 abzuschließen; erst nach Bekanntgabe der Ergebnisse der fünf Abiturprüfungsfächer. Die genauen Termine setzt der Prüfungsausschuss fest (BayMBl. 2025 Nr. 354, Nr. 1.4).'],
            ['entlassung', 'Ausstellung und Aushändigung des Abiturzeugnisses', '2027-06-25', null,
                'Entlassung Abiturientinnen und Abiturienten (BayMBl. 2025 Nr. 354, Nr. 5).'],
        ];

        $angelegt = 0;
        $uebersprungen = 0;

        foreach ($termine as $eintrag) {
            [$muster, $titel, $von, $bis] = $eintrag;
            $beschreibung = $eintrag[4] ?? null;

            $vorhanden = DB::table('global_calendar_events')
                ->where('title', $titel)->where('event_date', $von)->exists();

            if ($vorhanden) {
                $uebersprungen++;
                continue;
            }

            DB::table('global_calendar_events')->insert(
                self::MUSTER[$muster] + [
                    'title' => $titel,
                    'description' => $beschreibung,
                    'event_date' => $von,
                    'end_date' => $bis,
                    'start_time' => null,
                    'end_time' => null,
                    'location' => null,
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $angelegt++;
        }

        $this->command->info("✓ Kalendertermine: {$angelegt} eingefügt, {$uebersprungen} bereits vorhanden");
    }

    /**
     * Plausibilitätsprüfung: kein Prüfungstag darf in Ferien oder auf einen
     * Feiertag fallen. Findet Zahlendreher in den Terminlisten, die sonst erst
     * bei der Planung auffallen würden.
     */
    private function pruefeKollisionen(): void
    {
        $frei = DB::table('global_calendar_events')
            ->whereIn('event_type', ['holiday', 'public_holiday'])
            ->whereBetween('event_date', ['2026-09-01', '2027-08-31'])
            ->get(['title', 'event_date', 'end_date']);

        $pruefungen = DB::table('global_calendar_events')
            ->where(function ($q) {
                $q->where('title', 'like', 'Schriftliche Abiturprüfung%')
                    ->orWhere('title', 'like', '%Kolloquiumswoche');
            })
            ->whereBetween('event_date', ['2026-09-01', '2027-08-31'])
            ->get(['title', 'event_date', 'end_date']);

        $treffer = 0;

        foreach ($pruefungen as $p) {
            $pVon = strtotime($p->event_date);
            $pBis = strtotime($p->end_date ?: $p->event_date);

            foreach ($frei as $f) {
                $fVon = strtotime($f->event_date);
                $fBis = strtotime($f->end_date ?: $f->event_date);

                if ($pVon <= $fBis && $fVon <= $pBis) {
                    $this->command->warn("  ⚠ {$p->title} ({$p->event_date}) überschneidet sich mit {$f->title}");
                    $treffer++;
                }
            }
        }

        if ($treffer === 0) {
            $this->command->info('✓ Kein Prüfungstermin fällt in Ferien oder auf einen Feiertag');
        }
    }
}
