<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exam_schedule_period_id
 * @property string $course_codes Komma-separiert oder JSON
 * @property string $subject
 * @property string|null $level
 * @property \Carbon\Carbon $exam_date
 * @property \Carbon\Carbon|null $exam_end_date
 * @property int $start_period
 * @property int $end_period
 * @property string $status geplant|bestätigt|verschoben
 * @property string|null $notes
 * @property bool $is_highlighted
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $time_slot
 * @property-read string $date_range
 * @property-read \App\Models\ExamSchedulePeriod|null $period
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamScheduleEntry betweenDates($startDate, $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamScheduleEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamScheduleEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamScheduleEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamScheduleEntry status(string $status)
 * @mixin \Eloquent
 */
class ExamScheduleEntry extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;


    protected $fillable = [
        'exam_schedule_period_id',
        'course_codes',
        'subject',
        'level',
        'exam_type',
        'exam_date',
        'exam_end_date',
        'start_period',
        'end_period',
        'status',
        'notes',
        'is_highlighted',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'exam_end_date' => 'date',
        'start_period' => 'integer',
        'end_period' => 'integer',
        'is_highlighted' => 'boolean',
    ];
    
    /**
     * Get course_codes as array (handles both JSON and comma-separated strings)
     */
    public function getCourseCodesAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }
        
        // Try JSON decode first
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        
        // Fallback: split by comma
        return array_map('trim', explode(',', $value));
    }
    
    /**
     * Set course_codes (stores as comma-separated string for now)
     */
    public function setCourseCodesAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['course_codes'] = implode(',', $value);
        } else {
            $this->attributes['course_codes'] = $value;
        }
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ExamSchedulePeriod::class, 'exam_schedule_period_id');
    }

    /**
     * Get time slot as string (e.g. "1/2", "3-5")
     */
    public function getTimeSlotAttribute(): string
    {
        if ($this->start_period === 0) {
            return '';
        }

        if ($this->start_period === $this->end_period) {
            return (string) $this->start_period;
        }
        
        if ($this->end_period - $this->start_period === 1) {
            return $this->start_period . '/' . $this->end_period;
        }
        
        return $this->start_period . '-' . $this->end_period;
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('exam_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if this is a multi-day exam
     */
    public function isMultiDay(): bool
    {
        return $this->exam_end_date !== null 
            && $this->exam_end_date->gt($this->exam_date);
    }
    
    /**
     * Get duration in days
     */
    public function getDurationDays(): int
    {
        if (!$this->isMultiDay()) {
            return 1;
        }
        
        return (int) $this->exam_date->diffInDays($this->exam_end_date) + 1;
    }
    
    /**
     * Get all dates covered by this exam (for multi-day exams)
     */
    public function getExamDates(): array
    {
        if (!$this->isMultiDay()) {
            return [$this->exam_date];
        }
        
        $dates = [];
        $current = $this->exam_date->copy();
        
        while ($current->lte($this->exam_end_date)) {
            $dates[] = $current->copy();
            $current->addDay();
        }
        
        return $dates;
    }
    
    /**
     * Get formatted date range string
     */
    public function getDateRangeAttribute(): string
    {
        if (!$this->isMultiDay()) {
            return $this->exam_date->format('d.m.Y');
        }
        
        return $this->exam_date->format('d.m.Y') 
             . ' - ' 
             . $this->exam_end_date->format('d.m.Y');
    }

}
