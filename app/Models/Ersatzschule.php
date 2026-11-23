<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine staatlich genehmigte Ersatzschule nach § 64 GSO.
 *
 * Eigene Tabelle statt Freitext am Bewerber (Entscheidung 37 vom 27.08.2026):
 * § 64 Abs. 1 denkt durchgehend in Gruppen — die Anträge werden gesammelt
 * eingereicht, die Anzeige bis zum 15. Dezember kommt von der Schule, und die
 * Fachausschüsse werden nach Möglichkeit für diese Gruppe gebildet. All das
 * braucht später einen Anker; heute hängt daran nur der Bewerber.
 *
 * @property int $id
 * @property string $name
 * @property string|null $ort
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExternalApplicant> $externalApplicants
 * @property-read int|null $external_applicants_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ersatzschule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ersatzschule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ersatzschule query()
 * @mixin \Eloquent
 */
class Ersatzschule extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;

    /** Laravel bildete sonst "ersatzschules". */
    protected $table = 'ersatzschulen';

    protected $fillable = [
        'name',
        'ort',
        'notes',
    ];

    /**
     * Die Bewerber, die an dieser Ersatzschule unterrichtet werden.
     *
     * Fällt die Schule, bleiben sie stehen — der Fremdschlüssel ist
     * nullOnDelete. Ein Bewerber ohne Ersatzschule ist ein anderer Bewerber
     * nach § 60, kein kaputter Datensatz.
     */
    public function externalApplicants(): HasMany
    {
        return $this->hasMany(ExternalApplicant::class);
    }
}
