<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $teacher_id
 * @property int $exam_period_id
 * @property \Carbon\Carbon $date
 * @property bool $all_day
 * @property string|null $time_from
 * @property string|null $time_to
 * @property string|null $reason
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\ExamPeriod|null $examPeriod
 * @property-read \App\Models\Teacher|null $teacher
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeacherUnavailability newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeacherUnavailability newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeacherUnavailability query()
 * @mixin \Eloquent
 */
class TeacherUnavailability extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'teacher_unavailability';

    protected $fillable = [
        'teacher_id',
        'exam_period_id',
        'date',
        'all_day',
        'time_from',
        'time_to',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
        'all_day' => 'boolean',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }
}
