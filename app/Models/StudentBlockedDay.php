<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property \Carbon\Carbon $date
 * @property string|null $reason
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Student|null $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentBlockedDay newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentBlockedDay newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentBlockedDay query()
 * @mixin \Eloquent
 */
class StudentBlockedDay extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'student_blocked_days';

    protected $fillable = [
        'student_id',
        'date',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
