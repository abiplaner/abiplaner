<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $school_year
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon $end_date
 * @property bool $is_active
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamScheduleEntry> $examEntries
 * @property-read int|null $exam_entries_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSchedulePeriod active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSchedulePeriod forSchoolYear(string $schoolYear)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSchedulePeriod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSchedulePeriod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSchedulePeriod query()
 * @mixin \Eloquent
 */
class ExamSchedulePeriod extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;


    protected $fillable = [
        'name',
        'school_year',
        'start_date',
        'end_date',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function examEntries(): HasMany
    {
        return $this->hasMany(ExamScheduleEntry::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSchoolYear($query, string $schoolYear)
    {
        return $query->where('school_year', $schoolYear);
    }

}
