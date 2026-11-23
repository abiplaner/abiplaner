<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property \Carbon\Carbon $date
 * @property int $slot_number
 * @property string $prep_start_time
 * @property string $prep_end_time
 * @property string $exam_start_time
 * @property string $exam_end_time
 * @property int $preference_weight
 * @property bool $is_active
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $preference_label
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamAssignment> $examAssignments
 * @property-read int|null $exam_assignments_count
 * @property-read \App\Models\ExamPeriod|null $examPeriod
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Supervision> $supervisions
 * @property-read int|null $supervisions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot preferred()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot query()
 * @mixin \Eloquent
 */
class TimeSlot extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'exam_period_id',
        'date',
        'slot_number',
        'prep_start_time',
        'prep_end_time',
        'exam_start_time',
        'exam_end_time',
        'preference_weight',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'is_active' => 'boolean',
    ];

    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    public function examAssignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    public function supervisions(): HasMany
    {
        return $this->hasMany(Supervision::class);
    }

    /**
     * Scope für bevorzugte Slots (hoher preference_weight).
     */
    public function scopePreferred($query)
    {
        return $query->where('preference_weight', '>=', 100)
                     ->where('is_active', true)
                     ->orderByDesc('preference_weight');
    }

    /**
     * Scope für aktive Slots.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get preference label based on weight.
     */
    public function getPreferenceLabelAttribute(): string
    {
        if ($this->preference_weight >= 120) return 'Sehr bevorzugt';
        if ($this->preference_weight >= 100) return 'Bevorzugt';
        if ($this->preference_weight >= 80) return 'Neutral';
        if ($this->preference_weight >= 60) return 'Weniger bevorzugt';
        return 'Vermeiden';
    }

}
