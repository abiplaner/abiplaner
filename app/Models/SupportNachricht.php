<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Beitrag im Verlauf einer Fallakte (Modul 6.4, Stufe 1).
 *
 * Der Verlauf ersetzt das Mailpingpong: Die Schule sieht Frage und Antwort
 * dort, wo der Fall steht, und nicht in drei weitergeleiteten Nachrichten.
 *
 * `ROLLE_KI` ist fuer Stufe 2 vorgesehen und wird in Stufe 1 nie geschrieben —
 * die Spalte traegt sie schon, damit eine KI-Antwort spaeter keine Migration
 * braucht und im Verlauf sichtbar als solche erscheint.
 *
 * @property int $id
 * @property int $support_fall_id
 * @property string $autor_rolle
 * @property int|null $autor_user_id
 * @property string|null $autor_name
 * @property string $text
 * @property \Carbon\Carbon|null $gelesen_am_schule
 * @property \Carbon\Carbon|null $gelesen_am_support
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\SupportFall $fall
 * @mixin \Eloquent
 */
class SupportNachricht extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;

    public const ROLLE_SCHULE = 'schule';

    public const ROLLE_SUPPORT = 'support';

    public const ROLLE_KI = 'ki';


    protected $table = 'support_nachrichten';

    protected $fillable = [
        'support_fall_id', 'autor_rolle', 'autor_user_id', 'autor_name', 'text',
        'gelesen_am_schule', 'gelesen_am_support',
    ];

    protected $casts = [
        'gelesen_am_schule' => 'datetime',
        'gelesen_am_support' => 'datetime',
    ];

    /**
     * @return BelongsTo<SupportFall, $this>
     */
    public function fall(): BelongsTo
    {
        return $this->belongsTo(SupportFall::class, 'support_fall_id');
    }

    public function vonDerSchule(): bool
    {
        return $this->autor_rolle === self::ROLLE_SCHULE;
    }
}
