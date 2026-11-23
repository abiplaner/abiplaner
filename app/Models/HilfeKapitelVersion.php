<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine gespeicherte Fassung eines Hilfekapitels (Modul 6.3, zweite Stufe).
 *
 * Geschrieben wird **vor** jeder Aenderung, nicht danach: Die Version ist der
 * Stand, den es zu retten gaebe. Ein versehentlich geleertes Kapitel liesse
 * sich sonst nur ueber ein Backup der zentralen Datenbank zurueckholen — und
 * die sichert die Backup-Maske der Anwendung nicht.
 *
 * @property int $id
 * @property int $hilfe_kapitel_id
 * @property string $titel
 * @property string $gruppe
 * @property int $reihenfolge
 * @property string|null $routen
 * @property string|null $stichworte
 * @property string $markdown
 * @property int|null $user_id
 * @property string|null $benutzername
 * @property string|null $anlass
 * @property \Carbon\Carbon|null $created_at
 * @property-read \App\Models\HilfeKapitel|null $kapitel
 * @mixin \Eloquent
 */
class HilfeKapitelVersion extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $table = 'hilfe_kapitel_versionen';

    public const UPDATED_AT = null;

    protected $fillable = [
        'hilfe_kapitel_id', 'titel', 'gruppe', 'reihenfolge',
        'routen', 'stichworte', 'markdown',
        'user_id', 'benutzername', 'anlass',
    ];

    protected $casts = [
        'reihenfolge' => 'integer',
    ];

    /**
     * @return BelongsTo<HilfeKapitel, $this>
     */
    public function kapitel(): BelongsTo
    {
        return $this->belongsTo(HilfeKapitel::class, 'hilfe_kapitel_id');
    }

    /**
     * Die Felder dieser Fassung, wie sie in das Kapitel zurueckgeschrieben
     * werden — Schluessel, Benutzer und Zeitpunkt bleiben aussen vor.
     *
     * @return array{titel: string, gruppe: string, reihenfolge: int, routen: string|null, stichworte: string|null, markdown: string}
     */
    public function inhalt(): array
    {
        return [
            'titel' => $this->titel,
            'gruppe' => $this->gruppe,
            'reihenfolge' => $this->reihenfolge,
            'routen' => $this->routen,
            'stichworte' => $this->stichworte,
            'markdown' => $this->markdown,
        ];
    }
}
