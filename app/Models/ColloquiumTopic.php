<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $course_id
 * @property string $semester 12/1|12/2
 * @property int $slot
 * @property string $title
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Course $course
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ColloquiumTopic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ColloquiumTopic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ColloquiumTopic query()
 * @mixin \Eloquent
 */
class ColloquiumTopic extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'course_id',
        'semester',
        'slot',
        'title',
    ];

    protected $casts = [
        'slot' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
