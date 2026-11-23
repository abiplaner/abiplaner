<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $rows
 * @property int $columns
 * @property array|null $door_positions
 * @property string $orientation
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read int $available_seats_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Room> $rooms
 * @property-read int|null $rooms_count
 * @property-read \App\Models\School|null $school
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SeatingTemplateSeat> $seats
 * @property-read int|null $seats_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatingTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatingTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatingTemplate query()
 * @mixin \Eloquent
 */
class SeatingTemplate extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'name',
        'description',
        'rows',
        'columns',
        'door_positions',
        'orientation',
    ];

    protected $casts = [
        'rows' => 'integer',
        'columns' => 'integer',
        'door_positions' => 'array',
        'orientation' => 'string',
    ];

    /**
     * Schule, zu der diese Vorlage gehört
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Sitze dieser Vorlage
     */
    public function seats(): HasMany
    {
        return $this->hasMany(SeatingTemplateSeat::class, 'template_id');
    }

    /**
     * Räume, die diese Vorlage verwenden. Veraltet seit Modul 1.5, siehe
     * roomVariants() — der eingefrorene Altbestand über die
     * seating_template_id-Spalte bleibt hier zur Referenz bestehen.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'seating_template_id');
    }

    /**
     * Sitzplan-Varianten (Room ↔ Template), die diese Vorlage aktuell
     * tatsächlich verwenden (Standard oder Hörverstehen).
     */
    public function roomVariants(): HasMany
    {
        return $this->hasMany(RoomSeatingTemplate::class);
    }

    /**
     * Anzahl verfügbarer Plätze
     */
    public function getAvailableSeatsCountAttribute(): int
    {
        return $this->seats()->where('seat_type', 'available')->count();
    }

    /**
     * Gesamtkapazität der Vorlage (nur verfügbare Plätze)
     */
    public function getTotalCapacity(): int
    {
        return $this->seats()->where('seat_type', 'available')->count();
    }

    /**
     * Die fortlaufenden Platznummern des Layouts: `[seat_id => Nummer]`.
     *
     * Gezählt werden nur Plätze, auf denen jemand sitzen kann — erst alle
     * regulären reihenweise von links nach rechts, danach die Reserveplätze.
     * Gänge, blockierte Felder und das Pult bekommen keine Nummer.
     *
     * Diese Zählung stand bis zum 27.08.2026 fünfmal gleichlautend im
     * ExportController und nirgends sonst. Wer einen Export ausserhalb dieser
     * fünf Stellen schrieb, griff stattdessen auf
     * `seating_template_seats.seat_number` zu — **eine Spalte, die es nicht
     * gibt**. Das Ergebnis war kein Fehler, sondern ein leerer String: „Platz "
     * ohne Zahl. Ab hier gibt es nur noch diese eine Quelle.
     *
     * @return array<int, int>
     */
    public function platznummern(): array
    {
        $nummern = [];
        $zaehler = 1;

        $besetzbar = $this->seats
            ->whereIn('seat_type', ['available', 'reserve'])
            ->sortBy(fn (SeatingTemplateSeat $seat) => sprintf(
                '%d-%03d-%03d',
                $seat->seat_type === 'available' ? 0 : 1,
                $seat->row_number,
                $seat->column_number
            ));

        foreach ($besetzbar as $seat) {
            $nummern[$seat->id] = $zaehler++;
        }

        return $nummern;
    }
}
