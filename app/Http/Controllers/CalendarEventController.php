<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\GlobalCalendarEvent;
use App\Support\SchoolYear;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class CalendarEventController extends Controller
{
    /**
     * Display a listing of calendar events (school-specific + global)
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', CalendarEvent::class);
        
        // Prüfe ob Tenant aktiv ist (User hat Schule)
        $hasTenant = Auth::user()->school_id !== null;

        // Angezeigt wird immer genau ein Schuljahr (1. August bis 31. Juli):
        // ohne Angabe das laufende, ein unbrauchbarer Wert gilt als keine
        // Angabe. Der Monatsfilter grenzt innerhalb dieses Schuljahres ein —
        // der Monat legt dabei das Kalenderjahr fest (August bis Dezember
        // liegen im ersten, Januar bis Juli im zweiten Jahr).
        $schoolYear = $request->query('school_year');
        if (!is_string($schoolYear) || !SchoolYear::isValid($schoolYear)) {
            $schoolYear = SchoolYear::current();
        }

        [$von, $bis] = SchoolYear::range($schoolYear);

        $monat = $request->integer('month');
        if ($monat >= 1 && $monat <= 12) {
            $von = Carbon::create($monat >= 8 ? $von->year : $von->year + 1, $monat, 1)->startOfDay();
            $bis = $von->copy()->endOfMonth()->startOfDay();
        }

        $vonDatum = $von->toDateString();
        $bisDatum = $bis->toDateString();
        
        $schoolEvents = collect(); // Leere Collection als Default
        
        // Schulspezifische Events nur wenn Tenant aktiv
        if ($hasTenant) {
            $schoolQuery = CalendarEvent::query()
                ->with(['creator'])
                ->orderBy('event_date')
                ->orderBy('priority', 'desc');
            
            // Filter anwenden
            if ($request->filled('event_type')) {
                $schoolQuery->where('event_type', $request->event_type);
            }
            
            if ($request->filled('grade_level')) {
                $schoolQuery->forGradeLevel((int)$request->grade_level);
            }
            
            $schoolQuery->betweenDates($vonDatum, $bisDatum);
            
            $schoolEvents = $schoolQuery->get();
            $schoolEvents->each(function($event) {
                $event->is_global = false;
            });
        }
        
        // Globale Events (immer, auch für Superadmin)
        $globalQuery = GlobalCalendarEvent::query()
            ->with(['creator'])
            ->orderBy('event_date')
            ->orderBy('priority', 'desc');
        
        // Filter anwenden
        if ($request->filled('event_type')) {
            $globalQuery->where('event_type', $request->event_type);
        }
        
        if ($request->filled('grade_level')) {
            $globalQuery->forGradeLevel((int)$request->grade_level);
        }
        
        $globalQuery->betweenDates($vonDatum, $bisDatum);
        
        $globalEvents = $globalQuery->get();
        $globalEvents->each(function($event) {
            $event->is_global = true;
        });
        
        // Alle Events kombinieren und sortieren
        // WICHTIG: concat() statt merge() verwenden, da merge() bei gleichen
        // numerischen Keys (beide starten bei 0) Einträge überschreibt!
        $allEvents = $schoolEvents->concat($globalEvents)
            ->sortBy([
                ['event_date', 'asc'],
                ['priority', 'desc'],
            ]);
        
        // Manuelle Paginierung
        $perPage = 50;
        $page = $request->input('page', 1);
        $events = new LengthAwarePaginator(
            $allEvents->forPage($page, $perPage),
            $allEvents->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        // Auswahl fürs Umschalten: die üblichen Schuljahre plus jedes, in dem
        // tatsächlich Termine liegen — sonst wären ältere Termine unerreichbar.
        $vorhandene = GlobalCalendarEvent::query()->distinct()->pluck('event_date')
            ->concat($hasTenant ? CalendarEvent::query()->distinct()->pluck('event_date') : [])
            ->map(fn ($datum) => SchoolYear::forDate($datum));
        $schoolYearOptions = array_reverse(SchoolYear::options($schoolYear, $vorhandene));

        return view('calendar.index', compact('events', 'hasTenant', 'schoolYear', 'schoolYearOptions'));
    }

    /**
     * Show the form for creating a new event
     */
    public function create()
    {
        $this->authorize('create', CalendarEvent::class);
        
        return view('calendar.create');
    }
    
    /**
     * Store a newly created event
     */
    public function store(Request $request)
    {
        $this->authorize('create', CalendarEvent::class);
        
        $validated = $request->validate(array_merge(
            $this->calendarEventValidationRules(),
            ['is_global' => 'nullable|boolean']
        ));
        
        $validated['created_by'] = Auth::id();
        $validated['color'] = $validated['color'] ?? '#3B82F6';
        
        // Prüfe ob globales Event erstellt werden soll (nur Superadmin)
        if ($request->boolean('is_global')) {
            // Policy-Check für Superadmin
            if (!Auth::user()->isSuperAdmin()) {
                abort(403, 'Nur Superadmins können globale Events erstellen.');
            }
            
            // Erstelle GlobalCalendarEvent
            unset($validated['is_global'], $validated['is_mandatory']); // is_mandatory gibt's nicht in global
            GlobalCalendarEvent::create($validated);
            
            return redirect()
                ->route('calendar.index')
                ->with('success', 'Globaler Termin erfolgreich erstellt (sichtbar für alle Schulen).');
        }
        
        // Erstelle normales CalendarEvent (schulspezifisch, tenant-isoliert)
        unset($validated['is_global']);
        
        CalendarEvent::create($validated);
        
        return redirect()
            ->route('calendar.index')
            ->with('success', 'Termin erfolgreich erstellt.');
    }

    /**
     * Show the form for editing an event
     */
    public function edit(CalendarEvent $calendarEvent)
    {
        $this->authorize('update', $calendarEvent);
        
        return view('calendar.edit', compact('calendarEvent'));
    }
    
    /**
     * Update the specified event
     */
    public function update(Request $request, CalendarEvent $calendarEvent)
    {
        $this->authorize('update', $calendarEvent);
        
        $validated = $request->validate($this->calendarEventValidationRules());
        
        $calendarEvent->update($validated);
        
        return redirect()
            ->route('calendar.index')
            ->with('success', 'Termin erfolgreich aktualisiert.');
    }
    
    /**
     * Gemeinsame Validierungsregeln für store() und update()
     *
     * @return array<string, string>
     */
    private function calendarEventValidationRules(): array
    {
        return [
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'event_date'     => 'required|date',
            'end_date'       => 'nullable|date|after_or_equal:event_date',
            'start_time'     => 'nullable|date_format:H:i',
            'end_time'       => 'nullable|date_format:H:i|after:start_time',
            'event_type'     => 'required|in:deadline,exam_written,exam_oral,exam_practical,holiday,public_holiday,info,grading,administration,other',
            'grade_levels'   => 'nullable|array',
            'grade_levels.*' => 'integer|in:11,12,13',
            'color'          => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_recurring'   => 'boolean',
            'is_mandatory'   => 'boolean',
            'priority'       => 'nullable|integer|min:0|max:10',
            'location'       => 'nullable|string|max:255',
        ];
    }

    /**
     * Remove the specified event
     */
    public function destroy(CalendarEvent $calendarEvent)
    {
        $this->authorize('delete', $calendarEvent);
        
        $calendarEvent->delete();
        
        return redirect()
            ->route('calendar.index')
            ->with('success', 'Termin erfolgreich gelöscht.');
    }
}
