<?php

namespace Database\Seeders;

use App\Models\ExamPeriod;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Grundausstattung einer frisch angelegten Schule (Tenant).
 *
 * Läuft über `tenants:seed --class=TenantSeeder`, angestoßen von
 * `App\Listeners\InitializeTenantDatabase` nach dem Migrieren der Tenant-DB.
 *
 * Fehler werden hier bewusst als Exception geworfen und nicht nur auf der
 * Konsole gemeldet: Der Seeder läuft in einer Kette (School::create →
 * stancl-Pipeline → Listener → Artisan::call), und ein `return` nach einer
 * Fehlermeldung liefert Exit-Code 0 zurück. Die Kette meldet dann Erfolg,
 * obwohl die Tenant-Datenbank leer bleibt — genau so ist der Defekt vom
 * 25.08.2026 unbemerkt geblieben.
 *
 * Alle Schritte sind idempotent: ein zweiter Lauf gegen denselben Tenant legt
 * nichts doppelt an, sondern überspringt, was schon vorhanden ist.
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $school = $this->resolveSchool();
        $examPeriod = $this->resolveExamPeriod();

        $this->command->info("🏫 Seede Grunddaten für: {$school->name} ({$school->getKey()})");
        $this->command->info("📆 Prüfungszeitraum: {$examPeriod->name} (ID {$examPeriod->id})");
        $this->command->info('');

        $now = Carbon::now();
        $dates = $this->examDates($examPeriod);

        $this->seedExamMaterials($now);
        $this->seedRooms($now);
        $this->seedSlotTemplates((int) $examPeriod->id, $now);
        $this->seedTimeSlots((int) $examPeriod->id, $dates, $now);
        $this->seedSupervisionTimeSlots((int) $examPeriod->id, $dates, $now);

        $this->command->info('');
        $this->command->info('✅ Grunddaten eingespielt. Lehrer, Schüler und Kurse kommen über den Import.');
    }

    /** Die (einzige) Schule; der Seeder läuft nach der Ersteinrichtung. */
    private function resolveSchool(): School
    {
        $school = School::query()->first();

        if (!$school instanceof School) {
            throw new RuntimeException('TenantSeeder: Es ist noch keine Schule angelegt.');
        }

        return $school;
    }

    /**
     * Jüngster zentral gepflegter Prüfungszeitraum.
     *
     * Bewusst nicht auf ein festes Jahr verdrahtet: Eine heute angelegte Schule
     * soll den kommenden Abiturjahrgang bekommen, nicht den, der beim Schreiben
     * dieses Seeders aktuell war.
     */
    private function resolveExamPeriod(): ExamPeriod
    {
        $examPeriod = ExamPeriod::query()->orderByDesc('year')->first();

        if (!$examPeriod instanceof ExamPeriod) {
            throw new RuntimeException(
                'Kein Prüfungszeitraum in der zentralen Datenbank. Bitte zuerst den '
                . 'passenden Abitur-Seeder ausführen (z. B. Abitur2027Seeder).'
            );
        }

        return $examPeriod;
    }

    /**
     * Alle Kolloquiumstage der beiden Prüfungswochen des Zeitraums.
     *
     * @return list<string> Datumswerte im Format Y-m-d
     */
    private function examDates(ExamPeriod $examPeriod): array
    {
        $dates = [];

        foreach ([['week_1_start', 'week_1_end'], ['week_2_start', 'week_2_end']] as [$from, $to]) {
            $start = Carbon::parse($examPeriod->{$from})->startOfDay();
            $end = Carbon::parse($examPeriod->{$to})->startOfDay();

            for ($day = $start->copy(); $day->lessThanOrEqualTo($end); $day->addDay()) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    private function seedExamMaterials(Carbon $now): void
    {
        if (DB::table('exam_materials')->exists()) {
            $this->command->info('📚 Prüfungsmaterialien bereits vorhanden — übersprungen.');

            return;
        }

        $materials = [
            ['Zweisprachiges Wörterbuch', 'Zweisprachiges Wörterbuch mit ca. 150.000 Einträgen (Deutsch ↔ Fremdsprache)', '📖', true],
            ['Einsprachiges Wörterbuch', 'Einsprachiges Wörterbuch in der Fremdsprache', '📕', true],
            ['Deutsches Bedeutungswörterbuch', 'Wörterbuch der deutschen Sprache', '📘', true],
            ['Genehmigtes Latein-Wörterbuch', 'Vom Staatsministerium genehmigtes Wörterbuch für Latein', '📙', false],
            ['Genehmigtes Griechisch-Wörterbuch', 'Vom Staatsministerium genehmigtes Wörterbuch für Griechisch', '📗', false],
            ['PUTZGER Historischer Weltatlas', 'Ausgabe Bayern, 105. Auflage 2024', '🗺️', false],
            ['Geographie-Atlas', 'Haack, DIERCKE oder SEYDLITZ Weltatlas Bayern', '🌍', false],
            ['Mathematische Formeln', 'Vom Staatsministerium genehmigtes Dokument', '📐', true],
            ['Stochastische Tabellen', 'Vom Staatsministerium zugelassene Tabellen', '📊', false],
            ['Formelsammlung Mathematik/Nawi', 'Zugelassene Formelsammlung 2024', '📋', true],
            ['Wissenschaftlicher Taschenrechner', 'Nach KMS-Regelungen zugelassen', '🔢', true],
            ['CAS-Rechner', 'Für Mathematik MMS', '📱', false],
            ['Periodensystem', 'Aus Formelsammlung', '⚛️', false],
            ['Geodreieck & Zirkel', 'Geometrische Zeichengeräte', '📏', false],
            ['Farbstifte', 'Buntstifte für Zeichnungen', '🎨', false],
        ];

        $rows = [];

        foreach ($materials as [$name, $description, $icon, $isDefault]) {
            $rows[] = [
                'name' => $name,
                'description' => $description,
                'icon' => $icon,
                'is_default' => $isDefault,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('exam_materials')->insert($rows);
        $this->command->info('📚 ' . count($rows) . ' Prüfungsmaterialien angelegt.');
    }

    private function seedRooms(Carbon $now): void
    {
        if (DB::table('rooms')->exists()) {
            $this->command->info('🏢 Räume bereits vorhanden — übersprungen.');

            return;
        }

        $rooms = [
            ['MB201', 'exam', null],
            ['MB202', 'exam', null],
            ['MB203', 'exam', null],
            ['MB204', 'exam', null],
            ['MB205', 'exam', null],
            ['MB206', 'exam', null],
            ['MB207', 'exam', null],
            ['MB105', 'preparation', 'Vorbereitungsraum'],
            ['B2', 'corridor', 'Fluraufsicht'],
            ['MA206', 'waiting', 'Warteraum'],
            ['MA207', 'preparation', 'Vorbereitungsraum 2'],
            ['MA208', 'lockdown', 'Lockdown-Raum'],
        ];

        $rows = [];

        foreach ($rooms as [$number, $type, $notes]) {
            $rows[] = [
                'room_number' => $number,
                'room_type' => $type,
                'notes' => $notes,
                'seating_template_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('rooms')->insert($rows);
        $this->command->info('🏢 ' . count($rows) . ' Räume angelegt.');
    }

    private function seedSlotTemplates(int $examPeriodId, Carbon $now): void
    {
        if (DB::table('slot_templates')->where('exam_period_id', $examPeriodId)->exists()) {
            $this->command->info('⏰ Zeitslot-Vorlagen bereits vorhanden — übersprungen.');

            return;
        }

        $slots = $this->standardSlotTimes();

        $templates = [
            'Normaler Wochentag (Mo-Do)' => ['Standardtag mit 5 Prüfungen', 5, [120, 115, 110, 100, 90]],
            'Freitag (verkürzt)' => ['Verkürzter Freitag mit nur 3 Prüfungen', 3, [70, 65, 60]],
            'Intensivtag (6 Prüfungen)' => ['Bei Bedarf: 6 Prüfungen', 6, [110, 105, 100, 95, 85, 75]],
        ];

        $items = [];

        foreach ($templates as $name => [$description, $count, $weights]) {
            $templateId = DB::table('slot_templates')->insertGetId([
                'exam_period_id' => $examPeriodId,
                'name' => $name,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            for ($slotNumber = 1; $slotNumber <= $count; $slotNumber++) {
                $times = $slots[$slotNumber];

                $items[] = [
                    'slot_template_id' => $templateId,
                    'slot_number' => $slotNumber,
                    'prep_start_time' => $times['prep_start'],
                    'prep_end_time' => $times['prep_end'],
                    'exam_start_time' => $times['exam_start'],
                    'exam_end_time' => $times['exam_end'],
                    'preference_weight' => $weights[$slotNumber - 1],
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('slot_template_items')->insert($items);
        $this->command->info('⏰ 3 Zeitslot-Vorlagen mit ' . count($items) . ' Einträgen angelegt.');
    }

    /**
     * @param list<string> $dates
     */
    private function seedTimeSlots(int $examPeriodId, array $dates, Carbon $now): void
    {
        if (DB::table('time_slots')->where('exam_period_id', $examPeriodId)->exists()) {
            $this->command->info('📅 Zeitslots bereits vorhanden — übersprungen.');

            return;
        }

        $slots = $this->standardSlotTimes();
        $rows = [];

        foreach ($dates as $date) {
            for ($slotNumber = 1; $slotNumber <= 5; $slotNumber++) {
                $times = $slots[$slotNumber];

                $rows[] = [
                    'exam_period_id' => $examPeriodId,
                    'date' => $date,
                    'slot_number' => $slotNumber,
                    'prep_start_time' => $times['prep_start'],
                    'prep_end_time' => $times['prep_end'],
                    'exam_start_time' => $times['exam_start'],
                    'exam_end_time' => $times['exam_end'],
                    'preference_weight' => 100,
                    'is_active' => true,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('time_slots')->insert($rows);
        $this->command->info('📅 ' . count($rows) . ' Zeitslots angelegt (' . count($dates) . ' Tage × 5 Slots).');
    }

    /**
     * @param list<string> $dates
     */
    private function seedSupervisionTimeSlots(int $examPeriodId, array $dates, Carbon $now): void
    {
        if (DB::table('supervision_time_slots')->where('exam_period_id', $examPeriodId)->exists()) {
            $this->command->info('👮 Aufsichtszeitslots bereits vorhanden — übersprungen.');

            return;
        }

        $windows = [
            1 => ['13:30:00', '14:30:00'],
            2 => ['14:30:00', '15:30:00'],
            3 => ['15:30:00', '16:40:00'],
        ];

        $rows = [];

        foreach ($dates as $date) {
            foreach ($windows as $slotNumber => [$start, $end]) {
                $rows[] = [
                    'exam_period_id' => $examPeriodId,
                    'supervision_grid_id' => null,
                    'date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                    'slot_number' => $slotNumber,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('supervision_time_slots')->insert($rows);
        $this->command->info('👮 ' . count($rows) . ' Aufsichtszeitslots angelegt (' . count($dates) . ' Tage × 3 Slots).');
    }

    /**
     * Die sechs Standard-Zeitfenster eines Kolloquiumstags.
     *
     * @return array<int, array{prep_start: string, prep_end: string, exam_start: string, exam_end: string}>
     */
    private function standardSlotTimes(): array
    {
        return [
            1 => ['prep_start' => '08:00:00', 'prep_end' => '08:30:00', 'exam_start' => '08:30:00', 'exam_end' => '09:00:00'],
            2 => ['prep_start' => '09:10:00', 'prep_end' => '09:40:00', 'exam_start' => '09:40:00', 'exam_end' => '10:10:00'],
            3 => ['prep_start' => '10:20:00', 'prep_end' => '10:50:00', 'exam_start' => '10:50:00', 'exam_end' => '11:20:00'],
            4 => ['prep_start' => '11:30:00', 'prep_end' => '12:00:00', 'exam_start' => '12:00:00', 'exam_end' => '12:30:00'],
            5 => ['prep_start' => '12:40:00', 'prep_end' => '13:10:00', 'exam_start' => '13:10:00', 'exam_end' => '13:40:00'],
            6 => ['prep_start' => '13:50:00', 'prep_end' => '14:20:00', 'exam_start' => '14:20:00', 'exam_end' => '14:50:00'],
        ];
    }
}
