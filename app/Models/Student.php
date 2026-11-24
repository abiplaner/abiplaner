<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property string $external_id
 * @property string|null $grade 11|12|13
 * @property bool $bestanden Abitur bestanden (Default true; false blendet in Abiturfeier aus)
 * @property string $firstname
 * @property string $lastname
 * @property string|null $class_name
 * @property int|null $disability_compensation_percent
 * @property string|null $disability_compensation_notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $full_name
 * @property \Illuminate\Support\Collection|null $all_exams Dynamisch gesetzter Export-Hilfswert
 * @property-read \App\Models\ExamPeriod|null $examPeriod
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentExam> $exams
 * @property-read int|null $exams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentBlockedDay> $blockedDays
 * @property-read int|null $blocked_days_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student derStufe(string $stufe)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student query()
 * @mixin \Eloquent
 */
class Student extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    // Der Nachteilsausgleich rechnet für Schüler und externe Bewerber gleich —
    // die Formel steht deshalb nur einmal, im Trait.
    use \App\Models\Concerns\HatNachteilsausgleich;


    protected $fillable = [
        'exam_period_id',
        'external_id',
        'grade',
        'bestanden',
        'firstname',
        'lastname',
        'class_name',
        'disability_compensation_percent',
        'disability_compensation_notes',
    ];
    
    protected $casts = [
        'disability_compensation_percent' => 'integer',
        'bestanden' => 'boolean',
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

    public function exams(): HasMany
    {
        return $this->hasMany(StudentExam::class);
    }
    
    public function blockedDays(): HasMany
    {
        return $this->hasMany(StudentBlockedDay::class)->orderBy('date');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    public function absenceMeasures(): HasMany
    {
        return $this->hasMany(AbsenceMeasure::class)->orderByDesc('measure_date');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->lastname}, {$this->firstname}";
    }
    
    
    /**
     * Scope: Schüler einer Jahrgangsstufe ('12' oder '13').
     *
     * Zur Q13 zählen auch Schüler ohne Stufe — so legen ältere Datensätze
     * und die Tests sie an, wie in StudentExam::scopeImPruefungszeitraum().
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDerStufe($query, string $stufe)
    {
        return $stufe === '13'
            ? $query->where(fn ($q) => $q->where('grade', '13')->orWhereNull('grade'))
            : $query->where('grade', $stufe);
    }
}
