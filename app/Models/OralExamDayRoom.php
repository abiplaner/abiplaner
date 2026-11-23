<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property \Carbon\Carbon $date
 * @property int $room_id
 * @property string $room_role  exam|preparation|waiting
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Room $room
 */
class OralExamDayRoom extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'oral_exam_day_rooms';

    protected $fillable = [
        'exam_period_id',
        'date',
        'room_id',
        'room_role',
        'sort_order',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /** Rolle als lesbares Label */
    public function getRoleLabelAttribute(): string
    {
        return match($this->room_role) {
            'exam'        => 'Prüfungsraum',
            'preparation' => 'Vorbereitungsraum',
            'waiting'     => 'Warteraum',
            'corridor'    => 'Gang',
            default       => '–',
        };
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
