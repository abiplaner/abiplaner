<?php

namespace App\Http\Controllers;

use App\Models\GlobalCalendarEvent;
use Illuminate\Http\Request;

class GlobalCalendarEventController extends Controller
{
    /**
     * Display a listing of global calendar events.
     * Only accessible by super_admin in central context (no tenancy).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', GlobalCalendarEvent::class);

        $query = GlobalCalendarEvent::query();

        // Volltextsuche im Titel
        if ($search = $request->input('search')) {
            $query->where('title', 'like', '%' . $search . '%');
        }

        // Filter: Event-Typ
        if ($type = $request->input('type')) {
            $query->where('event_type', $type);
        }

        // Filter: Jahrgangsstufe
        if ($grade = $request->input('grade')) {
            $query->whereJsonContains('grade_levels', (int) $grade);
        }

        $events = $query->orderBy('event_date')
            ->orderBy('priority', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('admin.global-calendar.index', compact('events'));
    }

    /**
     * Show the form for creating a new global calendar event.
     */
    public function create()
    {
        $this->authorize('create', GlobalCalendarEvent::class);
        
        return view('admin.global-calendar.create');
    }

    /**
     * Store a newly created global calendar event.
     */
    public function store(Request $request)
    {
        $this->authorize('create', GlobalCalendarEvent::class);
        
        $validated = $request->validate($this->globalEventValidationRules());
        
        $validated['created_by'] = auth()->id();
        
        GlobalCalendarEvent::create($validated);
        
        return redirect()->route('admin.global-calendar.index')
            ->with('success', 'Globales Kalender-Event erfolgreich erstellt.');
    }

    /**
     * Display the specified global calendar event.
     */
    public function show(GlobalCalendarEvent $globalCalendarEvent)
    {
        $this->authorize('view', $globalCalendarEvent);
        
        return view('admin.global-calendar.show', compact('globalCalendarEvent'));
    }

    /**
     * Show the form for editing the specified global calendar event.
     */
    public function edit(GlobalCalendarEvent $globalCalendarEvent)
    {
        $this->authorize('update', $globalCalendarEvent);
        
        return view('admin.global-calendar.edit', compact('globalCalendarEvent'));
    }

    /**
     * Update the specified global calendar event.
     */
    public function update(Request $request, GlobalCalendarEvent $globalCalendarEvent)
    {
        $this->authorize('update', $globalCalendarEvent);
        
        $validated = $request->validate($this->globalEventValidationRules());
        
        $globalCalendarEvent->update($validated);
        
        return redirect()->route('admin.global-calendar.index')
            ->with('success', 'Globales Kalender-Event erfolgreich aktualisiert.');
    }

    /**
     * Gemeinsame Validierungsregeln für store() und update()
     *
     * @return array<string, string>
     */
    private function globalEventValidationRules(): array
    {
        return [
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'event_date'         => 'required|date',
            'end_date'           => 'nullable|date|after_or_equal:event_date',
            'start_time'         => 'nullable|date_format:H:i',
            'end_time'           => 'nullable|date_format:H:i|after:start_time',
            'event_type'         => 'required|in:exam,holiday,event,deadline,meeting',
            'grade_levels'       => 'nullable|array',
            'grade_levels.*'     => 'integer|min:5|max:13',
            'color'              => 'nullable|string|max:7',
            'is_recurring'       => 'boolean',
            'recurrence_pattern' => 'nullable|string',
            'priority'           => 'nullable|integer|min:1|max:10',
        ];
    }

    /**
     * Remove the specified global calendar event.
     */
    public function destroy(GlobalCalendarEvent $globalCalendarEvent)
    {
        $this->authorize('delete', $globalCalendarEvent);
        
        $globalCalendarEvent->delete();
        
        return redirect()->route('admin.global-calendar.index')
            ->with('success', 'Globales Kalender-Event erfolgreich gelöscht.');
    }
}
