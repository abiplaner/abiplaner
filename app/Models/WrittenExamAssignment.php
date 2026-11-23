<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $written_exam_year_date_id
 * @property int $course_id
 * @property int $room_id
 * @property int|null $capacity
 * @property string|null $notes
 * @property array|null $student_ids NTA-Gruppen-IDs oder Split-Schüler-IDs
 * @property string|null $split_label Gruppenbezeichnung bei Kursaufteilung, z.B. "2e1-1"
 * @property bool $is_hoerverstaendnis Hörverstehen-Zuordnung (moderne Fremdsprachen)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Room|null $room
 * @property-read \App\Models\Course|null $course
 * @property-read \App\Models\WrittenExamYearDate|null $examDate
 * @property-read \App\Models\School|null $school
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WrittenExamSeatAssignment> $seatAssignments
 * @property-read int|null $seat_assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Supervision> $supervisions
 * @property-read int|null $supervisions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrittenExamAssignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrittenExamAssignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrittenExamAssignment query()
 * @mixin \Eloquent
 */
class WrittenExamAssignment extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;
    
    protected $casts = [
        'student_ids'         => 'array',
        'is_hoerverstaendnis' => 'boolean',
    ];

    protected $fillable = [
        'written_exam_year_date_id',
        'course_id',
        'room_id',
        'notes',
        'student_ids',
        'split_label',
        'is_hoerverstaendnis',
        'required_supervisors',
        'written_exam_supervision_grid_id',
    ];
    
    /**
     * Relationship: belongs to WrittenExamYearDate (central, soft reference)
     */
    public function examDate(): BelongsTo
    {
        return $this->belongsTo(WrittenExamYearDate::class, 'written_exam_year_date_id');
    }
    
    /**
     * Relationship: WrittenExamAssignment belongs to Course
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
    
    /**
     * Relationship: WrittenExamAssignment belongs to Room
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function supervisionGrid(): BelongsTo
    {
        return $this->belongsTo(WrittenExamSupervisionGrid::class, 'written_exam_supervision_grid_id');
    }

    /**
     * Helper: Get seating template through room, passend zur Variante
     * (Standard oder Hörverstehen). Siehe Room::seatingTemplateFor().
     */
    public function getSeatingTemplate(): ?SeatingTemplate
    {
        $variant = $this->is_hoerverstaendnis
            ? RoomSeatingTemplate::VARIANT_HOERVERSTAENDNIS
            : RoomSeatingTemplate::VARIANT_STANDARD;

        return $this->room?->seatingTemplateFor($variant);
    }

    /**
     * Die Platznummern des Raumlayouts: `[seat_id => Nummer]`.
     *
     * Leer, wenn dem Raum keine Vorlage zugewiesen ist.
     *
     * @return array<int, int>
     */
    public function platznummern(): array
    {
        return $this->getSeatingTemplate()?->platznummern() ?? [];
    }
    
    /**
     * Relationship: WrittenExamAssignment belongs to School
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
    
    /**
     * Relationship: WrittenExamAssignment has many WrittenExamSeatAssignments
     */
    public function seatAssignments(): HasMany
    {
        return $this->hasMany(WrittenExamSeatAssignment::class);
    }
    
    /**
     * Relationship: WrittenExamAssignment has many Supervisions
     */
    public function supervisions(): HasMany
    {
        return $this->hasMany(Supervision::class, 'written_exam_assignment_id');
    }
    
    /**
     * Liefert die Schüler, die zu dieser Zuordnung gehören.
     * Wenn student_ids gesetzt ist (NTA-Gruppe), werden nur diese zurückgegeben,
     * sonst alle schriftlichen Schüler des Kurses.
     */
    public function getEffectiveStudents(): \Illuminate\Support\Collection
    {
        if (!empty($this->student_ids)) {
            return $this->course->writtenStudents->whereIn('id', $this->student_ids)->values();
        }
        return $this->course->writtenStudents;
    }

    public function getAssignedStudentsCount(): int
    {
        return $this->seatAssignments()->count();
    }
    
    /**
     * Helper: Get remaining capacity
     */
    public function getRemainingCapacity(): int
    {
        $template = $this->getSeatingTemplate();
        if (!$template) {
            return 0;
        }
        
        return $template->getTotalCapacity() - $this->getAssignedStudentsCount();
    }
}
