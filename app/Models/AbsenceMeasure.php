<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dokumentierte Maßnahme zu Absenzen/Verhalten eines Schülers (Roadmap 8.3).
 *
 * @property int $id
 * @property int $student_id
 * @property string $type siehe availableTypes()
 * @property string $aspect krankheit|unentschuldigt|verspaetung|keins
 * @property \Carbon\Carbon $measure_date
 * @property string|null $note
 * @property int|null $created_by_user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Student $student
 * @mixin \Eloquent
 */
class AbsenceMeasure extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'student_id',
        'type',
        'aspect',
        'measure_date',
        'note',
        'created_by_user_id',
    ];

    protected $casts = [
        'measure_date' => 'date',
    ];

    /**
     * Feste, reservierte Typen — die Attestpflicht-Statuslogik (8.3) baut
     * auf genau diesen beiden Werten auf, sie sind daher NICHT über die
     * Schuleinstellungen entfernbar.
     */
    public const RESERVED_TYPES = [
        'attestpflicht_verhaengt' => 'Attestpflicht verhängt',
        'attestpflicht_aufgehoben' => 'Attestpflicht aufgehoben',
    ];

    /** Vorbelegung für frei konfigurierbare Typen (siehe Schuleinstellungen). */
    public const DEFAULT_CUSTOM_TYPES = ['Gespräch geführt', 'Verweis', 'Sonstige Maßnahme'];

    public const ASPECT_LABELS = [
        'krankheit' => 'Krankheit / Attestpflicht',
        'unentschuldigt' => 'Unentschuldigtes Fehlen',
        'verspaetung' => 'Verspätung',
        'keins' => '— (kein Reset)',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Reservierte + über die Schuleinstellungen konfigurierte Typen.
     * Konfigurierbare Typen haben Label == Wert (kein separater Slug nötig).
     *
     * @return array<string, string> Wert => Label
     */
    public static function availableTypes(): array
    {
        $custom = SchoolSetting::get('absences.measure_types_custom');
        $customList = $custom !== null && trim($custom) !== ''
            ? array_values(array_filter(array_map('trim', explode("\n", $custom))))
            : self::DEFAULT_CUSTOM_TYPES;

        $customTypes = array_combine($customList, $customList);

        return self::RESERVED_TYPES + $customTypes;
    }

    /** Anzeigetext für einen gespeicherten Typ (fällt auf den Rohwert zurück, falls die Liste inzwischen geändert wurde). */
    public static function labelFor(string $type): string
    {
        return self::availableTypes()[$type] ?? $type;
    }
}
