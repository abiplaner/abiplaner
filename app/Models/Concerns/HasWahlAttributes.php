<?php

namespace App\Models\Concerns;

use App\Models\Student;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gemeinsame Attribute und Methoden für LkWahl und WseminarWahl.
 *
 * Beide Wahl-Modelle teilen:
 *  - schueler() Beziehung
 *  - scopeForSchuljahr() / scopeForJahrgang() Scopes
 *
 * Nicht enthalten (modell-spezifisch):
 *  - scopeZugeteilt / scopeNichtZugeteilt → unterschiedliche FK-Namen
 *    (zuteilung_lk_kurs_id vs. zuteilung_wseminar_kurs_id)
 *
 * @property string $schuljahr
 * @property string $jahrgang
 * @property int    $schueler_id
 * @property string|null $prioritaet_erhalten  erstwahl|zweitwahl|[drittwahl]|keine
 * @property-read \App\Models\Student|null $schueler
 */
trait HasWahlAttributes
{
    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function schueler(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'schueler_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForSchuljahr($query, string $schuljahr)
    {
        return $query->where('schuljahr', $schuljahr);
    }

    public function scopeForJahrgang($query, string $jahrgang)
    {
        return $query->where('jahrgang', $jahrgang);
    }
}
