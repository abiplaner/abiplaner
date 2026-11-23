<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string|null $external_id
 * @property string $shortcode
 * @property string $firstname
 * @property string $lastname
 * @property bool $can_supervise
 * @property string|null $employment_type
 * @property float|null $hours
 * @property bool $is_available_for_supervision
 * @property int $supervision_preference_weight
 * @property int|null $max_supervisions_per_day
 * @property string|null $title
 * @property string|null $gender
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $full_name
 * @property-read string $name Alias für full_name (Kompatibilität)
 * @property \Illuminate\Database\Eloquent\Collection|null $examsAsExaminer Dynamisch gesetzt (PDF-Export)
 * @property \Illuminate\Database\Eloquent\Collection|null $examsAsAssessor Dynamisch gesetzt (PDF-Export)
 * @property int|null $totalExams Dynamisch gesetzt (PDF-Export)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentExam> $assessorAssignments
 * @property-read int|null $assessor_assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $courses
 * @property-read int|null $courses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentExam> $examinerAssignments
 * @property-read int|null $examiner_assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subject> $subjects
 * @property-read int|null $subjects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Supervision> $supervisions
 * @property-read int|null $supervisions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TeacherUnavailability> $unavailabilities
 * @property-read int|null $unavailabilities_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher query()
 * @mixin \Eloquent
 */
class Teacher extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'external_id',
        'shortcode',
        'firstname',
        'lastname',
        'title',
        'gender',
        'contact_phone',
        'contact_email',
        'contact_bycs',
        'hours',
        'employment_type',
        'is_active',
        'is_available_for_supervision',
        'supervision_preference_weight',
        'max_supervisions_per_day',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_available_for_supervision' => 'boolean',
        'supervision_preference_weight' => 'integer',
        'max_supervisions_per_day' => 'integer',
        'contact_bycs' => 'boolean',
    ];

    /**
     * Nur aktive Lehrkräfte (Modul 1.1, Phase 2): für Prüfer-/Beisitzer-/
     * Aufsichts-Auswahlen und die Optimierung. Historische Zuweisungen
     * laufen über Relationen und bleiben von dem Filter unberührt.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Teacher>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Teacher>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships
    // Keine school()-Beziehung: Dieses Modell steht in der Datenbank des
    // Mandanten, School steht zentral — eine Eloquent-Beziehung ueberschreitet
    // diese Grenze nicht. Der Fremdschluessel waere ausserdem `school_id`
    // gewesen, eine Spalte, die es hier nicht gibt. Aufgerufen wurde sie nie;
    // die eigene Schule liefert `tenant()` bzw. `auth()->user()->school_id`.

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function unavailabilities(): HasMany
    {
        return $this->hasMany(TeacherUnavailability::class);
    }

    public function examinerAssignments(): HasMany
    {
        return $this->hasMany(StudentExam::class, 'examiner_id');
    }

    public function assessorAssignments(): HasMany
    {
        return $this->hasMany(StudentExam::class, 'assessor_id');
    }

    public function supervisions(): HasMany
    {
        return $this->hasMany(Supervision::class);
    }

    public function subjects(): BelongsToMany
    {
return $this->belongsToMany(Subject::class, 'teacher_subject');
    }

    // Helper Methods
    public function getFullNameAttribute(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    /**
     * Gibt die geschlechtsspezifische Amtsbezeichnung zurück.
     * Bei gender === 'w' wird die weibliche Form aus der Map verwendet.
     *
     * @param bool $withSpace Leerzeichen am Ende anfügen (für direkte Namensverkettung)
     */
    public function genderedTitle(bool $withSpace = true): string
    {
        if (!$this->title) {
            return '';
        }

        if ($this->gender === 'w') {
            $map = [
                'Oberstudiendirektor'             => 'Oberstudiendirektorin',
                'Studiendirektor'                 => 'Studiendirektorin',
                'Oberstudienrat'                  => 'Oberstudienrätin',
                'Studienrat'                      => 'Studienrätin',
                'Studienprofessor'                => 'Studienprofessorin',
                'Studienreferendar'               => 'Studienreferendarin',
                'Fachlehrer'                      => 'Fachlehrerin',
                'Beschäftigter'                   => 'Beschäftigte',
                'Akademischer Direktor'           => 'Akademische Direktorin',
                'Leitender Akademischer Direktor' => 'Leitende Akademische Direktorin',
            ];
            $result = $map[$this->title] ?? $this->title;
        } else {
            $result = $this->title;
        }

        return $withSpace ? $result . ' ' : $result;
    }

    /**
     * Abgekürzte, geschlechtsspezifische Amtsbezeichnung — "StDin", "OStR".
     *
     * In Niederschriften des Abiturprüfungsausschusses ist die Kurzform üblich
     * ("StDin Vogler"), während Prüfungsformulare die ausgeschriebene Form
     * verwenden. Für eine Amtsbezeichnung ohne bekannte Abkürzung wird die
     * ausgeschriebene Form aus genderedTitle() zurückgegeben — lieber lang und
     * richtig als frei erfunden.
     *
     * Akademische Grade (Dr.) führt das Datenmodell nicht; sie sind im Word-
     * Dokument von Hand zu ergänzen.
     *
     * @param bool $withSpace Leerzeichen am Ende anfügen (für direkte Namensverkettung)
     */
    public function genderedTitleShort(bool $withSpace = true): string
    {
        if (!$this->title) {
            return '';
        }

        $map = [
            'Oberstudiendirektor'             => ['OStD',   'OStDin'],
            'Studiendirektor'                 => ['StD',    'StDin'],
            'Oberstudienrat'                  => ['OStR',   'OStRin'],
            'Studienrat'                      => ['StR',    'StRin'],
            'Studienreferendar'               => ['StRef',  'StRefin'],
            'Fachlehrer'                      => ['FL',     'FLin'],
            'Akademischer Direktor'           => ['AkDir',  'AkDirin'],
            'Leitender Akademischer Direktor' => ['LAkDir', 'LAkDirin'],
        ];

        if (!isset($map[$this->title])) {
            return $this->genderedTitle($withSpace);
        }

        $result = $this->gender === 'w' ? $map[$this->title][1] : $map[$this->title][0];

        return $withSpace ? $result . ' ' : $result;
    }

    public function isAvailable(Carbon $date, ?Carbon $time = null): bool
    {
        $query = $this->unavailabilities()->where('date', $date->toDateString());
        
        if ($time) {
            $query->where(function ($q) use ($time) {
                $q->where('all_day', true)
                  ->orWhere(function ($q2) use ($time) {
                      $q2->where('time_from', '<=', $time->toTimeString())
                         ->where('time_to', '>=', $time->toTimeString());
                  });
            });
        } else {
            $query->where('all_day', true);
        }
        
        return !$query->exists();
    }
    
}
