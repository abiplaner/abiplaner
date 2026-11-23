<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $zusatzpruefung_id
 * @property int $zusatzpruef_day_id
 * @property int $zusatzpruef_day_room_id
 * @property string $start_time  'HH:MM'
 * @property int $exam_duration_minutes
 * @property int $prep_duration_minutes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Zusatzpruefung $zusatzpruefung
 * @property-read \App\Models\ZusatzpruefDay $day
 * @property-read \App\Models\ZusatzpruefDayRoom $dayRoom
 * @mixin \Eloquent
 */
class ZusatzpruefAssignment extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'zusatzpruef_assignments';

    protected $fillable = [
        'zusatzpruefung_id',
        'zusatzpruef_day_id',
        'zusatzpruef_day_room_id',
        'start_time',
        'exam_duration_minutes',
        'prep_duration_minutes',
    ];

    public function zusatzpruefung(): BelongsTo
    {
        return $this->belongsTo(Zusatzpruefung::class);
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(ZusatzpruefDay::class, 'zusatzpruef_day_id');
    }

    public function dayRoom(): BelongsTo
    {
        return $this->belongsTo(ZusatzpruefDayRoom::class, 'zusatzpruef_day_room_id');
    }

    /** Gesamtdauer in Minuten (Vorbereitung + Prüfung) */
    public function totalDurationMinutes(): int
    {
        return $this->prep_duration_minutes + $this->exam_duration_minutes;
    }

    /** Endzeit als 'HH:MM'-String */
    public function endTime(): string
    {
        [$h, $m] = explode(':', $this->start_time);
        $totalMin = (int)$h * 60 + (int)$m + $this->totalDurationMinutes();
        return sprintf('%02d:%02d', intdiv($totalMin, 60), $totalMin % 60);
    }
}
