<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property string $conflict_type
 * @property string $severity error|warning|info
 * @property string $description
 * @property string|null $affected_entity_type
 * @property int|null $affected_entity_id
 * @property int|null $time_slot_id
 * @property bool $is_resolved
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\ExamPeriod|null $examPeriod
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conflict errors()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conflict newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conflict newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conflict query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conflict unresolved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conflict warnings()
 * @property-read \App\Models\TimeSlot|null $timeSlot
 * @mixin \Eloquent
 */
class Conflict extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $fillable = [
        'exam_period_id',
        'conflict_type',
        'severity',
        'description',
        'affected_entity_type',
        'affected_entity_id',
        'time_slot_id',
        'is_resolved',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
    ];

    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeErrors($query)
    {
        return $query->where('severity', 'error');
    }

    public function scopeWarnings($query)
    {
        return $query->where('severity', 'warning');
    }
}
