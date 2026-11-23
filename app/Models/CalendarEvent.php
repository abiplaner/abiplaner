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
 * @property bool $is_mandatory
 * @property bool $is_global Keine Spalte: wird beim Zusammenfuehren mit
 *           GlobalCalendarEvent auf der Instanz gesetzt, damit die Ansicht
 *           beide Herkuenfte unterscheiden kann. Nie speichern.
 * @property int $priority
 * @property string|null $location
 * @property int|null $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read bool $is_multi_day
 * @property-read string $date_range
 * @property-read string $event_type_name
 * @property-read string $grade_levels_name
 * @property-read \App\Models\User|null $creator
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent betweenDates($startDate, $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent forGradeLevel($gradeLevel)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent ofType($type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent query()
 * @mixin \Eloquent
 */
class CalendarEvent extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory, HasCalendarEventAttributes;

    /**
     * TENANT-SPECIFIC table - each school has its own calendar events
     * For global events (holidays, etc.) see GlobalCalendarEvent
     */

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
        'is_mandatory',
        'priority',
        'location',
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'end_date' => 'date',
        'grade_levels' => 'array',
        'is_recurring' => 'boolean',
        'is_mandatory' => 'boolean',
        'priority' => 'integer',
    ];

    // scopeForSchool() ist entfallen (30.08.2026).
    //
    // Er gab die Query unveraendert zurueck — ein Filter, der nicht filtert,
    // und niemand rief ihn mehr auf. In einer Datenbank je Schule gehoeren
    // alle Termine ohnehin dieser einen Schule.

    /**
     * Accessor: Event-Typ als lesbare Bezeichnung (inkl. Prüfungstypen)
     */
    public function getEventTypeNameAttribute(): string
    {
        return match($this->event_type) {
            'deadline'         => 'Frist',
            'exam_written'     => 'Schriftliche Prüfung',
            'exam_oral'        => 'Mündliche Prüfung',
            'exam_practical'   => 'Praktische Prüfung',
            'holiday'          => 'Ferien',
            'public_holiday'   => 'Feiertag',
            'info'             => 'Information',
            'grading'          => 'Notenabgabe',
            'administration'   => 'Verwaltung',
            default            => 'Sonstiges',
        };
    }

    /**
     * Accessor: Jahrgangsstufen als lesbare Bezeichnung
     */
    public function getGradeLevelsNameAttribute(): string
    {
        if (empty($this->grade_levels)) {
            return 'Alle Jahrgangsstufen';
        }

        $names = array_map(function ($level) {
            return match($level) {
                11      => '11. Jgst.',
                12      => 'Q12',
                13      => 'Q13',
                default => (string) $level,
            };
        }, $this->grade_levels);

        return implode(', ', $names);
    }
}
