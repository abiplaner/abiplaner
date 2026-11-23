<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomSeatingTemplate;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Barryvdh\DomPDF\Facade\Pdf;

class RoomController extends Controller
{
    use AuthorizesRequests;

    /**
     * Schreibt die Standard- und Hörverstehen-Sitzplan-Varianten eines
     * Raums aus dem Request (Modul 1.5). Leere Auswahl entfernt die
     * jeweilige Variante wieder.
     */
    private function syncSeatingTemplateVariants(Room $room, Request $request): void
    {
        $map = [
            RoomSeatingTemplate::VARIANT_STANDARD => $request->input('seating_template_id'),
            RoomSeatingTemplate::VARIANT_HOERVERSTAENDNIS => $request->input('hv_seating_template_id'),
        ];

        foreach ($map as $variant => $templateId) {
            if ($templateId) {
                RoomSeatingTemplate::updateOrCreate(
                    ['room_id' => $room->id, 'variant' => $variant],
                    ['seating_template_id' => $templateId]
                );
            } else {
                RoomSeatingTemplate::where('room_id', $room->id)->where('variant', $variant)->delete();
            }
        }
    }
    
    private function getSchoolId()
    {
        $user = auth()->user();
        if ($user->school_id) {
            return $user->school_id;
        }
        
        // Fallback für Super Admin: Erste Schule nehmen.
        // school_number, nicht id — `schools` hat keine Spalte `id`; siehe
        // die ausführliche Notiz in PlanningController::getSchoolId().
        if ($user->isSuperAdmin() || $user->email === 'admin@example.com') {
            return \App\Models\School::first()?->school_number;
        }
        
        return null;
    }
    
    public function index()
    {
        $this->authorize('viewAny', Room::class);
        
        // Räume der eigenen Schule (ohne Prüfungszeitraum-Einschränkung)
        $rooms = Room::with(['seatingTemplateVariants.seatingTemplate.seats'])
            ->orderBy('room_type')
            ->orderBy('room_number')
            ->get();

        $seatingTemplates = \App\Models\SeatingTemplate::orderBy('name')->get();

        return view('rooms.index', compact('rooms', 'seatingTemplates'));
    }
    
    public function create()
    {
        $this->authorize('create', Room::class);
        
        // Lade verfügbare Sitzplan-Vorlagen der Schule
        $seatingTemplates = \App\Models\SeatingTemplate::orderBy('name')->get();
        
        return view('rooms.create', compact('seatingTemplates'));
    }

    
    public function store(Request $request)
    {
        $this->authorize('create', Room::class);
        
        $validated = $request->validate([
            'room_number' => 'required|string|max:20',
            'room_type' => 'required|in:exam,preparation,waiting,lockdown,corridor',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'seating_template_id' => 'nullable|exists:seating_templates,id',
            'hv_seating_template_id' => 'nullable|exists:seating_templates,id',
        ]);

        // is_active wird als Checkbox gesendet, daher standardmäßig false wenn nicht gesetzt
        $validated['is_active'] = $request->has('is_active');

        $room = Room::create([
            'room_number' => $validated['room_number'],
            'room_type' => $validated['room_type'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        $this->syncSeatingTemplateVariants($room, $request);
        
        ActivityLogger::log('room_created', 'Room', $room->id, "Raum {$room->room_number} erstellt");
        
        return redirect()->route('rooms.index')
            ->with('success', 'Raum erfolgreich erstellt.');
    }
    
    public function edit(Room $room)
    {
        $this->authorize('update', $room);
        
        // Lade verfügbare Sitzplan-Vorlagen der Schule
        $seatingTemplates = \App\Models\SeatingTemplate::orderBy('name')->get();
        $room->load('seatingTemplateVariants');
        
        return view('rooms.edit', compact('room', 'seatingTemplates'));
    }
    
    public function update(Request $request, Room $room)
    {
        $this->authorize('update', $room);
        
        $validated = $request->validate([
            'room_number' => 'required|string|max:20',
            'room_type' => 'required|in:exam,preparation,waiting,lockdown,corridor',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'seating_template_id' => 'nullable|exists:seating_templates,id',
            'hv_seating_template_id' => 'nullable|exists:seating_templates,id',
        ]);
        
        // is_active wird als Checkbox gesendet, daher standardmäßig false wenn nicht gesetzt
        $validated['is_active'] = $request->has('is_active');
        
        $room->update([
            'room_number' => $validated['room_number'],
            'room_type' => $validated['room_type'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        $this->syncSeatingTemplateVariants($room, $request);
        
        ActivityLogger::log('room_updated', 'Room', $room->id, "Raum {$room->room_number} aktualisiert");
        
        return redirect()->route('rooms.index')
            ->with('success', 'Raum erfolgreich aktualisiert.');
    }
    
    public function destroy(Room $room)
    {
        $this->authorize('delete', $room);

        $roomNumber = $room->room_number;

        // Abhängige Datensätze zuerst löschen (FK-Constraints umgehen)
        $room->examAssignments()->delete();
        $room->prepAssignments()->delete();
        $room->supervisions()->delete();
        $room->writtenExamAssignments()->delete();

        $room->delete();

        ActivityLogger::log('room_deleted', 'Room', $room->id, "Raum {$roomNumber} gelöscht");

        return redirect()->route('rooms.index')
            ->with('success', "Raum \"{$roomNumber}\" erfolgreich gelöscht.");
    }
    
    /**
     * Show the room occupancy schedule for an exam period.
     */
    public function occupancy()
    {
        // Aktuellen Prüfungszeitraum finden (neuester)
        $examPeriod = app(\App\Services\CurrentExamPeriodService::class)->current();
        
        if (!$examPeriod) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Kein Prüfungszeitraum gefunden.');
        }
        
        $this->authorize('viewAny', Room::class);
        
        // Get all exam rooms for this school (automatisch durch Tenancy isoliert)
        $rooms = Room::where('room_type', 'exam')
            ->where('is_active', true)
            ->orderBy('room_number')
            ->get();
        
        // Get all time slots with their assignments
        $timeSlots = \App\Models\TimeSlot::where('exam_period_id', $examPeriod->id)
            ->with([
                'examAssignments.studentExam.student', 
                'examAssignments.studentExam.course.subject', 
                'examAssignments.studentExam.examiner',
                'examAssignments.studentExam.assessor',
                'examAssignments.examRoom'
            ])
            ->orderBy('date')
            ->orderBy('slot_number')
            ->get();
        
        // Build occupancy matrix
        $occupancyData = [];
        foreach ($timeSlots as $slot) {
            $slotKey = $slot->date->format('Y-m-d') . '_' . $slot->slot_number;
            $occupancyData[$slotKey] = [
                'date' => $slot->date,
                'slot_number' => $slot->slot_number,
                'time' => $slot->exam_start_time,
                'rooms' => []
            ];
            
            foreach ($rooms as $room) {
                $assignments = $slot->examAssignments->where('exam_room_id', $room->id);
                $occupancyData[$slotKey]['rooms'][$room->id] = [
                    'room' => $room,
                    'assignments' => $assignments,
                    'count' => $assignments->count()
                ];
            }
        }
        
        return view('rooms.occupancy', compact('examPeriod', 'rooms', 'occupancyData'));
    }

    /**
     * Exportiert den Raumbelegungsplan als PDF (eine Seite pro Woche und Raum)
     */
    public function exportOccupancyPdf(\App\Models\ExamPeriod $examPeriod)
    {
        $this->authorize('viewAny', Room::class);
        
        // Get all exam rooms for this school (nicht nach exam_period gefiltert)
        $rooms = Room::where('room_type', 'exam')
            ->where('is_active', true)
            ->orderBy('room_number')
            ->get();
        
        // Get all time slots with their assignments
        $timeSlots = \App\Models\TimeSlot::where('exam_period_id', $examPeriod->id)
            ->with([
                'examAssignments.studentExam.student', 
                'examAssignments.studentExam.course.subject', 
                'examAssignments.studentExam.examiner',
                'examAssignments.studentExam.assessor',
                'examAssignments.examRoom'
            ])
            ->orderBy('date')
            ->orderBy('slot_number')
            ->get();
        
        // Gruppiere nach Woche
        $week1Slots = $timeSlots->filter(function($slot) use ($examPeriod) {
            return $slot->date >= $examPeriod->week_1_start && $slot->date <= $examPeriod->week_1_end;
        });
        
        $week2Slots = $timeSlots->filter(function($slot) use ($examPeriod) {
            return $slot->date >= $examPeriod->week_2_start && $slot->date <= $examPeriod->week_2_end;
        });
        
        // Build data structure: [room][week][dates]
        $pdfData = [];
        
        foreach ($rooms as $room) {
            $roomData = [
                'room' => $room,
                'weeks' => []
            ];
            
            // Woche 1
            $week1Data = [
                'week_number' => 1,
                'start' => $examPeriod->week_1_start,
                'end' => $examPeriod->week_1_end,
                'dates' => []
            ];
            
            $slotsByDate = $week1Slots->groupBy(function($slot) {
                return $slot->date->format('Y-m-d');
            });
            
            foreach ($slotsByDate as $dateKey => $dateSlots) {
                $date = $dateSlots->first()->date;
                $week1Data['dates'][$dateKey] = [
                    'date' => $date,
                    'slots' => []
                ];
                
                foreach ($dateSlots as $slot) {
                    $assignments = $slot->examAssignments->where('exam_room_id', $room->id);
                    
                    $week1Data['dates'][$dateKey]['slots'][] = [
                        'slot_number' => $slot->slot_number,
                        'time' => $slot->exam_start_time,
                        'assignments' => $assignments
                    ];
                }
            }
            
            $roomData['weeks'][1] = $week1Data;
            
            // Woche 2
            $week2Data = [
                'week_number' => 2,
                'start' => $examPeriod->week_2_start,
                'end' => $examPeriod->week_2_end,
                'dates' => []
            ];
            
            $slotsByDate = $week2Slots->groupBy(function($slot) {
                return $slot->date->format('Y-m-d');
            });
            
            foreach ($slotsByDate as $dateKey => $dateSlots) {
                $date = $dateSlots->first()->date;
                $week2Data['dates'][$dateKey] = [
                    'date' => $date,
                    'slots' => []
                ];
                
                foreach ($dateSlots as $slot) {
                    $assignments = $slot->examAssignments->where('exam_room_id', $room->id);
                    
                    $week2Data['dates'][$dateKey]['slots'][] = [
                        'slot_number' => $slot->slot_number,
                        'time' => $slot->exam_start_time,
                        'assignments' => $assignments
                    ];
                }
            }
            
            $roomData['weeks'][2] = $week2Data;
            
            $pdfData[] = $roomData;
        }
        
        $schoolId = $this->getSchoolId();
        $school = \App\Models\School::find($schoolId);
        
        $pdf = Pdf::loadView('exports.room-occupancy-pdf', [
            'examPeriod' => $examPeriod,
            'pdfData' => $pdfData,
            'school' => $school,
        ])
        ->setPaper('a4', 'landscape');  // Querformat!
        
        $filename = 'Raumbelegung_' . $examPeriod->name . '_' . date('Y-m-d') . '.pdf';
        
        return $pdf->download($filename);
    }
}
