<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property \Carbon\Carbon $date
 * @property string|null $label
 * @property string $start_time  'HH:MM'
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ZusatzpruefDayRoom> $dayRooms
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ZusatzpruefDayTimeslot> $timeslots
 * @mixin \Eloquent
 */
class ZusatzpruefDay extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'zusatzpruef_days';

    protected $fillable = [
        'exam_period_id',
        'date',
        'label',
        'start_time',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function dayRooms(): HasMany
    {
        return $this->hasMany(ZusatzpruefDayRoom::class, 'zusatzpruef_day_id')->orderBy('sort_order');
    }

    public function timeslots(): HasMany
    {
        return $this->hasMany(ZusatzpruefDayTimeslot::class, 'zusatzpruef_day_id')->orderBy('start_time');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ZusatzpruefAssignment::class, 'zusatzpruef_day_id');
    }
}
