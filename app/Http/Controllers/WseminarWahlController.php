<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WseminarWahl;
use App\Models\WseminarKurs;

class WseminarWahlController extends AbstractWahlController
{
    // =========================================================================
    // AbstractWahlController – Pflicht-Implementierungen
    // =========================================================================

    protected function makeImportService(): object
    {
        return new \App\Services\WseminarImportService();
    }

    protected function getIndexRoute(): string
    {
        return 'wseminar-wahlen.index';
    }

    protected function getJahrgangValidationRule(): string
    {
        return 'required|integer|in:11';
    }

    protected function getWahlModel(): string
    {
        return WseminarWahl::class;
    }

    protected function getImportViewName(): string
    {
        return 'wseminar-wahlen.import';
    }

    /** W-Seminare gibt es nur in Q11. */
    protected function getImportJahrgaenge(): array
    {
        return [11];
    }

    // =========================================================================
    // Eigene Methoden (nicht in der Basis-Klasse)
    // =========================================================================

    /**
     * Display a listing of the W-Seminar wahlen.
     */
    public function index(Request $request)
    {
        $query = WseminarWahl::query()
            ->with(['schueler', 'erstwahl.subject', 'zweitwahl.subject', 'drittwahl.subject', 'zugeteilterKurs.subject']);

        // Filter by search term
        if ($request->filled('search')) {
            $search = $request->input('search');

            // Subjects in central DB first, to avoid cross-DB join errors
            $subjectIds = \App\Models\Subject::where('name', 'like', "%{$search}%")->pluck('id')->toArray();

            $query->where(function ($q) use ($search, $subjectIds) {
                $q->whereHas('schueler', function ($q) use ($search) {
                    $q->where('lastname', 'like', "%{$search}%")
                      ->orWhere('firstname', 'like', "%{$search}%")
                      ->orWhere('class_name', 'like', "%{$search}%");
                });

                $searchCourseClosure = function ($q) use ($search, $subjectIds) {
                    $q->where(function ($subQ) use ($search, $subjectIds) {
                        $subQ->where('titel', 'like', "%{$search}%");
                        if (!empty($subjectIds)) {
                            $subQ->orWhereIn('subject_id', $subjectIds);
                        }
                    });
                };

                $q->orWhereHas('erstwahl', $searchCourseClosure)
                  ->orWhereHas('zweitwahl', $searchCourseClosure)
                  ->orWhereHas('drittwahl', $searchCourseClosure)
                  ->orWhereHas('zugeteilterKurs', $searchCourseClosure);
            });
        }

        // Filter by status
        if ($request->filled('status') && $request->input('status') !== 'alle') {
            $status = $request->input('status');
            if ($status === 'nicht_zugeteilt') {
                $query->whereNull('zuteilung_wseminar_kurs_id');
            } elseif (in_array($status, ['erstwahl', 'zweitwahl', 'drittwahl', 'keine'])) {
                $query->where('prioritaet_erhalten', $status);
            }
        }

        // Sorting
        $sort      = $request->input('sort');
        $direction = $request->input('direction', 'asc');

        if ($sort === 'lastname') {
            $query->leftJoin('students', 'wseminar_wahlen.schueler_id', '=', 'students.id')
                  ->orderBy('students.lastname', $direction)
                  ->orderBy('students.firstname', $direction)
                  ->select('wseminar_wahlen.*');
        } elseif ($sort === 'class_name') {
            $query->leftJoin('students', 'wseminar_wahlen.schueler_id', '=', 'students.id')
                  ->orderBy('students.class_name', $direction)
                  ->orderBy('students.lastname', $direction)
                  ->select('wseminar_wahlen.*');
        } elseif ($sort === 'status') {
            $query->orderBy('prioritaet_erhalten', $direction);
        } elseif ($sort === 'zuteilung') {
            $query->leftJoin('wseminar_kurse as zk', 'wseminar_wahlen.zuteilung_wseminar_kurs_id', '=', 'zk.id')
                  ->orderBy('zk.titel', $direction)
                  ->select('wseminar_wahlen.*');
        } else {
            $query->orderBy('schuljahr', 'desc')
                  ->orderBy('jahrgang', 'desc')
                  ->orderByRaw('zuteilung_wseminar_kurs_id IS NULL DESC')
                  ->orderBy('schueler_id');
        }

        $wahlen = $query->paginate(50)->withQueryString();

        $latestWahl = WseminarWahl::query()
            ->orderBy('schuljahr', 'desc')
            ->orderBy('jahrgang', 'desc')
            ->first();
        $latestSchuljahr = $latestWahl ? $latestWahl->schuljahr : $this->getCurrentSchuljahr();
        $latestJahrgang  = $latestWahl ? $latestWahl->jahrgang : 11;

        return view('wseminar-wahlen.index', compact(
            'wahlen',
            'latestSchuljahr',
            'latestJahrgang',
            'sort',
            'direction'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(WseminarWahl $wseminarWahl)
    {
        $kurse = WseminarKurs::query()
            ->where('schuljahr', $wseminarWahl->schuljahr)
            ->where('jahrgang', $wseminarWahl->jahrgang)
            ->with('subject')
            ->get()
            ->sortBy('kurs_nummer');

        return view('wseminar-wahlen.edit', compact('wseminarWahl', 'kurse'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, WseminarWahl $wseminarWahl)
    {
        $validated = $request->validate([
            'erstwahl_wseminar_id'  => 'required|exists:wseminar_kurse,id',
            'zweitwahl_wseminar_id' => 'nullable|exists:wseminar_kurse,id',
            'drittwahl_wseminar_id' => 'nullable|exists:wseminar_kurse,id',
        ]);

        $wseminarWahl->update($validated);

        return redirect()->route('wseminar-wahlen.index')
            ->with('success', 'Wahl wurde erfolgreich aktualisiert.');
    }
}
