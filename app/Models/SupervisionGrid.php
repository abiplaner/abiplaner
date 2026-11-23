<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aufsichtsraster – definiert eine Gruppe von Zeitslots für einen Prüfungszeitraum.
 * Beispiele: "Standard", "Hörverstehen Englisch"
 *
 * @property int         $id
 * @property int         $exam_period_id
 * @property string      $name
 * @property string|null $description
 * @property string|null $color      Tailwind-Farbname (z.B. 'blue', 'green', 'orange')
 * @property bool        $is_default Wird als Standard-Raster für neue Räume verwendet
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupervisionTimeSlot> $timeSlots
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RoomSupervisionGrid> $roomAssignments
 * @mixin \Eloquent
 */
class SupervisionGrid extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $fillable = [
        'exam_period_id',
        'name',
        'description',
        'color',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /** Zeitslots, die zu diesem Raster gehören */
    public function timeSlots(): HasMany
    {
        return $this->hasMany(SupervisionTimeSlot::class);
    }

    /** Raum-Zuweisungen pro Tag */
    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomSupervisionGrid::class);
    }

    /**
     * Tailwind-Farbklassen für Badge/Header-Anzeige
     * Gibt [bg, text, border] zurück
     * @return array<string>
     */
    public function getColorClasses(): array
    {
        return match ($this->color) {
            'green'  => ['bg-green-100',  'text-green-800',  'border-green-300'],
            'orange' => ['bg-orange-100', 'text-orange-800', 'border-orange-300'],
            'purple' => ['bg-purple-100', 'text-purple-800', 'border-purple-300'],
            'red'    => ['bg-red-100',    'text-red-800',    'border-red-300'],
            'yellow' => ['bg-yellow-100', 'text-yellow-800', 'border-yellow-300'],
            default  => ['bg-blue-100',   'text-blue-800',   'border-blue-300'],
        };
    }
}
