<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine Fallakte der Support-Organisation (Modul 6.4, Stufe 1).
 *
 * **Zentrale Tabelle** (Entscheidung 43). Die Verbindung heisst `central` und
 * nicht `mysql`: Ausserhalb der Tests sind beide Zeichen fuer Zeichen dieselbe
 * Konfiguration, im Testlauf aber nicht — dort ist `central` die
 * SQLite-:memory:-Datenbank, `mysql` dagegen die echte MariaDB. Genau daran
 * hat `ActivityLog` einmal bei jedem Feature-Testlauf in die
 * Entwicklungsdatenbank geschrieben (89 Zeilen je Aktion, 27.08.2026).
 *
 * @property int $id
 * @property string|null $school_id
 * @property string|null $schule_name
 * @property int|null $user_id
 * @property string|null $melder_name
 * @property string|null $melder_email
 * @property string $betreff
 * @property string $beschreibung
 * @property string|null $route_name
 * @property string|null $url
 * @property string|null $hilfe_slug
 * @property string $status
 * @property array<string, mixed>|null $kontext
 * @property string|null $fehler_id
 * @property \Carbon\Carbon|null $benachrichtigt_am
 * @property \Carbon\Carbon|null $geschlossen_am
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupportNachricht> $nachrichten
 * @property int|null $ungelesen_count Aggregat aus withCount(['nachrichten as ungelesen_count' => ...]), SupportController
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupportAnhang> $anhaenge
 * @property-read \App\Models\School|null $school
 * @property-read \App\Models\User|null $user
 * @mixin \Eloquent
 */
class SupportFall extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;

    public const STATUS_NEU = 'neu';

    public const STATUS_IN_ARBEIT = 'in_arbeit';

    public const STATUS_WARTET_AUF_SCHULE = 'wartet_auf_schule';

    public const STATUS_BEANTWORTET = 'beantwortet';

    public const STATUS_ERLEDIGT = 'erledigt';

    /** @var list<string> */
    public const STATUS = [
        self::STATUS_NEU,
        self::STATUS_IN_ARBEIT,
        self::STATUS_WARTET_AUF_SCHULE,
        self::STATUS_BEANTWORTET,
        self::STATUS_ERLEDIGT,
    ];

    /** Beschriftungen fuer die Masken — an einer Stelle, nicht in drei Views. */
    public const STATUS_TEXT = [
        self::STATUS_NEU => 'Neu',
        self::STATUS_IN_ARBEIT => 'In Arbeit',
        self::STATUS_WARTET_AUF_SCHULE => 'Wartet auf die Schule',
        self::STATUS_BEANTWORTET => 'Beantwortet',
        self::STATUS_ERLEDIGT => 'Erledigt',
    ];


    protected $table = 'support_faelle';

    protected $fillable = [
        'school_id', 'schule_name', 'user_id', 'melder_name', 'melder_email',
        'betreff', 'beschreibung', 'route_name', 'url', 'hilfe_slug',
        'status', 'kontext', 'fehler_id', 'benachrichtigt_am', 'geschlossen_am',
    ];

    protected $casts = [
        'kontext' => 'array',
        'benachrichtigt_am' => 'datetime',
        'geschlossen_am' => 'datetime',
    ];

    /**
     * @return HasMany<SupportNachricht, $this>
     */
    public function nachrichten(): HasMany
    {
        return $this->hasMany(SupportNachricht::class)->orderBy('id');
    }

    /**
     * Bildschirmfotos (Entscheidung 48). Sie verschwinden mit dem Abschluss
     * des Falls — die Akte bleibt, die Bilder nicht.
     *
     * @return HasMany<SupportAnhang, $this>
     */
    public function anhaenge(): HasMany
    {
        return $this->hasMany(SupportAnhang::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        // schools.school_number ist der Primaerschluessel, nicht `id`.
        return $this->belongsTo(School::class, 'school_id', 'school_number');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Faelle, die noch Arbeit machen — die Zahl in der Kopfzeile des
     * Super-Admins.
     *
     * @param  Builder<SupportFall>  $query
     * @return Builder<SupportFall>
     */
    public function scopeOffen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_ERLEDIGT]);
    }

    /**
     * Faelle, die zu lange offen stehen (Entscheidung 53).
     *
     * Die Aufbewahrungsfristen haengen alle an `geschlossen_am`; ein offener
     * Fall wird nie geloescht. Damit „offen lassen" nicht der bequeme Weg
     * wird, die Frist zu umgehen, mahnt das Dashboard diese Faelle an. Die
     * Grenze steht in `config/support.php`.
     *
     * Gezaehlt wird ab `created_at` — dem Eingang. `geschlossen_am` ist bei
     * einem offenen Fall definitionsgemaess null und taugt hier nicht.
     *
     * @param  Builder<SupportFall>  $query
     * @return Builder<SupportFall>
     */
    public function scopeLangeOffen(Builder $query): Builder
    {
        $monate = max(1, (int) config('support.aufbewahrung.offen_hinweis_monate'));

        return $query->offen()->where('created_at', '<=', now()->subMonths($monate));
    }

    /**
     * Wurde der Kontext nach Ablauf der Frist gekuerzt?
     *
     * **Ein leeres Feld muss sagen koennen, warum es leer ist.** Ohne diesen
     * Vermerk stuende in der Akte „kein Kontext erfasst" — und niemand koennte
     * unterscheiden, ob die Erhebung gescheitert ist oder die Frist gegriffen
     * hat.
     */
    public function kontextGekuerztAm(): ?string
    {
        $kontext = $this->kontext;

        if (! is_array($kontext)) {
            return null;
        }

        $wert = $kontext['gekuerzt_am'] ?? null;

        return is_string($wert) ? $wert : null;
    }

    public function statusText(): string
    {
        return self::STATUS_TEXT[$this->status] ?? $this->status;
    }

    /**
     * Hat der Support seit der letzten Sicht der Schule geantwortet?
     *
     * Bewusst ueber die Nachrichten und nicht ueber den Status: Der Status
     * kann sich aus anderen Gruenden aendern, ungelesen ist ungelesen.
     */
    public function hatUngeleseneAntwort(): bool
    {
        return $this->nachrichten()
            ->where('autor_rolle', '!=', SupportNachricht::ROLLE_SCHULE)
            ->whereNull('gelesen_am_schule')
            ->exists();
    }
}
