<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Models\Supervision;
use App\Models\SupervisionTemplate;
use App\Models\SupervisionTemplateAssignment;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupervisionTemplateController extends Controller
{
    public function index(ExamPeriod $examPeriod)
    {
        $templates = SupervisionTemplate::where('exam_period_id', $examPeriod->id)
            ->with('assignments.teacher', 'assignments.room')
            ->orderBy('name')
            ->get();

        return view('supervisions.templates.index', compact('examPeriod', 'templates'));
    }

    public function store(Request $request, ExamPeriod $examPeriod)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'source_date' => 'required|date'
        ]);

        try {
            $template = DB::transaction(function() use ($validated, $examPeriod) {
                // Template erstellen
                $template = SupervisionTemplate::create([
                    'exam_period_id' => $examPeriod->id,
                    'name' => $validated['name'],
                    'description' => $validated['description']
                ]);

                // Aufsichten von source_date kopieren
                $timeSlots = TimeSlot::where('exam_period_id', $examPeriod->id)
                    ->whereDate('date', $validated['source_date'])
                    ->get();

                foreach ($timeSlots as $slot) {
                    foreach ($slot->supervisions as $supervision) {
                        SupervisionTemplateAssignment::create([
                            'template_id' => $template->id,
                            'room_id' => $supervision->room_id,
                            'supervision_type' => $supervision->supervision_type,
                            'teacher_id' => $supervision->teacher_id,
                            'slot_number' => $slot->slot_number
                        ]);
                    }
                }

                return $template;
            });

            // Bei AJAX-Request JSON zurückgeben
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Template erfolgreich erstellt',
                    'template' => $template
                ]);
            }
            
            return redirect()
                ->route('supervisions.index', $examPeriod)
                ->with('success', 'Template erfolgreich erstellt');

        } catch (\Exception $e) {
            // Bei AJAX-Request JSON zurückgeben
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fehler beim Erstellen des Templates: ' . $e->getMessage()
                ], 422);
            }
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Fehler beim Erstellen des Templates: ' . $e->getMessage()]);
        }
    }

    public function apply(Request $request, ExamPeriod $examPeriod, SupervisionTemplate $template)
    {
        $validated = $request->validate([
            'target_dates' => 'required|array',
            'target_dates.*' => 'date',
            'overwrite' => 'boolean'
        ]);

        try {
            DB::transaction(function() use ($validated, $examPeriod, $template) {
                foreach ($validated['target_dates'] as $date) {
                    $timeSlots = TimeSlot::where('exam_period_id', $examPeriod->id)
                        ->whereDate('date', $date)
                        ->get();

                    foreach ($timeSlots as $slot) {
                        // Template-Zuweisungen für diesen Slot holen
                        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupervisionTemplateAssignment> $assignments */
                        $assignments = $template->assignments()
                            ->where('slot_number', $slot->slot_number)
                            ->get();

                        foreach ($assignments as $assignment) {
                            // Prüfen ob Lehrer verfügbar (falls zugewiesen)
                            if ($assignment->teacher_id && !$assignment->teacher->isAvailable($date)) {
                                continue; // Überspringe wenn nicht verfügbar
                            }

                            // Bei overwrite=true: vorhandene Aufsicht überschreiben
                            // Bei overwrite=false: nur erstellen wenn noch nicht vorhanden
                            if ($validated['overwrite'] ?? false) {
                                Supervision::updateOrCreate(
                                    [
                                        'time_slot_id' => $slot->id,
                                        'room_id' => $assignment->room_id,
                                        'supervision_type' => $assignment->supervision_type
                                    ],
                                    ['teacher_id' => $assignment->teacher_id]
                                );
                            } else {
                                Supervision::firstOrCreate(
                                    [
                                        'time_slot_id' => $slot->id,
                                        'room_id' => $assignment->room_id,
                                        'supervision_type' => $assignment->supervision_type
                                    ],
                                    ['teacher_id' => $assignment->teacher_id]
                                );
                            }
                        }
                    }
                }
            });

            // Bei AJAX-Request JSON zurückgeben
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Template erfolgreich angewendet'
                ]);
            }
            
            return back()->with('success', 'Template erfolgreich angewendet');

        } catch (\Exception $e) {
            // Bei AJAX-Request JSON zurückgeben
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fehler beim Anwenden des Templates: ' . $e->getMessage()
                ], 422);
            }
            
            return back()->withErrors(['error' => 'Fehler beim Anwenden des Templates: ' . $e->getMessage()]);
        }
    }

    public function destroy(ExamPeriod $examPeriod, SupervisionTemplate $template)
    {
        // Prüfen ob Template zum ExamPeriod gehört
        if ($template->exam_period_id !== $examPeriod->id) {
            return back()->withErrors(['error' => 'Template gehört nicht zu diesem Prüfungszeitraum']);
        }

        $template->delete();

        return redirect()
            ->route('supervisions.templates.index', $examPeriod)
            ->with('success', 'Template erfolgreich gelöscht');
    }
}
