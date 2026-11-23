<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $template_id
 * @property int $room_id
 * @property string $supervision_type preparation|waiting|lockdown|corridor
 * @property int|null $teacher_id
 * @property int $slot_number
 * @property int|null $day_of_week
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\SupervisionTemplate|null $template
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupervisionTemplateAssignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupervisionTemplateAssignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupervisionTemplateAssignment query()
 * @property-read \App\Models\Room|null $room
 * @property-read \App\Models\Teacher|null $teacher
 * @mixin \Eloquent
 */
class SupervisionTemplateAssignment extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $fillable = [
        'template_id',
        'room_id',
        'supervision_type',
        'teacher_id',
        'slot_number',
        'day_of_week',
    ];

    protected $casts = [
        'slot_number' => 'integer',
        'day_of_week' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SupervisionTemplate::class, 'template_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
