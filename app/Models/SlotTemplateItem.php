<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $slot_template_id
 * @property int $slot_number
 * @property string $prep_start_time
 * @property string $prep_end_time
 * @property string $exam_start_time
 * @property string $exam_end_time
 * @property int $preference_weight
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $time_range
 * @property-read string $preference_label
 * @property-read \App\Models\SlotTemplate|null $template
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotTemplateItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotTemplateItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotTemplateItem query()
 * @mixin \Eloquent
 */
class SlotTemplateItem extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;


    protected $fillable = [
        'slot_template_id',
        'slot_number',
        'prep_start_time',
        'prep_end_time',
        'exam_start_time',
        'exam_end_time',
        'preference_weight',
        'notes',
    ];

    protected $casts = [
        'prep_start_time' => 'datetime:H:i',
        'prep_end_time' => 'datetime:H:i',
        'exam_start_time' => 'datetime:H:i',
        'exam_end_time' => 'datetime:H:i',
    ];

    /**
     * Get the template that owns this item.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SlotTemplate::class, 'slot_template_id');
    }

    /**
     * Get a human-readable time range.
     */
    public function getTimeRangeAttribute(): string
    {
        return sprintf(
            '%s - %s',
            \Carbon\Carbon::parse($this->exam_start_time)->format('H:i'),
            \Carbon\Carbon::parse($this->exam_end_time)->format('H:i')
        );
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
