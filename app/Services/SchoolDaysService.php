<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\GlobalCalendarEvent;
use App\Models\SchoolEvent;
use Illuminate\Support\Carbon;

/**
 * Berechnet die bisherigen Schultage im laufenden Schuljahr (Roadmap 8.2),
 * als Nenner für den Krankheitstage-Prozentwert.
 *
 * Portiert die Logik aus infoportal-backend
 * (app/Console/Commands/ScrapeFehlzeiten.php calculateSchoolDays()):
 *   1. Schuljahresbeginn = Tag nach Ende der Sommerferien
 *   2. Schultage = Wochentage (Mo–Fr) zwischen Schuljahresbeginn und heute,
 *      abzüglich aller Tage, die in einen Ferien-/Feiertagszeitraum fallen
 *
 * Abweichend vom Original (das eine einzelne `holidays`-Tabelle mit
 * `code = 'SF'` abfragt) gibt es in abiplaner drei Kalenderquellen, die hier
 * zusammengeführt werden: SchoolEvent (tenant), CalendarEvent (tenant),
 * GlobalCalendarEvent (zentral, für alle Schulen).
 *
 * Zweite Abweichung: Die "Sommerferien zwischen Juli des Vorjahres und
 * September des aktuellen Jahres"-Suche aus dem Original ist mehrdeutig,
 * sobald (wie in abiplaner) auch zukünftige Sommerferien bereits im
 * Kalender stehen. Hier wird stattdessen der jüngste bereits VERGANGENE
 * Sommerferien-Eintrag verwendet.
 */
class SchoolDaysService
{
    /**
     * Ermittelt den Schuljahresbeginn: Tag nach Ende der letzten bereits
     * vergangenen Sommerferien, sonst 15. September (des laufenden bzw.
     * vorigen Schuljahres, je nach aktuellem Monat).
     */
    public function schoolYearStart(): Carbon
    {
        $today = Carbon::today();

        $summerHolidayEnd = $this->holidayIntervals($today->copy()->subYears(2), $today)
            ->filter(fn($interval) => $this->looksLikeSommerferien($interval['title']))
            ->filter(fn($interval) => $interval['end']->lte($today))
            ->sortByDesc(fn($interval) => $interval['end']->timestamp)
            ->first();

        if ($summerHolidayEnd) {
            return $summerHolidayEnd['end']->copy()->addDay()->startOfDay();
        }

        // Fallback: 15. September — im richtigen Schuljahr, nicht im
        // Kalenderjahr: vor September zählt das vorige Kalenderjahr.
        $fallbackYear = $today->month >= 9 ? $today->year : $today->year - 1;

        return Carbon::create($fallbackYear, 9, 15)->startOfDay();
    }

    /**
     * Zählt die Schultage (Mo–Fr, ohne Ferien/Feiertage) vom
     * Schuljahresbeginn bis einschließlich heute (bzw. bis $until).
     */
    public function countSchoolDaysUntil(?Carbon $until = null): int
    {
        $until = ($until ?? Carbon::today())->startOfDay();
        $start = $this->schoolYearStart();

        if ($start->gt($until)) {
            return 0;
        }

        $freeDates = $this->freeDatesSet($start, $until);

        $count = 0;
        $cursor = $start->copy();
        while ($cursor->lte($until)) {
            if ($cursor->isWeekday() && !$freeDates->contains($cursor->format('Y-m-d'))) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    /**
     * Alle Kalendertage (als 'Y-m-d'-Strings) im Bereich, die auf einen
     * Ferien- oder Feiertagszeitraum fallen.
     */
    private function freeDatesSet(Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        $dates = collect();
        foreach ($this->holidayIntervals($from, $to) as $interval) {
            $cursor = $interval['start']->copy();
            while ($cursor->lte($interval['end'])) {
                $dates->push($cursor->format('Y-m-d'));
                $cursor->addDay();
            }
        }

        return $dates->unique();
    }

    /**
     * Ferien-/Feiertagszeiträume aus allen drei Kalenderquellen, die den
     * Bereich [$from, $to] überschneiden.
     *
     * @return \Illuminate\Support\Collection<int, array{title: string, start: Carbon, end: Carbon}>
     */
    private function holidayIntervals(Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        $intervals = collect();

        SchoolEvent::whereIn('type', ['ferien', 'feiertag'])
            ->where('start_date', '<=', $to)
            ->where(function ($q) use ($from) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $from);
            })
            ->get(['name', 'start_date', 'end_date'])
            ->each(function ($event) use ($intervals) {
                $intervals->push([
                    'title' => $event->name,
                    'start' => $event->start_date,
                    'end' => $event->end_date ?? $event->start_date,
                ]);
            });

        $this->pushCalendarEventIntervals($intervals, CalendarEvent::class, $from, $to);
        $this->pushCalendarEventIntervals($intervals, GlobalCalendarEvent::class, $from, $to);

        return $intervals;
    }

    /**
     * Holt Ferien-/Feiertagszeiträume aus CalendarEvent oder
     * GlobalCalendarEvent (identisches Schema: title/event_date/end_date/
     * event_type) und hängt sie an $intervals an.
     *
     * @param class-string<CalendarEvent>|class-string<GlobalCalendarEvent> $modelClass
     */
    private function pushCalendarEventIntervals(\Illuminate\Support\Collection $intervals, string $modelClass, Carbon $from, Carbon $to): void
    {
        $modelClass::whereIn('event_type', ['holiday', 'public_holiday'])
            ->where('event_date', '<=', $to)
            ->where(function ($q) use ($from) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $from);
            })
            ->get(['title', 'event_date', 'end_date'])
            ->each(function ($event) use ($intervals) {
                $intervals->push([
                    'title' => $event->title,
                    'start' => $event->event_date,
                    'end' => $event->end_date ?? $event->event_date,
                ]);
            });
    }

    private function looksLikeSommerferien(string $title): bool
    {
        return str_contains(mb_strtolower($title), 'sommerferien');
    }
}
