<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Weist einem Prüfungsraum für einen konkreten Tag ein Aufsichtsraster zu.
 *
 * @property int         $id
 * @property int         $room_id
 * @property \Carbon\Carbon $date
 * @property int         $supervision_grid_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Room              $room
 * @property-read \App\Models\SupervisionGrid   $supervisionGrid
 * @mixin \Eloquent
 */
class RoomSupervisionGrid extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $fillable = [
        'room_id',
        'date',
        'supervision_grid_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function supervisionGrid(): BelongsTo
    {
        return $this->belongsTo(SupervisionGrid::class);
    }
}
