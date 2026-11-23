<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\Room;
use App\Models\StudentExam;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * PlanningSimulationService — Modul 4.6 Optimierungssimulation
 *
 * Beantwortet die vorgelagerte Ressourcenfrage: Wie viele Prüfungstage,
 * Räume und Zeitslots pro Tag braucht es, um alle mündlichen Prüfungen
 * unterzubringen — und welche Kombinationen sind günstig?
 *
 * Kernmechanik (Konzept Kap. 2/3):
 *  - Stufe 1: arithmetische Machbarkeitsschranke (instant, kein Optimizer).
 *  - Stufe 2: echter Optimizer-Dry-Run gegen hypothetische, NICHT
 *    persistierte In-Memory-Ressourcen (E-1/E-2, Wilfried 09.07.2026).
 *
 * Verändert NIEMALS den Echtdatenbestand: keine TimeSlot-/OralExamDayRoom-/
 * ExamAssignment-Writes. Die erzeugten Modelle sind flüchtige Instanzen.
 */
class PlanningSimulationService
{
    /**
     * Feste, weite Startwerte für die Bandbreiten (Konzept Kap. 4a). Jede
     * Schule kann sie über SchoolSetting überschreiben. Format: [min, max].
     */
    private const DEFAULT_RANGES = [
        'days'          => [1, 15],
        'slots_per_day' => [1, 8],
        'rooms'         => [1, 20],
    ];

    public function __construct(
        private OptimizationService $optimizationService
    ) {
    }

    /**
     * Führt einen einzelnen Dry-Run für eine konkrete Ressourcen-Kombination
     * durch und liefert dessen Kennzahlen zurück, ohne etwas zu speichern.
     *
     * @return array{
     *     feasible: bool,
     *     scheduled: int,
     *     total_oral: int,
     *     unplaced: int,
     *     days: int,
     *     slots_per_day: int,
     *     rooms: int,
     *     capacity: int,
     *     stats: array<string, mixed>,
     *     warnings: array<int, mixed>,
     *     plan?: array<int, array<string, mixed>>
     * }
     *
     * @param bool $withPlan Wenn true, wird zusätzlich unter 'plan' die
     *   exemplarische Belegung (Tag/Slot/Raum/Schüler/Prüfer/Beisitzer) der
     *   hypothetischen Ressourcen aufgebaut. Nur für die finalen Vorschläge
     *   sinnvoll, da das Nachladen der Namen zusätzliche Queries kostet.
     */
    public function dryRun(ExamPeriod $examPeriod, int $days, int $slotsPerDay, int $rooms, bool $withPlan = false): array
    {
        $timeSlots = $this->buildHypotheticalSlots($examPeriod, $days, $slotsPerDay);
        $roomModels = $this->buildHypotheticalRooms($rooms);

        $this->optimizationService->injectResources($timeSlots, $roomModels);
        try {
            $result = $this->optimizationService->optimize($examPeriod, [
                'school_id' => auth()->user()?->school_id,
                'simulation' => true,
            ]);
        } finally {
            // Injektion immer aufheben, damit ein späterer echter Lauf
            // wieder regulär aus der DB lädt (auch im Fehlerfall).
            $this->optimizationService->clearInjectedResources();
        }

        $scheduled = count($result['assignments']);
        $totalOral = $this->countUnplannedOralExams($examPeriod);
        $capacity = $days * $slotsPerDay * $rooms;

        $out = [
            'feasible'      => $scheduled >= $totalOral && $totalOral > 0,
            'scheduled'     => $scheduled,
            'total_oral'    => $totalOral,
            'unplaced'      => max(0, $totalOral - $scheduled),
            'days'          => $days,
            'slots_per_day' => $slotsPerDay,
            'rooms'         => $rooms,
            'capacity'      => $capacity,
            'stats'         => $result['stats'] ?? [],
            'warnings'      => $result['warnings'] ?? [],
        ];

        if ($withPlan) {
            $out['plan'] = $this->buildExamplePlan($result['assignments'], $timeSlots, $roomModels);
        }

        return $out;
    }

    /**
     * Baut aus den Optimizer-Zuweisungen und den hypothetischen Ressourcen eine
     * exemplarische, nach Tag → Slot gruppierte Belegung auf. Dient nur der
     * Plausibilisierung im Export (Modul 4.6, Phase 5) – die Räume sind
     * Platzhalter (SIM-x), die Uhrzeiten fiktiv. Es werden KEINE Echtdaten
     * geschrieben; nur die Namen der beteiligten Schüler/Lehrkräfte/Fächer
     * werden nachgeladen, um die Belegung lesbar zu machen.
     *
     * @param array<int, array<string, mixed>> $assignments Optimizer-Rohzuweisungen
     *   (je Eintrag u. a. student_exam_id, time_slot_id, exam_room_id).
     * @param Collection<int, TimeSlot> $timeSlots Hypothetische Slots (ID → Datum/Nr).
     * @param Collection<int, Room> $rooms Hypothetische Räume (ID → SIM-Nummer).
     * @return array<int, array<string, mixed>> Liste von Tages-Blöcken, je Tag
     *   ein Array {date, weekday, slots: [{slot_number, exams: [...]}, ...]}.
     */
    private function buildExamplePlan(array $assignments, Collection $timeSlots, Collection $rooms): array
    {
        if ($assignments === []) {
            return [];
        }

        // Nachschlage-Maps für die hypothetischen Ressourcen.
        $slotById = [];
        foreach ($timeSlots as $slot) {
            $slotById[$slot->id] = $slot;
        }
        $roomById = [];
        foreach ($rooms as $room) {
            $roomById[$room->id] = $room->room_number;
        }

        // Beteiligte StudentExams samt Namen in EINER Query nachladen.
        $examIds = array_values(array_filter(array_map(
            static fn ($a) => $a['student_exam_id'] ?? null,
            $assignments
        )));
        // externalExamSubject.subject muss mit: getSubjectName() und
        // getStudentName() lesen es fuer externe Bewerber, die weder Kurs
        // noch Schuelerdatensatz haben.
        $exams = StudentExam::whereIn('id', $examIds)
            ->with(['student', 'course.subject', 'examiner', 'assessor',
                    'externalExamSubject.externalApplicant', 'externalExamSubject.subject'])
            ->get()
            ->keyBy('id');

        // Zeilen aufbauen und nach Tag → Slot gruppieren.
        // Struktur: 'Y-m-d' => slot_number => list<row>
        $grouped = [];
        foreach ($assignments as $a) {
            $slot = $slotById[$a['time_slot_id'] ?? null] ?? null;
            $exam = $exams->get($a['student_exam_id'] ?? null);
            if ($slot === null || $exam === null) {
                continue;
            }

            $date = Carbon::parse($slot->date)->format('Y-m-d');
            $slotNo = (int) $slot->slot_number;

            // Fach und Name ueber die Modell-Methoden, nicht ueber course und
            // student: Ein externer Bewerber hat beides nicht, und der
            // Simulationsbericht zeigte fuer ihn zweimal "–", obwohl Fach und
            // Name bekannt sind. getSubjectName() faellt auf das Kurskuerzel
            // zurueck, wenn ein Kurs kein Katalogfach traegt.
            $subject = $exam->getSubjectName() ?? $exam->getSubjectLabel();
            // Prüfer/Beisitzer: FK ist nullable → nur bei gesetzter ID lesen.
            $examiner = $exam->examiner_id !== null ? ($exam->examiner->shortcode ?? '–') : '–';
            $assessor = $exam->assessor_id !== null ? ($exam->assessor->shortcode ?? '–') : '–';

            $grouped[$date][$slotNo][] = [
                'room'      => $roomById[$a['exam_room_id'] ?? null] ?? '–',
                'student'   => $exam->getStudentName(),
                'subject'   => $subject,
                'examiner'  => $examiner,
                'assessor'  => $assessor,
            ];
        }

        // In eine geordnete, view-freundliche Liste überführen.
        ksort($grouped);
        $plan = [];
        foreach ($grouped as $date => $slotsMap) {
            ksort($slotsMap);
            $slots = [];
            foreach ($slotsMap as $slotNo => $rows) {
                // Innerhalb eines Slots nach Raumnummer sortieren.
                usort($rows, static fn ($x, $y) => strnatcmp((string) $x['room'], (string) $y['room']));
                $slots[] = [
                    'slot_number' => $slotNo,
                    'exams'       => $rows,
                ];
            }
            $plan[] = [
                'date'    => $date,
                'weekday' => Carbon::parse($date)->locale('de')->isoFormat('dddd'),
                'count'   => array_sum(array_map(static fn ($s) => count($s['exams']), $slots)),
                'slots'   => $slots,
            ];
        }

        return $plan;
    }

    /**
     * Sucht über den durch $ranges aufgespannten Kombinationsraum und liefert
     * bis zu drei Vorschläge (Konzept Kap. 4).
     *
     * Zweistufig, um teure Optimizer-Läufe zu sparen:
     *  A. MACHBARKEITS-FRONT bestimmen. Für jede Kombination:
     *     1. Stufe 1 (arithmetische Schranke) filtert offensichtlich Unmögliches.
     *     2. Monotonie-Cache: Ist (d,s,r) schon als machbar bekannt, sind alle
     *        (≥d,≥s,≥r) ebenfalls machbar; ist (d,s,r) unmöglich, sind alle
     *        (≤d,≤s,≤r) ebenfalls unmöglich – kein erneuter Dry-Run nötig.
     *     3. Sonst: echter Dry-Run, nur zur Ja/Nein-Entscheidung.
     *  B. Aus allen machbaren Kombinationen die drei Vorschläge auswählen
     *     (kompakt/entzerrt/ausgewogen) und NUR für diese die vollen
     *     Kennzahlen per Dry-Run nachziehen.
     *
     * @param array{days: array{0:int,1:int}, slots_per_day: array{0:int,1:int}, rooms: array{0:int,1:int}} $ranges
     * @param int $maxDryRuns Sicherheitslimit an echten Optimizer-Läufen (Stufe A).
     * @return array{
     *     demand: array{total:int, max_teacher_load:int, max_student_load:int},
     *     feasible_count: int,
     *     suggestions: array<string, array<string, mixed>>,
     *     dry_runs: int,
     *     skipped_arithmetic: int
     * }
     */
    public function search(ExamPeriod $examPeriod, array $ranges, int $maxDryRuns = 60): array
    {
        $demand = $this->analyzeDemand($examPeriod);
        $combos = $this->expandSearchSpace($ranges);

        $decided = [];            // "d-s-r" => bool
        $feasibleCombos = [];     // list<array{0:int,1:int,2:int}>
        $dryRuns = 0;
        $skippedArithmetic = 0;

        foreach ($combos as [$days, $slots, $rooms]) {
            // Stufe 1: arithmetische Schranke.
            if (!$this->isArithmeticallyFeasible($demand, $days, $slots, $rooms)['feasible']) {
                $skippedArithmetic++;
                $decided[$this->comboKey($days, $slots, $rooms)] = false;
                continue;
            }

            // Monotonie: ohne Dry-Run entscheiden, wenn möglich.
            $known = $this->lookupMonotone($decided, $days, $slots, $rooms);
            if ($known === null && $dryRuns < $maxDryRuns) {
                $known = $this->dryRun($examPeriod, $days, $slots, $rooms)['feasible'];
                $dryRuns++;
            }

            $decided[$this->comboKey($days, $slots, $rooms)] = (bool) $known;
            if ($known === true) {
                $feasibleCombos[] = [$days, $slots, $rooms];
            }
        }

        // Stufe B: Vorschläge wählen und deren volle Kennzahlen nachziehen.
        $picked = $this->pickSuggestionCombos($feasibleCombos);
        $suggestions = [];
        foreach ($picked as $label => $combo) {
            [$d, $s, $r] = $combo;
            // Für die finalen Vorschläge zusätzlich die exemplarische Belegung
            // (Detailplan) aufbauen – nur hier, nicht in der Feasibility-Front.
            $suggestions[$label] = $this->dryRun($examPeriod, $d, $s, $r, true);
            $dryRuns++;
        }

        return [
            'demand'             => $demand,
            'feasible_count'     => count($feasibleCombos),
            'suggestions'        => $suggestions,
            'dry_runs'           => $dryRuns,
            'skipped_arithmetic' => $skippedArithmetic,
        ];
    }

    /**
     * Lädt die schulspezifischen Bandbreiten aus SchoolSetting; fehlt ein Wert,
     * gilt der feste weite Startwert aus DEFAULT_RANGES (Konzept Kap. 4a).
     *
     * @return array{days: array{0:int,1:int}, slots_per_day: array{0:int,1:int}, rooms: array{0:int,1:int}}
     */
    public function loadRanges(): array
    {
        $ranges = [];
        foreach (self::DEFAULT_RANGES as $dim => [$defMin, $defMax]) {
            $min = (int) (\App\Models\SchoolSetting::get("simulation.$dim.min") ?? $defMin);
            $max = (int) (\App\Models\SchoolSetting::get("simulation.$dim.max") ?? $defMax);
            // Defensive: min ≤ max, beide ≥ 1.
            $min = max(1, $min);
            $max = max($min, $max);
            $ranges[$dim] = [$min, $max];
        }

        return $ranges;
    }

    /**
     * Speichert die schulspezifischen Bandbreiten als SchoolSetting-Defaults.
     *
     * @param array{days: array{0:int,1:int}, slots_per_day: array{0:int,1:int}, rooms: array{0:int,1:int}} $ranges
     */
    public function saveRanges(array $ranges): void
    {
        foreach (['days', 'slots_per_day', 'rooms'] as $dim) {
            \App\Models\SchoolSetting::set("simulation.$dim.min", (string) $ranges[$dim][0]);
            \App\Models\SchoolSetting::set("simulation.$dim.max", (string) $ranges[$dim][1]);
        }
    }


    /**
     * Baut hypothetische, NICHT persistierte TimeSlot-Instanzen mit stabilen,
     * eindeutigen IDs. Die Tage werden aus den real verfügbaren Werktagen der
     * beiden Perioden-Wochen gewählt (die Lage der Prüfungswochen ist zentral
     * vorgegeben und wird nicht variiert — nur, wie viele Tage genutzt werden).
     *
     * @return Collection<int, TimeSlot>
     */
    private function buildHypotheticalSlots(ExamPeriod $examPeriod, int $days, int $slotsPerDay): Collection
    {
        $dates = $this->pickExamDates($examPeriod, $days);

        $slots = new Collection();
        $id = 1; // stabile, laufende ID über alle hypothetischen Slots
        foreach ($dates as $date) {
            for ($n = 1; $n <= $slotsPerDay; $n++) {
                $slot = new TimeSlot([
                    'exam_period_id'  => $examPeriod->id,
                    'date'            => $date->format('Y-m-d'),
                    'slot_number'     => $n,
                    'prep_start_time' => '08:00:00',
                    'prep_end_time'   => '08:30:00',
                    'exam_start_time' => '08:30:00',
                    'exam_end_time'   => '09:00:00',
                    'preference_weight' => 100,
                    'is_active'       => true,
                ]);
                // ID manuell setzen; Instanz bleibt bewusst nicht-persistiert
                // (exists=false), damit ein versehentliches save() auffiele.
                $slot->id = $id++;
                $slot->exists = false;
                $slots->push($slot);
            }
        }

        return $slots;
    }

    /**
     * Baut hypothetische, NICHT persistierte Room-Instanzen mit stabilen IDs.
     *
     * @return Collection<int, Room>
     */
    private function buildHypotheticalRooms(int $rooms): Collection
    {
        $collection = new Collection();
        for ($i = 1; $i <= $rooms; $i++) {
            $room = new Room([
                'room_number' => 'SIM-' . $i,
                'room_type'   => 'exam',
                'is_active'   => true,
            ]);
            $room->id = $i;
            $room->exists = false;
            $collection->push($room);
        }

        return $collection;
    }

    /**
     * Wählt die ersten $days Werktage aus den beiden Perioden-Wochen, möglichst
     * ausgewogen auf beide Wochen verteilt (damit die 2-Wochen-Logik des
     * Optimizers greift). Werktage = Mo–Fr.
     *
     * @return array<int, Carbon>
     */
    private function pickExamDates(ExamPeriod $examPeriod, int $days): array
    {
        $week1 = $this->weekdaysBetween($examPeriod->week_1_start, $examPeriod->week_1_end);
        $week2 = $this->weekdaysBetween($examPeriod->week_2_start, $examPeriod->week_2_end);

        // Abwechselnd aus Woche 1 und Woche 2 ziehen, bis $days erreicht ist.
        $dates = [];
        $i1 = 0;
        $i2 = 0;
        while (count($dates) < $days && ($i1 < count($week1) || $i2 < count($week2))) {
            if ($i1 < count($week1)) {
                $dates[] = $week1[$i1++];
                if (count($dates) >= $days) {
                    break;
                }
            }
            if ($i2 < count($week2)) {
                $dates[] = $week2[$i2++];
            }
        }

        return $dates;
    }

    /**
     * Liefert alle Werktage (Mo–Fr) im Intervall [$start, $end].
     *
     * @return array<int, Carbon>
     */
    private function weekdaysBetween(Carbon $start, Carbon $end): array
    {
        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($cursor->isWeekday()) {
                $days[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Zählt die noch ungeplanten mündlichen Prüfungen der Periode
     * (die Nachfrage-Größe, gegen die die Kapazität geprüft wird).
     */
    private function countUnplannedOralExams(ExamPeriod $examPeriod): int
    {
        return StudentExam::imPruefungszeitraum($examPeriod)
            ->where('exam_type', 'oral')
            ->count();
    }

    /**
     * Ermittelt die Nachfrage-Kennzahlen der mündlichen Prüfungen einer Periode
     * (Grundlage der arithmetischen Schranke, Konzept Kap. 2.1).
     *
     * - total: Gesamtzahl mündlicher Prüfungen.
     * - max_teacher_load: höchste Einsatzzahl einer einzelnen Lehrkraft
     *   (Prüfer + Beisitzer zusammen – beide müssen gleichzeitig anwesend sein,
     *   daher kann keine der beiden Rollen parallelisiert werden).
     * - max_student_load: höchste Prüfungszahl eines einzelnen Prüflings.
     *
     * Der Prüflings-Engpass zählt über den Prüflingsschlüssel und nicht über
     * `student_id`: der ist für externe Bewerber null, alle externen Prüfungen
     * der Periode wären unter dem Schlüssel "" zusammengefallen — und
     * `max_student_load` hätte eine Person ausgewiesen, die es nicht gibt.
     * Die Schranke wäre dadurch zu pessimistisch geworden und hätte
     * Ressourcen-Kombinationen als unmöglich gemeldet, die es nicht sind.
     *
     * @return array{total: int, max_teacher_load: int, max_student_load: int}
     */
    public function analyzeDemand(ExamPeriod $examPeriod): array
    {
        $exams = StudentExam::imPruefungszeitraum($examPeriod)
            ->where('exam_type', 'oral')
            ->with('externalExamSubject')
            ->get(['id', 'student_id', 'external_exam_subject_id', 'examiner_id', 'assessor_id']);

        $teacherLoad = [];
        $studentLoad = [];

        foreach ($exams as $exam) {
            if ($exam->examiner_id !== null) {
                $teacherLoad[$exam->examiner_id] = ($teacherLoad[$exam->examiner_id] ?? 0) + 1;
            }
            if ($exam->assessor_id !== null) {
                $teacherLoad[$exam->assessor_id] = ($teacherLoad[$exam->assessor_id] ?? 0) + 1;
            }
            $schluessel = $exam->prueflingsSchluessel();
            $studentLoad[$schluessel] = ($studentLoad[$schluessel] ?? 0) + 1;
        }

        return [
            'total'            => $exams->count(),
            'max_teacher_load' => $teacherLoad === [] ? 0 : max($teacherLoad),
            'max_student_load' => $studentLoad === [] ? 0 : max($studentLoad),
        ];
    }

    /**
     * Arithmetische Machbarkeitsschranke (Stufe 1, Konzept Kap. 2.1): prüft
     * ohne Optimizer-Lauf, ob eine Ressourcen-Kombination überhaupt möglich
     * sein KANN. Ein `feasible => true` bedeutet „nicht offensichtlich
     * unmöglich" – die endgültige Machbarkeit klärt erst der Dry-Run (Stufe 2).
     *
     * Engpässe:
     *  - Gesamtkapazität:   total ≤ Tage × Slots/Tag × Räume
     *  - Prüfer-Engpass:    max_teacher_load ≤ Tage × Slots/Tag   (raum­unabhängig)
     *  - Schüler-Engpass:   max_student_load ≤ Tage × Slots/Tag   (raum­unabhängig)
     *
     * @param array{total: int, max_teacher_load: int, max_student_load: int} $demand
     * @return array{feasible: bool, reasons: array<int, string>, capacity: int, parallel_per_day: int}
     */
    public function isArithmeticallyFeasible(array $demand, int $days, int $slotsPerDay, int $rooms): array
    {
        $capacity      = $days * $slotsPerDay * $rooms;
        $slotsPerRoom  = $days * $slotsPerDay; // Zeitpunkte, an denen eine Person höchstens prüfen kann
        $reasons       = [];

        if ($demand['total'] > $capacity) {
            $reasons[] = sprintf(
                'Gesamtkapazität zu klein: %d Prüfungen > %d Plätze (%d×%d×%d).',
                $demand['total'], $capacity, $days, $slotsPerDay, $rooms
            );
        }

        if ($demand['max_teacher_load'] > $slotsPerRoom) {
            $reasons[] = sprintf(
                'Prüfer-Engpass: eine Lehrkraft hat %d Einsätze, aber nur %d Zeitpunkte (%d Tage × %d Slots) verfügbar – mehr Räume helfen hier nicht.',
                $demand['max_teacher_load'], $slotsPerRoom, $days, $slotsPerDay
            );
        }

        if ($demand['max_student_load'] > $slotsPerRoom) {
            $reasons[] = sprintf(
                'Schüler-Engpass: ein Schüler hat %d Prüfungen, aber nur %d Zeitpunkte (%d Tage × %d Slots) verfügbar.',
                $demand['max_student_load'], $slotsPerRoom, $days, $slotsPerDay
            );
        }

        return [
            'feasible'         => $reasons === [],
            'reasons'          => $reasons,
            'capacity'         => $capacity,
            'parallel_per_day' => $slotsPerDay * $rooms,
        ];
    }

    /**
     * Spannt den Kombinationsraum aus den Bandbreiten auf. Reihenfolge:
     * aufsteigend nach Gesamtressourcen (Tage+Slots+Räume), damit die
     * Monotonie-Beschneidung möglichst früh greift (kleine Kombinationen
     * zuerst → machbare setzen sofort die obere Front).
     *
     * @param array{days: array{0:int,1:int}, slots_per_day: array{0:int,1:int}, rooms: array{0:int,1:int}} $ranges
     * @return list<array{0:int,1:int,2:int}>
     */
    private function expandSearchSpace(array $ranges): array
    {
        $combos = [];
        for ($d = $ranges['days'][0]; $d <= $ranges['days'][1]; $d++) {
            for ($s = $ranges['slots_per_day'][0]; $s <= $ranges['slots_per_day'][1]; $s++) {
                for ($r = $ranges['rooms'][0]; $r <= $ranges['rooms'][1]; $r++) {
                    $combos[] = [$d, $s, $r];
                }
            }
        }

        usort($combos, function ($a, $b) {
            return array_sum($a) <=> array_sum($b);
        });

        return $combos;
    }

    private function comboKey(int $days, int $slots, int $rooms): string
    {
        return $days . '-' . $slots . '-' . $rooms;
    }

    /**
     * Monotonie-Nachschlag: liefert true, wenn eine bereits als machbar
     * entschiedene Kombination in jeder Dimension ≤ (d,s,r) ist (dann ist
     * (d,s,r) erst recht machbar); false, wenn eine bereits als unmöglich
     * entschiedene Kombination in jeder Dimension ≥ (d,s,r) ist (dann ist
     * (d,s,r) ebenfalls unmöglich); sonst null (unbekannt).
     *
     * @param array<string, bool> $decided
     */
    private function lookupMonotone(array $decided, int $days, int $slots, int $rooms): ?bool
    {
        foreach ($decided as $key => $isFeasible) {
            [$kd, $ks, $kr] = array_map('intval', explode('-', $key));

            if ($isFeasible && $kd <= $days && $ks <= $slots && $kr <= $rooms) {
                return true;
            }
            if (!$isFeasible && $kd >= $days && $ks >= $slots && $kr >= $rooms) {
                return false;
            }
        }

        return null;
    }

    /**
     * Wählt aus den machbaren Kombinationen bis zu drei Vorschläge
     * (Konzept Kap. 4.2, Priorität aus Kap. 1.3):
     *  - compact:  wenigste Prüfungstage (bei Gleichstand wenigste Slots/Tag).
     *  - spread:   wenigste Slots/Tag = geringste Tagesbelastung
     *              (bei Gleichstand wenigste Tage).
     *  - balanced: bester gewichteter Kompromiss aus normierten Tagen,
     *              Slots/Tag und Räumen (Tage am höchsten gewichtet).
     *
     * Doppelte Labels (wenn dieselbe Kombination mehrere Kriterien gewinnt)
     * werden zusammengeführt, sodass keine identischen Vorschläge doppelt
     * teuer nachgerechnet werden.
     *
     * @param list<array{0:int,1:int,2:int}> $feasible
     * @return array<string, array{0:int,1:int,2:int}>
     */
    private function pickSuggestionCombos(array $feasible): array
    {
        if ($feasible === []) {
            return [];
        }

        // compact: min Tage, dann min Slots/Tag, dann min Räume.
        $compact = $this->minBy($feasible, fn ($c) => [$c[0], $c[1], $c[2]]);

        // spread: min Slots/Tag, dann min Tage, dann min Räume.
        $spread = $this->minBy($feasible, fn ($c) => [$c[1], $c[0], $c[2]]);

        // balanced: gewichtete Summe (Tage×3 + Slots×2 + Räume×1), klein = gut.
        $balanced = $this->minBy($feasible, fn ($c) => [$c[0] * 3 + $c[1] * 2 + $c[2], $c[0], $c[1]]);

        // Zusammenführen unter Vermeidung identischer Kombinationen.
        $result = [];
        $seen = [];
        foreach (['compact' => $compact, 'spread' => $spread, 'balanced' => $balanced] as $label => $combo) {
            $key = $this->comboKey($combo[0], $combo[1], $combo[2]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[$label] = $combo;
        }

        return $result;
    }

    /**
     * Liefert das Element aus $items, dessen $keyFn-Wert (ein Array, lexikografisch
     * verglichen) minimal ist.
     *
     * @param list<array{0:int,1:int,2:int}> $items
     * @param callable(array{0:int,1:int,2:int}): array<int, int> $keyFn
     * @return array{0:int,1:int,2:int}
     */
    private function minBy(array $items, callable $keyFn): array
    {
        $best = $items[0];
        $bestKey = $keyFn($best);
        foreach ($items as $item) {
            $key = $keyFn($item);
            if ($key < $bestKey) {
                $best = $item;
                $bestKey = $key;
            }
        }

        return $best;
    }
}
