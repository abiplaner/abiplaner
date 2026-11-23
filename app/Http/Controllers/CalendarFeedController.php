<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\GlobalCalendarEvent;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    /**
     * Generate iCal feed for a school
     */
    public function feed(Request $request, string $token, ?int $gradeLevel = null)
    {
        // Find school by token
        $school = School::where('calendar_token', $token)->firstOrFail();

        // Öffentliche Route ohne eingeloggten Nutzer: Tenancy wird hier nicht
        // automatisch initialisiert (InitializeTenancyByUser greift nur bei
        // Auth). calendar_events liegt aber in der Tenant-DB der Schule -
        // ohne manuelle Initialisierung schlägt die Abfrage fehl ("Table
        // 'abiplaner.calendar_events' doesn't exist").
        //
        // WICHTIG: Auch die ICS-Generierung muss innerhalb des Tenant-Kontexts
        // laufen, nicht nur die Datenbankabfrage. Eloquent castet Attribute
        // wie created_at/updated_at LAZY beim ersten Zugriff (nicht beim
        // get()) und benötigt dafür erneut die Connection - nach einem
        // vorzeitigen Verlassen des Kontexts schlägt das mit "Database
        // connection [tenant] not configured" fehl. Darum beides in mit().
        $ical = app(\App\Services\AktuelleSchule::class)->mit(
            $school,
            fn (School $schule) => $this->generateICalendar($this->collectEvents($gradeLevel), $schule, $gradeLevel)
        );
        
        // Filename based on grade level
        $filename = 'abiplaner-' . $school->short_name;
        if ($gradeLevel) {
            $filename .= '-jgst-' . $gradeLevel;
        }
        $filename .= '.ics';
        
        // Return response with correct headers
        return response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * Schul- und globale Termine kombiniert, wie auf der /calendar-Seite
     * (CalendarEventController::index) - der Feed soll genau das enthalten,
     * was dort angezeigt wird. Muss innerhalb eines initialisierten
     * Tenant-Kontexts aufgerufen werden (für CalendarEvent).
     *
     * @return \Illuminate\Support\Collection<int, CalendarEvent|GlobalCalendarEvent>
     */
    private function collectEvents(?int $gradeLevel): \Illuminate\Support\Collection
    {
        $schoolQuery = CalendarEvent::query()->orderBy('event_date');
        if ($gradeLevel) {
            $schoolQuery->forGradeLevel($gradeLevel);
        }
        $schoolEvents = $schoolQuery->get();
        $schoolEvents->each(function ($event) {
            $event->is_global = false;
        });

        $globalQuery = GlobalCalendarEvent::query()->orderBy('event_date');
        if ($gradeLevel) {
            $globalQuery->forGradeLevel($gradeLevel);
        }
        $globalEvents = $globalQuery->get();
        $globalEvents->each(function ($event) {
            $event->is_global = true;
        });

        // concat() statt merge(): beide Collections starten bei Key 0,
        // merge() würde sich sonst gegenseitig überschreiben.
        return $schoolEvents->concat($globalEvents)
            ->sortBy('event_date')
            ->values();
    }
    
    /**
     * Generate iCalendar (.ics) format
     */
    private function generateICalendar($events, $school, ?int $gradeLevel = null): string
    {
        $lines = [];
        
        // Calendar header
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//AbiPlaner//Calendar Feed//DE';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        
        // Calendar name based on grade level
        $calendarName = 'AbiPlaner - ' . $school->name;
        if ($gradeLevel) {
            $gradeName = match($gradeLevel) {
                11 => '11. Jahrgangsstufe',
                12 => 'Q12',
                13 => 'Q13',
                default => 'Jahrgangsstufe ' . $gradeLevel,
            };
            $calendarName .= ' (' . $gradeName . ')';
        }
        
        $lines[] = 'X-WR-CALNAME:' . $calendarName;
        $lines[] = 'X-WR-TIMEZONE:Europe/Berlin';
        $lines[] = 'X-WR-CALDESC:Abitur-Termine für ' . $school->name;
        
        // Add events
        foreach ($events as $event) {
            $lines = array_merge($lines, $this->generateEvent($event));
        }
        
        // Calendar footer
        $lines[] = 'END:VCALENDAR';
        
        return implode("\r\n", array_map(
            fn (string $line): string => $this->foldLine($line),
            $lines
        ));
    }

    /**
     * Faltet eine Inhaltszeile nach RFC 5545 §3.1.
     *
     * Zeilen dürfen 75 Oktette (ohne CRLF) nicht überschreiten; längere werden
     * mit CRLF + einem führenden Leerzeichen umgebrochen. Ohne das Falten
     * liefert der Feed überlange DESCRIPTION-Zeilen aus (auf Produktion am
     * 25.08.2026 sechs Stück) — Google Kalender verzeiht das, strengere Parser
     * wie Apple Kalender nicht zwingend.
     *
     * Gezählt wird in Oktetten, umgebrochen aber an Zeichengrenzen: ein mitten
     * durchtrennter UTF-8-Mehrbytecode (deutsche Umlaute!) macht den Feed sonst
     * unlesbar.
     */
    private function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];
        $current = '';
        $limit = 75; // erste Zeile: volle 75 Oktette

        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > $limit) {
                $chunks[] = $current;
                $current = '';
                $limit = 74; // Folgezeilen: ein Oktett für das führende Leerzeichen
            }

            $current .= $char;
        }

        $chunks[] = $current;

        return implode("\r\n ", $chunks);
    }
    
    /**
     * Generate a single event in iCal format
     */
    private function generateEvent($event): array
    {
        $lines = [];
        
        $lines[] = 'BEGIN:VEVENT';
        
        // Unique ID – Präfix nach Herkunft, da school- und global-Events
        // unabhängig voneinander bei 1 hochzählen und sonst kollidieren würden.
        $source = $event->is_global ? 'global' : 'school';
        $lines[] = 'UID:abiplaner-' . $source . '-event-' . $event->id . '@abiplaner.local';
        
        // Timestamps
        $lines[] = 'DTSTAMP:' . Carbon::now()->format('Ymd\THis\Z');
        $lines[] = 'CREATED:' . $event->created_at->format('Ymd\THis\Z');
        $lines[] = 'LAST-MODIFIED:' . $event->updated_at->format('Ymd\THis\Z');
        
        // Event dates
        if ($event->start_time) {
            // Event with time
            $startDateTime = Carbon::parse($event->event_date->format('Y-m-d') . ' ' . $event->start_time->format('H:i:s'));
            $lines[] = 'DTSTART:' . $startDateTime->format('Ymd\THis');
            
            if ($event->end_time) {
                $endDateTime = Carbon::parse($event->event_date->format('Y-m-d') . ' ' . $event->end_time->format('H:i:s'));
                $lines[] = 'DTEND:' . $endDateTime->format('Ymd\THis');
            }
        } else {
            // All-day event
            $lines[] = 'DTSTART;VALUE=DATE:' . $event->event_date->format('Ymd');
            
            if ($event->end_date) {
                // For multi-day events, add 1 day to end date (iCal spec)
                $endDate = $event->end_date->addDay();
                $lines[] = 'DTEND;VALUE=DATE:' . $endDate->format('Ymd');
            } else {
                // Single day event needs end date = start date + 1
                $endDate = $event->event_date->copy()->addDay();
                $lines[] = 'DTEND;VALUE=DATE:' . $endDate->format('Ymd');
            }
        }
        
        // Event details
        $lines[] = 'SUMMARY:' . $this->escapeText($event->title);
        
        if ($event->description) {
            $lines[] = 'DESCRIPTION:' . $this->escapeText($event->description);
        }
        
        if ($event->location) {
            $lines[] = 'LOCATION:' . $this->escapeText($event->location);
        }
        
        // Categories
        $lines[] = 'CATEGORIES:' . $event->event_type_name;
        
        // Status
        if ($event->is_mandatory) {
            $lines[] = 'STATUS:CONFIRMED';
        }
        
        // Priority (iCal uses 1-9, 1 is highest)
        $icalPriority = max(1, 10 - $event->priority);
        $lines[] = 'PRIORITY:' . $icalPriority;
        
        // Color (X-APPLE-CALENDAR-COLOR for Apple devices)
        $lines[] = 'COLOR:' . $event->color;

        // Erinnerung (Modul 3.1): Kalender-App erinnert 1 Tag vor dem Termin,
        // kein serverseitiger Versand nötig. Funktioniert für Termine mit
        // Uhrzeit ebenso wie für ganztägige Termine (TRIGGER relativ zu DTSTART).
        $lines[] = 'BEGIN:VALARM';
        $lines[] = 'ACTION:DISPLAY';
        $lines[] = 'DESCRIPTION:' . $this->escapeText($event->title);
        $lines[] = 'TRIGGER:-P1D';
        $lines[] = 'END:VALARM';

        $lines[] = 'END:VEVENT';
        
        return $lines;
    }
    
    /**
     * Escape text for iCal format
     */
    private function escapeText(string $text): string
    {
        $text = str_replace(['\\', ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], $text);
        return $text;
    }
    
    /**
     * Regenerate calendar token for current school
     */
    public function regenerateToken(Request $request)
    {
        $school = $request->user()->school;
        
        if (!$school) {
            return back()->with('error', 'Keine Schule zugeordnet.');
        }
        
        $school->generateCalendarToken();
        
        return back()->with('success', 'Kalender-Link wurde neu generiert.');
    }
}
