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
 * @property int|null $erstwahl_wseminar_id
 * @property int|null $zweitwahl_wseminar_id
 * @property int|null $drittwahl_wseminar_id
 * @property int|null $zuteilung_wseminar_kurs_id
 * @property string|null $prioritaet_erhalten  erstwahl|zweitwahl|drittwahl|keine
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Student|null $schueler
 * @property-read \App\Models\WseminarKurs|null $erstwahl
 * @property-read \App\Models\WseminarKurs|null $zweitwahl
 * @property-read \App\Models\WseminarKurs|null $drittwahl
 * @property-read \App\Models\WseminarKurs|null $zugeteilterKurs
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarWahl forJahrgang(string $jahrgang)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarWahl forSchuljahr(string $schuljahr)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarWahl newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarWahl newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarWahl nichtZugeteilt()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarWahl query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarWahl zugeteilt()
 * @mixin \Eloquent
 */
class WseminarWahl extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasWahlAttributes;

    protected $table = 'wseminar_wahlen';

    protected $fillable = [
        'schuljahr',
        'jahrgang',
        'schueler_id',
        'erstwahl_wseminar_id',
        'zweitwahl_wseminar_id',
        'drittwahl_wseminar_id',
        'zuteilung_wseminar_kurs_id',
        'prioritaet_erhalten',
    ];

    protected $casts = [
        'prioritaet_erhalten' => 'string',
    ];

    // Relationships (WseminarWahl-spezifisch)
    public function erstwahl(): BelongsTo
    {
        return $this->belongsTo(WseminarKurs::class, 'erstwahl_wseminar_id');
    }

    public function zweitwahl(): BelongsTo
    {
        return $this->belongsTo(WseminarKurs::class, 'zweitwahl_wseminar_id');
    }

    public function drittwahl(): BelongsTo
    {
        return $this->belongsTo(WseminarKurs::class, 'drittwahl_wseminar_id');
    }

    public function zugeteilterKurs(): BelongsTo
    {
        return $this->belongsTo(WseminarKurs::class, 'zuteilung_wseminar_kurs_id');
    }

    // Scopes (WseminarWahl-spezifisch)
    public function scopeZugeteilt($query)
    {
        return $query->whereNotNull('zuteilung_wseminar_kurs_id');
    }

    public function scopeNichtZugeteilt($query)
    {
        return $query->whereNull('zuteilung_wseminar_kurs_id');
    }
}
