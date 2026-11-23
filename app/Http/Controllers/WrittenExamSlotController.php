<?php

namespace App\Http\Controllers;

use App\Models\WrittenExamSlot;
use App\Models\WrittenExamYearDate;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Verwaltet die schriftlichen Prüfungs-Slots (jahresunabhängige Templates)
 * sowie die konkreten Jahrestermine. Nur für Super-Admins zugänglich.
 *
 * Routes:
 *   GET    /admin/written-exam-slots              → index
 *   GET    /admin/written-exam-slots/create        → create
 *   POST   /admin/written-exam-slots              → store
 *   GET    /admin/written-exam-slots/{slot}/edit  → edit
 *   PUT    /admin/written-exam-slots/{slot}        → update
 *   DELETE /admin/written-exam-slots/{slot}        → destroy
 *
 *   GET    /admin/written-exam-year-dates/{year}           → yearDatesIndex
 *   POST   /admin/written-exam-year-dates/{year}           → yearDatesStore
 *   DELETE /admin/written-exam-year-dates/{yearDate}       → yearDateDestroy
 */
class WrittenExamSlotController extends Controller
{
    // =========================================================================
    // SLOTS (jahresunabhängige Templates)
    // =========================================================================

    public function index()
    {
        $this->authorize('viewAny', WrittenExamSlot::class);

        $slots = WrittenExamSlot::with('subjects')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('requirement_level');

        $availableYears = WrittenExamYearDate::select('exam_year')
            ->distinct()
            ->orderBy('exam_year', 'desc')
            ->pluck('exam_year');

        return view('admin.written-exam-slots.index', compact('slots', 'availableYears'));
    }

    public function create()
    {
        $this->authorize('create', WrittenExamSlot::class);

        $subjects = Subject::where('can_abitur', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.written-exam-slots.create', compact('subjects'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', WrittenExamSlot::class);

        $validated = $request->validate([
            'name'              => 'required|string|max:100',
            'requirement_level' => 'required|in:grundlegend,erhoeht',
            'start_time'        => 'required|date_format:H:i',
            'end_time'          => 'required|date_format:H:i|after:start_time',
            'sort_order'        => 'required|integer|min:0|max:9999',
            'subject_ids'       => 'nullable|array',
            'subject_ids.*'     => 'exists:subjects,id',
        ]);

        try {
            DB::beginTransaction();

            $slot = WrittenExamSlot::create([
                'name'              => $validated['name'],
                'requirement_level' => $validated['requirement_level'],
                'start_time'        => $validated['start_time'],
                'end_time'          => $validated['end_time'],
                'sort_order'        => $validated['sort_order'],
            ]);

            if (!empty($validated['subject_ids'])) {
                $slot->subjects()->attach($validated['subject_ids']);
            }

            DB::commit();

            Log::info('WrittenExamSlot erstellt', ['slot_id' => $slot->id, 'user' => auth()->id()]);

            return redirect()->route('admin.written-exam-slots.index')
                ->with('success', "Prüfungs-Slot \"{$slot->name}\" erfolgreich angelegt.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fehler beim Erstellen des WrittenExamSlots', ['error' => $e->getMessage()]);

            return back()->withInput()
                ->with('error', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function edit(WrittenExamSlot $writtenExamSlot)
    {
        $this->authorize('update', $writtenExamSlot);

        $subjects = Subject::where('can_abitur', true)->orderBy('sort_order')->orderBy('name')->get();
        $selectedSubjectIds = $writtenExamSlot->subjects->pluck('id')->all();

        return view('admin.written-exam-slots.edit', compact(
            'writtenExamSlot', 'subjects', 'selectedSubjectIds'
        ));
    }

    public function update(Request $request, WrittenExamSlot $writtenExamSlot)
    {
        $this->authorize('update', $writtenExamSlot);

        $validated = $request->validate([
            'name'              => 'required|string|max:100',
            'requirement_level' => 'required|in:grundlegend,erhoeht',
            'start_time'        => 'required|date_format:H:i',
            'end_time'          => 'required|date_format:H:i|after:start_time',
            'sort_order'        => 'required|integer|min:0|max:9999',
            'subject_ids'       => 'nullable|array',
            'subject_ids.*'     => 'exists:subjects,id',
        ]);

        try {
            DB::beginTransaction();

            $writtenExamSlot->update([
                'name'              => $validated['name'],
                'requirement_level' => $validated['requirement_level'],
                'start_time'        => $validated['start_time'],
                'end_time'          => $validated['end_time'],
                'sort_order'        => $validated['sort_order'],
            ]);

            $writtenExamSlot->subjects()->sync($validated['subject_ids'] ?? []);

            DB::commit();

            Log::info('WrittenExamSlot aktualisiert', ['slot_id' => $writtenExamSlot->id, 'user' => auth()->id()]);

            return redirect()->route('admin.written-exam-slots.index')
                ->with('success', "Slot \"{$writtenExamSlot->name}\" aktualisiert.");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function destroy(WrittenExamSlot $writtenExamSlot)
    {
        $this->authorize('delete', $writtenExamSlot);

        // Schutz: Slot nicht löschen wenn Jahrestermine existieren
        if ($writtenExamSlot->yearDates()->exists()) {
            return back()->with('error',
                "Der Slot \"{$writtenExamSlot->name}\" kann nicht gelöscht werden, " .
                "da noch Jahrestermine dafür existieren."
            );
        }

        $name = $writtenExamSlot->name;
        $writtenExamSlot->delete(); // cascade löscht slot_subjects automatisch

        Log::info('WrittenExamSlot gelöscht', ['name' => $name, 'user' => auth()->id()]);

        return redirect()->route('admin.written-exam-slots.index')
            ->with('success', "Slot \"{$name}\" gelöscht.");
    }

    // =========================================================================
    // JAHRESTERMINE (konkretes Datum je Slot und Jahr)
    // =========================================================================

    public function yearDatesIndex(int $year)
    {
        $this->authorize('viewAny', WrittenExamSlot::class);

        $days = WrittenExamSlot::with(['subjects', 'yearDates' => function ($q) use ($year) {
            $q->where('exam_year', $year);
        }])
        ->orderBy('sort_order')
        ->get()
        ->groupBy(fn($s) => intdiv($s->sort_order, 10));

        $availableYears = WrittenExamYearDate::select('exam_year')
            ->distinct()
            ->orderBy('exam_year', 'desc')
            ->pluck('exam_year');

        return view('admin.written-exam-slots.year-dates', compact('days', 'year', 'availableYears'));
    }

    public function yearDatesStore(Request $request, int $year)
    {
        $this->authorize('create', WrittenExamSlot::class);

        $request->validate([
            'dates'             => 'required|array',
            'dates.*'           => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->dates as $slotId => $date) {
                if (empty($date)) {
                    // Datum entfernen
                    WrittenExamYearDate::where('exam_year', $year)
                        ->where('written_exam_slot_id', $slotId)
                        ->delete();
                    continue;
                }

                WrittenExamYearDate::updateOrCreate(
                    ['exam_year' => $year, 'written_exam_slot_id' => $slotId],
                    ['exam_date' => $date]
                );
            }

            DB::commit();

            Log::info("WrittenExamYearDates für {$year} gespeichert", ['user' => auth()->id()]);

            return redirect()->route('admin.written-exam-slots.year-dates', $year)
                ->with('success', "Termine für Abitur {$year} gespeichert.");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Fehler beim Speichern: ' . $e->getMessage());
        }
    }

    public function yearDateDestroy(WrittenExamYearDate $writtenExamYearDate)
    {
        $this->authorize('delete', WrittenExamSlot::class);

        $writtenExamYearDate->delete();

        return back()->with('success', 'Jahrestermin gelöscht.');
    }
}
