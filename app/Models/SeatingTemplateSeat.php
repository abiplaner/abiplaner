<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $template_id
 * @property int $row_number
 * @property int $column_number
 * @property string $seat_type available|blocked|aisle|reserve|teacher_desk
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WrittenExamSeatAssignment> $seatAssignments
 * @property-read int|null $seat_assignments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatingTemplateSeat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatingTemplateSeat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatingTemplateSeat query()
 * @property-read \App\Models\SeatingTemplate|null $template
 * @mixin \Eloquent
 */
class SeatingTemplateSeat extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $fillable = [
        'template_id',
        'row_number',
        'column_number',
        'seat_type',
        'notes',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'column_number' => 'integer',
    ];

    /**
     * Vorlage, zu der dieser Sitz gehört
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SeatingTemplate::class, 'template_id');
    }

    /**
     * Zuweisungen für diesen Sitz
     */
    public function seatAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WrittenExamSeatAssignment::class, 'seat_id');
    }

    /**
     * Prüfen, ob der Sitz verfügbar ist
     */
    public function isAvailable(): bool
    {
        return $this->seat_type === 'available';
    }

    /**
     * Prüfen, ob der Sitz blockiert ist
     */
    public function isBlocked(): bool
    {
        return $this->seat_type === 'blocked';
    }

}
