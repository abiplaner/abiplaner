<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Zeitslot-Vorlagen für Aufsichten bei schriftlichen Abiturprüfungen.
 *
 * Nicht datumsgebunden – definiert die Schichtzeiten (z.B. 8:00–10:15),
 * die für alle Prüfungstage gleich gelten.
 *
 * @property int         $id
 * @property int         $slot_number
 * @property int|null    $written_exam_supervision_grid_id
 * @property string      $start_time
 * @property string      $end_time
 * @property string|null $label
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $time_range
 * @property-read \App\Models\WrittenExamSupervisionGrid|null $grid
 * @mixin \Eloquent
 */
class WrittenExamSupervisionTimeSlot extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $fillable = [
        'slot_number',
        'written_exam_supervision_grid_id',
        'start_time',
        'end_time',
        'label',
        'notes',
    ];

    public function grid(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WrittenExamSupervisionGrid::class, 'written_exam_supervision_grid_id');
    }

    public function getTimeRangeAttribute(): string
    {
        return substr($this->start_time, 0, 5) . ' – ' . substr($this->end_time, 0, 5);
    }
}
