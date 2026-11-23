<?php

namespace App\Models;

use App\Models\Concerns\HasKursAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int $id
 * @property string $schuljahr
 * @property string $jahrgang
 * @property int $subject_id
 * @property int $kurs_nummer
 * @property bool $ist_extern
 * @property int|null $lehrer_id
 * @property string|null $notizen
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read int $schueler_anzahl
 * @property-read \App\Models\Subject|null $subject
 * @property-read \App\Models\Teacher|null $lehrer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LkWahl> $wahlen
 * @property-read int|null $wahlen_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkKurs extern()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkKurs forJahrgang(string $jahrgang)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkKurs forSchuljahr(string $schuljahr)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkKurs newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkKurs newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LkKurs query()
 * @mixin \Eloquent
 */
class LkKurs extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasKursAttributes;

    protected $table = 'lk_kurse';

    protected $fillable = [
        'schuljahr',
        'jahrgang',
        'subject_id',
        'kurs_nummer',
        'ist_extern',
        'lehrer_id',
        'notizen',
    ];

    protected $casts = [
        'ist_extern'  => 'boolean',
        'kurs_nummer' => 'integer',
    ];

    // Relationships (LkKurs-spezifisch)
    public function wahlen(): HasMany
    {
        return $this->hasMany(LkWahl::class, 'zuteilung_lk_kurs_id');
    }

    // Scopes (LkKurs-spezifisch)
    public function scopeExtern($query)
    {
        return $query->where('ist_extern', true);
    }

    // Accessor für Schülerzahl
    protected function schuelerAnzahl(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->wahlen()->count(),
        );
    }
}
