<?php

namespace App\Models;

use App\Services\ExternalApplicantValidationService;
use App\Services\ExterneGesamtqualifikationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $external_applicant_id
 * @property int $subject_id Referenz auf die zentrale subjects-Tabelle
 * @property string $exam_part part1_written|part2_oral
 * @property int $subject_number
 * @property string $ergebnis_quelle pruefung|einbringung (Par 64 Abs. 2 S. 1)
 * @property int|null $eingebrachte_punktzahl Punktzahl 0-15 in einfacher Wertung aus 13/II
 * @property int|null $examiner_id
 * @property-read \App\Models\Subject|null $subject
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\ExternalApplicant|null $externalApplicant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamSubject newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamSubject newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamSubject oral()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamSubject query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamSubject written()
 * @property-read \App\Models\Teacher|null $examiner
 * @property-read \App\Models\StudentExam|null $studentExam
 * @mixin \Eloquent
 */
class ExternalExamSubject extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;

    protected $fillable = [
        'external_applicant_id',
        'subject_id',
        'exam_part',
        'subject_number',
        'ergebnis_quelle',
        'eingebrachte_punktzahl',
        'examiner_id',
        'notes',
    ];

    protected $casts = [
        'eingebrachte_punktzahl' => 'integer',
    ];

    /**
     * § 64 Abs. 2 S. 1 — wird dieses Fach eingebracht statt geprüft?
     *
     * Die Wörter sind dieselben wie im Rechner
     * (ExterneGesamtqualifikationService::QUELLE_*); zwei Vokabulare für
     * dieselbe Tatsache wären eine Fehlerquelle mehr.
     */
    public function istEingebracht(): bool
    {
        return $this->ergebnis_quelle === ExterneGesamtqualifikationService::QUELLE_EINBRINGUNG;
    }

    /**
     * Relationships
     */
    public function externalApplicant(): BelongsTo
    {
        return $this->belongsTo(ExternalApplicant::class);
    }

    /**
     * Die Prüfung zu diesem Fach — höchstens eine.
     *
     * Den Schlüssel trägt seit dem Öffnen von student_exams die Prüfung, und
     * er ist dort unique. Keine Prüfung entsteht für Fächer, die nach
     * § 64 Abs. 2 eingebracht statt geprüft werden.
     */
    public function studentExam(): HasOne
    {
        return $this->hasOne(StudentExam::class);
    }
    
    public function examiner(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'examiner_id');
    }

    /**
     * Scopes
     */
    public function scopeWritten($query)
    {
        return $query->where('exam_part', 'part1_written');
    }

    public function scopeOral($query)
    {
        return $query->where('exam_part', 'part2_oral');
    }

    
    /**
     * Position 1–8 nach Anlage 14: die vier Fächer des ersten Prüfungsteils,
     * danach die vier des zweiten. Die Anlage knüpft an diese Nummerierung
     * mehrere Vorgaben — Mathematik ist Fach 1, nur Fach 5 darf im zweiten
     * Teil auf erhöhtem Niveau liegen.
     */
    public function position(): int
    {
        return $this->exam_part === 'part2_oral'
            ? 4 + (int) $this->subject_number
            : (int) $this->subject_number;
    }

    /**
     * Das Fach aus dem zentralen Katalog.
     *
     * subjects ist zentral, external_exam_subjects liegt im Mandanten — die
     * Beziehung ist eine zweite Abfrage, kein Join. Sie trägt jetzt den Namen
     * subject(), weil die gleichnamige Freitextspalte gefallen ist.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Der Name des Fachs aus dem zentralen Katalog.
     */
    public function fachname(): ?string
    {
        $fach = $this->subject;

        return $fach instanceof Subject ? $fach->name : null;
    }

    /**
     * Erhöhtes oder grundlegendes Anforderungsniveau nach § 61 Abs. 1 S. 5.
     *
     * Die Regel selbst steht genau einmal, in
     * ExternalApplicantValidationService::istErhoehtesNiveau(); hier wird sie
     * nur auf dieses Fach angewandt. Deutsch und Mathematik sind immer eA,
     * dazu das eine Leistungsfach des Bewerbers.
     *
     * Die Wörter sind dieselben wie an `courses.level` — "grundlegend" und
     * "erhoeht". Die frühere Spalte auf external_exam_subjects führte
     * "basic"/"advanced"; zwei Vokabulare für dieselbe Tatsache sind eine
     * Fehlerquelle mehr, und die Spalte ist ohnehin gefallen.
     */
    public function niveau(): string
    {
        $bewerber = $this->externalApplicant;

        $erhoeht = app(ExternalApplicantValidationService::class)->istErhoehtesNiveau(
            $this->subject,
            $bewerber instanceof ExternalApplicant ? $bewerber->leistungsfach_subject_id : null,
        );

        return $erhoeht ? 'erhoeht' : 'grundlegend';
    }
}
