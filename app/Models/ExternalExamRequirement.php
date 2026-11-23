<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $valid_from Jahr ab dem diese Regel gilt
 * @property int|null $valid_to Jahr bis zu dem diese Regel gilt
 * @property string $requirement_type
 * @property string $requirement_key
 * @property array|null $requirement_value
 * @property string|null $description
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement validForYear(int $year)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement whereRequirementKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement whereRequirementType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement whereRequirementValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalExamRequirement whereValidTo($value)
 * @mixin \Eloquent
 */
class ExternalExamRequirement extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    // This model uses the central database connection (not tenant-specific)

    protected $fillable = [
        'valid_from',
        'valid_to',
        'requirement_type',
        'requirement_key',
        'requirement_value',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requirement_value' => 'array', // JSON stored as array
    ];

    /**
     * Scope: Only active requirements
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Valid for a specific year
     */
    public function scopeValidForYear($query, int $year)
    {
        return $query->where('valid_from', '<=', $year)
                    ->where(function($q) use ($year) {
                        $q->whereNull('valid_to')
                          ->orWhere('valid_to', '>=', $year);
                    });
    }

    /**
     * Scope: Filter by requirement type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('requirement_type', $type);
    }

}
