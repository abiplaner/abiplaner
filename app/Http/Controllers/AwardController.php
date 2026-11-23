<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\AwardDescription;
use App\Models\AwardType;
use App\Services\CurrentExamPeriodService;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;

class AwardController extends Controller
{
    // ----------------------------------------------------------------
    // Hauptseite
    // ----------------------------------------------------------------

    public function index()
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();

        if (!$examPeriod) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Prüfungszeitraum vorhanden.');
        }

        $awardTypes   = AwardType::with('teacher')->orderBy('sort_order')->orderBy('name')->get();
        $descriptions = AwardDescription::orderBy('description')->pluck('description');
        $teachers     = Teacher::active()->orderBy('shortcode')->get();
        $students     = Student::where('exam_period_id', $examPeriod->id)
            ->where('grade', '13')
            ->where('bestanden', true)
            ->orderBy('lastname')->orderBy('firstname')->get();

        $awards = Award::where('exam_period_id', $examPeriod->id)
            ->with(['student', 'awardType.teacher', 'awardDescription'])
            ->orderBy('award_type_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $awardsByType    = $awards->groupBy('award_type_id');
        $awardsByStudent = $awards->groupBy('student_id');

        return view('abiturfeier.index', compact(
            'examPeriod', 'awardTypes', 'descriptions',
            'awards', 'students', 'teachers',
            'awardsByType', 'awardsByStudent'
        ));
    }

    // ----------------------------------------------------------------
    // Ehrungen CRUD
    // ----------------------------------------------------------------

    public function store(Request $request)
    {
        $validated = $request->validate([
            'exam_period_id' => 'required|integer',
            'student_ids'    => 'required|array|min:1',
            'student_ids.*'  => 'integer|exists:students,id',
            'award_type_id'  => 'required|integer',
            'notes'          => 'nullable|string|max:1000',
            'rank'           => 'nullable|integer|min:1|max:9',
            'is_candidate'   => 'nullable',
        ]);

        $awardTypeId  = (int) $validated['award_type_id'];
        $examPeriodId = (int) $validated['exam_period_id'];
        $isCandidate  = isset($validated['is_candidate']) && $validated['is_candidate'] === '1';
        $rank         = $validated['rank'] ?? null;
        $notes        = $validated['notes'] ?? null;

        $added    = 0;
        $skipped  = 0;

        foreach ($validated['student_ids'] as $studentId) {
            $exists = Award::where('exam_period_id', $examPeriodId)
                ->where('student_id', $studentId)
                ->where('award_type_id', $awardTypeId)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Award::create([
                'exam_period_id' => $examPeriodId,
                'student_id'     => $studentId,
                'award_type_id'  => $awardTypeId,
                'notes'          => $notes,
                'rank'           => $rank,
                'is_candidate'   => $isCandidate,
            ]);
            $added++;
        }

        $msg = $added . ' Ehrung' . ($added !== 1 ? 'en' : '') . ' angelegt.';
        if ($skipped > 0) {
            $msg .= ' (' . $skipped . ' bereits vorhanden, übersprungen.)';
        }

        return redirect()->back()
            ->with('success', $msg)
            ->with('open_award_type_id', $awardTypeId);
    }

    public function update(Request $request, Award $award)
    {
        $validated = $request->validate([
            'notes'        => 'nullable|string|max:1000',
            'rank'         => 'nullable|integer|min:1|max:9',
            'is_candidate' => 'nullable',
        ]);

        $award->update([
            'notes'        => $validated['notes'] ?? null,
            'rank'         => $validated['rank'] ?? null,
            'is_candidate' => isset($validated['is_candidate']) && $validated['is_candidate'] === '1',
        ]);

        return redirect()->back()
            ->with('success', 'Ehrung aktualisiert.')
            ->with('open_award_type_id', $award->award_type_id);
    }

    public function destroy(Award $award)
    {
        $typeId = $award->award_type_id;
        $award->delete();
        return redirect()->back()
            ->with('success', 'Ehrung entfernt.')
            ->with('open_award_type_id', $typeId);
    }

    // ----------------------------------------------------------------
    // Preisarten (AwardType) — Inline-Modal
    // ----------------------------------------------------------------

    public function awardTypes()
    {
        return response()->json(
            AwardType::orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function storeAwardType(Request $request)
    {
        $validated = $request->validate([
            'name'                    => 'required|string|max:150',
            'category'                => 'nullable|string|max:100',
            'teacher_id'              => 'nullable|integer',
            'no_winner_note'          => 'nullable|string|max:300',
            'certificate_in_envelope' => 'nullable',
            'has_physical_prize'      => 'nullable',
            'sort_order'              => 'nullable|integer|min:0',
        ]);

        AwardType::create([
            'name'                    => $validated['name'],
            'category'                => $validated['category'] ?? null,
            'teacher_id'              => $validated['teacher_id'] ?? null,
            'no_winner_note'          => $validated['no_winner_note'] ?? null,
            'certificate_in_envelope' => isset($validated['certificate_in_envelope']) && $validated['certificate_in_envelope'] === '1',
            'has_physical_prize'      => isset($validated['has_physical_prize']) && $validated['has_physical_prize'] === '1',
            'sort_order'              => $validated['sort_order'] ?? 0,
            'active'                  => true,
        ]);

        return back()->with('success', 'Preisart angelegt.');
    }

    public function updateAwardType(Request $request, AwardType $awardType)
    {
        $validated = $request->validate([
            'name'                    => 'required|string|max:150',
            'category'                => 'nullable|string|max:100',
            'teacher_id'              => 'nullable|integer',
            'active'                  => 'nullable',
            'no_winner_note'          => 'nullable|string|max:300',
            'certificate_in_envelope' => 'nullable',
            'has_physical_prize'      => 'nullable',
            'sort_order'              => 'nullable|integer|min:0',
        ]);

        $awardType->update([
            'name'                    => $validated['name'],
            'category'                => $validated['category'] ?? null,
            'teacher_id'              => $validated['teacher_id'] ?? null,
            'active'                  => isset($validated['active']) && $validated['active'] === '1',
            'no_winner_note'          => $validated['no_winner_note'] ?? null,
            'certificate_in_envelope' => isset($validated['certificate_in_envelope']) && $validated['certificate_in_envelope'] === '1',
            'has_physical_prize'      => isset($validated['has_physical_prize']) && $validated['has_physical_prize'] === '1',
            'sort_order'              => $validated['sort_order'] ?? $awardType->sort_order,
        ]);

        return back()->with('success', 'Preisart aktualisiert.');
    }

    public function destroyAwardType(AwardType $awardType)
    {
        $inUse = Award::where('award_type_id', $awardType->id)->exists();
        if ($inUse) {
            return back()->withErrors(['award_type' =>
                'Diese Preisart kann nicht gelöscht werden, da bereits Ehrungen vergeben wurden. Bitte deaktivieren.']);
        }

        $awardType->delete();
        return back()->with('success', 'Preisart gelöscht.');
    }

    // ----------------------------------------------------------------
    // Beschreibungs-Pool — AJAX Autocomplete
    // ----------------------------------------------------------------

    public function awardDescriptions(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $results = AwardDescription::when($q !== '', fn($query) =>
                $query->where('description', 'like', "%{$q}%"))
            ->orderBy('description')
            ->limit(20)
            ->pluck('description');

        return response()->json($results);
    }

    // ----------------------------------------------------------------
    // PDF-Exporte
    // ----------------------------------------------------------------

    public function pdfGesamt()
    {
        [$examPeriod, $awardTypes, $awards] = $this->loadPdfData();

        $awardsByType = $awards->groupBy('award_type_id');

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('abiturfeier.pdf.gesamt', compact(
            'examPeriod', 'awardTypes', 'awardsByType'
        ));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('ehrungen_gesamt.pdf');
    }

    public function pdfPreisart(AwardType $awardType)
    {
        [$examPeriod, , $awards] = $this->loadPdfData();

        $awardType->loadMissing('teacher');

        $awardsForType = $awards->where('award_type_id', $awardType->id)
            ->sortBy(function (Award $a): string {
                /** @var \App\Models\Student|null $s */
                $s = $a->student;
                return ($s !== null ? $s->lastname : '') . ($s !== null ? $s->firstname : '');
            });

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('abiturfeier.pdf.preisart', [
            'examPeriod'    => $examPeriod,
            'awardType'     => $awardType,
            'awardsForType' => $awardsForType,
        ]);
        $pdf->setPaper('A4', 'portrait');

        $safeName = preg_replace('/[^a-z0-9]/i', '_', $awardType->name);
        return $pdf->stream("ehrungen_{$safeName}.pdf");
    }

    public function pdfSchueler()
    {
        [$examPeriod, $awardTypes, $awards] = $this->loadPdfData();

        // Alle Schüler alphabetisch sortiert (ausgezeichnete und nicht ausgezeichnete)
        $collator = new \Collator('de_DE@collation=phonebook');
        $allStudents = \App\Models\Student::where('exam_period_id', $examPeriod->id)
            ->where('grade', '13')
            ->where('bestanden', true)
            ->orderBy('lastname')->orderBy('firstname')->get();

        // Ehrungen als Map: student_id → Collection von Awards
        $awardsByStudentId = $awards->groupBy('student_id');
        $typeMap           = $awardTypes->keyBy('id');

        // Schüler alphabetisch sortieren via Collator
        $sorted = $allStudents->sort(function (\App\Models\Student $a, \App\Models\Student $b) use ($collator): int {
            return $collator->compare(
                $a->lastname . $a->firstname,
                $b->lastname . $b->firstname
            );
        });

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('abiturfeier.pdf.schueler', compact(
            'examPeriod', 'awardTypes', 'sorted', 'awardsByStudentId', 'typeMap'
        ));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('ehrungen_schueler.pdf');
    }

    // ----------------------------------------------------------------
    // Hilfsmethode: gemeinsame Datenlast für alle PDFs
    // ----------------------------------------------------------------

    /**
     * @return array{0: \App\Models\ExamPeriod, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection}
     */
    private function loadPdfData(): array
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();
        $awardTypes = AwardType::with('teacher')->orderBy('sort_order')->orderBy('name')->get();

        // Bewusst nur Schüler: Auszeichnungen hängen im Datenmodell an
        // `awards.student_id`; für externe Bewerber gibt es sie nicht.
        $awards = Award::where('exam_period_id', $examPeriod->id)
            ->whereHas('student', fn ($q) => $q->where('bestanden', true))
            ->with(['student', 'awardType.teacher', 'awardDescription'])
            ->orderBy('award_type_id')
            ->orderBy('sort_order')
            ->get();

        return [$examPeriod, $awardTypes, $awards];
    }
}
