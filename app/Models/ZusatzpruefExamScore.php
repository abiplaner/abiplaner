<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Speichert die tatsächliche Prüfungspunktzahl (0–15) für ein Abitur-Prüfungsfach
 * eines Schülers mit Zusatzprüfung. Wird im Mindestpunkte-Kalkulator verwendet.
 *
 * Nur für feste Fächer (ohne Zusatzprüfung) nötig; bei ZP-Fächern steht der
 * schriftliche Wert bereits in zusatzpruefungen.written_points.
 *
 * @property int $id
 * @property int $student_id
 * @property int $student_exam_id
 * @property int $exam_period_id
 * @property int|null $score  0–15, null = noch nicht eingetragen
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Student $student
 * @property-read \App\Models\StudentExam $studentExam
 * @mixin \Eloquent
 */
class ZusatzpruefExamScore extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'zusatzpruef_exam_scores';

    protected $fillable = [
        'student_id',
        'student_exam_id',
        'exam_period_id',
        'score',
        'practical_grade',
    ];

    protected $casts = [
        'score'           => 'integer',
        'practical_grade' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function studentExam(): BelongsTo
    {
        return $this->belongsTo(StudentExam::class);
    }
}
