<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aufsichtsraster für schriftliche Abiturprüfungen.
 * Global – nicht datumsgebunden, wie WrittenExamSupervisionTimeSlot.
 *
 * Beispiele: "Standard", "Hörverstehen Englisch"
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property string|null $color
 * @property bool        $is_default
 * @property bool        $is_foreign_language
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WrittenExamSupervisionTimeSlot> $timeSlots
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WrittenExamAssignment> $assignments
 * @mixin \Eloquent
 */
class WrittenExamSupervisionGrid extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $fillable = [
        'name',
        'description',
        'color',
        'is_default',
        'is_foreign_language',
    ];

    protected $casts = [
        'is_default'          => 'boolean',
        'is_foreign_language' => 'boolean',
    ];

    public function timeSlots(): HasMany
    {
        return $this->hasMany(WrittenExamSupervisionTimeSlot::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WrittenExamAssignment::class);
    }

    /**
     * CSS-Klassen für Badges [bg, text, border]
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
