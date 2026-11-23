<?php

namespace Database\Seeders;

use App\Models\ExamPeriod;
use App\Models\School;
use App\Support\Grundausstattung;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Grundausstattung einer frisch angelegten Schule.
 *
 * Läuft bei der Ersteinrichtung (ErsteinrichtungController) und in den Tests
 * (TenantTestCase::createTestSchool). Was angelegt wird, steht in
 * App\Support\Grundausstattung — nachgebildet dem Abitur 2026 der
 * Pilotschule, mit neutralen Namen (Stand 23.11.2026):
 *
 * - Prüfungsmaterialien,
 * - vier Sitzpläne und fünfzehn Räume, die Prüfungsräume mit Sitzplan,
 * - Zeitslot-Vorlagen (Wochentag, Freitag, Ausweichtag),
 * - die Kolloquiumsslots: Mo–Do nachmittags, freitags vormittags; Tage, die
 *   im bayernweiten Kalender Feiertag oder Ferien sind, bleiben frei,
 * - die Aufsichtsfenster der Kolloquien mit einem Standardraster,
 * - die Aufsichtsraster der schriftlichen Prüfungen (Standard, Fremdsprachen).
 *
 * Fehler werden bewusst als Exception geworfen und nicht nur auf der
 * Konsole gemeldet: Ein `return` nach einer Fehlermeldung liefert Exit-Code 0,
 * die Kette meldet dann Erfolg, obwohl nichts angelegt ist — so ist der
 * Defekt vom 25.08.2026 unbemerkt geblieben.
 *
 * Alle Schritte sind idempotent: Was schon da ist, wird übersprungen.
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
        $this->seedRoomsAndSeating($now);
        $this->seedSlotTemplates((int) $examPeriod->id, $now);
        $this->seedTimeSlots((int) $examPeriod->id, $dates, $now);
        $this->seedSupervisionTimeSlots((int) $examPeriod->id, $dates, $now);
        $this->seedWrittenExamSupervisionGrids($now);

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
     * Jüngster Prüfungszeitraum. Die Stammdaten legen Zeiträume nur bis zum
     * laufenden Schuljahr an (BayernTermine), der jüngste ist also der
     * Jahrgang, der gerade auf das Abitur zugeht.
     */
    private function resolveExamPeriod(): ExamPeriod
    {
        $examPeriod = ExamPeriod::query()->orderByDesc('year')->first();

        if (!$examPeriod instanceof ExamPeriod) {
            throw new RuntimeException(
                'Kein Prüfungszeitraum vorhanden. Bitte zuerst die Stammdaten einspielen '
                . '(php artisan db:seed bzw. termine:einspielen --zeitraeume).'
            );
        }

        return $examPeriod;
    }

    /**
     * Die Kolloquiumstage der beiden Prüfungswochen: Mo–Fr, ohne Tage, die im
     * bayernweiten Kalender Feiertag oder Ferien sind (2028 etwa Christi
     * Himmelfahrt mitten in der ersten Woche).
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
                if ($day->isWeekend() || $this->isDayOff($day->toDateString())) {
                    continue;
                }

                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    private function isDayOff(string $date): bool
    {
        return DB::table('global_calendar_events')
            ->whereIn('event_type', ['holiday', 'public_holiday'])
            ->where('event_date', '<=', $date . ' 23:59:59')
            ->where(function ($q) use ($date) {
                $q->where(fn ($q) => $q->whereNull('end_date')->where('event_date', '>=', $date))
                    ->orWhere('end_date', '>=', $date);
            })
            ->exists();
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

    /**
     * Sitzpläne und Räume. Jeder Prüfungsraum bekommt seinen Sitzplan als
     * Standard-Variante (room_seating_templates) und zusätzlich in der alten
     * Spalte rooms.seating_template_id, die einige Masken noch lesen.
     */
    private function seedRoomsAndSeating(Carbon $now): void
    {
        if (DB::table('rooms')->exists()) {
            $this->command->info('🏢 Räume bereits vorhanden — übersprungen.');

            return;
        }

        $seatTypes = ['.' => 'available', 'B' => 'blocked', 'P' => 'teacher_desk', 'G' => 'aisle'];
        $templateIds = [];
        $seats = [];

        foreach (Grundausstattung::SITZPLAENE as $key => $plan) {
            $templateIds[$key] = DB::table('seating_templates')->insertGetId([
                'name' => $plan['name'],
                'description' => $plan['beschreibung'],
                'rows' => count($plan['plan']),
                'columns' => strlen($plan['plan'][0]),
                'door_positions' => json_encode([$plan['tuer']]),
                'orientation' => $plan['blick'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($plan['plan'] as $rowIndex => $row) {
                foreach (str_split($row) as $colIndex => $code) {
                    $seats[] = [
                        'template_id' => $templateIds[$key],
                        'row_number' => $rowIndex + 1,
                        'column_number' => $colIndex + 1,
                        'seat_type' => $seatTypes[$code],
                        'notes' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        DB::table('seating_template_seats')->insert($seats);

        $variants = 0;

        foreach (Grundausstattung::RAEUME as [$number, $type, $notes, $planKey]) {
            $templateId = $planKey !== null ? $templateIds[$planKey] : null;

            $roomId = DB::table('rooms')->insertGetId([
                'room_number' => $number,
                'room_type' => $type,
                'notes' => $notes,
                'seating_template_id' => $templateId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($templateId !== null) {
                DB::table('room_seating_templates')->insert([
                    'room_id' => $roomId,
                    'variant' => 'standard',
                    'seating_template_id' => $templateId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $variants++;
            }
        }

        $this->command->info('🪑 ' . count($templateIds) . ' Sitzpläne mit ' . count($seats) . ' Feldern angelegt.');
        $this->command->info('🏢 ' . count(Grundausstattung::RAEUME) . " Räume angelegt, {$variants} davon mit Sitzplan.");
    }

    private function seedSlotTemplates(int $examPeriodId, Carbon $now): void
    {
        if (DB::table('slot_templates')->where('exam_period_id', $examPeriodId)->exists()) {
            $this->command->info('⏰ Zeitslot-Vorlagen bereits vorhanden — übersprungen.');

            return;
        }

        $items = [];

        foreach (Grundausstattung::SLOT_VORLAGEN as $name => [$description, $morning, $weights]) {
            $templateId = DB::table('slot_templates')->insertGetId([
                'exam_period_id' => $examPeriodId,
                'name' => $name,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $times = $morning ? Grundausstattung::SLOTS_VORMITTAG : Grundausstattung::SLOTS_NACHMITTAG;

            foreach ($times as $slotNumber => [$prepStart, $prepEnd, $examStart, $examEnd]) {
                $items[] = [
                    'slot_template_id' => $templateId,
                    'slot_number' => $slotNumber,
                    'prep_start_time' => $prepStart,
                    'prep_end_time' => $prepEnd,
                    'exam_start_time' => $examStart,
                    'exam_end_time' => $examEnd,
                    'preference_weight' => $weights[$slotNumber - 1],
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('slot_template_items')->insert($items);
        $this->command->info('⏰ ' . count(Grundausstattung::SLOT_VORLAGEN) . ' Zeitslot-Vorlagen mit ' . count($items) . ' Einträgen angelegt.');
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

        $rows = [];

        foreach ($dates as $date) {
            $friday = Grundausstattung::istFreitag($date);
            $times = $friday ? Grundausstattung::SLOTS_VORMITTAG : Grundausstattung::SLOTS_NACHMITTAG;

            foreach ($times as $slotNumber => [$prepStart, $prepEnd, $examStart, $examEnd]) {
                $rows[] = [
                    'exam_period_id' => $examPeriodId,
                    'date' => $date,
                    'slot_number' => $slotNumber,
                    'prep_start_time' => $prepStart,
                    'prep_end_time' => $prepEnd,
                    'exam_start_time' => $examStart,
                    'exam_end_time' => $examEnd,
                    'preference_weight' => $friday ? Grundausstattung::GEWICHT_FREITAG : Grundausstattung::GEWICHTE_WOCHENTAG[$slotNumber],
                    'is_active' => true,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('time_slots')->insert($rows);
        $this->command->info('📅 ' . count($rows) . ' Zeitslots an ' . count($dates) . ' Tagen angelegt (Mo–Do nachmittags, Fr vormittags).');
    }

    /**
     * Aufsichtsfenster der Kolloquien, gehängt an ein Standardraster des
     * Zeitraums (wie im Bestand der Pilotschule).
     *
     * @param list<string> $dates
     */
    private function seedSupervisionTimeSlots(int $examPeriodId, array $dates, Carbon $now): void
    {
        if (DB::table('supervision_time_slots')->where('exam_period_id', $examPeriodId)->exists()) {
            $this->command->info('👮 Aufsichtszeitslots bereits vorhanden — übersprungen.');

            return;
        }

        $gridId = DB::table('supervision_grids')->where('exam_period_id', $examPeriodId)->where('is_default', true)->value('id')
            ?? DB::table('supervision_grids')->insertGetId([
                'exam_period_id' => $examPeriodId,
                'name' => 'Standard',
                'description' => 'Aufsicht in Warteraum und Flur während der Kolloquien',
                'color' => 'blue',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $rows = [];

        foreach ($dates as $date) {
            $windows = Grundausstattung::istFreitag($date)
                ? Grundausstattung::AUFSICHT_VORMITTAG
                : Grundausstattung::AUFSICHT_NACHMITTAG;

            foreach ($windows as $slotNumber => [$start, $end]) {
                $rows[] = [
                    'exam_period_id' => $examPeriodId,
                    'supervision_grid_id' => $gridId,
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
        $this->command->info('👮 ' . count($rows) . ' Aufsichtszeitslots an ' . count($dates) . ' Tagen angelegt.');
    }

    /**
     * Aufsichtsraster der schriftlichen Prüfungen. Die Slotnummern laufen
     * über alle Raster durch (1–8, 9–16) wie im Bestand — ältere Stellen
     * lesen die Nummer ohne das Raster.
     */
    private function seedWrittenExamSupervisionGrids(Carbon $now): void
    {
        if (DB::table('written_exam_supervision_grids')->exists()) {
            $this->command->info('✏️ Aufsichtsraster der schriftlichen Prüfungen bereits vorhanden — übersprungen.');

            return;
        }

        $number = 1;

        foreach (Grundausstattung::SCHRIFTLICHE_AUFSICHT as $grid) {
            $gridId = DB::table('written_exam_supervision_grids')->insertGetId([
                'name' => $grid['name'],
                'description' => null,
                'color' => $grid['farbe'],
                'is_default' => $grid['standard'],
                'is_foreign_language' => $grid['fremdsprache'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $rows = [];

            foreach ($grid['slots'] as [$start, $end, $label]) {
                $rows[] = [
                    'slot_number' => $number++,
                    'written_exam_supervision_grid_id' => $gridId,
                    'start_time' => $start,
                    'end_time' => $end,
                    'label' => $label,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('written_exam_supervision_time_slots')->insert($rows);
        }

        $this->command->info('✏️ ' . count(Grundausstattung::SCHRIFTLICHE_AUFSICHT) . ' Aufsichtsraster der schriftlichen Prüfungen angelegt.');
    }
}
