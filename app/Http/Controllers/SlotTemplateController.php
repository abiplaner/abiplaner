<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Models\SlotTemplate;
use App\Models\SlotTemplateItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlotTemplateController extends Controller
{
    /**
     * Display a listing of templates for an exam period.
     */
    public function index(ExamPeriod $examPeriod)
    {
        $templates = $examPeriod->slotTemplates()
            ->with('items')
            ->orderBy('name')
            ->get();

        return view('slot-templates.index', compact('examPeriod', 'templates'));
    }

    /**
     * Show the form for creating a new template.
     */
    public function create(ExamPeriod $examPeriod)
    {
        return view('slot-templates.create', compact('examPeriod'));
    }

    /**
     * Store a newly created template.
     */
    public function store(Request $request, ExamPeriod $examPeriod)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $template = $examPeriod->slotTemplates()->create($validated);

        return redirect()
            ->route('slot-templates.edit', ['examPeriod' => $examPeriod, 'slotTemplate' => $template])
            ->with('success', 'Template erfolgreich erstellt. Fügen Sie nun Zeitslots hinzu.');
    }

    /**
     * Show the form for editing the template.
     */
    public function edit(ExamPeriod $examPeriod, SlotTemplate $slotTemplate)
    {
        $slotTemplate->load('items');
        
        return view('slot-templates.edit', compact('examPeriod', 'slotTemplate'));
    }

    /**
     * Update the template.
     */
    public function update(Request $request, ExamPeriod $examPeriod, SlotTemplate $slotTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $slotTemplate->update($validated);

        return redirect()
            ->route('slot-templates.edit', ['examPeriod' => $examPeriod, 'slotTemplate' => $slotTemplate])
            ->with('success', 'Template aktualisiert.');
    }

    /**
     * Delete the template.
     */
    public function destroy(ExamPeriod $examPeriod, SlotTemplate $slotTemplate)
    {
        $slotTemplate->delete();

        return redirect()
            ->route('slot-templates.index', $examPeriod)
            ->with('success', 'Template gelöscht.');
    }

    /**
     * Apply template to specific dates.
     */
    public function applyToDates(Request $request, ExamPeriod $examPeriod, SlotTemplate $slotTemplate)
    {
        $validated = $request->validate([
            'dates'     => 'required|array',
            'dates.*'   => 'required|date',
            'overwrite' => 'boolean',
        ]);

        $overwrite = $request->boolean('overwrite', false);

        DB::beginTransaction();
        try {
            $affected = $slotTemplate->applyToDates($validated['dates'], $overwrite);
            DB::commit();

            $msg = $overwrite
                ? "{$affected} Zeitslots erstellt oder aktualisiert."
                : "{$affected} neue Zeitslots erstellt.";

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Fehler beim Anwenden des Templates: ' . $e->getMessage());
        }
    }
}
