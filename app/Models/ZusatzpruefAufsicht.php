<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $zusatzpruef_day_id
 * @property int $zusatzpruef_day_room_id
 * @property int|null $zusatzpruef_day_timeslot_id
 * @property int $teacher_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\ZusatzpruefDay $day
 * @property-read \App\Models\ZusatzpruefDayRoom $dayRoom
 * @property-read \App\Models\ZusatzpruefDayTimeslot|null $timeslot
 * @property-read \App\Models\Teacher $teacher
 * @mixin \Eloquent
 */
class ZusatzpruefAufsicht extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'zusatzpruef_aufsichten';

    protected $fillable = [
        'zusatzpruef_day_id',
        'zusatzpruef_day_room_id',
        'zusatzpruef_day_timeslot_id',
        'teacher_id',
    ];

    public function day(): BelongsTo
    {
        return $this->belongsTo(ZusatzpruefDay::class, 'zusatzpruef_day_id');
    }

    public function dayRoom(): BelongsTo
    {
        return $this->belongsTo(ZusatzpruefDayRoom::class, 'zusatzpruef_day_room_id');
    }

    public function timeslot(): BelongsTo
    {
        return $this->belongsTo(ZusatzpruefDayTimeslot::class, 'zusatzpruef_day_timeslot_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
