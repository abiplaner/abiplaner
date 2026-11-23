<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $school_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property string|null $description
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon $created_at
 * @property string|null $user_email Backup falls User gelöscht wird
 * @property string|null $model_type z.B. AppModelsStudent
 * @property int|null $model_id
 * @property string|null $old_values
 * @property string|null $new_values
 * @property string|null $updated_at
 * @property-read \App\Models\School|null $school
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereUserEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereUserId($value)
 * @mixin \Eloquent
 */
class ActivityLog extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    public $timestamps = false;
    protected $dates = ['created_at'];

    protected $fillable = [
        'school_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'ip_address',
        'user_agent',
    ];
    
    /**
     * Apply global scopes dynamically based on tenant context
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->school_id && auth()->user()) {
                $model->school_id = auth()->user()->school_id;
            }
            $model->created_at = now();
        });
    }
    
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return parent::newEloquentBuilder($query);
    }

    public function school(): BelongsTo
    {
        // Explizit Foreign Key und Owner Key angeben, da schools.school_number der Primary Key ist
        return $this->belongsTo(School::class, 'school_id', 'school_number');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
