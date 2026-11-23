<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Die zentralen Kalendertermine 2026/27, die neben den Abiturterminen
 * (Abitur2027Seeder) noch fehlten: Fristen und Zeugnistermine der Oberstufe,
 * der Gedenktag und alle Feiertage.
 *
 * Grundlage ist der Bestand 2025/26 (GlobalCalendarEventsSeeder). Der ISB-
 * Terminplaner für Oberstufenkoordinatoren 2026/27 lag am 21.09.2026 noch nicht
 * vor; die Daten sind deshalb aus den Regeln abgeleitet, die 2025/26 in den
 * Beschreibungen stehen. Jede Regel ist gegen das Vorjahr gegengerechnet und
 * trifft dort exakt:
 *
 * - Kurswahl-Änderung: Ende der ersten vier Wochen ab Unterrichtsbeginn
 *   (15.09.2026 + 27 Tage; 2025: 16.09. + 27 = 13.10.).
 * - Zeugnis AA 13/1: erster Unterrichtstag nach dem 23. Januar (2026: Mo 26.01.).
 * - Zeugnis AA 12/1: letzter Unterrichtstag der zweiten vollen Unterrichtswoche
 *   im Februar. Die Woche 08.–12.02.2027 ist Ferien und zählt nicht, also
 *   01.–05. und 15.–19.02. (Auslegung; in den Vorjahren lag vor dem
 *   Zeugnistag keine Ferienwoche, der Fall kam dort nicht vor).
 * - Festlegung der mündlichen Fächer: sechs Wochen vor dem ersten schriftlichen
 *   Prüfungstag (21.04.2027 − 42 Tage; 2026: 22.04. − 42 = 11.03.).
 * - Feste Daten: 15.04. (Kursprogramm), 15.07. (Vergabeverfahren),
 *   27.01. (Gedenktag).
 * - Festlegung des dritten Abiturprüfungsfachs: Fr 29.01.2027 (Wilfried,
 *   21.09.2026; der 31.01. ist ein Sonntag). 2026 stand im Terminplaner der 31.01.
 * - Abgabe der Seminararbeit: Di 10.11.2026, Q13 (Wilfried, 21.09.2026). Der
 *   Bestand 2025/26 führt den Termin bei Stufe 12; die Stufe hier ist Absicht.
 * - Zeugnis AA 12/2: Jahreszeugnisse am 30.07.2027 (KM Bayern).
 * - Feiertage: aus dem Osterdatum gerechnet (Ostern 2027 = 28.03.).
 *
 * NICHT belegt: „Meldung anderer Bewerber" (15.12.) ist der Wert von 2025/26;
 * die Rechtsgrundlage wurde nicht nachgelesen. Vor dem Einsatz prüfen.
 *
 * Bewusst NICHT enthalten, weil es dafür weder Quelle noch Regel gibt:
 * BayEFG-Prüfungen, Maximilianeumsprüfung, Sporteignungsprüfung.
 *
 * Reformationstag: in Bayern unterrichtsfrei (Wilfried, 21.09.2026), deshalb wie
 * 2025/26 dabei.
 *
 * Idempotent wie Abitur2027Seeder: Was mit gleichem Titel am gleichen Tag schon
 * steht, wird übersprungen. Ein zweiter Lauf meldet „0 eingefügt".
 *
 * Quellen: KM Bayern (Ferien und Feiertage; Prüfungen und Zeugnisse), ISB-
 * Terminplaner für Oberstufenkoordinatoren 2022/23, 2024/25 und 2025/26.
 */
class Kalender202627Seeder extends Seeder
{
    /** Feldwerte je Terminart, übernommen aus dem Bestand 2025/26. */
    private const MUSTER = [
        'feiertag' => ['event_type' => 'public_holiday', 'color' => '#EF4444', 'is_recurring' => true, 'priority' => 3, 'grade_levels' => null],
        // Kein gesetzlicher Feiertag oder ein Sonntag — im Bestand 2025/26 mit Rang 2 und ohne Wiederholung.
        'freier_tag' => ['event_type' => 'public_holiday', 'color' => '#EF4444', 'is_recurring' => false, 'priority' => 2, 'grade_levels' => null],
        'gedenktag' => ['event_type' => 'info', 'color' => '#6B7280', 'is_recurring' => false, 'priority' => 3, 'grade_levels' => null],
        'zeugnis_12' => ['event_type' => 'administration', 'color' => '#8B5CF6', 'is_recurring' => true, 'priority' => 8, 'grade_levels' => '[12]'],
        'zeugnis_13' => ['event_type' => 'administration', 'color' => '#8B5CF6', 'is_recurring' => true, 'priority' => 8, 'grade_levels' => '[13]'],
        'abiturfrist' => ['event_type' => 'deadline', 'color' => '#DC2626', 'is_recurring' => true, 'priority' => 10, 'grade_levels' => '[13]'],
        'seminararbeit' => ['event_type' => 'deadline', 'color' => '#DC2626', 'is_recurring' => true, 'priority' => 10, 'grade_levels' => '[13]'],
        'kurswahl_aenderung' => ['event_type' => 'deadline', 'color' => '#F59E0B', 'is_recurring' => false, 'priority' => 5, 'grade_levels' => '[11,12]'],
        'kursprogramm' => ['event_type' => 'deadline', 'color' => '#F59E0B', 'is_recurring' => false, 'priority' => 7, 'grade_levels' => '[11]'],
        'bewerber' => ['event_type' => 'deadline', 'color' => '#F59E0B', 'is_recurring' => true, 'priority' => 8, 'grade_levels' => '[13]'],
        'vergabe' => ['event_type' => 'deadline', 'color' => '#F59E0B', 'is_recurring' => false, 'priority' => 7, 'grade_levels' => '[13]'],
    ];

    /**
     * Die Termine. `schultag` markiert die, die auf einen Unterrichtstag fallen
     * müssen (kein Wochenende, keine Ferien) — die Plausibilitätsprobe am Ende
     * prüft nur diese. Öffentlich, damit der Test dieselbe Liste benutzt.
     *
     * @return list<array{muster: string, titel: string, von: string, beschreibung?: string, schultag?: bool}>
     */
    public static function termine(): array
    {
        return [
            // --- Oberstufe: Fristen und Zeugnisse ---
            ['muster' => 'kurswahl_aenderung', 'titel' => 'Änderung der Kurswahl mit Genehmigung der Schulleitung',
                'von' => '2026-10-12', 'schultag' => true,
                'beschreibung' => 'Zu Beginn des AA 13/1 und in den ersten vier Wochen des AA 12/1'],
            ['muster' => 'seminararbeit', 'titel' => 'Abgabe der Seminararbeit',
                'von' => '2026-11-10', 'schultag' => true,
                'beschreibung' => 'Letzter Termin zur Abgabe der Seminararbeit'],
            ['muster' => 'bewerber', 'titel' => 'Meldung anderer Bewerber für Abiturprüfung',
                'von' => '2026-12-15', 'schultag' => true,
                'beschreibung' => 'Meldung anderer Bewerberinnen und Bewerber für die Abiturprüfung an der Schule'],
            ['muster' => 'zeugnis_13', 'titel' => 'Zeugnis AA 13/1',
                'von' => '2027-01-25', 'schultag' => true,
                'beschreibung' => '1. Unterrichtstag nach dem 23. Januar'],
            ['muster' => 'abiturfrist', 'titel' => 'Endgültige Festlegung der schriftlichen Abiturprüfungsfächer',
                'von' => '2027-01-29', 'schultag' => true,
                'beschreibung' => 'Spätester Termin zur endgültigen Festlegung (des dritten Abiturprüfungsfachs)'],
            ['muster' => 'zeugnis_12', 'titel' => 'Zeugnis AA 12/1',
                'von' => '2027-02-19', 'schultag' => true,
                'beschreibung' => 'Letzter Unterrichtstag der 2. vollen Unterrichtswoche im Februar'],
            ['muster' => 'abiturfrist', 'titel' => 'Endgültige Festlegung der mündlichen Abiturprüfungsfächer',
                'von' => '2027-03-10', 'schultag' => true,
                'beschreibung' => 'Spätestens sechs Wochen vor Beginn der schriftlichen Abiturprüfungen'],
            ['muster' => 'kursprogramm', 'titel' => 'Letzter Termin zur Wahl des Kursprogramms',
                'von' => '2027-04-15', 'schultag' => true,
                'beschreibung' => 'Letzter Termin zur Wahl des Kursprogramms für die Q12 und Q13'],
            ['muster' => 'vergabe', 'titel' => 'Meldeschluss für zentrales Vergabeverfahren',
                'von' => '2027-07-15', 'schultag' => true,
                'beschreibung' => 'Meldeschluss für in das zentrale Vergabeverfahren einbezogene Studiengänge'],
            ['muster' => 'zeugnis_12', 'titel' => 'Zeugnis AA 12/2',
                'von' => '2027-07-30', 'schultag' => true],
            ['muster' => 'gedenktag', 'titel' => 'Tag des Gedenkens an die Opfer des Nationalsozialismus',
                'von' => '2027-01-27'],

            // --- Feiertage und unterrichtsfreie Tage (Ostern 2027 = 28.03.) ---
            ['muster' => 'feiertag', 'titel' => 'Reformationstag', 'von' => '2026-10-31'],
            ['muster' => 'feiertag', 'titel' => 'Allerheiligen', 'von' => '2026-11-01'],
            ['muster' => 'freier_tag', 'titel' => 'Heiligabend', 'von' => '2026-12-24'],
            ['muster' => 'feiertag', 'titel' => '1. Weihnachtstag', 'von' => '2026-12-25'],
            ['muster' => 'feiertag', 'titel' => '2. Weihnachtstag', 'von' => '2026-12-26'],
            ['muster' => 'freier_tag', 'titel' => 'Silvester', 'von' => '2026-12-31'],
            ['muster' => 'feiertag', 'titel' => 'Neujahr', 'von' => '2027-01-01'],
            ['muster' => 'feiertag', 'titel' => 'Heilige Drei Könige', 'von' => '2027-01-06'],
            ['muster' => 'freier_tag', 'titel' => 'Rosenmontag', 'von' => '2027-02-08'],
            ['muster' => 'feiertag', 'titel' => 'Karfreitag', 'von' => '2027-03-26'],
            ['muster' => 'freier_tag', 'titel' => 'Ostersonntag', 'von' => '2027-03-28'],
            ['muster' => 'feiertag', 'titel' => 'Ostermontag', 'von' => '2027-03-29'],
            ['muster' => 'freier_tag', 'titel' => 'Pfingstsonntag', 'von' => '2027-05-16'],
            ['muster' => 'feiertag', 'titel' => 'Pfingstmontag', 'von' => '2027-05-17'],
            ['muster' => 'feiertag', 'titel' => 'Fronleichnam', 'von' => '2027-05-27'],
        ];
    }

    public function run(): void
    {
        $angelegt = 0;
        $uebersprungen = 0;

        foreach (self::termine() as $termin) {
            $vorhanden = DB::table('global_calendar_events')
                ->where('title', $termin['titel'])->where('event_date', $termin['von'])->exists();

            if ($vorhanden) {
                $uebersprungen++;
                continue;
            }

            DB::table('global_calendar_events')->insert(
                self::MUSTER[$termin['muster']] + [
                    'title' => $termin['titel'],
                    'description' => $termin['beschreibung'] ?? null,
                    'event_date' => $termin['von'],
                    'end_date' => null,
                    'start_time' => null,
                    'end_time' => null,
                    'location' => null,
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $angelegt++;
        }

        $this->command->info("✓ Kalendertermine 2026/27: {$angelegt} eingefügt, {$uebersprungen} bereits vorhanden");

        $this->pruefeSchultage();
    }

    /**
     * Plausibilitätsprobe: Ein Termin, der auf einen Unterrichtstag gehört,
     * darf nicht auf ein Wochenende, in Ferien oder auf einen Feiertag fallen.
     * Findet Zahlendreher in der Liste und Ferieneinträge, die nicht dazu passen.
     */
    private function pruefeSchultage(): void
    {
        $frei = DB::table('global_calendar_events')
            ->whereIn('event_type', ['holiday', 'public_holiday'])
            ->whereBetween('event_date', ['2026-08-01', '2027-07-31'])
            ->get(['title', 'event_date', 'end_date']);

        $treffer = 0;

        foreach (self::termine() as $termin) {
            if (empty($termin['schultag'])) {
                continue;
            }

            $tag = strtotime($termin['von']);

            if ((int) date('N', $tag) >= 6) {
                $this->command->warn("  ⚠ {$termin['titel']} ({$termin['von']}) fällt auf ein Wochenende");
                $treffer++;
                continue;
            }

            foreach ($frei as $f) {
                if ($tag >= strtotime($f->event_date) && $tag <= strtotime($f->end_date ?: $f->event_date)) {
                    $this->command->warn("  ⚠ {$termin['titel']} ({$termin['von']}) fällt auf {$f->title}");
                    $treffer++;
                }
            }
        }

        if ($treffer === 0) {
            $this->command->info('✓ Kein Schultermin fällt auf ein Wochenende, in Ferien oder auf einen Feiertag');
        }
    }
}
