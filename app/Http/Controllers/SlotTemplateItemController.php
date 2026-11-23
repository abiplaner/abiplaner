<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Models\SlotTemplate;
use App\Models\SlotTemplateItem;
use Illuminate\Http\Request;

class SlotTemplateItemController extends Controller
{
    /**
     * Store a new slot item in the template.
     */
    public function store(Request $request, ExamPeriod $examPeriod, SlotTemplate $slotTemplate)
    {
        $validated = $request->validate([
            'slot_number' => 'required|integer|min:1',
            'prep_start_time' => 'required|date_format:H:i',
            'prep_end_time' => 'required|date_format:H:i|after:prep_start_time',
            'exam_start_time' => 'required|date_format:H:i|after_or_equal:prep_end_time',
            'exam_end_time' => 'required|date_format:H:i|after:exam_start_time',
            'preference_weight' => 'required|integer|min:0|max:200',
            'notes' => 'nullable|string',
        ]);

        $slotTemplate->items()->create($validated);

        return back()->with('success', 'Zeitslot zum Template hinzugefügt.');
    }

    /**
     * Update an existing slot item.
     */
    public function update(Request $request, ExamPeriod $examPeriod, SlotTemplate $slotTemplate, SlotTemplateItem $slotTemplateItem)
    {
        $validated = $request->validate([
            'slot_number' => 'required|integer|min:1',
            'prep_start_time' => 'required|date_format:H:i',
            'prep_end_time' => 'required|date_format:H:i|after:prep_start_time',
            'exam_start_time' => 'required|date_format:H:i|after_or_equal:prep_end_time',
            'exam_end_time' => 'required|date_format:H:i|after:exam_start_time',
            'preference_weight' => 'required|integer|min:0|max:200',
            'notes' => 'nullable|string',
        ]);

        $slotTemplateItem->update($validated);

        return back()->with('success', 'Zeitslot aktualisiert.');
    }

    /**
     * Delete a slot item.
     */
    public function destroy(ExamPeriod $examPeriod, SlotTemplate $slotTemplate, SlotTemplateItem $slotTemplateItem)
    {
        $slotTemplateItem->delete();

        return back()->with('success', 'Zeitslot entfernt.');
    }
}
