<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property string $name
 * @property string|null $description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\ExamPeriod|null $examPeriod
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SlotTemplateItem> $items
 * @property-read int|null $items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotTemplate query()
 * @mixin \Eloquent
 */
class SlotTemplate extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;


    protected $fillable = [
        'exam_period_id',
        'name',
        'description',
    ];

    /**
     * Get the exam period that owns the template.
     */
    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    /**
     * Get all items in this template.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SlotTemplateItem::class)->orderBy('slot_number');
    }

    /**
     * Apply this template to specific dates.
     * 
     * @param array $dates Array of Carbon dates or date strings
     * @param bool $overwrite Whether to overwrite existing slots
     * @return int Number of time slots created or updated
     */
    public function applyToDates(array $dates, bool $overwrite = false): int
    {
        $affected = 0;
        
        foreach ($dates as $date) {
            /** @var SlotTemplateItem $item */
            foreach ($this->items as $item) {
                $existing = TimeSlot::where('exam_period_id', $this->exam_period_id)
                    ->where('date', $date)
                    ->where('slot_number', $item->slot_number)
                    ->first();
                
                if ($existing) {
                    if ($overwrite) {
                        $existing->update([
                            'prep_start_time'    => $item->prep_start_time,
                            'prep_end_time'      => $item->prep_end_time,
                            'exam_start_time'    => $item->exam_start_time,
                            'exam_end_time'      => $item->exam_end_time,
                            'preference_weight'  => $item->preference_weight,
                        ]);
                        $affected++;
                    }
                } else {
                    TimeSlot::create([
                        'exam_period_id'     => $this->exam_period_id,
                        'date'               => $date,
                        'slot_number'        => $item->slot_number,
                        'prep_start_time'    => $item->prep_start_time,
                        'prep_end_time'      => $item->prep_end_time,
                        'exam_start_time'    => $item->exam_start_time,
                        'exam_end_time'      => $item->exam_end_time,
                        'preference_weight'  => $item->preference_weight,
                        'is_active'          => true,
                    ]);
                    $affected++;
                }
            }
        }
        
        return $affected;
    }

}
