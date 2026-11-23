<?php

namespace App\Http\Controllers\Concerns;

/**
 * Hilfsmethoden für die Darstellung von Prioritäts-Labels.
 *
 * Sowohl LkKursController als auch WseminarKursController benötigen
 * die Übersetzung von Datenbank-Werten ('erstwahl', 'zweitwahl', …) in
 * lesbare deutsche Labels ('1. Wahl', '2. Wahl', …).
 *
 * Die Kurs-Controller und deren Excel-Exporte verwenden diese Methode,
 * um doppelten Code zu vermeiden.
 */
trait HasPrioritaetLabel
{
    /**
     * Gibt das lesbare Deutsche Label für einen Prioritäts-Wert zurück.
     *
     * @param  string|null $prioritaet  DB-Wert: 'erstwahl', 'zweitwahl', 'drittwahl', 'keine', null
     * @return string                   z.B. '1. Wahl', '2. Wahl', '3. Wahl' oder '-'
     */
    protected function getPrioritaetLabel(?string $prioritaet): string
    {
        return match ($prioritaet) {
            'erstwahl'  => '1. Wahl',
            'zweitwahl' => '2. Wahl',
            'drittwahl' => '3. Wahl',
            default     => '-',
        };
    }

    /**
     * Gibt das ausgeschriebene Label zurück (für Statistik-Zeilen).
     * Unterscheidet sich von getPrioritaetLabel() nur in der Großschreibung.
     */
    protected function getPrioritaetLabelLong(?string $prioritaet): string
    {
        return match ($prioritaet) {
            'erstwahl'  => 'Erstwahl',
            'zweitwahl' => 'Zweitwahl',
            'drittwahl' => 'Drittwahl',
            default     => '-',
        };
    }
}
