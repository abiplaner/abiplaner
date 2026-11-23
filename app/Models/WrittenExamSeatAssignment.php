<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $written_exam_assignment_id
 * @property int|null $student_id
 * @property string|null $guest_name
 * @property int|null $external_applicant_id
 * @property int $seat_id
 * @property int|null $sort_order
 * @property \Carbon\Carbon|null $assigned_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\WrittenExamAssignment|null $assignment
 * @property-read \App\Models\SeatingTemplateSeat|null $seat
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrittenExamSeatAssignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrittenExamSeatAssignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrittenExamSeatAssignment query()
 * @property-read \App\Models\Student|null $student
 * @property-read \App\Models\ExternalApplicant|null $externalApplicant
 * @mixin \Eloquent
 */
class WrittenExamSeatAssignment extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;
    
    protected $fillable = [
        'written_exam_assignment_id',
        'student_id',
        'guest_name',
        'external_applicant_id',
        'seat_id',
        'sort_order',
        'assigned_at'
    ];
    
    protected $casts = [
        'assigned_at' => 'datetime',
    ];
    
    /**
     * Relationship: WrittenExamSeatAssignment belongs to WrittenExamAssignment
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(WrittenExamAssignment::class, 'written_exam_assignment_id');
    }
    
    /**
     * Relationship: WrittenExamSeatAssignment belongs to Student
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    
    /**
     * Relationship: WrittenExamSeatAssignment belongs to SeatingTemplateSeat
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(SeatingTemplateSeat::class, 'seat_id');
    }

    /**
     * Relationship: WrittenExamSeatAssignment belongs to ExternalApplicant
     */
    public function externalApplicant(): BelongsTo
    {
        return $this->belongsTo(ExternalApplicant::class);
    }

    // ------------------------------------------------------------------
    // Wer sitzt hier?
    //
    // Ein Sitzplatz trägt genau eine von drei Angaben: einen eigenen
    // Schüler, einen externen Bewerber oder einen freien Gastnamen. Diese
    // Fallunterscheidung stand bis zum 27.08. an vier Stellen im
    // ExportController und noch einmal in den Blades — jede Kopie mit
    // eigener Kommazerlegung. Ab hier steht sie einmal.
    // ------------------------------------------------------------------

    /**
     * Die Person auf dem Platz, sofern sie einen Datensatz hat.
     *
     * Gastschüler liefern null: sie sind nur ein Name.
     */
    public function pruefling(): Student|ExternalApplicant|null
    {
        return $this->student ?? $this->externalApplicant;
    }

    public function istExterner(): bool
    {
        return $this->external_applicant_id !== null;
    }

    /**
     * Gast im engen Sinn: ein Name ohne Datensatz dahinter.
     */
    public function istGast(): bool
    {
        return $this->student_id === null
            && $this->external_applicant_id === null
            && $this->guest_name !== null;
    }

    /**
     * Der Name in der Form „Nachname, Vorname" — für alle drei Fälle.
     *
     * Student und ExternalApplicant liefern ihn schon so; nur heißen ihre
     * Spalten verschieden (`lastname` gegen `last_name`). Über `full_name`
     * spielt das keine Rolle mehr.
     */
    public function anzeigename(): string
    {
        $person = $this->pruefling();

        return $person !== null
            ? (string) $person->full_name
            : trim((string) $this->guest_name);
    }

    public function nachname(): string
    {
        return $this->nameTeile()[0];
    }

    public function vorname(): string
    {
        return $this->nameTeile()[1];
    }

    /**
     * Nachteilsausgleich in Prozent — 0, wenn keiner hinterlegt ist.
     *
     * Für Gastschüler bleibt es bei 0: ihre Schule plant deren Zeiten.
     */
    public function nachteilsausgleichProzent(): int
    {
        $person = $this->pruefling();

        return $person !== null ? (int) ($person->disability_compensation_percent ?? 0) : 0;
    }

    /**
     * Das Objekt, mit dem die Sitzplan-Blades arbeiten.
     *
     * Für einen eigenen Schüler ist das der Schüler selbst — die Views
     * greifen dort auf mehr zu als nur den Namen, und das soll so bleiben.
     * Für externe Bewerber und Gäste entsteht ein Ersatzobjekt in genau
     * derselben Form: `lastname`, `firstname`, `external_id`. Der externe
     * Bewerber bringt zusätzlich seinen Nachteilsausgleich mit, der Gast
     * nicht.
     */
    public function anzeigeObjekt(): object
    {
        if ($this->student instanceof Student) {
            return $this->student;
        }

        [$nachname, $vorname] = $this->nameTeile();

        return (object) [
            'lastname'    => $nachname,
            'firstname'   => $vorname,
            'external_id' => null,
            'is_guest'    => $this->istGast(),
            'is_extern'   => $this->istExterner(),
            'disability_compensation_percent' => $this->nachteilsausgleichProzent() ?: null,
        ];
    }

    /**
     * Der Anzeigename, zerlegt in [Nachname, Vorname].
     *
     * Fehlt das Komma — beim frei getippten Gastnamen möglich —, gilt der
     * ganze String als Nachname.
     *
     * @return array{0: string, 1: string}
     */
    private function nameTeile(): array
    {
        $roh = $this->anzeigename();
        if ($roh === '') {
            return ['', ''];
        }

        $teile = explode(',', $roh, 2);

        return [trim($teile[0]), trim($teile[1] ?? '')];
    }
}
