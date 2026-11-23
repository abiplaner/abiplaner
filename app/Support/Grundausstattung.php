<?php

namespace App\Support;

/**
 * Was eine frisch eingerichtete Schule mitbekommt (23.11.2026): Räume,
 * Sitzpläne, Zeitraster der Kolloquien und die Aufsichtsraster.
 *
 * Nachgebildet dem Bestand der Pilotschule im Abitur 2026, aber mit
 * neutralen Namen — im öffentlichen Repo steht keine echte Raumnummer.
 * Die Struktur ist echt: drei baugleiche Klassenzimmer-Reihen, zwei kleine
 * Räume, ein Fachraum, Vorbereitungs- und Warteraum; Kolloquien Mo–Do
 * nachmittags, freitags vormittags.
 *
 * Eine Stelle für TenantSeeder (legt an) und SetupChecklistService (erkennt
 * die Vorgabenamen, solange sie unverändert dastehen).
 */
final class Grundausstattung
{
    /**
     * Sitzpläne. `plan` je Reihe von vorn: `.` Platz, `B` gesperrt,
     * `P` Lehrerpult, `G` Gang. Türen wie im Bestand der Pilotschule.
     *
     * @var array<string, array{name: string, beschreibung: string, tuer: array{side: string, index: int}, blick: string, plan: list<string>}>
     */
    public const SITZPLAENE = [
        'klassenzimmer' => [
            'name' => 'Klassenzimmer 8×4 (25 Plätze)',
            'beschreibung' => 'Vorn Pult und zwei gesperrte Plätze, in der letzten Reihe drei gesperrt',
            'tuer' => ['side' => 'left', 'index' => 1],
            'blick' => 'top',
            'plan' => ['BBPP', '....', '....', '....', '....', '....', '....', '.BBB'],
        ],
        'klassenzimmer_voll' => [
            'name' => 'Klassenzimmer 8×4, letzte Reihe frei (28 Plätze)',
            'beschreibung' => 'Vorn Pult und zwei gesperrte Plätze, die letzte Reihe ist voll besetzbar',
            'tuer' => ['side' => 'left', 'index' => 1],
            'blick' => 'bottom',
            'plan' => ['BBPP', '....', '....', '....', '....', '....', '....', '....'],
        ],
        'klein' => [
            'name' => 'Kleiner Raum 4×4 (4 Plätze)',
            'beschreibung' => 'Für Nachteilsausgleich und Einzelprüfungen',
            'tuer' => ['side' => 'top', 'index' => 3],
            'blick' => 'top',
            'plan' => ['GGGG', '.B.G', '.B.G', 'BPPB'],
        ],
        'fachraum' => [
            'name' => 'Fachraum 6×3 (12 Plätze)',
            'beschreibung' => 'Etwa für das Hörverstehen in den Fremdsprachen',
            'tuer' => ['side' => 'right', 'index' => 2],
            'blick' => 'top',
            'plan' => ['GPP', 'GGG', '...', '...', '...', '...'],
        ],
    ];

    /**
     * Räume: Nummer, Typ, Notiz, Sitzplan.
     *
     * @var list<array{0: string, 1: string, 2: string|null, 3: string|null}>
     */
    public const RAEUME = [
        ['Raum 001', 'exam', null, 'klassenzimmer'],
        ['Raum 002', 'exam', null, 'klassenzimmer'],
        ['Raum 003', 'exam', null, 'klassenzimmer_voll'],
        ['Raum 101', 'exam', null, 'klassenzimmer'],
        ['Raum 102', 'exam', null, 'klassenzimmer'],
        ['Raum 103', 'exam', null, 'klassenzimmer_voll'],
        ['Raum 201', 'exam', null, 'klassenzimmer'],
        ['Raum 202', 'exam', null, 'klassenzimmer'],
        ['Raum 203', 'exam', null, 'klassenzimmer_voll'],
        ['Raum 204', 'exam', null, 'klein'],
        ['Raum 205', 'exam', null, 'klein'],
        ['Raum 106', 'exam', 'Fachraum', 'fachraum'],
        ['Raum 206', 'preparation', 'Vorbereitungsraum', null],
        ['Raum 207', 'waiting', 'Warteraum', null],
        ['Flur 2. OG', 'corridor', 'Fluraufsicht', null],
    ];

    /**
     * Kolloquiumsslots Mo–Do: nachmittags im 50-Minuten-Takt, je 30 Minuten
     * Vorbereitung und Prüfung (Pilotschule 2026: 13:10–17:00).
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string}> Slot => [Vorb. von, bis, Prüfung von, bis]
     */
    public const SLOTS_NACHMITTAG = [
        1 => ['12:40:00', '13:10:00', '13:10:00', '13:40:00'],
        2 => ['13:30:00', '14:00:00', '14:00:00', '14:30:00'],
        3 => ['14:20:00', '14:50:00', '14:50:00', '15:20:00'],
        4 => ['15:10:00', '15:40:00', '15:40:00', '16:10:00'],
        5 => ['16:00:00', '16:30:00', '16:30:00', '17:00:00'],
    ];

    /**
     * Kolloquiumsslots am Freitag: vormittags im 70-Minuten-Takt
     * (Pilotschule 2026: 08:30–13:40).
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public const SLOTS_VORMITTAG = [
        1 => ['08:00:00', '08:30:00', '08:30:00', '09:00:00'],
        2 => ['09:10:00', '09:40:00', '09:40:00', '10:10:00'],
        3 => ['10:20:00', '10:50:00', '10:50:00', '11:20:00'],
        4 => ['11:30:00', '12:00:00', '12:00:00', '12:30:00'],
        5 => ['12:40:00', '13:10:00', '13:10:00', '13:40:00'],
    ];

    /**
     * Zeitslot-Vorlagen: Name => [Beschreibung, Freitagsraster?, Gewichte je Slot].
     * Höheres Gewicht = wird beim Verteilen bevorzugt.
     *
     * @var array<string, array{0: string, 1: bool, 2: list<int>}>
     */
    public const SLOT_VORLAGEN = [
        'Normaler Wochentag (Mo–Do)' => ['Nachmittags, 5 Prüfungen von 13:10 bis 17:00', false, [120, 115, 110, 100, 90]],
        'Freitag (vormittags)' => ['Vormittags, 5 Prüfungen von 08:30 bis 13:40', true, [100, 100, 100, 100, 100]],
        'Ausweichtag' => ['Tag, der nach Möglichkeit nicht genutzt werden soll', false, [85, 85, 80, 80, 70]],
    ];

    /** Gewichte der Slots an einem Kolloquiumstag, wie in der Vorlage. */
    public const GEWICHTE_WOCHENTAG = [1 => 120, 2 => 115, 3 => 110, 4 => 100, 5 => 90];

    public const GEWICHT_FREITAG = 100;

    /**
     * Aufsichtsfenster der Kolloquien (Warteraum, Flur). Mo–Do wie 2026;
     * freitags auf den Vormittag gelegt, passend zu den Slots.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const AUFSICHT_NACHMITTAG = [
        1 => ['13:30:00', '14:30:00'],
        2 => ['14:30:00', '15:30:00'],
        3 => ['15:30:00', '16:40:00'],
    ];

    /** @var array<int, array{0: string, 1: string}> */
    public const AUFSICHT_VORMITTAG = [
        1 => ['08:20:00', '10:00:00'],
        2 => ['10:00:00', '11:40:00'],
        3 => ['11:40:00', '13:40:00'],
    ];

    /**
     * Aufsichtsraster der schriftlichen Prüfungen (Pilotschule 2026).
     * Das Fremdsprachen-Raster beginnt früher wegen des Hörverstehens und
     * erscheint im Aufsichtsplan nur an Tagen mit Fremdsprachenprüfung.
     *
     * @var list<array{name: string, farbe: string, standard: bool, fremdsprache: bool, slots: list<array{0: string, 1: string, 2: string|null}>}>
     */
    public const SCHRIFTLICHE_AUFSICHT = [
        [
            'name' => 'Standard',
            'farbe' => 'blue',
            'standard' => true,
            'fremdsprache' => false,
            'slots' => [
                ['07:40:00', '08:40:00', null],
                ['08:40:00', '09:35:00', null],
                ['09:35:00', '10:30:00', null],
                ['10:30:00', '11:25:00', null],
                ['11:25:00', '12:20:00', null],
                ['12:20:00', '13:15:00', null],
                ['13:15:00', '14:00:00', null],
                ['14:00:00', '15:00:00', 'Zeitzuschlag'],
            ],
        ],
        [
            'name' => 'Moderne Fremdsprachen',
            'farbe' => 'green',
            'standard' => false,
            'fremdsprache' => true,
            'slots' => [
                ['07:55:00', '08:45:00', 'Hörverstehen'],
                ['08:45:00', '09:35:00', null],
                ['09:35:00', '10:30:00', null],
                ['10:30:00', '11:25:00', null],
                ['11:25:00', '12:20:00', null],
                ['12:20:00', '13:15:00', null],
                ['13:15:00', '14:00:00', 'eA oder Zeitzuschlag gA'],
                ['14:00:00', '14:50:00', 'Zeitzuschlag eA'],
            ],
        ],
    ];

    /** @return list<string> Die Vorgabe-Raumnummern. */
    public static function raumnummern(): array
    {
        return array_column(self::RAEUME, 0);
    }

    /** Freitage bekommen das Vormittagsraster. */
    public static function istFreitag(string $datum): bool
    {
        return (int) date('N', (int) strtotime($datum)) === 5;
    }
}
