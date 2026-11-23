<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sitzplan-Variante eines Raums (z. B. "standard" oder "hoerverstaendnis").
 * Ersetzt Room::seating_template_id als Quelle der Wahrheit (Modul 1.5) —
 * ein Raum kann mehrere Sitzplan-Varianten haben, statt dass für jede
 * Variante ein eigener Raum-Datensatz angelegt werden muss.
 *
 * @property int $id
 * @property int $room_id
 * @property string $variant
 * @property int $seating_template_id
 * @property-read \App\Models\Room $room
 * @property-read \App\Models\SeatingTemplate $seatingTemplate
 */
class RoomSeatingTemplate extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    public const VARIANT_STANDARD = 'standard';
    public const VARIANT_HOERVERSTAENDNIS = 'hoerverstaendnis';

    protected $fillable = [
        'room_id',
        'variant',
        'seating_template_id',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function seatingTemplate(): BelongsTo
    {
        return $this->belongsTo(SeatingTemplate::class);
    }
}
