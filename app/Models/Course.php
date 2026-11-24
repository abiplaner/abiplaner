<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property int $subject_id
 * @property string $course_code
 * @property string|null $name Optionaler Anzeigename (dynamisch)
 * @property int $teacher_id
 * @property int|null $second_corrector_id
 * @property string|null $level grundlegend|erhoeht
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Subject|null $subject
 * @property-read \App\Models\Teacher $teacher
 * @property-read \App\Models\Teacher|null $secondCorrector
 * @property-read \App\Models\ExamPeriod|null $examPeriod
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentExam> $studentExams
 * @property-read int|null $student_exams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ColloquiumTopic> $colloquiumTopics
 * @property-read int|null $colloquium_topics_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Student> $students
 * @property-read int|null $students_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WrittenExamAssignment> $writtenExamAssignments
 * @property-read int|null $written_exam_assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Student> $writtenStudents
 * @property-read int|null $written_students_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course derStufe(string $stufe)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course query()
 * @mixin \Eloquent
 */
class Course extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'exam_period_id',
        'subject_id',
        'course_code',
        'teacher_id',
        'second_corrector_id',
        'level',
    ];

    // Keine school()-Beziehung: Dieses Modell steht in der Datenbank des
    // Mandanten, School steht zentral — eine Eloquent-Beziehung ueberschreitet
    // diese Grenze nicht. Der Fremdschluessel waere ausserdem `school_id`
    // gewesen, eine Spalte, die es hier nicht gibt. Aufgerufen wurde sie nie;
    // die eigene Schule liefert `tenant()` bzw. `auth()->user()->school_id`.

    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function secondCorrector(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'second_corrector_id');
    }

    public function studentExams(): HasMany
    {
        return $this->hasMany(StudentExam::class);
    }

    public function colloquiumTopics(): HasMany
    {
        return $this->hasMany(ColloquiumTopic::class);
    }

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(
            Student::class,
            StudentExam::class,
            'course_id',      // Foreign key on student_exams table
            'id',             // Foreign key on students table
            'id',             // Local key on courses table
            'student_id'      // Local key on student_exams table
        );
    }

    /**
     * Schüler der Q13 mit schriftlicher Abiturprüfung in diesem Kurs.
     *
     * Grundlage für Raumzuordnung, Sitzpläne und Raumlisten. Die Q12 hat im
     * selben Prüfungszeitraum ebenfalls schriftliche Prüfungen (ihre
     * künftigen Abiturfächer) — in jahrgangsübergreifenden Kursen säßen sie
     * sonst mit im Prüfungsraum.
     */
    public function writtenStudents(): HasManyThrough
    {
        return $this->hasManyThrough(
            Student::class,
            StudentExam::class,
            'course_id',
            'id',
            'id',
            'student_id'
        )->where('student_exams.exam_type', 'written')
            ->where(fn ($q) => $q->where('students.grade', '13')->orWhereNull('students.grade'));
    }

    /**
     * Scope: Kurse einer Jahrgangsstufe ('13' oder '12').
     *
     * Ein Kurs trägt keine Stufe. Seit Q12 und Q13 im selben Prüfungszeitraum
     * stehen (Abitur 2027: 59 Kurse „1…“, 57 „2…“), entscheidet die Stufe
     * seiner Schüler — nicht das Präfix, das ist die Kursbenennung der
     * Schule.
     *
     * Ein Kurs gehört zu einer Stufe, wenn er mindestens einen Schüler dieser
     * Stufe hat. Jahrgangsübergreifende Kurse („3…“, z. B. 3l1 mit Q12 und
     * Q13) gehören damit zu beiden — wer ihre Prüfungen lädt, muss sie mit
     * StudentExam::scopeDerStufe() auf die Stufe beschränken.
     *
     * Zur Q13 zählt außerdem ein Kurs ganz ohne Prüfungen, so wie Schüler
     * ohne Stufe zur Q13 zählen (Student::scopeDerStufe()).
     *
     * @param \Illuminate\Database\Eloquent\Builder<Course> $query
     * @return \Illuminate\Database\Eloquent\Builder<Course>
     */
    public function scopeDerStufe($query, string $stufe)
    {
        $hatSchuelerDerStufe = fn ($q) => $q->whereHas(
            'studentExams',
            fn ($e) => $e->derStufe($stufe)
        );

        if ($stufe === '13') {
            return $query->where(fn ($q) => $hatSchuelerDerStufe($q)->orWhereDoesntHave('studentExams'));
        }

        return $hatSchuelerDerStufe($query);
    }

    /**
     * Relationship: Course has many WrittenExamAssignments
     */
    public function writtenExamAssignments(): HasMany
    {
        return $this->hasMany(WrittenExamAssignment::class);
    }

    /**
     * Get the German display name for the level
     */
    public function getLevelDisplayName(): string
    {
        return match($this->level) {
            'erhoeht' => 'eA (erhöht)',
            'grundlegend' => 'gA (grundlegend)',
            default => 'Keine Angabe'
        };
    }
}
