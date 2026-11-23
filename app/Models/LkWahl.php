<?php

namespace App\Models;

use App\Models\Concerns\HasWahlAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $schuljahr
 * @property string $jahrgang
 * @property int $schueler_id
 * @property int $erstwahl_subject_id
 * @property bool $is_erstwahl_extern
 * @property int $zweitwahl_subject_id
 * @property bool $is_zweitwahl_extern
 * @property int|null $zuteilung_lk_kurs_id
 * @property string|null $prioritaet_erhalten  erstwahl|zweitwahl|keine
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Student|null $schueler
 * @property-read \App\Models\Subject|null $erstwahl
 * @property-read \App\Models\Subject|null $zweitwahl
 * @property-read \App\Models\LkKurs|null $zugeteilterKurs
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkWahl forJahrgang(string $jahrgang)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkWahl forSchuljahr(string $schuljahr)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkWahl newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkWahl newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkWahl nichtZugeteilt()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkWahl query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkWahl zugeteilt()
 * @mixin \Eloquent
 */
class LkWahl extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasWahlAttributes;

    protected $table = 'lk_wahlen';

    protected $fillable = [
        'schuljahr',
        'jahrgang',
        'schueler_id',
        'erstwahl_subject_id',
        'zweitwahl_subject_id',
        'zuteilung_lk_kurs_id',
        'prioritaet_erhalten',
        'is_erstwahl_extern',
        'is_zweitwahl_extern',
    ];

    protected $casts = [
        'is_erstwahl_extern'  => 'boolean',
        'is_zweitwahl_extern' => 'boolean',
        'prioritaet_erhalten' => 'string',
    ];

    // Relationships (LkWahl-spezifisch)
    public function erstwahl(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'erstwahl_subject_id');
    }

    public function zweitwahl(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'zweitwahl_subject_id');
    }

    public function zugeteilterKurs(): BelongsTo
    {
        return $this->belongsTo(LkKurs::class, 'zuteilung_lk_kurs_id');
    }

    // Scopes (LkWahl-spezifisch)
    public function scopeZugeteilt($query)
    {
        return $query->whereNotNull('zuteilung_lk_kurs_id');
    }

    public function scopeNichtZugeteilt($query)
    {
        return $query->whereNull('zuteilung_lk_kurs_id');
    }
}
