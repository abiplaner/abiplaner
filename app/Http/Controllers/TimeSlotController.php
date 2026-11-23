<?php

namespace App\Http\Controllers;

use App\Models\TimeSlot;
use App\Models\ExamPeriod;
use App\Services\CurrentExamPeriodService;
use App\Models\ExamDayTemplate;
use App\Models\SlotTemplate;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TimeSlotController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of time slots.
     */
    public function index()
    {
        // Aktuellen Prüfungszeitraum finden (neuester)
        $examPeriod = app(CurrentExamPeriodService::class)->current();
        
        if (!$examPeriod) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Prüfungszeitraum vorhanden.');
        }
        
        $timeSlots = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->with(['examAssignments.studentExam.student', 'examAssignments.studentExam.assessor'])
            ->orderBy('date')
            ->orderBy('slot_number')
            ->get();
        
        // Anzahl der Räume für Kapazitätsberechnung
        $roomCount = \App\Models\Room::where('room_type', 'exam')
            ->where('is_active', true)
            ->count();
        
        // Templates für Raster-Dropdown
        $slotTemplates = SlotTemplate::where('exam_period_id', $examPeriod->id)
            ->with('items')
            ->orderBy('name')
            ->get();

        $week1Dates = $examPeriod->getExamDates(1);
        $week2Dates = $examPeriod->getExamDates(2);

        // Fester Bezug Tag → Raster aus DB
        $activeTemplateByDate = ExamDayTemplate::where('exam_period_id', $examPeriod->id)
            ->pluck('slot_template_id', 'date')
            ->mapWithKeys(fn($tid, $date) => [
                \Carbon\Carbon::parse($date)->format('Y-m-d') => $tid
            ])
            ->toArray();

        return view('time-slots.index', compact(
            'examPeriod', 'timeSlots', 'roomCount', 'slotTemplates', 'week1Dates', 'week2Dates', 'activeTemplateByDate'
        ));
    }
    
    /**
     * Store a newly created time slot.
     */
    public function store(Request $request, ExamPeriod $examPeriod)
    {
        $this->authorize('update', $examPeriod);
        
        $validated = $request->validate([
            'date' => 'required|date',
            'slot_number' => 'required|integer|min:1',
            'prep_start_time' => 'required|date_format:H:i',
            'prep_end_time' => 'required|date_format:H:i|after:prep_start_time',
            'exam_start_time' => 'required|date_format:H:i|after:prep_end_time',
            'exam_end_time' => 'required|date_format:H:i|after:exam_start_time',
            'preference_weight' => 'integer|min:0|max:200',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        // Prüfe, ob Zeitslot bereits existiert
        $exists = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->where('date', $validated['date'])
            ->where('slot_number', $validated['slot_number'])
            ->exists();
        
        if ($exists) {
            return redirect()->back()
                ->with('error', 'Ein Zeitslot mit dieser Nummer existiert bereits für dieses Datum.');
        }
        
        $validated['exam_period_id'] = $examPeriod->id;
        $validated['is_active'] = $request->has('is_active');
        $validated['preference_weight'] = $validated['preference_weight'] ?? 100;
        
        $timeSlot = TimeSlot::create($validated);
        
        ActivityLogger::log(
            'timeslot_created', 
            'TimeSlot', 
            $timeSlot->id, 
            "Zeitslot {$timeSlot->date->format('d.m.Y')} #{$timeSlot->slot_number} erstellt"
        );
        
        return redirect()->route('planning.time-slots.index')
            ->with('success', 'Zeitslot erfolgreich erstellt.');
    }
    
    /**
     * Update the specified time slot.
     */
    public function update(Request $request, TimeSlot $timeSlot)
    {
        $this->authorize('update', $timeSlot->examPeriod);
        
        $validated = $request->validate([
            'date' => 'required|date',
            'slot_number' => 'required|integer|min:1',
            'prep_start_time' => 'required|date_format:H:i',
            'prep_end_time' => 'required|date_format:H:i',
            'exam_start_time' => 'required|date_format:H:i',
            'exam_end_time' => 'required|date_format:H:i',
            'preference_weight' => 'integer|min:0|max:200',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        // Manuelle Zeitvalidierung
        $prepStart = strtotime($validated['prep_start_time']);
        $prepEnd = strtotime($validated['prep_end_time']);
        $examStart = strtotime($validated['exam_start_time']);
        $examEnd = strtotime($validated['exam_end_time']);
        
        if ($prepEnd <= $prepStart) {
            return response()->json([
                'success' => false,
                'message' => 'Die Vorbereitungsendzeit muss nach der Vorbereitungsstartzeit liegen.'
            ], 422);
        }
        
        if ($examStart < $prepEnd) {
            return response()->json([
                'success' => false,
                'message' => 'Die Prüfungsstartzeit muss nach oder gleich der Vorbereitungsendzeit liegen.'
            ], 422);
        }
        
        if ($examEnd <= $examStart) {
            return response()->json([
                'success' => false,
                'message' => 'Die Prüfungsendzeit muss nach der Prüfungsstartzeit liegen.'
            ], 422);
        }
        
        // Prüfe, ob neuer Zeitslot bereits existiert (außer dem aktuellen)
        if ($validated['date'] != $timeSlot->date->format('Y-m-d') || 
            $validated['slot_number'] != $timeSlot->slot_number) {
            
            $exists = TimeSlot::where('exam_period_id', $timeSlot->exam_period_id)
                ->where('date', $validated['date'])
                ->where('slot_number', $validated['slot_number'])
                ->where('id', '!=', $timeSlot->id)
                ->exists();
            
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ein Zeitslot mit dieser Nummer existiert bereits für dieses Datum.'
                ], 422);
            }
        }
        
        $validated['is_active'] = $request->has('is_active');
        
        $timeSlot->update($validated);
        
        ActivityLogger::log(
            'timeslot_updated', 
            'TimeSlot', 
            $timeSlot->id, 
            "Zeitslot {$timeSlot->date->format('d.m.Y')} #{$timeSlot->slot_number} aktualisiert"
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Zeitslot erfolgreich aktualisiert.'
        ]);
    }
    
    /**
     * Remove the specified time slot.
     */
    public function destroy(TimeSlot $timeSlot)
    {
        $this->authorize('update', $timeSlot->examPeriod);
        
        // Prüfe, ob Zeitslot Zuweisungen hat
        $assignmentCount = $timeSlot->examAssignments()->count();
        
        if ($assignmentCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Zeitslot kann nicht gelöscht werden. Es gibt {$assignmentCount} Prüfungszuweisungen."
            ], 422);
        }
        
        $date = $timeSlot->date->format('d.m.Y');
        $slotNumber = $timeSlot->slot_number;
        
        $timeSlot->delete();
        
        ActivityLogger::log(
            'timeslot_deleted', 
            'TimeSlot', 
            $timeSlot->id, 
            "Zeitslot {$date} #{$slotNumber} gelöscht"
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Zeitslot erfolgreich gelöscht.'
        ]);
    }

    /**
     * Assign a slot template to a specific date (replaces all existing slots).
     */
    public function assignTemplate(Request $request, ExamPeriod $examPeriod)
    {
        $validated = $request->validate([
            'date'        => 'required|date',
            'template_id' => 'required|integer',
        ]);

        $hasAssignments = TimeSlot::where('exam_period_id', $examPeriod->id)
            ->where('date', $validated['date'])
            ->whereHas('examAssignments')
            ->exists();

        if ($hasAssignments) {
            return response()->json([
                'success' => false,
                'message' => 'Dieser Tag hat bereits Prüfungszuweisungen.',
            ], 422);
        }

        /** @var \App\Models\SlotTemplate|null $template */
        $template = SlotTemplate::with('items')->find($validated['template_id']);

        if (!$template || $template->exam_period_id !== $examPeriod->id) {
            return response()->json(['success' => false, 'message' => 'Raster nicht gefunden.'], 404);
        }

        TimeSlot::where('exam_period_id', $examPeriod->id)
            ->where('date', $validated['date'])
            ->delete();

        foreach ($template->items as $item) {
            TimeSlot::create([
                'exam_period_id'      => $examPeriod->id,
                'date'                => $validated['date'],
                'slot_number'         => $item->slot_number,
                'prep_start_time'     => $item->prep_start_time,
                'prep_end_time'       => $item->prep_end_time,
                'exam_start_time'     => $item->exam_start_time,
                'exam_end_time'       => $item->exam_end_time,
                'preference_weight'   => $item->preference_weight,
                'is_active'           => true,
            ]);
        }

        // Festen Bezug Tag → Raster speichern
        ExamDayTemplate::updateOrCreate(
            ['exam_period_id' => $examPeriod->id, 'date' => $validated['date']],
            ['slot_template_id' => $template->id]
        );

        return response()->json([
            'success' => true,
            'message' => "Raster \"{$template->name}\" für " . \Carbon\Carbon::parse($validated['date'])->format('d.m.Y') . " gesetzt.",
        ]);
    }
}

