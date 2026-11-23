<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $zusatzpruefung_id
 * @property int|null $teacher_id
 * @property string $role  erstpruefer|zweitkorrektor|gast
 * @property string|null $name_override  Für Gäste ohne Teacher-Eintrag
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Zusatzpruefung $zusatzpruefung
 * @property-read \App\Models\Teacher|null $teacher
 * @mixin \Eloquent
 */
class ZusatzpruefPruefer extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'zusatzpruef_pruefer';

    protected $fillable = [
        'zusatzpruefung_id',
        'teacher_id',
        'role',
        'name_override',
        'sort_order',
    ];

    public function zusatzpruefung(): BelongsTo
    {
        return $this->belongsTo(Zusatzpruefung::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'erstpruefer'    => '1. Prüfer',
            'zweitkorrektor' => '2. Korrektor',
            'gast'           => 'Gast',
            default          => '–',
        };
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->teacher) {
            return $this->teacher->shortcode . ' ' . $this->teacher->lastname;
        }
        return $this->name_override ?? '–';
    }
}
