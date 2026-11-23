<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property int|null $student_id  null bei externen Bewerbern
 * @property int $student_exam_id
 * @property string $status  pending|placed
 * @property string|null $notes
 * @property bool $absent
 * @property int|null $written_points
 * @property int|null $target_points
 * @property bool $locked
 * @property bool $improvement_only
 * @property int|null $improvement_points_needed
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Student|null $student
 * @property-read \App\Models\StudentExam $studentExam
 * @property-read \App\Models\ZusatzpruefAssignment|null $assignment
 * @property-read \App\Models\ZusatzpruefExamScore|null $examScore
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ZusatzpruefPruefer> $pruefer
 * @mixin \Eloquent
 */
class Zusatzpruefung extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'zusatzpruefungen';

    protected $fillable = [
        'exam_period_id',
        'student_id',
        'student_exam_id',
        'status',
        'notes',
        'absent',
        'written_points',
        'target_points',
        'locked',
        'improvement_only',
        'improvement_points_needed',
        'sort_order',
        'excluded_semester',
        'focus_semester',
    ];

    protected $casts = [
        'absent'           => 'boolean',
        'locked'           => 'boolean',
        'improvement_only' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function studentExam(): BelongsTo
    {
        return $this->belongsTo(StudentExam::class);
    }

    /**
     * Der Name des Prüflings.
     *
     * `student_id` ist seit der Septemberrunde für externe Bewerber null —
     * maßgeblich ist die Prüfung, und die kennt beide Fälle.
     *
     * Liefert seit dem 30.08.2026 immer eine Zeichenkette, wie
     * StudentExam::getStudentName(). Hängt keine Prüfung daran, steht derselbe
     * Platzhalter da — auch das ist eine Unstimmigkeit in den Daten und gehört
     * sichtbar gemacht, nicht als Lücke gerendert.
     */
    public function prueflingsName(): string
    {
        $pruefung = $this->studentExam;

        return $pruefung instanceof StudentExam
            ? $pruefung->getStudentName()
            : StudentExam::NAME_UNBEKANNT;
    }

    /**
     * Kurzform „Nachname, V." für enge Spalten in den Exporten.
     */
    public function prueflingsKurzname(): string
    {
        $pruefung = $this->studentExam;

        return $pruefung instanceof StudentExam ? $pruefung->prueflingsKurzname() : '?';
    }

    /**
     * Der Schlüssel des Prüflings ("s<id>" bzw. "e<id>") — für alles, was
     * Zusatzprüfungen zu einer Person zusammenfasst.
     */
    public function prueflingsSchluessel(): string
    {
        $pruefung = $this->studentExam;

        return $pruefung instanceof StudentExam ? $pruefung->prueflingsSchluessel() : '';
    }

    /**
     * Nachteilsausgleich des Prüflings — für Schüler wie für externe
     * Bewerber. Die Prüfung kennt beide Fälle, `student` nur einen.
     */
    public function hatNachteilsausgleich(): bool
    {
        $pruefung = $this->studentExam;

        return $pruefung instanceof StudentExam && $pruefung->hatNachteilsausgleich();
    }

    public function nachteilsausgleichProzent(): int
    {
        $pruefung = $this->studentExam;

        return $pruefung instanceof StudentExam ? $pruefung->nachteilsausgleichProzent() : 0;
    }

    /**
     * Die um den Nachteilsausgleich verlängerte Dauer in Minuten.
     */
    public function kompensierteDauer(int $regelzeit): int
    {
        $pruefung = $this->studentExam;

        return $pruefung instanceof StudentExam
            ? $pruefung->kompensierteDauer($regelzeit)
            : $regelzeit;
    }

    /**
     * Gehört diese Zusatzprüfung zu einem externen Bewerber?
     */
    public function istExtern(): bool
    {
        $pruefung = $this->studentExam;

        return $pruefung instanceof StudentExam && $pruefung->isExternalApplicant();
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(ZusatzpruefAssignment::class);
    }

    /**
     * Die zur selben Prüfung erfassten Noten — für Sport die Praxisnote.
     *
     * Der Schlüssel ist `student_exam_id`, nicht `student_id`: die Spalte
     * trägt in `zusatzpruef_exam_scores` einen UNIQUE-Index, es gibt also
     * genau eine Zeile je Prüfung, und `student_id` ist für externe Bewerber
     * null.
     */
    public function examScore(): HasOne
    {
        return $this->hasOne(ZusatzpruefExamScore::class, 'student_exam_id', 'student_exam_id');
    }

    /**
     * Ist das eine Zusatzprüfung in Sport?
     */
    public function istSport(): bool
    {
        $pruefung = $this->studentExam;

        return $pruefung instanceof StudentExam && $pruefung->istSport();
    }

    /**
     * Die Praxisnote (0–15) des Sportfachs, sonst null.
     *
     * Sie steht nicht an der Zusatzprüfung, sondern in
     * `zusatzpruef_exam_scores` (siehe Docblock ZusatzpruefExamScore).
     */
    public function sportPraxisnote(): ?int
    {
        if (! $this->istSport()) {
            return null;
        }

        $note = $this->examScore;

        return $note instanceof ZusatzpruefExamScore ? $note->practical_grade : null;
    }

    public function pruefer(): HasMany
    {
        return $this->hasMany(ZusatzpruefPruefer::class)->orderBy('sort_order');
    }

    /**
     * Kombinierte Note (0–15) zur ANZEIGE.
     * round((2 × schriftlich + mündlich) / 3), jedoch kein Aufrunden auf 1
     * (d. h. Ergebnisse < 1 werden auf 0 gesetzt).
     *
     * ACHTUNG: Das ist NICHT die amtliche Punktzahl. Anlage 12 zu § 52 Abs. 1
     * GSO rundet erst das Endergebnis der vierfachen Wertung
     * (P = round((2s + m) / 3 × 4)) — hier zuerst zu runden und danach zu
     * multiplizieren, weicht ab: bei s = 8, m = 10 ergäbe das 36 statt 35.
     * Für Summen und Beiträge deshalb ausschließlich
     * getExamResultPoints() bzw. MindestpunkteService verwenden.
     *
     * Gibt null zurück wenn written_points oder target_points fehlen — und
     * bei einer Sport-Zusatzprüfung auch dann, wenn die Praxisnote fehlt.
     */
    public function getCombinedPoints(): ?int
    {
        if ($this->written_points === null || $this->target_points === null) {
            return null;
        }

        $dienst = app(\App\Services\MindestpunkteService::class);

        if ($this->istSport()) {
            $praxis = $this->sportPraxisnote();

            return $praxis === null
                ? null
                : $dienst->calcSportZpCombined($praxis, $this->written_points, $this->target_points);
        }

        return $dienst->calcCombined($this->written_points, $this->target_points);
    }

    /**
     * Amtliche Punktzahl des Fachs in vierfacher Wertung (0–60) nach
     * Anlage 12 zu § 52 Abs. 1 GSO: P = round((2s + m) / 3 × 4), Rundung
     * erst am Endergebnis. Die Formel steht im MindestpunkteService, an den
     * hier delegiert wird, damit es nur eine Fassung davon gibt.
     *
     * **Sport rechnet anders** (§ 52 Abs. 2 Nr. 2 GSO): dort tritt die
     * Praxisnote neben die Theorienote, P = round((Praxis + Theorie +
     * mündlich) × 4 / 3) — nicht 2 × Theorie. Ohne diese Unterscheidung wäre
     * die Theorienote doppelt gewichtet und die Praxisnote gar nicht.
     *
     * Gibt null zurück wenn written_points oder target_points fehlen — und
     * bei einer Sport-Zusatzprüfung auch dann, wenn die Praxisnote fehlt: die
     * amtliche Punktzahl ist dann schlicht nicht berechenbar.
     */
    public function getExamResultPoints(): ?int
    {
        if ($this->written_points === null || $this->target_points === null) {
            return null;
        }

        $dienst = app(\App\Services\MindestpunkteService::class);

        if ($this->istSport()) {
            $praxis = $this->sportPraxisnote();

            return $praxis === null
                ? null
                : $dienst->calcSportZpContribution($praxis, $this->written_points, $this->target_points);
        }

        return $dienst->calcZpContribution($this->written_points, $this->target_points);
    }

    /**
     * Verbesserung der Gesamtpunktzahl durch diese Zusatzprüfung (×4 gewertet).
     *
     * Ohne Zusatzprüfung trägt das Fach written_points × 4 bei, mit
     * Zusatzprüfung die amtliche Punktzahl nach Anlage 12. Die Differenz ist
     * die Verbesserung. Früher wurde hier die gerundete Anzeige-Note
     * (getCombinedPoints) mit 4 multipliziert — das weicht von der amtlichen
     * Rundung ab (Modul 2.1, Befund 08/2026).
     *
     * **Im Sport ist auch die Bezugsgröße eine andere.** Ohne Zusatzprüfung
     * trägt ein Sportfach (Theorie + Praxis) × 2 bei
     * (MindestpunkteService::scoreContribution()), nicht Theorie × 4 — die
     * Praxisnote steht gleichberechtigt neben der Theorienote. Beide Seiten
     * der Differenz müssen deshalb im Sport anders gebildet werden; wer nur
     * eine von beiden umstellt, macht den Fehler bloß kleiner.
     *
     * Zur Probe: die Verbesserung ist genau dann 0, wenn die mündliche Note
     * dem Schnitt (Theorie + Praxis) / 2 entspricht — dem Wert also, mit dem
     * das Fach ohne Zusatzprüfung in der einfachen Wertung stünde.
     *
     * Gibt null zurück wenn Berechnung nicht möglich — im Sport auch ohne
     * Praxisnote.
     */
    public function getPointsDelta(): ?int
    {
        $result = $this->getExamResultPoints();

        if ($result === null || $this->written_points === null) {
            return null;
        }

        if ($this->istSport()) {
            $praxis = $this->sportPraxisnote();

            return $praxis === null
                ? null
                : $result - ($this->written_points + $praxis) * 2;
        }

        return $result - $this->written_points * 4;
    }
}
