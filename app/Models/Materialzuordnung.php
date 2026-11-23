<?php

namespace App\Models;

/**
 * Eine Zeile der Zuordnungstabelle exam_material_subject: Was an der Zuordnung
 * zwischen einem Pruefungsmaterial und einem Fach steht.
 *
 * Ein eigener Typ und kein stdClass, damit die beiden Angaben benannt und
 * gepruefft sind. Die Eigenschaftsnamen sind die der Spalten — die Masken und
 * die Aufsichtsliste lesen sie unter diesen Namen.
 *
 * Kein Eloquent-Modell: Die Zuordnung hat keinen eigenen Lebenslauf, sie wird
 * mit dem Material gesetzt (siehe ExamMaterial::zuordnungenSetzen()).
 */
final readonly class Materialzuordnung
{
    public function __construct(
        public bool $is_required,
        public ?string $notes,
    ) {
    }
}
