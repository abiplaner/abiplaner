<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int $exam_period_id
 * @property int|null $current_total_points
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Student $student
 * @mixin \Eloquent
 */
class ZusatzpruefStudentDeficit extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'zusatzpruef_student_deficits';

    protected $fillable = [
        'student_id',
        'exam_period_id',
        'current_total_points',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
