<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Kapitel des Hilfesystems (Modul 6.3).
 *
 * **Zentrale Tabelle** — ein Satz Texte fuer alle Schulen. Wie `Subject`,
 * `School` und `ExamPeriod` liegt das Modell auf der Verbindung `mysql`; im
 * Mandanten gibt es die Tabelle nicht, ein Join darauf scheitert erst zur
 * Laufzeit.
 *
 * @property int $id
 * @property string $slug
 * @property string $titel
 * @property string $gruppe
 * @property int $reihenfolge
 * @property string|null $routen
 * @property string|null $stichworte
 * @property string $markdown
 * @property string|null $grundfassung_hash
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HilfeKapitelVersion> $versionen
 * @mixin \Eloquent
 */
class HilfeKapitel extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;


    protected $table = 'hilfe_kapitel';

    protected $fillable = [
        'slug', 'titel', 'gruppe', 'reihenfolge',
        'routen', 'stichworte', 'markdown', 'grundfassung_hash',
    ];

    protected $casts = [
        'reihenfolge' => 'integer',
    ];

    /**
     * @return HasMany<HilfeKapitelVersion, $this>
     */
    public function versionen(): HasMany
    {
        return $this->hasMany(HilfeKapitelVersion::class)->latest('id');
    }

    /**
     * Die Routenpraefixe als Liste.
     *
     * @return list<string>
     */
    public function routenListe(): array
    {
        return self::liste($this->routen);
    }

    /**
     * @return list<string>
     */
    public function stichwortListe(): array
    {
        return self::liste($this->stichworte);
    }

    /**
     * Kommagetrennte Eingabe zu einer sauberen Liste — leere Glieder fallen
     * heraus, damit ein versehentliches Komma am Ende kein leeres Praefix
     * erzeugt (das wuerde auf jede Route passen).
     *
     * @return list<string>
     */
    public static function liste(?string $wert): array
    {
        if ($wert === null || trim($wert) === '') {
            return [];
        }

        $teile = array_map('trim', explode(',', $wert));

        return array_values(array_filter($teile, static fn (string $t): bool => $t !== ''));
    }

    /**
     * Der Fingerabdruck des Inhalts — Grundlage dafuer, ob der Seeder ein
     * Kapitel noch anfassen darf. Bewusst ueber alle redigierbaren Felder,
     * nicht nur ueber das Markdown: Wer nur die Routen aendert, hat das
     * Kapitel genauso redigiert.
     */
    public function fingerabdruck(): string
    {
        return self::fingerabdruckAus($this->inhaltFuerVersion());
    }

    /**
     * @param array{titel: string, gruppe: string, reihenfolge: int, routen: string|null, stichworte: string|null, markdown: string} $werte
     */
    public static function fingerabdruckAus(array $werte): string
    {
        return sha1(implode("\x1f", [
            $werte['titel'],
            $werte['gruppe'],
            (string) $werte['reihenfolge'],
            (string) ($werte['routen'] ?? ''),
            (string) ($werte['stichworte'] ?? ''),
            $werte['markdown'],
        ]));
    }

    /**
     * Die redigierbaren Felder — fuer den Abzug in die Versionstabelle.
     *
     * @return array{titel: string, gruppe: string, reihenfolge: int, routen: string|null, stichworte: string|null, markdown: string}
     */
    public function inhaltFuerVersion(): array
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

    /**
     * Wurde das Kapitel seit der Erstbefuellung von Hand geaendert?
     */
    public function istRedigiert(): bool
    {
        return $this->grundfassung_hash === null
            || $this->grundfassung_hash !== $this->fingerabdruck();
    }
}
