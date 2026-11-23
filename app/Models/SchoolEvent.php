<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $school_year
 * @property string $name
 * @property string $type ferien|feiertag|veranstaltung|exkursion|pruefung|sonstiges
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property string|null $description
 * @property bool $blocks_exams
 * @property array|null $affected_grades
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $color_class
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolEvent betweenDates($startDate, $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolEvent forSchoolYear(string $schoolYear)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolEvent ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolEvent query()
 * @mixin \Eloquent
 */
class SchoolEvent extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;


    protected $fillable = [
        'school_year',
        'name',
        'type',
        'start_date',
        'end_date',
        'description',
        'blocks_exams',
        'affected_grades',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'blocks_exams' => 'boolean',
        'affected_grades' => 'array',
    ];

    /**
     * Check if event is on a specific date
     */
    public function isOnDate($date): bool
    {
        $checkDate = is_string($date) ? \Carbon\Carbon::parse($date) : $date;
        
        if ($this->end_date) {
            return $checkDate->between($this->start_date, $this->end_date);
        }
        
        return $checkDate->isSameDay($this->start_date);
    }

    /**
     * Get display color based on event type
     */
    public function getColorClassAttribute(): string
    {
        return match($this->type) {
            'ferien' => 'bg-gray-200 text-gray-800',
            'feiertag' => 'bg-red-100 text-red-800',
            'veranstaltung' => 'bg-blue-100 text-blue-800',
            'exkursion' => 'bg-green-100 text-green-800',
            'pruefung' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Scope: Filter by school year
     */
    public function scopeForSchoolYear($query, string $schoolYear)
    {
        return $query->where('school_year', $schoolYear);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Events in date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_date', '<=', $startDate)
                     ->where('end_date', '>=', $endDate);
              });
        });
    }

}
