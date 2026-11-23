<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string|null $category
 * @property int|null $teacher_id
 * @property bool $active
 * @property string|null $no_winner_note   — Text, wenn in diesem Jahr kein Preisträger
 * @property bool $certificate_in_envelope — Urkunde wird ins Kuvert gelegt
 * @property bool $has_physical_prize      — Sachpreis vorhanden
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AwardType extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    // Tenant-DB – Preisarten sind schulspezifisch (Umzug aus zentraler DB am 20.06.2026).
    protected $fillable = [
        'name', 'category', 'teacher_id', 'active', 'no_winner_note',
        'certificate_in_envelope', 'has_physical_prize', 'sort_order',
    ];

    protected $casts = [
        'active'                  => 'boolean',
        'certificate_in_envelope' => 'boolean',
        'has_physical_prize'      => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}

