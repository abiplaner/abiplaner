<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupervisionTemplateAssignment> $assignments
 * @property-read int|null $assignments_count
 * @property-read \App\Models\ExamPeriod|null $examPeriod
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupervisionTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupervisionTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupervisionTemplate query()
 * @mixin \Eloquent
 */
class SupervisionTemplate extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $fillable = [
        'exam_period_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SupervisionTemplateAssignment::class, 'template_id');
    }
}
