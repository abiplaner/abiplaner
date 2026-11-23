<?php

namespace App\Models;

use App\Models\Concerns\HasCalendarEventAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property \Carbon\Carbon $event_date
 * @property \Carbon\Carbon|null $end_date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string $event_type
 * @property array|null $grade_levels
 * @property string $color
 * @property bool $is_recurring
 * @property bool $is_global Immer true bei GlobalCalendarEvent
 * @property int $priority
 * @property string|null $location
 * @property int|null $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read bool $is_multi_day
 * @property-read string $date_range
 * @property-read string $event_type_name
 * @property-read \App\Models\User|null $creator
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent betweenDates($startDate, $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent forGradeLevel($gradeLevel)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent ofType($type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereEventDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereGradeLevels($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereIsRecurring($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GlobalCalendarEvent whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GlobalCalendarEvent extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory, HasCalendarEventAttributes;

    /**
     * CENTRAL table - visible to ALL schools
     * Only superadmins can create/edit these events
     */
    protected $table = 'global_calendar_events';

    protected $fillable = [
        'title',
        'description',
        'event_date',
        'end_date',
        'start_time',
        'end_time',
        'event_type',
        'grade_levels',
        'color',
        'is_recurring',
        'priority',
        'location',
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'end_date' => 'date',
        'grade_levels' => 'array',
        'is_recurring' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Accessor: Event-Typ als lesbare Bezeichnung (nur globale Typen, keine Prüfungstypen)
     */
    public function getEventTypeNameAttribute(): string
    {
        return match($this->event_type) {
            'deadline'       => 'Frist',
            'holiday'        => 'Ferien',
            'public_holiday' => 'Feiertag',
            'info'           => 'Information',
            'administration' => 'Verwaltung',
            default          => 'Sonstiges',
        };
    }
}
