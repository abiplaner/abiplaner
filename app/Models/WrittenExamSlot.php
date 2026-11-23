<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * Jahresunabhängiges Prüfungszeit-Template (zentral, Ministeriums-Vorgabe).
 *
 * Startzeit, Endzeit und Fachzuordnungen ändern sich nicht über die Jahre –
 * nur das konkrete Datum variiert (→ WrittenExamYearDate).
 *
 * @property int $id
 * @property string $name
 * @property string $requirement_level  grundlegend|erhoeht
 * @property string $start_time
 * @property string $end_time
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subject> $subjects
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WrittenExamYearDate> $yearDates
 * @mixin \Eloquent
 */
class WrittenExamSlot extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'name',
        'requirement_level',
        'start_time',
        'end_time',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'written_exam_slot_subjects',
            'written_exam_slot_id',
            'subject_id'
        )->withTimestamps();
    }

    public function yearDates(): HasMany
    {
        return $this->hasMany(WrittenExamYearDate::class, 'written_exam_slot_id');
    }

    public function getDurationMinutes(): int
    {
        return (int) Carbon::parse($this->start_time)->diffInMinutes(Carbon::parse($this->end_time));
    }

    public function getEndTimeWithNta(int $compensationPercent): Carbon
    {
        $extra = (int) round($this->getDurationMinutes() * $compensationPercent / 100);
        return Carbon::parse($this->end_time)->addMinutes($extra);
    }

    /**
     * Alias für getEndTimeWithNta() – Rückwärtskompatibilität.
     */
    public function getEndTimeWithCompensation(int $compensationPercent): Carbon
    {
        return $this->getEndTimeWithNta($compensationPercent);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<static> $query */
    public function scopeGrundlegend($query): void
    {
        $query->where('requirement_level', 'grundlegend');
    }

    /** @param \Illuminate\Database\Eloquent\Builder<static> $query */
    public function scopeErhoeht($query): void
    {
        $query->where('requirement_level', 'erhoeht');
    }
}
