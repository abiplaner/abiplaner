<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $zusatzpruef_day_id
 * @property string $start_time  'HH:MM' — Beginn der Vorbereitungszeit
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\ZusatzpruefDay $day
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ZusatzpruefAufsicht> $aufsichten
 * @mixin \Eloquent
 */
class ZusatzpruefDayTimeslot extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'zusatzpruef_day_timeslots';

    protected $fillable = [
        'zusatzpruef_day_id',
        'start_time',
    ];

    public function day(): BelongsTo
    {
        return $this->belongsTo(ZusatzpruefDay::class, 'zusatzpruef_day_id');
    }

    public function aufsichten(): HasMany
    {
        return $this->hasMany(ZusatzpruefAufsicht::class, 'zusatzpruef_day_timeslot_id');
    }
}
