<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\WrittenExamYearDate;

/**
 * @property int $id
 * @property int|null $student_id  null bei externen Bewerbern
 * @property int|null $course_id   null bei externen Bewerbern
 * @property int|null $examiner_id null bis ein Prüfer zugewiesen ist
 * @property int|null $assessor_id
 * @property int|null $second_corrector_id
 * @property string|null $coupling_id UUID
 * @property string $exam_type written|oral
 * @property int|null $preferred_week Gewünschte Prüfungswoche (1 oder 2)
 * @property int|null $written_exam_year_date_id
 * @property int|null $external_exam_subject_id Prüfungsfach eines externen Bewerbers
 * @property int|null $cnt Aggregat-Hilfswert (z.B. aus DB-Abfragen)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder forAbiturStudentsOf(\App\Models\ExamPeriod $examPeriod)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentExam imPruefungszeitraum(\App\Models\ExamPeriod $examPeriod)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentExam desselbenPrueflings(\App\Models\StudentExam $exam)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentExam desPrueflings(string $schluessel)
 * @property-read \App\Models\Teacher|null $assessor
 * @property-read \App\Models\ExamAssignment|null $assignment   Aktiver (nicht-abgesagter) Termin
 * @property-read \App\Models\ExamAssignment[]|\Illuminate\Database\Eloquent\Collection $assignments  Alle Termine inkl. abgesagter
 * @property-read \App\Models\Course|null $course
 * @property-read \App\Models\Teacher|null $examiner
 * @property-read \App\Models\ExternalExamSubject|null $externalExamSubject
 * @property-read \App\Models\Teacher|null $secondCorrector
 * @property-read \App\Models\Student|null $student
 * @property-read \App\Models\WrittenExamYearDate|null $writtenExamYearDate
 * @property-read \App\Models\WrittenExamYearDate|null $writtenExamDate
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentExam externalApplicants()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentExam newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentExam newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentExam query()
 * @mixin \Eloquent
 */
class StudentExam extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'student_id',
        'course_id',
        'examiner_id',
        'assessor_id',
        'second_corrector_id',
        'coupling_id',
        'exam_type', // 'written' oder 'oral'
        'written_exam_year_date_id',
        'external_exam_subject_id', // Prüfungsfach eines externen Bewerbers (null bei regulären Schülern)
        'preferred_week', // Gewünschte Prüfungswoche (1 oder 2)
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'examiner_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'assessor_id');
    }

    public function secondCorrector(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'second_corrector_id');
    }

    public function writtenExamDate(): BelongsTo
    {
        return $this->belongsTo(WrittenExamYearDate::class, 'written_exam_year_date_id');
    }

    /**
     * Aktiver (nicht-abgesagter) Termin – ergibt null wenn nur Erkrankungs-Absagen vorhanden.
     * Wird für whereDoesntHave('assignment') in ungeplanten Prüfungen verwendet.
     */
    public function assignment(): HasOne
    {
        return $this->hasOne(ExamAssignment::class)
                    ->where('status', '!=', 'cancelled')
                    ->latest();
    }

    /**
     * Abgesagter Termin wegen Erkrankung (falls vorhanden).
     * Wird verwendet um erkrankte Schüler in der Planung zu kennzeichnen.
     */
    public function illnessAssignment(): HasOne
    {
        return $this->hasOne(ExamAssignment::class)
                    ->where('cancelled_reason', 'illness');
    }

    /**
     * Alle Termine (inkl. abgesagter) – für die Matrix-Ansicht im Planer.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    /**
     * Zusatzprüfung für diesen schriftlichen Prüfungseintrag (falls vorhanden)
     */
    public function zusatzpruefung(): HasOne
    {
        return $this->hasOne(Zusatzpruefung::class);
    }

    /**
     * Das Prüfungsfach eines externen Bewerbers, zu dem diese Prüfung gehört.
     *
     * Die Richtung ist seit dem Öffnen von student_exams diese: die Prüfung
     * trägt den Schlüssel, unique. Bei regulären Schülern ist sie null.
     */
    public function externalExamSubject(): BelongsTo
    {
        return $this->belongsTo(ExternalExamSubject::class);
    }

    /**
     * Get the written exam seat assignment for this exam
     * Note: This relationship works through a subquery since WrittenExamSeatAssignment
     * links to students, not student_exams
     */
    public function getWrittenExamSeatAssignment()
    {
        if ($this->exam_type !== 'written' || !$this->written_exam_year_date_id) {
            return null;
        }

        return WrittenExamSeatAssignment::where('student_id', $this->student_id)
            ->whereHas('assignment', function($query) {
                $query->where('written_exam_year_date_id', $this->written_exam_year_date_id)
                      ->where('course_id', $this->course_id);
            })
            ->with(['assignment.room', 'seat'])
            ->first();
    }

    /**
     * Die zentrale ID des Prüfungszeitraums, zu dem diese Prüfung gehört.
     *
     * student_exams führt den Prüfungszeitraum nicht selbst (Entscheidung vom
     * 26.08.2026): für reguläre Schüler steht er am Schüler, für externe
     * Bewerber am Bewerber. Eine eigene Spalte wäre eine Kopie neben ihrer
     * Quelle — dasselbe Muster, das in dieser Runde zweimal gefallen ist.
     *
     * Die frühere Beziehung examPeriod() lief als HasOneThrough über
     * students und lieferte für Externe deshalb immer null.
     */
    public function pruefungszeitraumId(): ?int
    {
        $fach = $this->externalExamSubject;

        if ($fach instanceof ExternalExamSubject) {
            $bewerber = $fach->externalApplicant;

            return $bewerber instanceof ExternalApplicant ? $bewerber->exam_period_id : null;
        }

        $schueler = $this->student;

        return $schueler instanceof Student ? $schueler->exam_period_id : null;
    }

    public function isAssigned(): bool
    {
        // Mündliche Prüfung: Prüfe ob ein ExamAssignment existiert
        if ($this->exam_type === 'oral') {
            return $this->assignment()->exists();
        }
        
        // Schriftliche Prüfung: Prüfe ob ein Jahrestermin gesetzt ist
        if ($this->exam_type === 'written') {
            return !is_null($this->written_exam_year_date_id);
        }
        
        return false;
    }

    /**
     * Check if this exam is coupled with others
     */
    public function isCoupled(): bool
    {
        return !is_null($this->coupling_id);
    }

    /**
     * Get all exams in the same coupling group
     */
    public function coupledExams()
    {
        if (!$this->isCoupled()) {
            return collect([]);
        }

        return static::where('coupling_id', $this->coupling_id)
            ->where('id', '!=', $this->id)
            ->with(['student', 'course'])
            ->get();
    }

    /**
     * Check if this is a written exam (S1, S2, S3)
     */
    public function isWrittenExam(): bool
    {
        return $this->exam_type === 'written';
    }

    /**
     * Check if this is an oral exam/colloquium (K1, K2)
     */
    public function isOralExam(): bool
    {
        return $this->exam_type === 'oral';
    }

    /**
     * Get exam type display name
     */
    public function getExamTypeDisplayName(): string
    {
        return match($this->exam_type) {
            'written' => 'Schriftlich',
            'oral' => 'Mündlich (Kolloquium)',
            default => 'Unbekannt'
        };
    }

    /**
     * Gehört diese Prüfung zu einem externen Bewerber?
     *
     * Die frühere Spalte is_external_applicant war eine zweite Kodierung
     * derselben Tatsache und ist mit der Septemberrunde gefallen. Maßgeblich
     * ist der Schlüssel auf das Prüfungsfach.
     */
    public function isExternalApplicant(): bool
    {
        return $this->external_exam_subject_id !== null;
    }

    /**
     * Scope: nur Prüfungen externer Bewerber
     */
    public function scopeExternalApplicants($query)
    {
        return $query->whereNotNull('external_exam_subject_id');
    }

    /**
     * Schlüssel des Prüflings — für alles, was "pro Person" zählt.
     *
     * Für reguläre Schüler ist das die students-ID, für externe Bewerber die
     * des Bewerbers. Ein einheitlicher Schlüssel ist nötig, seit student_id
     * für Externe null ist: als Array-Schlüssel würde null zu "" und alle
     * externen Bewerber fielen in denselben Topf — der Planer hielte sie für
     * eine einzige Person und gäbe ihnen zusammen zwei Prüfungswochen.
     *
     * Das Präfix trennt die beiden Nummernkreise, die sonst kollidieren.
     */
    public function prueflingsSchluessel(): string
    {
        $fach = $this->externalExamSubject;

        if ($fach instanceof ExternalExamSubject) {
            return 'e' . $fach->external_applicant_id;
        }

        return 's' . ((int) $this->student_id);
    }

    /**
     * Scope: alle Prüfungen eines Prüfungszeitraums — reguläre und externe.
     *
     * Die Verzweigung steht hier und sonst nirgends: für reguläre Schüler
     * hängt der Zeitraum am Schüler, für externe Bewerber am Bewerber.
     * Vorher gingen die Kandidatenabfragen des Planers über whereHas('student')
     * und sahen externe Prüfungen deshalb überhaupt nicht.
     *
     * Im selben Zeitraum stehen auch die Q11 und Q12 des Schuljahres (Wahlen,
     * Absenzen, Abiturfächer der Q12). Deren Prüfungen gehören nicht zum
     * Abitur dieses Zeitraums: Am 18.11.2026 hat die Verteilung für das
     * Abitur 2027 die Kolloquien der Q12 eingeplant, weil der Scope nicht nach
     * Stufe filterte. Deshalb nur Stufe 13 — und Schüler ohne Stufe, wie sie
     * ältere Datensätze und die Tests anlegen.
     */
    public function scopeImPruefungszeitraum($query, ExamPeriod $examPeriod)
    {
        return $query->where(function ($q) use ($examPeriod) {
            $q->whereHas('student', fn ($s) => $s->where('exam_period_id', $examPeriod->id)
                ->where(fn ($g) => $g->where('grade', '13')->orWhereNull('grade')))
              ->orWhereHas(
                  'externalExamSubject.externalApplicant',
                  fn ($b) => $b->where('exam_period_id', $examPeriod->id)
              );
        });
    }

    /**
     * Scope: Prüfungen eines Prüflings, angesprochen über seinen Schlüssel
     * ("s<id>" für einen Schüler, "e<id>" für einen externen Bewerber).
     *
     * Das ist die Auflösung, die prueflingsSchluessel() erzeugt — die
     * Oberfläche reicht den Schlüssel durch, der Server macht daraus wieder
     * eine Abfrage. Ein unbekanntes Format liefert bewusst nichts, statt
     * stillschweigend alles zu liefern.
     */
    public function scopeDesPrueflings($query, string $schluessel)
    {
        if (preg_match('/^([se])(\d+)$/', $schluessel, $treffer) !== 1) {
            return $query->whereRaw('1 = 0');
        }

        if ($treffer[1] === 's') {
            return $query->where('student_id', (int) $treffer[2]);
        }

        return $query->whereHas(
            'externalExamSubject',
            fn ($q) => $q->where('external_applicant_id', (int) $treffer[2])
        );
    }

    /**
     * Scope: Prüfungen desselben Prüflings wie die übergebene Prüfung.
     */
    public function scopeDesselbenPrueflings($query, StudentExam $exam)
    {
        return $query->desPrueflings($exam->prueflingsSchluessel());
    }

    /**
     * Scope: Nur Prüfungen von Abitur-Schülern (Klasse 12/13 oder ohne Klasse) eines Prüfungszeitraums.
     * Eliminiert den redundanten whereHas-Block in SubjectStatsExport und StudentController.
     */
    public function scopeForAbiturStudentsOf($query, ExamPeriod $examPeriod)
    {
        return $query->whereHas('student', function ($q) use ($examPeriod) {
            $q->where('exam_period_id', $examPeriod->id)
              ->where(function ($q2) {
                  $q2->where('class_name', 'LIKE', '12%')
                     ->orWhere('class_name', 'LIKE', '13%')
                     ->orWhereNull('class_name');
              });
        });
    }

    /**
     * Das Fach dieser Prüfung.
     *
     * Bei einem externen Bewerber steht es im zentralen Katalog, bei einem
     * regulären Schüler am Kurs. Kopien auf dieser Tabelle gibt es keine mehr.
     */
    public function getSubjectName(): ?string
    {
        $fach = $this->externalExamSubject;

        if ($fach instanceof ExternalExamSubject) {
            return $fach->fachname();
        }

        // courses hat keine subject-Spalte, sondern eine subject_id: $kurs->subject
        // ist das Fach aus dem zentralen Katalog. Die frühere Fassung gab es
        // unverändert zurück und hätte damit ein Subject statt eines Strings
        // geliefert — der Rückgabetyp hätte das mit einem TypeError quittiert,
        // sobald jemand die Methode für einen regulären Schüler aufruft.
        return $this->fachAusKatalog()?->name;
    }

    /**
     * Das Kürzel des Fachs aus dem zentralen Katalog.
     */
    public function getSubjectCode(): ?string
    {
        return $this->fachAusKatalog()?->code;
    }

    /**
     * Das Fach dieser Prüfung als Katalogeintrag — aus dem Prüfungsfach des
     * externen Bewerbers oder aus dem Kurs des Schülers.
     */
    public function fachAusKatalog(): ?Subject
    {
        $fach = $this->externalExamSubject;

        if ($fach instanceof ExternalExamSubject) {
            $katalogfach = $fach->subject;

            return $katalogfach instanceof Subject ? $katalogfach : null;
        }

        $kurs = $this->course;

        if (! $kurs instanceof Course) {
            return null;
        }

        $katalogfach = $kurs->subject;

        return $katalogfach instanceof Subject ? $katalogfach : null;
    }

    /**
     * Ist das Fach dieser Prüfung Mathematik?
     *
     * Steht hier und nicht an jedem Leser, aus demselben Grund wie
     * `Subject::istSport()`: Die Frage entscheidet, welche Anmeldung ein
     * Prüfling ausfüllt — in Mathematik wird ein **Stoffgebiet** gestrichen
     * (Stochastik oder Geometrie, Analysis ist verpflichtend), sonst ein
     * **Ausbildungsabschnitt**.
     *
     * Zwei von sechs Lesern fragten `$se->course->subject->code === 'M'` und
     * lagen damit für externe Bewerber immer falsch — die haben keinen Kurs.
     * Folge: Im Prüfungsplan und in der Lehrerübersicht fehlte das gestrichene
     * Stoffgebiet ganz, weil die Beschriftung nur Ausbildungsabschnitte kennt
     * und `stochastik` dort nicht vorkommt. Der Prüfer ging also ohne die
     * Angabe in die Prüfung.
     */
    public function istMathematik(): bool
    {
        return $this->getSubjectCode() === 'M';
    }

    /**
     * Die Farbe des Fachs — für die farbigen Ränder der Planungsansicht.
     *
     * Geht über `fachAusKatalog()` und trifft damit **beide** Fälle. Die
     * naheliegende Fassung `$exam->course->subject->color` tut das nicht: Ein
     * externer Bewerber hat keinen Kurs, und der Zugriff wirft dann. Genau so
     * ist die Kolloquiumsplanung am 29.08.2026 mit 500 ausgestiegen, sobald
     * eine ungeplante Prüfung eines Externen in der Seitenleiste stand.
     *
     * Der Rückfallwert steht hier und nicht an jedem Leser: Ein zweites
     * `?? '#999'` an anderer Stelle wäre die zweite Kopie derselben Regel.
     */
    public function fachfarbe(): string
    {
        // Fallunterscheidung statt ?-> wie bei den Nachbarn oben: color ist
        // auf Subject nicht nullable, ein ?->color ?? … waere also irrefuehrend
        // (und PHPStan meldet es).
        $fach = $this->fachAusKatalog();

        return $fach instanceof Subject ? $fach->color : '#999999';
    }

    /**
     * Die Bezeichnung des Fachs, wie die Planungsansichten sie zeigen:
     * bei regulären Schülern das Kurskürzel, bei Externen der Fachname.
     */
    public function getSubjectLabel(): string
    {
        if ($this->isExternalApplicant()) {
            return $this->getSubjectName() ?? '–';
        }

        // Fallunterscheidung statt ?-> : Larastan hält die Beziehung für
        // nicht-nullable, seit course_id nullable ist stimmt das nicht mehr.
        $kurs = $this->course;

        return $kurs instanceof Course ? (string) $kurs->course_code : '–';
    }

    /**
     * Grundlegendes oder erhöhtes Anforderungsniveau.
     *
     * Für Externe leitet es § 61 Abs. 1 S. 5 her — Deutsch, Mathematik und das
     * Leistungsfach —, für reguläre Schüler steht es am Kurs.
     */
    public function getLevel(): ?string
    {
        $fach = $this->externalExamSubject;

        if ($fach instanceof ExternalExamSubject) {
            return $fach->niveau();
        }

        return $this->course?->level;
    }

    /**
     * Ist dieses Fach das Leistungsfach des Prüflings?
     *
     * Nicht dasselbe wie erhöhtes Anforderungsniveau. Bei externen Bewerbern
     * sind Deutsch und Mathematik nach § 61 Abs. 1 S. 5 **immer** auf erhöhtem
     * Niveau, ohne deshalb Leistungsfach zu sein — Leistungsfach ist das eine
     * Fach, das mit der Anmeldung zusätzlich festgelegt wird
     * (`external_applicants.leistungsfach_subject_id`). Wer beides gleichsetzt,
     * findet bei jedem Externen drei Leistungsfächer statt einem.
     *
     * Für reguläre Schüler steht es am Kurscode: ein führendes „2" plus
     * Großbuchstabe kennzeichnet den Leistungskurs.
     *
     * Die Ableitung steht hier genau einmal; Anzeige, Mindestpunkte-Abbildung
     * und Optimierer holen sie von hier.
     */
    public function istLeistungsfach(): bool
    {
        $fach = $this->externalExamSubject;

        if ($fach instanceof ExternalExamSubject) {
            $bewerber = $fach->externalApplicant;

            if (! $bewerber instanceof ExternalApplicant) {
                return false;
            }

            return $bewerber->leistungsfach_subject_id !== null
                && (int) $fach->subject_id === (int) $bewerber->leistungsfach_subject_id;
        }

        return preg_match('/^2[A-Z]/', $this->getSubjectLabel()) === 1;
    }

    /**
     * Ist das Fach dieser Prüfung ein Sportfach?
     *
     * Fragt den Katalogeintrag (Subject::istSport()) und deckt damit beide
     * Prüflingsarten ab. Die frühere Fassung im ZusatzpruefungController sah
     * nur `studentExam->course?->subject` an und hätte einen externen
     * Bewerber nie als Sportfall erkannt.
     */
    public function istSport(): bool
    {
        return $this->fachAusKatalog()?->istSport() ?? false;
    }

    /**
     * Der Prüfling dieser Prüfung — ein Schüler oder ein externer Bewerber.
     *
     * Beide führen ihren Nachteilsausgleich selbst (Trait
     * HatNachteilsausgleich). Wer eine Prüfungszeit berechnet, fragt hier und
     * nicht die students-Tabelle: ein externer Bewerber kann einen
     * Nachteilsausgleich haben wie jeder andere Prüfling auch.
     */
    public function pruefling(): Student|ExternalApplicant|null
    {
        $fach = $this->externalExamSubject;

        if ($fach instanceof ExternalExamSubject) {
            $bewerber = $fach->externalApplicant;

            return $bewerber instanceof ExternalApplicant ? $bewerber : null;
        }

        $schueler = $this->student;

        return $schueler instanceof Student ? $schueler : null;
    }

    public function hatNachteilsausgleich(): bool
    {
        $pruefling = $this->pruefling();

        return $pruefling !== null && $pruefling->hasDisabilityCompensation();
    }

    public function nachteilsausgleichProzent(): int
    {
        $pruefling = $this->pruefling();

        return $pruefling === null ? 0 : (int) ($pruefling->disability_compensation_percent ?? 0);
    }

    /**
     * Die um den Nachteilsausgleich des Prüflings verlängerte Dauer in Minuten.
     */
    public function kompensierteDauer(int $regelzeit): int
    {
        $pruefling = $this->pruefling();

        return $pruefling === null ? $regelzeit : $pruefling->getCompensatedDuration($regelzeit);
    }

    /**
     * Steht überall, wo sich kein Name ermitteln lässt.
     *
     * Großgeschrieben, weil der Wert mitten in Sätze eingesetzt wird und dort
     * die Stelle eines Namens einnimmt: „Unbekannter Prüfling hat am 12.05.
     * bereits eine mündliche Prüfung."
     */
    public const NAME_UNBEKANNT = 'Unbekannter Prüfling';

    /**
     * Der Name des Prüflings — aus dem Schülerdatensatz oder vom Bewerber.
     *
     * Liefert **immer** eine Zeichenkette. Bis zum 30.08.2026 war der Rückgabe-
     * wert `?string`, und das ging auf zwei Arten schief:
     *
     * - In zwölf Protokoll- und Logtexten wurde er ungeschützt interpoliert.
     *   Aus einem fehlenden Namen wurde dort kein Fehler, sondern eine Lücke:
     *   „Prüfung für  zugewiesen". Wer das im Protokoll liest, weiß nicht
     *   einmal, dass etwas fehlt.
     * - Sechs Masken fielen auf `$exam->student?->lastname` zurück — auf genau
     *   den Wert, der null ist, wenn diese Methode null liefert. Der Rückfall
     *   konnte nie greifen.
     *
     * Null war ohnehin kein Zustand, mit dem irgendein Aufrufer etwas anfangen
     * konnte: Alle setzten sofort einen Platzhalter ein, jeder einen anderen
     * („?", „Unbekannt", „–", „Der Bewerber", ""). Jetzt gibt es einen.
     *
     * Der Fall selbst bleibt eine Unstimmigkeit in den Daten — eine Prüfung
     * ohne Schüler und ohne externen Bewerber. Sichtbar ist besser als leer.
     */
    public function getStudentName(): string
    {
        return $this->prueflingsNameOderNull() ?? self::NAME_UNBEKANNT;
    }

    /**
     * Der rohe Name, oder null, wenn keine der beiden Beziehungen trägt.
     *
     * Nur für die Kurzformen unten: Die schneiden am Komma und brauchen ihre
     * eigenen, kürzeren Platzhalter für enge Spalten.
     */
    private function prueflingsNameOderNull(): ?string
    {
        $fach = $this->externalExamSubject;

        if ($fach instanceof ExternalExamSubject) {
            $bewerber = $fach->externalApplicant;

            return $bewerber instanceof ExternalApplicant ? $bewerber->full_name : null;
        }

        return $this->student?->full_name;
    }

    /**
     * Nur der Nachname des Prüflings.
     *
     * Für Listen, die aus Platzgründen ohnehin nur den Nachnamen zeigen
     * (Raumbelegung, Gesamtplan). Ersetzt den direkten Zugriff auf
     * `student->lastname`, der für externe Bewerber wirft.
     */
    public function prueflingsNachname(): string
    {
        $name = $this->prueflingsNameOderNull();

        if ($name === null || $name === '') {
            return '?';
        }

        return trim(explode(',', $name, 2)[0]);
    }

    /**
     * Nur der Vorname des Prüflings — Gegenstück zu prueflingsNachname().
     */
    public function prueflingsVorname(): string
    {
        $name = $this->prueflingsNameOderNull();

        if ($name === null || $name === '') {
            return '';
        }

        return trim(explode(',', $name, 2)[1] ?? '');
    }

    /**
     * Kurzform für enge Spalten: „Nachname, V." — für Schüler wie für
     * externe Bewerber, deren full_name ebenfalls „Nachname, Vorname" ist.
     */
    public function prueflingsKurzname(): string
    {
        $name = $this->prueflingsNameOderNull();

        if ($name === null || $name === '') {
            return '?';
        }

        $teile    = explode(',', $name, 2);
        $nachname = trim($teile[0]);
        $vorname  = trim($teile[1] ?? '');

        if ($vorname === '') {
            return $nachname;
        }

        return $nachname . ', ' . mb_strtoupper(mb_substr($vorname, 0, 1)) . '.';
    }

}
