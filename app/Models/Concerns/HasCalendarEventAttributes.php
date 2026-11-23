<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gemeinsame Attribute und Methoden für CalendarEvent und GlobalCalendarEvent.
 *
 * @property \Carbon\Carbon      $event_date
 * @property \Carbon\Carbon|null $end_date
 * @property string              $event_type
 * @property array|null          $grade_levels
 * @property int|null            $created_by
 * @property-read bool           $is_multi_day
 * @property-read string         $date_range
 */
trait HasCalendarEventAttributes
{
    /**
     * Relationship: Event wurde von einem User erstellt
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: Events für eine bestimmte Jahrgangsstufe
     */
    public function scopeForGradeLevel($query, $gradeLevel)
    {
        return $query->where(function ($q) use ($gradeLevel) {
            $q->whereNull('grade_levels') // null = alle Jahrgangsstufen
              ->orWhereJsonContains('grade_levels', $gradeLevel);
        });
    }

    /**
     * Scope: Events nach Typ
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope: Events in einem Datumsbereich (inkl. mehrtägige Events)
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('event_date', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->whereNotNull('end_date')
                     ->where('event_date', '<=', $endDate)
                     ->where('end_date', '>=', $startDate);
              });
        });
    }

    /**
     * Accessor: Ist das Event mehrtägig?
     */
    public function getIsMultiDayAttribute(): bool
    {
        return $this->end_date && $this->end_date->ne($this->event_date);
    }

    /**
     * Accessor: Formatierter Datumsbereich
     */
    public function getDateRangeAttribute(): string
    {
        if ($this->is_multi_day) {
            return $this->event_date->format('d.m.Y') . ' - ' . $this->end_date->format('d.m.Y');
        }
        return $this->event_date->format('d.m.Y');
    }

}
