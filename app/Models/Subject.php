<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code Offizielles Fächerkürzel
 * @property string $name
 * @property string $color Hex-Farbcode
 * @property string|null $aufgabenfeld
 * @property bool $is_foreign_language
 * @property bool $is_science
 * @property bool $can_be_late_starting
 * @property bool $can_abitur
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject orderedForDisplay()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereAufgabenfeld($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereCanAbitur($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereCanBeLateStarting($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereIsForeignLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereIsScience($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Subject extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    /**
     * This is a CENTRAL table - uses central database connection.
     * Subjects are defined centrally and available to all schools.
     */
    
    protected $fillable = [
        'code',
        'name',
        'color',
        'aufgabenfeld',
        'is_foreign_language',
        'is_science',
        'can_be_late_starting',
        'can_abitur',
        'sort_order',
    ];
    
    protected $casts = [
        'can_abitur' => 'boolean',
        'is_foreign_language' => 'boolean',
        'is_science' => 'boolean',
        'can_be_late_starting' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Note: Subject is a CENTRAL table.
     * Cannot directly relate to tenant tables (courses, teachers).
     * These relationships must be managed at the tenant level.
     */
    
    /**
     * Scopes
     */
    public function scopeOrderedForDisplay($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
    
    /**
     * Hilfsmethoden
     */
    public function getAufgabenfeldLabel(): string
    {
        return match($this->aufgabenfeld) {
            'sprachlich_literarisch' => 'Sprachlich-literarisch-künstlerisch',
            'gesellschaftswissenschaftlich' => 'Gesellschaftswissenschaftlich',
            'mint' => 'Mathematisch-naturwissenschaftlich-technisch',
            'sport' => 'Sport',
            'sonstiges' => 'Sonstiges',
            default => 'Unbekannt',
        };
    }
    
    /**
     * Ist das ein Sportfach?
     *
     * Sportfächer haben eine Praxisnote, die in den Abiturbeitrag eingeht —
     * ohne Zusatzprüfung als (Theorie + Praxis) × 2, mit Zusatzprüfung über
     * MindestpunkteService::calcSportZpContribution(). Wer das nicht abfragt,
     * rechnet in beiden Fällen falsch.
     *
     * Die Erkennung läuft über das Kürzel, nicht über `aufgabenfeld`. Im
     * Katalog stimmen beide heute überein (Sm, Smw, Sw sind genau die Fächer
     * mit `aufgabenfeld = 'sport'`), aber das Kürzel ist das Merkmal, an dem
     * die Rechnung von Anfang an hängt.
     *
     * Diese Ableitung steht hier genau einmal — sie lag bis zum 27.08.2026 in
     * zwei Kopien (AbiturExamDataService und ZusatzpruefungController).
     */
    public function istSport(): bool
    {
        return in_array($this->code, ['Sw', 'Sm', 'Smw'], true);
    }

}
