<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property string $date
 * @property int $slot_template_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\SlotTemplate|null $slotTemplate
 * @mixin \Eloquent
 */
class ExamDayTemplate extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'exam_period_id',
        'date',
        'slot_template_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function slotTemplate(): BelongsTo
    {
        return $this->belongsTo(SlotTemplate::class);
    }
}
