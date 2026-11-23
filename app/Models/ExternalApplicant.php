<?php

namespace App\Models;

use App\Services\ExterneGesamtqualifikationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property \Carbon\Carbon $birth_date
 * @property string|null $notes
 * @property \Carbon\Carbon|null $anonymize_at
 * @property bool $is_anonymized
 * @property \Carbon\Carbon|null $abgebrochen_am Entscheidung 51: Koordinator hat den Abbruch bewusst festgestellt
 * @property string|null $abbruch_grund d2 (Par 61 Abs. 4 S. 2) oder e6 (Par 62 Abs. 1 S. 4)
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $full_name
 * @property int $exam_period_id
 * @property int|null $ersatzschule_id Par 64: Ersatzschule, an der der Bewerber unterrichtet wird
 * @property int|null $leistungsfach_subject_id Par 61 Abs. 1 S. 5: Leistungsfach neben Deutsch und Mathematik
 * @property int|null $zweite_fremdsprache_subject_id Par 61 Abs. 3 S. 7: die zweite Fremdsprache
 * @property-read \App\Models\Ersatzschule|null $ersatzschule
 * @property int|null $disability_compensation_percent Nachteilsausgleich in Prozent
 * @property string|null $disability_compensation_notes
 * @property int|null $subject_count Aggregat aus withCount()
 * @property int|null $planned_count Aggregat aus withCount()
 * @property int|null $plannable_count Dynamisch gesetzt: Zahl der Faecher, die eine Pruefung bekommen (Par 64 Abs. 2: sechs statt acht)
 * @property string|null $validation_status Dynamisch gesetzt
 * @property array|null $validation_errors Dynamisch gesetzt
 * @property-read \App\Models\ExamPeriod|null $examPeriod
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExternalExamSubject> $examSubjects
 * @property-read int|null $exam_subjects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExternalExamSubject> $oralSubjects
 * @property-read int|null $oral_subjects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExternalExamSubject> $writtenSubjects
 * @property-read int|null $written_subjects_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalApplicant active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalApplicant forExamPeriod(int $examPeriodId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalApplicant forYear(int $year)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalApplicant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalApplicant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalApplicant onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalApplicant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalApplicant withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalApplicant withoutTrashed()
 * @mixin \Eloquent
 */
class ExternalApplicant extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;
    use SoftDeletes;
    // Auch ein externer Bewerber kann einen Nachteilsausgleich haben; die
    // Prüfungszeiten verlängern sich für ihn genauso wie für einen Schüler.
    use \App\Models\Concerns\HatNachteilsausgleich;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'birth_date',
        'exam_period_id',
        'ersatzschule_id',
        'leistungsfach_subject_id',
        'zweite_fremdsprache_subject_id',
        'disability_compensation_percent',
        'disability_compensation_notes',
        'notes',
        'anonymize_at',
        'is_anonymized',
        'abgebrochen_am',
        'abbruch_grund',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'anonymize_at' => 'datetime',
        'is_anonymized' => 'boolean',
        'disability_compensation_percent' => 'integer',
        'abgebrochen_am' => 'datetime',
    ];

    /**
     * Entscheidung 51 (01.09.2026): der Koordinator hat den Abbruch bewusst
     * festgestellt — siehe App\Services\ExterneAbbruchService.
     */
    public function istAbgebrochen(): bool
    {
        return $this->abgebrochen_am !== null;
    }

    /**
     * Mit dem Bewerber fallen seine Prüfungsfächer — und über deren Kaskade
     * die Prüfungen, deren Termine und die Zusatzprüfungen.
     *
     * Nötig, weil das Model soft löscht: `delete()` setzt nur `deleted_at`,
     * die Datenbank sieht keine Löschung und räumt von sich aus nichts ab.
     * Vor der Septemberrunde war das folgenlos — es gab keine Prüfungen.
     */
    protected static function booted(): void
    {
        static::deleting(function (ExternalApplicant $bewerber): void {
            $bewerber->examSubjects()->delete();
            // Der Sitzplatz hängt zwar an einem Fremdschlüssel mit
            // ON DELETE CASCADE, aber der greift beim Soft Delete nicht:
            // die Datenbank sieht dabei keine Löschung. Ohne diese Zeile
            // bliebe ein besetzter, aber namenloser Platz im Sitzplan
            // stehen — und der fiele erst am Prüfungstag auf.
            $bewerber->seatAssignments()->delete();
        });
    }

    /**
     * Get the exam period for this applicant
     */
    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    /**
     * Die Ersatzschule, an der der Bewerber unterrichtet wird — § 64.
     *
     * Leer für jeden anderen Bewerber nach § 60. Nur wer hier eine Schule
     * stehen hat, kann von der Ersetzung nach § 64 Abs. 2 Gebrauch machen.
     */
    public function ersatzschule(): BelongsTo
    {
        return $this->belongsTo(Ersatzschule::class);
    }

    /**
     * Schüler einer staatlich genehmigten Ersatzschule?
     */
    public function istErsatzschueler(): bool
    {
        return $this->ersatzschule_id !== null;
    }

    /**
     * Das Bewertungsschema — Anlage 14 oder Anlage 15.
     *
     * Die einzige Stelle, an der das entschieden wird (Entscheidung 34 vom
     * 27.08.2026). Es gibt bewusst kein Schemafeld: § 64 Abs. 2 S. 1 sagt
     * "kann". Ein Ersatzschüler, der von der Ersetzung keinen Gebrauch macht,
     * wird nach Anlage 14 bewertet wie jeder andere Bewerber — die Schule
     * allein entscheidet nichts.
     *
     * Abgeleitet wird deshalb allein aus den Fächern: sobald eines
     * eingebracht statt geprüft wird, gilt Anlage 15. Dass es genau zwei sein
     * müssen, ist eine Regel der Validierung (G5) und keine Frage des
     * Schemas — sonst fiele ein halb erfasster Bewerber stillschweigend auf
     * Anlage 14 zurück und würde falsch gerechnet.
     *
     * @return 'anlage_14'|'anlage_15'
     */
    public function bewertungsschema(): string
    {
        return $this->eingebrachteFaecher()->isEmpty()
            ? ExterneGesamtqualifikationService::SCHEMA_ANLAGE_14
            : ExterneGesamtqualifikationService::SCHEMA_ANLAGE_15;
    }

    /**
     * Die Fächer, deren Ergebnis nach § 64 Abs. 2 eingebracht statt geprüft
     * wird — nach Anlage 15 die Positionen 7 und 8.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ExternalExamSubject>
     */
    public function eingebrachteFaecher(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->examSubjects
            ->filter(static fn (ExternalExamSubject $fach): bool => $fach->istEingebracht())
            ->values();
    }

    /**
     * Get all exam subjects for this applicant
     */
    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExternalExamSubject::class);
    }

    /**
     * Die Sitzplätze, die der Bewerber in schriftlichen Prüfungen belegt.
     */
    public function seatAssignments(): HasMany
    {
        return $this->hasMany(WrittenExamSeatAssignment::class);
    }

    /**
     * Get written exam subjects (Part 1)
     */
    public function writtenSubjects(): HasMany
    {
        return $this->examSubjects()->where('exam_part', 'part1_written')->orderBy('subject_number');
    }

    /**
     * Get oral exam subjects (Part 2)
     */
    public function oralSubjects(): HasMany
    {
        return $this->examSubjects()->where('exam_part', 'part2_oral')->orderBy('subject_number');
    }

    /**
     * Wie viele mündliche Prüfungen dieser Bewerber bekommt.
     *
     * Bewusst keine feste Vier: Für einen Ersatzschüler nach § 64 Abs. 2
     * werden zwei der vier Fächer des zweiten Teils aus 13/II eingebracht
     * statt geprüft (Entscheidungen 34 und 39) — für ihn sind es zwei. Die
     * Zahl steht damit in der Fächerwahl und nirgendwo sonst; eine Konstante
     * hier würde bei jedem Ersatzschüler eine Lücke melden, die keine ist.
     */
    public function sollMuendlichePruefungen(): int
    {
        return $this->oralSubjects
            ->reject(static fn (ExternalExamSubject $fach): bool => $fach->istEingebracht())
            ->count();
    }

    /**
     * Get the applicant's full name
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->last_name}, {$this->first_name}";
    }

    /**
     * Scope: Filter by exam period
     */
    public function scopeForExamPeriod($query, int $examPeriodId)
    {
        return $query->where('exam_period_id', $examPeriodId);
    }
    
    /**
     * Scope: Filter by exam year (convenience method)
     * 
     * Since exam_periods is in the central DB and external_applicants is in tenant DB,
     * we cannot use whereHas() for cross-database queries. Instead, we fetch the
     * exam_period_ids for the year first, then filter by those IDs.
     */
    public function scopeForYear($query, int $year)
    {
        // Hole alle ExamPeriod IDs für dieses Jahr aus der zentralen DB
        $examPeriodIds = ExamPeriod::where('year', $year)->pluck('id');
        
        // Filtere nach diesen IDs
        return $query->whereIn('exam_period_id', $examPeriodIds);
    }

    /**
     * Scope: Only active (not anonymized)
     */
    public function scopeActive($query)
    {
        return $query->where('is_anonymized', false);
    }
}
