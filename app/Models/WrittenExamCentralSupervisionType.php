<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Terminspezifische zusätzliche Aufsichtsposition.
 *
 * Gangaufsicht (corridor) und Reserve (reserve) sind fest – kein Eintrag nötig.
 * Diese Tabelle speichert NUR die optionalen Extras eines Termins,
 * z.B. "2. Gangaufsicht", "Eingang Süd" – nur bei großen Prüfungen.
 *
 * @property int $id
 * @property int $written_exam_year_date_id
 * @property string $name
 * @property int $sort_order
 * @property string $color  Tailwind-Farbname (orange, teal, pink, yellow, blue, purple)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class WrittenExamCentralSupervisionType extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $fillable = ['written_exam_year_date_id', 'name', 'sort_order', 'color'];

    public function writtenExamYearDate(): BelongsTo
    {
        return $this->belongsTo(WrittenExamYearDate::class, 'written_exam_year_date_id');
    }

    public function supervisions(): HasMany
    {
        return $this->hasMany(Supervision::class, 'central_supervision_type_id');
    }
}
