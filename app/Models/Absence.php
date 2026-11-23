<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einzelne Absenz aus dem Infoportal-CSV-Import (Roadmap 8.1).
 *
 * @property int $id
 * @property int $student_id
 * @property int $absence_import_id
 * @property string $source_key
 * @property \Carbon\Carbon $date_from
 * @property \Carbon\Carbon $date_to
 * @property int $days
 * @property int $hours
 * @property int $minutes
 * @property string $art_raw
 * @property string $category krank|verspaetet|fehlt|befreit|tageweise_befreit|sonstige
 * @property int|null $hour_from
 * @property int|null $hour_to
 * @property int|null $minutes_late
 * @property string $excuse_status schriftlich|muendlich|nein|attest|attestpflicht|offen
 * @property string|null $reason Kann Gesundheitsdaten enthalten (Art. 9 DSGVO)
 * @property bool $is_school_absence
 * @property \Carbon\Carbon|null $source_recorded_at
 * @property string|null $source_recorded_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Student $student
 * @property-read \App\Models\AbsenceImport $absenceImport
 * @mixin \Eloquent
 */
class Absence extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'student_id',
        'absence_import_id',
        'source_key',
        'date_from',
        'date_to',
        'days',
        'hours',
        'minutes',
        'art_raw',
        'category',
        'hour_from',
        'hour_to',
        'minutes_late',
        'excuse_status',
        'reason',
        'is_school_absence',
        'source_recorded_at',
        'source_recorded_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'is_school_absence' => 'boolean',
        'source_recorded_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function absenceImport(): BelongsTo
    {
        return $this->belongsTo(AbsenceImport::class);
    }

}
