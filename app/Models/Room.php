<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $room_number
 * @property string $room_type exam|preparation|waiting|lockdown|corridor
 * @property string|null $notes
 * @property int|null $seating_template_id Veraltet seit Modul 1.5, siehe seatingTemplateVariants()
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\SeatingTemplate|null $seatingTemplate Veraltet seit Modul 1.5, siehe seatingTemplateFor()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RoomSeatingTemplate> $seatingTemplateVariants
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamAssignment> $examAssignments
 * @property-read int|null $exam_assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamAssignment> $prepAssignments
 * @property-read int|null $prep_assignments_count
 * @property-read \App\Models\School|null $school
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Supervision> $supervisions
 * @property-read int|null $supervisions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WrittenExamAssignment> $writtenExamAssignments
 * @property-read int|null $written_exam_assignments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room query()
 * @mixin \Eloquent
 */
class Room extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'room_number',
        'room_type',
        'notes',
        'is_active',
        'seating_template_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function examAssignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class, 'exam_room_id');
    }

    public function prepAssignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class, 'prep_room_id');
    }

    public function supervisions(): HasMany
    {
        return $this->hasMany(Supervision::class);
    }

    public function seatingTemplate(): BelongsTo
    {
        return $this->belongsTo(SeatingTemplate::class);
    }

    /**
     * Sitzplan-Varianten dieses Raums (Modul 1.5), z. B. "standard" und
     * "hoerverstaendnis". Ersetzt seating_template_id/seatingTemplate() als
     * Quelle der Wahrheit — diese bleiben nur als eingefrorener Altbestand
     * (letzter Stand vor der Migration) bestehen und werden von neuem Code
     * nicht mehr geschrieben.
     */
    public function seatingTemplateVariants(): HasMany
    {
        return $this->hasMany(RoomSeatingTemplate::class);
    }

    /**
     * Sitzplan-Vorlage für eine bestimmte Variante (Standard oder
     * Hörverstehen). Fällt auf "standard" zurück, wenn für eine
     * angeforderte Nicht-Standard-Variante keine eigene Vorlage hinterlegt
     * ist — die meisten Räume brauchen keine eigene Hörverstehen-Vorlage,
     * weil der Standard-Sitzplan für den 30-minütigen Hörverstehen-Teil
     * ebenfalls passt.
     */
    public function seatingTemplateFor(string $variant = RoomSeatingTemplate::VARIANT_STANDARD): ?SeatingTemplate
    {
        $match = $this->seatingTemplateVariants->firstWhere('variant', $variant);

        if (!$match && $variant !== RoomSeatingTemplate::VARIANT_STANDARD) {
            $match = $this->seatingTemplateVariants->firstWhere('variant', RoomSeatingTemplate::VARIANT_STANDARD);
        }

        return $match?->seatingTemplate;
    }
    
    public function writtenExamAssignments(): HasMany
    {
        return $this->hasMany(WrittenExamAssignment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('room_type', $type);
    }
}
