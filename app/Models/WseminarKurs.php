<?php

namespace App\Models;

use App\Models\Concerns\HasKursAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $schuljahr
 * @property string $jahrgang
 * @property int $subject_id
 * @property string $titel
 * @property string|null $beschreibung
 * @property int|null $lehrer_id
 * @property int $max_teilnehmer
 * @property int $kurs_nummer
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Subject|null $subject
 * @property-read \App\Models\Teacher|null $lehrer
 * @property \Illuminate\Support\Collection|null $sorted_wahlen Dynamisch gesetzt (PDF-Export)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WseminarWahl> $drittwahlen
 * @property int|null $drittwahlen_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WseminarWahl> $erstwahlen
 * @property int|null $erstwahlen_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WseminarWahl> $wahlen
 * @property-read int|null $wahlen_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WseminarWahl> $zweitwahlen
 * @property int|null $zweitwahlen_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarKurs forJahrgang(string $jahrgang)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarKurs forSchuljahr(string $schuljahr)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarKurs newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarKurs newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WseminarKurs query()
 * @mixin \Eloquent
 */
class WseminarKurs extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasKursAttributes;

    protected $table = 'wseminar_kurse';

    protected $fillable = [
        'schuljahr',
        'jahrgang',
        'subject_id',
        'titel',
        'beschreibung',
        'lehrer_id',
        'max_teilnehmer',
        'kurs_nummer',
    ];

    protected $casts = [
        'max_teilnehmer' => 'integer',
        'kurs_nummer'    => 'integer',
    ];

    // Relationships (WseminarKurs-spezifisch)
    public function wahlen(): HasMany
    {
        return $this->hasMany(WseminarWahl::class, 'zuteilung_wseminar_kurs_id');
    }

    public function erstwahlen(): HasMany
    {
        return $this->hasMany(WseminarWahl::class, 'erstwahl_wseminar_id');
    }

    public function zweitwahlen(): HasMany
    {
        return $this->hasMany(WseminarWahl::class, 'zweitwahl_wseminar_id');
    }

    public function drittwahlen(): HasMany
    {
        return $this->hasMany(WseminarWahl::class, 'drittwahl_wseminar_id');
    }

    // Accessor
    // Helper methods
    public function getAnzahlTeilnehmer(): int
    {
        return $this->wahlen()->count();
    }

}
