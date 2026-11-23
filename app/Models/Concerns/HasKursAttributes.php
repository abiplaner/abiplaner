<?php

namespace App\Models\Concerns;

use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gemeinsame Attribute und Methoden für LkKurs und WseminarKurs.
 *
 * Beide Kurs-Modelle teilen:
 *  - subject() / lehrer() Beziehungen
 *  - scopeForSchuljahr() / scopeForJahrgang() Scopes
 *
 * @property string      $schuljahr
 * @property string      $jahrgang
 * @property int         $subject_id
 * @property int         $kurs_nummer
 * @property int|null    $lehrer_id
 * @property-read \App\Models\Subject|null  $subject
 * @property-read \App\Models\Teacher|null  $lehrer
 */
trait HasKursAttributes
{
    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function lehrer(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'lehrer_id');
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
