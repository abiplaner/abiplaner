<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exam_period_id
 * @property int $student_id
 * @property int $award_type_id
 * @property int|null $award_description_id
 * @property string|null $notes
 * @property int|null $rank              — 1 = 1. Preisträger, 2 = 2. Preisträger, null = kein Rang
 * @property bool $is_candidate          — true = Kandidat/Vorschlag, noch nicht definitiv
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Student|null $student
 * @property-read \App\Models\AwardType|null $awardType
 * @property-read \App\Models\AwardDescription|null $awardDescription
 */
class Award extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'exam_period_id',
        'student_id',
        'award_type_id',
        'award_description_id',
        'notes',
        'rank',
        'is_candidate',
        'sort_order',
    ];

    protected $casts = [
        'is_candidate' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function awardType(): BelongsTo
    {
        return $this->belongsTo(AwardType::class);
    }

    public function awardDescription(): BelongsTo
    {
        return $this->belongsTo(AwardDescription::class);
    }

    public function getAwardTypeNameAttribute(): string
    {
        $type = $this->awardType;
        return $type !== null ? $type->name : '–';
    }

    public function getDescriptionTextAttribute(): string
    {
        $desc = $this->awardDescription;
        return $desc !== null ? $desc->description : '';
    }

}
