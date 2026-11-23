<?php

namespace App\Services;

use App\Models\ExamSchedulePeriod;
use App\Models\GlobalCalendarEvent;
use App\Support\SchoolYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Die vier Klausurenplan-Zeiträume eines Schuljahres: Q12/1, Q12/2, Q13/1, Q13/2.
 *
 * Es gibt sie jedes Jahr, und ihre Grenzen sind keine freie Wahl, sondern
 * ergeben sich aus dem Terminkalender des Schuljahres:
 *
 *  - Q12/1 und Q13/1 beginnen mit dem Termin „Änderung der Kurswahl …" (die
 *    Kurswahl steht dann fest), nicht schon mit dem Schuljahresbeginn: in den
 *    ersten Wochen werden noch keine Klausuren geschrieben (2026/27: 12.10.).
 *  - Q12/1 endet mit der Zeugnisverteilung „Zeugnis AA 12/1", Q13/1 schon mit
 *    „Zeugnis AA 13/1" — die Q13 hat ihr erstes Halbjahr früher abgeschlossen.
 *  - Q12/2 beginnt am nächsten Schultag nach Q12/1 und endet mit „Zeugnis AA 12/2".
 *  - Q13/2 beginnt am nächsten Schultag nach Q13/1 und endet am Freitag der
 *    Woche, in der die erste schriftliche Abiturprüfung liegt.
 *
 * Bis zum 21.09.2026 gab es dafür keine Maske und keinen Seeder für ein
 * anderes Jahr als 2025/26; die vier Zeilen wurden von Hand eingetragen. Für
 * 2026/27 fehlten sie, und die Seite meldete „Kein Klausurenplan gefunden".
 */
class KlausurenplanZeitraeumeService
{
    /**
     * Die Termine, aus denen sich die Grenzen ergeben.
     * Schlüssel => [Titelmuster (SQL LIKE), lesbare Bezeichnung].
     */
    private const ANKER = [
        'kurswahl'  => ['Änderung der Kurswahl%', '„Änderung der Kurswahl …"'],
        'zeugnis13' => ['Zeugnis AA 13/1', '„Zeugnis AA 13/1"'],
        'zeugnis121' => ['Zeugnis AA 12/1', '„Zeugnis AA 12/1"'],
        'zeugnis122' => ['Zeugnis AA 12/2', '„Zeugnis AA 12/2"'],
        'abitur'    => ['Schriftliche Abiturprüfung%', 'die schriftlichen Abiturprüfungen'],
    ];

    /**
     * Die vier Zeiträume, wie sie der Terminkalender des Schuljahres ergibt.
     *
     * `zeitraeume` ist leer, sobald ein Termin fehlt; `fehlend` nennt dann,
     * welche. Es wird nie geraten.
     *
     * @return array{zeitraeume: list<array{name: string, start: Carbon, end: Carbon, aktiv: bool, notiz: string}>, fehlend: list<string>}
     */
    public function ableiten(string $schoolYear): array
    {
        if (!SchoolYear::isValid($schoolYear)) {
            return ['zeitraeume' => [], 'fehlend' => ['ein gültiges Schuljahr (z. B. 2026/27)']];
        }

        [$von, $bis] = SchoolYear::range($schoolYear);

        $termine = [];
        $fehlend = [];

        foreach (self::ANKER as $schluessel => [$muster, $bezeichnung]) {
            $datum = GlobalCalendarEvent::query()
                ->where('title', 'like', $muster)
                ->whereBetween('event_date', [$von->toDateString(), $bis->toDateString()])
                ->orderBy('event_date')
                ->value('event_date');

            if ($datum === null) {
                $fehlend[] = $bezeichnung;

                continue;
            }

            $termine[$schluessel] = Carbon::parse($datum)->startOfDay();
        }

        if ($fehlend !== []) {
            return ['zeitraeume' => [], 'fehlend' => $fehlend];
        }

        $q12ErsterTag = $this->naechsterSchultag($termine['zeugnis121']);
        $q13ZweiterTag = $this->naechsterSchultag($termine['zeugnis13']);
        $abiturWoche = $termine['abitur']->copy()->startOfWeek(Carbon::MONDAY)->addDays(4);

        return [
            'zeitraeume' => [
                [
                    'name' => 'Q12/1',
                    'start' => $termine['kurswahl'],
                    'end' => $termine['zeugnis121'],
                    'aktiv' => true,
                    'notiz' => "Erstes Halbjahr Q12, Schuljahr {$schoolYear}",
                ],
                [
                    'name' => 'Q13/1',
                    'start' => $termine['kurswahl'],
                    'end' => $termine['zeugnis13'],
                    'aktiv' => false,
                    'notiz' => "Erstes Halbjahr Q13, Schuljahr {$schoolYear}",
                ],
                [
                    'name' => 'Q12/2',
                    'start' => $q12ErsterTag,
                    'end' => $termine['zeugnis122'],
                    'aktiv' => false,
                    'notiz' => "Zweites Halbjahr Q12, Schuljahr {$schoolYear}",
                ],
                [
                    'name' => 'Q13/2',
                    'start' => $q13ZweiterTag,
                    'end' => $abiturWoche,
                    'aktiv' => false,
                    'notiz' => "Zweites Halbjahr Q13, Schuljahr {$schoolYear} (bis Abitur)",
                ],
            ],
            'fehlend' => [],
        ];
    }

    /**
     * Legt die Zeiträume an, die es für das Schuljahr noch nicht gibt.
     *
     * Vorhandene Zeilen bleiben unberührt, auch wenn ihre Daten von der
     * Ableitung abweichen — wer sie von Hand angepasst hat, behält seine
     * Fassung. Ohne die nötigen Kalendertermine passiert nichts.
     *
     * @return int Anzahl der neu angelegten Zeiträume
     */
    public function anlegen(string $schoolYear): int
    {
        $abgeleitet = $this->ableiten($schoolYear)['zeitraeume'];
        $neu = 0;

        DB::transaction(function () use ($abgeleitet, $schoolYear, &$neu): void {
            foreach ($abgeleitet as $zeitraum) {
                $vorhanden = ExamSchedulePeriod::forSchoolYear($schoolYear)
                    ->where('name', $zeitraum['name'])
                    ->exists();

                if ($vorhanden) {
                    continue;
                }

                ExamSchedulePeriod::create([
                    'name' => $zeitraum['name'],
                    'school_year' => $schoolYear,
                    'start_date' => $zeitraum['start']->toDateString(),
                    'end_date' => $zeitraum['end']->toDateString(),
                    'is_active' => $zeitraum['aktiv'],
                    'notes' => $zeitraum['notiz'],
                ]);
                $neu++;
            }
        });

        return $neu;
    }

    /**
     * Der Tag nach $tag, das Wochenende übersprungen.
     */
    private function naechsterSchultag(Carbon $tag): Carbon
    {
        $naechster = $tag->copy()->addDay();

        while ($naechster->isWeekend()) {
            $naechster->addDay();
        }

        return $naechster;
    }
}
