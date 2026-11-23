<?php

namespace App\Models;

use App\Support\SchoolYear;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $teacher_id
 * @property string $class_name
 * @property string|null $course_code
 * @property string|null $subject
 * @property string|null $room
 * @property int $day_of_week 1-5
 * @property int $period 1-11
 * @property string $school_year
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $day_name
 * @property-read string $day_short
 * @property-read string $time_slot
 * @property-read \App\Models\Teacher|null $teacher
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableEntry oberstufe()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableEntry q13()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableEntry query()
 * @mixin \Eloquent
 */
class TimetableEntry extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;


    protected $fillable = [
        'teacher_id',
        'class_name',
        'course_code',
        'subject',
        'room',
        'day_of_week',
        'period',
        'school_year',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'period' => 'integer',
    ];

    /**
     * Relationship to Teacher (via teacher_id foreign key)
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    /**
     * Scope: Only Q13 entries
     */
    public function scopeQ13($query)
    {
        return $query->where('class_name', 'Q13');
    }

    /**
     * Scope: Q12 and Q13 (Oberstufe)
     */
    public function scopeOberstufe($query)
    {
        return $query->whereIn('class_name', ['Q12', 'Q13']);
    }

    /**
     * Get day name in German
     */
    public function getDayNameAttribute(): string
    {
        return match($this->day_of_week) {
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            default => 'Unbekannt',
        };
    }

    /**
     * Get short day name
     */
    public function getDayShortAttribute(): string
    {
        return match($this->day_of_week) {
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            default => '?',
        };
    }

    /**
     * Get time slot description
     */
    public function getTimeSlotAttribute(): string
    {
        return $this->day_short . ' ' . $this->period . '.';
    }

    /**
     * Get the current (latest) school year present in the table
     */
    public static function getCurrentSchoolYear(): string
    {
        // Ohne Einträge das laufende Schuljahr. Die frühere Rechnung aus
        // date('Y') lieferte von Januar bis Juli das folgende Schuljahr.
        return self::max('school_year') ?? SchoolYear::current();
    }

    /**
     * Schuljahr für ein Datum ermitteln (Stichtag 1. August).
     * Beispiel: 2026-05-15 → "2025/26", 2026-09-10 → "2026/27".
     */
    public static function schoolYearForDate(\Carbon\Carbon $date): string
    {
        return SchoolYear::forDate($date);
    }

    /**
     * Stundenplanfreie Wochentage (1=Mo … 5=Fr) je Lehrkraft für ein Schuljahr.
     *
     * Enthält NUR Lehrkräfte, die mindestens einen Stundenplaneintrag haben —
     * "keine Daten" darf nicht als "ganze Woche frei" fehlinterpretiert werden.
     * Lehrkräfte ohne freien Wochentag sind ebenfalls nicht enthalten.
     *
     * @return array<int, array<int, true>> teacher_id => [Wochentag => true]
     */
    public static function freeWeekdaysByTeacher(string $schoolYear): array
    {
        $occupied = self::where('school_year', $schoolYear)
            ->whereNotNull('teacher_id')
            ->distinct()
            ->get(['teacher_id', 'day_of_week'])
            ->groupBy('teacher_id');

        $result = [];
        foreach ($occupied as $teacherId => $entries) {
            $busyDays = $entries->pluck('day_of_week')->all();
            $free = [];
            foreach ([1, 2, 3, 4, 5] as $day) {
                if (!in_array($day, $busyDays, true)) {
                    $free[$day] = true;
                }
            }
            if ($free !== []) {
                $result[(int) $teacherId] = $free;
            }
        }

        return $result;
    }

    /**
     * Wie freeWeekdaysByTeacher(), aber mit Schuljahr-Auflösung aus einem Datum:
     * zuerst das per schoolYearForDate() abgeleitete Schuljahr; existieren dafür
     * keine Einträge, Fallback auf das neueste vorhandene Schuljahr.
     *
     * @return array<int, array<int, true>> teacher_id => [Wochentag => true]
     */
    public static function freeWeekdaysByTeacherForDate(\Carbon\Carbon $date): array
    {
        $schoolYear = self::schoolYearForDate($date);
        $result = self::freeWeekdaysByTeacher($schoolYear);

        if ($result === []) {
            $fallbackYear = self::getCurrentSchoolYear();
            if ($fallbackYear !== $schoolYear) {
                $result = self::freeWeekdaysByTeacher($fallbackYear);
            }
        }

        return $result;
    }

    /**
     * Get all occupied time slots for a teacher
     */
    public static function getTeacherSchedule(string $teacherCode, string $schoolYear = '')
    {
        if ($schoolYear === '') {
            $schoolYear = self::getCurrentSchoolYear();
        }
        return self::whereHas('teacher', function($q) use ($teacherCode) {
                $q->where('shortcode', $teacherCode);
            })
            ->where('school_year', $schoolYear)
            ->orderBy('day_of_week')
            ->orderBy('period')
            ->get()
            ->groupBy('day_of_week');
    }

    /**
     * Get all courses for a specific class (Q12/Q13)
     */
    public static function getClassSchedule(string $className, string $schoolYear = '')
    {
        if ($schoolYear === '') {
            $schoolYear = self::getCurrentSchoolYear();
        }

        return self::where('class_name', $className)
                   ->where('school_year', $schoolYear)
                   ->orderBy('day_of_week')
                   ->orderBy('period')
                   ->get()
                   ->groupBy('day_of_week');
    }
}
