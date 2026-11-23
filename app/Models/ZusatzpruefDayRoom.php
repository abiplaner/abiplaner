<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $zusatzpruef_day_id
 * @property int $exam_room_id
 * @property int|null $prep_room_id
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\ZusatzpruefDay $day
 * @property-read \App\Models\Room $examRoom
 * @property-read \App\Models\Room|null $prepRoom
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ZusatzpruefAssignment> $assignments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ZusatzpruefAufsicht> $aufsichten
 * @mixin \Eloquent
 */
class ZusatzpruefDayRoom extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'zusatzpruef_day_rooms';

    protected $fillable = [
        'zusatzpruef_day_id',
        'exam_room_id',
        'prep_room_id',
        'sort_order',
    ];

    public function day(): BelongsTo
    {
        return $this->belongsTo(ZusatzpruefDay::class, 'zusatzpruef_day_id');
    }

    public function examRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'exam_room_id');
    }

    public function prepRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'prep_room_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ZusatzpruefAssignment::class, 'zusatzpruef_day_room_id');
    }

    public function aufsichten(): HasMany
    {
        return $this->hasMany(ZusatzpruefAufsicht::class, 'zusatzpruef_day_room_id');
    }
}
