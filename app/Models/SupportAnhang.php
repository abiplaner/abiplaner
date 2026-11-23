<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Bildschirmfoto an einer Support-Meldung (Modul 6.4, Entscheidung 48).
 *
 * Zentrale Tabelle wie die Fallakte selbst; die Datei liegt auf der Platte
 * `local` (storage/app/private) und ist **nicht** ueber das Web erreichbar.
 *
 * @property int $id
 * @property int $support_fall_id
 * @property string $dateiname
 * @property string $pfad
 * @property string $mime
 * @property int $groesse
 * @property int|null $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\SupportFall $fall
 * @mixin \Eloquent
 */
class SupportAnhang extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;


    protected $table = 'support_anhaenge';

    protected $fillable = [
        'support_fall_id', 'dateiname', 'pfad', 'mime', 'groesse', 'user_id',
    ];

    protected $casts = [
        'groesse' => 'integer',
    ];

    /**
     * @return BelongsTo<SupportFall, $this>
     */
    public function fall(): BelongsTo
    {
        return $this->belongsTo(SupportFall::class, 'support_fall_id');
    }

    /** Fuer die Anzeige — Bytes liest niemand gern. */
    public function groesseLesbar(): string
    {
        $kb = $this->groesse / 1024;

        return $kb < 1024
            ? number_format($kb, 0, ',', '.') . ' KB'
            : number_format($kb / 1024, 1, ',', '.') . ' MB';
    }
}
