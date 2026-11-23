<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Schulspezifische Einstellungen (pro Mandant).
 * Key-Value-Store für konfigurierbare Texte und Werte.
 *
 * Bekannte Keys:
 *   supervision_note.gangaufsicht   – Hinweistext Gangaufsicht (HTML)
 *   supervision_note.vorbereitung   – Hinweistext Vorbereitungsraum (HTML)
 *   supervision_note.warteraum      – Hinweistext Warteraum (HTML)
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolSetting query()
 * @mixin \Eloquent
 */
class SchoolSetting extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = ['key', 'value'];

    /** Einzelnen Wert lesen. */
    public static function get(string $key, ?string $default = null): ?string
    {
        $row = static::where('key', $key)->first();
        return $row !== null ? $row->value : $default;
    }

    /** Einzelnen Wert speichern (Insert or Update). */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Alle Settings als assoziatives Array zurückgeben.
     *
     * @return array<string, string|null>
     */
    public static function allKeyed(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }
}
