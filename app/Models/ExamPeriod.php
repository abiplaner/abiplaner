<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $year
 * @property \Carbon\Carbon $week_1_start
 * @property \Carbon\Carbon $week_1_end
 * @property \Carbon\Carbon $week_2_start
 * @property \Carbon\Carbon $week_2_end
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Conflict> $conflicts
 * @property-read int|null $conflicts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SlotTemplate> $slotTemplates
 * @property-read int|null $slot_templates_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod whereWeek1End($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod whereWeek1Start($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod whereWeek2End($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod whereWeek2Start($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamPeriod whereYear($value)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $courses
 * @property-read int|null $courses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Student> $students
 * @property-read int|null $students_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TimeSlot> $timeSlots
 * @property-read int|null $time_slots_count
 * @mixin \Eloquent
 */
class ExamPeriod extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    /**
     * This is a CENTRAL table - uses central database connection.
     * Exam periods are defined centrally (by the ministry) and available to all schools.
     */
    
    protected $fillable = [
        'name',
        'year',
        'week_1_start',
        'week_1_end',
        'week_2_start',
        'week_2_end',
    ];

    protected $casts = [
        'week_1_start' => 'date',
        'week_1_end' => 'date',
        'week_2_start' => 'date',
        'week_2_end' => 'date',
    ];

    protected static function booted()
    {
        // ExamPeriods sind global - keine school_id mehr benötigt
    }

    // Relationships
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }



    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function slotTemplates(): HasMany
    {
        return $this->hasMany(SlotTemplate::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(Conflict::class);
    }

    // Custom Methods
    /**
     * Get all exam dates for a specific week
     * 
     * @param int $week Week number (1 or 2)
     * @return \Illuminate\Support\Collection Collection of Carbon dates (weekdays only)
     */
    public function getExamDates(int $week)
    {
        $start = $week === 1 ? $this->week_1_start : $this->week_2_start;
        $end = $week === 1 ? $this->week_1_end : $this->week_2_end;
        
        $dates = collect();
        $currentDate = $start->copy();
        
        while ($currentDate->lte($end)) {
            // Nur Wochentage (Montag-Freitag)
            if ($currentDate->isWeekday()) {
                $dates->push($currentDate->copy());
            }
            $currentDate->addDay();
        }
        
        return $dates;
    }
}
