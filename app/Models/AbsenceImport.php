<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $original_filename
 * @property int $row_count
 * @property int $matched_count
 * @property int $created_count
 * @property int $updated_count
 * @property int $skipped_count
 * @property int $filtered_count
 * @property \Carbon\Carbon|null $period_from
 * @property \Carbon\Carbon|null $period_to
 * @property int|null $imported_by_user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Absence> $absences
 * @mixin \Eloquent
 */
class AbsenceImport extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'original_filename',
        'row_count',
        'matched_count',
        'created_count',
        'updated_count',
        'skipped_count',
        'filtered_count',
        'period_from',
        'period_to',
        'imported_by_user_id',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
    ];

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }
}
