<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $student_exam_id
 * @property int $exam_week   0 = außerhalb der Prüfungswochen, 1/2 = Prüfungswoche,
 *                            3 = vorgezogen nach § 61 Abs. 4 (nur externe Bewerber)
 * @property int|null $time_slot_id
 * @property int $exam_room_id
 * @property int|null $prep_room_id
 * @property string $status  planned|confirmed|completed|cancelled
 * @property string|null $cancelled_reason  illness|other
 * @property int|null $replaces_id  FK auf exam_assignments (Original, das dieser Termin ersetzt)
 * @property \Carbon\Carbon|null $free_date
 * @property string|null $free_time_start
 * @property string|null $free_time_end
 * @property bool $is_locked
 * @property string|null $lock_reason
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Room|null $examRoom
 * @property-read \App\Models\Room|null $prepRoom
 * @property-read \App\Models\StudentExam|null $studentExam
 * @property-read \App\Models\TimeSlot|null $timeSlot
 * @property-read \App\Models\ExamAssignment|null $replaces
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAssignment locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAssignment active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAssignment cancelled()
 * @mixin \Eloquent
 */
class ExamAssignment extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    /**
     * Ein Termin ohne Zeitslot, außerhalb der beiden Prüfungswochen —
     * heute der Nachtermin nach Erkrankung.
     */
    public const WOCHE_AUSSERHALB = 0;

    /**
     * Vorgezogene mündliche Prüfung nach § 61 Abs. 4 S. 1: zwei der vier
     * Kolloquien eines externen Bewerbers dürfen in den zwei Wochen vor
     * Beginn der schriftlichen Abiturprüfungen liegen. Wie WOCHE_AUSSERHALB
     * ohne Zeitslot, mit Datum und Uhrzeit von Hand — aber ein eigener Wert,
     * damit die Anwendung diese Termine von den Nachterminen unterscheiden
     * kann. § 61 Abs. 4 S. 2 hängt daran.
     *
     * @see \App\Services\VorgezogeneKolloquienService
     */
    public const WOCHE_VORGEZOGEN = 3;

    protected $fillable = [
        'student_exam_id',
        'exam_week',
        'time_slot_id',
        'exam_room_id',
        'prep_room_id',
        'status',
        'cancelled_reason',
        'replaces_id',
        'free_date',
        'free_time_start',
        'free_time_end',
        'notes',
        'is_locked',
        'lock_reason',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'free_date' => 'date',
    ];

    // ─── Relationen ──────────────────────────────────────────────────────────

    public function studentExam(): BelongsTo
    {
        return $this->belongsTo(StudentExam::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function examRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'exam_room_id');
    }

    public function prepRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'prep_room_id');
    }

    /**
     * Das Original-Assignment, das dieser Nachtermin ersetzt.
     * (Nur gesetzt auf dem Nachtermin-Assignment)
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class, 'replaces_id');
    }

    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /** Scope für fixierte Prüfungen */
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    /** Scope für aktive (nicht abgesagte) Prüfungen */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /** Scope für abgesagte Prüfungen */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}
