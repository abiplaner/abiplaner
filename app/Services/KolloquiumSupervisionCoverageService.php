<?php

namespace App\Services;

use App\Models\ExamAssignment;
use App\Models\ExamPeriod;
use App\Models\OralExamDayRoom;
use App\Models\Supervision;

/**
 * Kontrolliert, ob für die Kolloquien die "richtige Anzahl" an Aufsichten
 * (Vorbereitungsraum / Warteraum / Gangaufsicht) eingesetzt ist.
 *
 * Der Bedarf hängt direkt an den echten Prüfungs-Zeitslots (App\Models\TimeSlot,
 * verwaltet unter /planning/time-slots) — es gibt dafür KEIN eigenes
 * Zeitraster für Aufsichten. Pro Prüfungsslot mit mindestens einer aktiven
 * Prüfung:
 *   - Gangaufsicht: eine Aufsicht je an dem Tag konfiguriertem Gang-Ort
 *   - Vorbereitungsraum: eine Aufsicht je an dem Tag konfiguriertem
 *     Vorbereitungsraum (Kolloquium → Räume & Tage) – die Prüflinge eines
 *     Zeitslots bereiten sich gemeinsam in diesem einen Raum vor, NICHT
 *     einzeln in unterschiedlichen Räumen
 *   - Warteraum: NUR bei Kopplungen (StudentExam::coupling_id, >1 Prüfling in
 *     der Gruppe). Wer vor dem Vorbereitungsbeginn des zuletzt startenden
 *     Prüflings der Gruppe bereits fertig ist, muss ab dem eigenen Prüfungsende
 *     bis dahin warten. Diese Wartespanne kann mehrere echte Prüfungsslots
 *     überlappen (z. B. während der 2. und 3. Staffel, wenn der 1. Prüfling
 *     schon nach der 1. Staffel fertig ist) – die Aufsicht wird deshalb auf
 *     JEDEN Prüfungsslot gelegt, dessen eigenes Zeitfenster die Wartespanne
 *     überlappt, je an dem Tag konfiguriertem Warteraum. Der eigene Slot des
 *     wartenden Prüflings selbst zählt NICHT mit (dort läuft noch die eigene
 *     Prüfung, niemand wartet währenddessen).
 *
 * Aufsichten (Supervision) werden direkt über `time_slot_id` (FK auf den
 * echten TimeSlot) zugeordnet, nicht über ein separates Zeitraster.
 */
class KolloquiumSupervisionCoverageService
{
    /**
     * @return array{
     *     neededCellKeys: array<string, true>,
     *     coveredCellKeys: array<string, true>,
     *     missingCellKeys: array<string, true>,
     *     orphanedCellKeys: array<string, true>,
     *     uncoveredCount: int,
     *     orphanedCount: int,
     * }
     */
    public function compute(ExamPeriod $examPeriod): array
    {
        // 1) Aktive Prüfungs-Zuweisungen mit Zeitslot + Kopplung laden (Bedarfsgrundlage)
        $assignments = ExamAssignment::where('status', '!=', 'cancelled')
            ->whereNotNull('time_slot_id')
            ->whereHas('timeSlot', fn ($q) => $q->where('exam_period_id', $examPeriod->id))
            ->with(['timeSlot', 'studentExam'])
            ->get();

        $byDate     = $assignments->groupBy(fn ($a) => $a->timeSlot?->date->format('Y-m-d'));
        $byTimeSlot = $assignments->groupBy('time_slot_id');

        // 2) Konfigurierte Vorbereitungs-/Warte-/Gang-Räume je Tag
        $dayRooms = OralExamDayRoom::where('exam_period_id', $examPeriod->id)
            ->where('date', '!=', '1900-01-01')
            ->get();
        $prepRoomsByDate     = $dayRooms->where('room_role', 'preparation')
            ->groupBy(fn ($r) => $r->date->format('Y-m-d'));
        $waitingRoomsByDate  = $dayRooms->where('room_role', 'waiting')
            ->groupBy(fn ($r) => $r->date->format('Y-m-d'));
        $corridorRoomsByDate = $dayRooms->where('room_role', 'corridor')
            ->groupBy(fn ($r) => $r->date->format('Y-m-d'));

        // 3) Bedarf: "{time_slot_id}_{room_id}_{type}" => true
        $neededCellKeys = [];

        // 3a) Gangaufsicht + Vorbereitungsraum: je Prüfungsslot, je konfiguriertem Raum
        foreach ($byTimeSlot as $timeSlotId => $slotAssignments) {
            $ts = $slotAssignments->first()?->timeSlot;
            if (!$ts) {
                continue;
            }
            $dateStr = $ts->date->format('Y-m-d');

            foreach (($corridorRoomsByDate[$dateStr] ?? collect()) as $cr) {
                $neededCellKeys[$timeSlotId . '_' . $cr->room_id . '_corridor'] = true;
            }

            foreach (($prepRoomsByDate[$dateStr] ?? collect()) as $pr) {
                $neededCellKeys[$timeSlotId . '_' . $pr->room_id . '_preparation'] = true;
            }
        }

        // 3b) Warteraum: aus Kopplungen abgeleitet, überlappt ggf. mehrere Prüfungsslots
        foreach ($byDate as $dateStr => $dayAssignments) {
            if ($dateStr === null) {
                continue;
            }

            $daySlots = $dayAssignments->pluck('timeSlot')->filter()->unique('id')->values();

            // Letzter Vorbereitungsbeginn je Kopplungsgruppe an diesem Tag
            $couplingLastPrep = [];
            $couplingCount    = [];
            foreach ($dayAssignments as $a) {
                $cid = $a->studentExam?->coupling_id;
                if (!$cid) {
                    continue;
                }
                $couplingCount[$cid] = ($couplingCount[$cid] ?? 0) + 1;
                $t = (string)$a->timeSlot->prep_start_time;
                if (!isset($couplingLastPrep[$cid]) || $t > $couplingLastPrep[$cid]) {
                    $couplingLastPrep[$cid] = $t;
                }
            }
            $relevantCouplings = array_filter($couplingCount, fn ($c) => $c > 1);

            // Wartespanne je betroffenem Prüfling: [eigenes Prüfungsende, letzter Vorbereitungsbeginn)
            foreach ($dayAssignments as $a) {
                $cid = $a->studentExam?->coupling_id;
                if (!$cid || !isset($relevantCouplings[$cid])) {
                    continue;
                }
                $myPrep    = (string)$a->timeSlot->prep_start_time;
                $myExamEnd = (string)$a->timeSlot->exam_end_time;
                $lastPrep  = $couplingLastPrep[$cid];

                if ($myPrep >= $lastPrep || $lastPrep <= $myExamEnd) {
                    continue; // kein Wartebedarf für diesen Prüfling
                }

                // Alle Prüfungsslots des Tages, deren eigenes Zeitfenster die
                // Wartespanne [myExamEnd, lastPrep) überlappt
                foreach ($daySlots as $slot) {
                    $overlaps = (string)$slot->prep_start_time < $lastPrep
                        && $myExamEnd < (string)$slot->exam_end_time;
                    if (!$overlaps) {
                        continue;
                    }
                    foreach (($waitingRoomsByDate[$dateStr] ?? collect()) as $wr) {
                        $neededCellKeys[$slot->id . '_' . $wr->room_id . '_waiting'] = true;
                    }
                }
            }
        }

        // 4) Vorhandene Aufsichten (direkt am echten TimeSlot) laden
        $supervisions = Supervision::whereIn('time_slot_id', $byTimeSlot->keys())
            ->whereIn('supervision_type', ['preparation', 'waiting', 'corridor'])
            ->whereNotNull('room_id')
            ->get();
        $coveredCellKeys = $supervisions
            ->mapWithKeys(fn ($s) => [$s->time_slot_id . '_' . $s->room_id . '_' . $s->supervision_type => true])
            ->all();

        // 5) Fehlend / überflüssig ableiten
        $missingCellKeys  = array_diff_key($neededCellKeys, $coveredCellKeys);
        $orphanedCellKeys = array_diff_key($coveredCellKeys, $neededCellKeys);

        return [
            'neededCellKeys'   => $neededCellKeys,
            'coveredCellKeys'  => $coveredCellKeys,
            'missingCellKeys'  => $missingCellKeys,
            'orphanedCellKeys' => $orphanedCellKeys,
            'uncoveredCount'   => count($missingCellKeys),
            'orphanedCount'    => count($orphanedCellKeys),
        ];
    }
}
