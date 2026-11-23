<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\WrittenExamSlot;
use App\Models\Subject;

/**
 * Seeder für die schriftlichen Abitur-Prüfungsslots (jahresunabhängig).
 *
 * Grundlage: KMS Allgemeine Bestimmungen Abiturprüfung 2026, Anlage 1
 *
 * Struktur:
 *   written_exam_slots          → 23 Slots (Zeiten + Fächer, jahresunabhängig)
 *   written_exam_slot_subjects  → Fach-Zuordnungen
 *
 * Die Prüfungstage je Jahr kommen aus database/data/termine (BayernTermine),
 * adressiert über sort_order. Läuft nur beim ersten Start: Er leert die
 * Tabellen, auch written_exam_year_dates.
 *
 * WICHTIG: Russisch (Ru) gibt es nur auf grundlegendem Niveau (gA).
 *          Deutsch (D) und Mathematik (M) gibt es schriftlich nur als eA.
 *          Kunst (Ku) und Musik (Mu) gibt es schriftlich nur als eA.
 *          Sport gibt es schriftlich nur als eA (Sammeltag).
 */
class WrittenExamSlotsSeeder extends Seeder
{
    public function run(): void
    {
        // Alle bestehenden Slots und Termine löschen (für idempotentes Seeding)
        // Schema::disable…() statt SET FOREIGN_KEY_CHECKS: unter MySQL
        // dasselbe Statement, unter SQLite (lokale Edition) PRAGMA foreign_keys.
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('written_exam_year_dates')->truncate();
        DB::table('written_exam_slot_subjects')->truncate();
        DB::table('written_exam_slots')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $this->command->info('Erstelle schriftliche Prüfungs-Slots...');

        // ----------------------------------------------------------------
        // Fach-ID-Lookup (nach Code)
        // ----------------------------------------------------------------
        $subjectIds = Subject::pluck('id', 'code');

        $missing = [];
        $lookup = function (string $code) use ($subjectIds, &$missing): ?int {
            $id = $subjectIds[$code] ?? null;
            if ($id === null) {
                $missing[] = $code;
            }
            return $id;
        };

        // ----------------------------------------------------------------
        // Slot-Definitionen
        // Spalten: name | level | start | end | sort | subjects (Codes)
        // ----------------------------------------------------------------
        $slotDefinitions = [

            // === Prüfungstag 1: Mi 22.04. ===
            ['Biologie gA',     'grundlegend', '09:00', '13:15', 10, ['B']],
            ['Biologie eA',     'erhoeht',     '09:00', '14:00', 11, ['B']],

            // === Prüfungstag 2: Do 23.04. ===
            ['Physik gA',       'grundlegend', '09:00', '13:15', 20, ['Ph']],
            ['Physik eA',       'erhoeht',     '09:00', '14:00', 21, ['Ph']],

            // === Prüfungstag 3: Fr 24.04. ===
            ['Chemie gA',       'grundlegend', '09:00', '13:15', 30, ['C']],
            ['Chemie eA',       'erhoeht',     '09:00', '14:00', 31, ['C']],

            // === Prüfungstag 4: Di 28.04. – nur eA! ===
            ['Deutsch eA',      'erhoeht',     '08:00', '13:15', 40, ['D']],

            // === Prüfungstag 5: Do 30.04. ===
            ['Englisch gA',     'grundlegend', '08:15', '13:15', 50, ['E']],
            ['Englisch eA',     'erhoeht',     '08:15', '13:45', 51, ['E']],

            // === Prüfungstag 6: Mi 06.05. – nur eA, inkl. MMS ===
            ['Mathematik eA',   'erhoeht',     '09:00', '14:00', 60, ['M']],

            // === Prüfungstag 7: Fr 08.05. ===
            ['Französisch gA',  'grundlegend', '08:15', '13:15', 70, ['F']],
            ['Französisch eA',  'erhoeht',     '08:15', '13:45', 71, ['F']],

            // === Prüfungstag 8: Mo 11.05. – Sammeltag gA ===
            // Russisch (Ru) ist absichtlich NUR hier (gA), nicht im eA-Sammeltag!
            ['Sammeltag gA – Mod. Fremdsprachen', 'grundlegend', '08:15', '13:15', 80,
                ['It', 'Ru', 'Sp']],
            ['Sammeltag gA – Altsprachen',        'grundlegend', '09:00', '13:00', 81,
                ['L', 'Gr']],
            ['Sammeltag gA – Gesellschaft/Religion/Ethik', 'grundlegend', '09:00', '12:30', 82,
                ['G', 'Geo', 'PuG', 'WR', 'K', 'Ev', 'Ort', 'Eth']],
            ['Sammeltag gA – Informatik',         'grundlegend', '09:00', '13:15', 84,
                ['Inf']],

            // === Prüfungstag 9: Mi 13.05. – Sammeltag eA ===
            // Russisch erscheint hier NICHT – es gibt kein Russisch eA!
            ['Sammeltag eA – Mod. Fremdsprachen', 'erhoeht',     '08:15', '13:45', 90,
                ['It', 'Sp']],
            ['Sammeltag eA – Altsprachen',        'erhoeht',     '09:00', '14:00', 91,
                ['L', 'Gr']],
            ['Sammeltag eA – Gesellschaft/Religion/Ethik', 'erhoeht',     '09:00', '13:30', 92,
                ['G', 'Geo', 'PuG', 'WR', 'K', 'Ev', 'Ort', 'Eth']],
            ['Sammeltag eA – Informatik',         'erhoeht',     '09:00', '14:00', 94,
                ['Inf']],
            // Sport, Kunst, Musik: schriftlich nur auf eA
            ['Sammeltag eA – Sport',              'erhoeht',     '09:00', '12:00', 95,
                ['Sm', 'Sw', 'Smw']],
            ['Sammeltag eA – Kunst',              'erhoeht',     '08:00', '13:00', 96,
                ['Ku']],
            // * exklusive Vorspielzeit
            ['Sammeltag eA – Musik',              'erhoeht',     '08:30', '12:00', 97,
                ['Mu']],
        ];

        // ----------------------------------------------------------------
        // Slots anlegen und Fächer zuordnen
        // ----------------------------------------------------------------
        foreach ($slotDefinitions as [$name, $level, $start, $end, $sort, $codes]) {
            $slot = WrittenExamSlot::create([
                'name'              => $name,
                'requirement_level' => $level,
                'start_time'        => $start,
                'end_time'          => $end,
                'sort_order'        => $sort,
            ]);

            $subjectIdsToAttach = collect($codes)
                ->map(fn ($code) => $lookup($code))
                ->filter()
                ->all();

            if (!empty($subjectIdsToAttach)) {
                $slot->subjects()->attach($subjectIdsToAttach);
            }
        }

        $count = count($slotDefinitions);
        $this->command->info("✅ {$count} Slots angelegt");

        if (!empty($missing)) {
            $this->command->warn('⚠ Folgende Fach-Codes wurden nicht gefunden: ' . implode(', ', array_unique($missing)));
        }

        // Die Prüfungstage je Jahr (`written_exam_year_dates`) stehen seit
        // 23.11.2026 in database/data/termine/*.json und kommen über
        // BayernTermineSeeder bzw. `termine:einspielen` — dort über
        // sort_order, nicht über die ID.
    }
}
