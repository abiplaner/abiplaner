<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property int|null $supervision_grid_id
 * @property \Carbon\Carbon $date
 * @property string $start_time
 * @property string $end_time
 * @property int $slot_number
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $time_range
 * @property-read \App\Models\ExamPeriod|null $examPeriod
 * @property-read \App\Models\SupervisionGrid|null $supervisionGrid
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Supervision> $supervisions
 * @property-read int|null $supervisions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupervisionTimeSlot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupervisionTimeSlot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupervisionTimeSlot query()
 * @mixin \Eloquent
 */
class SupervisionTimeSlot extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $fillable = [
        'exam_period_id',
        'supervision_grid_id',
        'date',
        'start_time',
        'end_time',
        'slot_number',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    public function supervisionGrid(): BelongsTo
    {
        return $this->belongsTo(SupervisionGrid::class);
    }

    public function supervisions(): HasMany
    {
        return $this->hasMany(Supervision::class);
    }
    
    /**
     * Formatierte Zeitanzeige
     */
    public function getTimeRangeAttribute(): string
    {
        return substr($this->start_time, 0, 5) . ' - ' . substr($this->end_time, 0, 5);
    }
}
