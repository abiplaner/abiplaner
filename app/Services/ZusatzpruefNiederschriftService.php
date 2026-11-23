<?php

namespace App\Services;

use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\ZusatzpruefAssignment;
use App\Models\ZusatzpruefPruefer;
use App\Models\Zusatzpruefung;
use Carbon\Carbon;

/**
 * Stellt die Daten für die Niederschriften über die mündlichen Zusatzprüfungen
 * zusammen (Modul 4.1).
 *
 * Bewusst als Service und nicht im Controller: Die Auswahl (wer bekommt
 * überhaupt ein Formular?) und die Zeitrechnung (Vorbereitung vor Prüfung)
 * sind die Stellen, an denen ein Fehler erst beim gedruckten Stapel auffiele —
 * sie sollen ohne HTTP-Schicht testbar sein.
 */
class ZusatzpruefNiederschriftService
{
    /**
     * Ein Eintrag je Prüfling, fertig für die Blade-Vorlage.
     *
     * Ausgeschlossen sind nicht angetretene Prüflinge (`absent`) und alle,
     * die noch keinen Platz im Plan haben — ohne Termin, Raum und Uhrzeit
     * wäre das Formular leer und damit wertlos.
     *
     * @param  list<int>|null  $dayIds             null = alle Prüfungstage
     * @param  list<int>       $zusatzpruefungIds  leer = keine Einschränkung
     * @return list<array<string, mixed>>
     */
    public function entries(
        ExamPeriod $examPeriod,
        ?array $dayIds = null,
        array $zusatzpruefungIds = [],
        ?School $school = null,
    ): array {
        $assignments = ZusatzpruefAssignment::whereHas(
                'day',
                fn ($q) => $q->where('exam_period_id', $examPeriod->id)
                    ->when($dayIds !== null, fn ($q2) => $q2->whereIn('id', $dayIds))
            )
            ->whereHas('zusatzpruefung', fn ($q) => $q->where('absent', false)
                ->when($zusatzpruefungIds !== [], fn ($q2) => $q2->whereIn('id', $zusatzpruefungIds))
            )
            ->with([
                'day',
                'dayRoom.examRoom',
                'dayRoom.prepRoom',
                'zusatzpruefung.student',
                'zusatzpruefung.studentExam.course.subject',
                'zusatzpruefung.pruefer.teacher',
            ])
            ->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        // Reihenfolge des Stapels: Prüfungstag, Uhrzeit, dann Nachname (DIN 5007-2)
        $collator = new \Collator('de_DE@collation=phonebook');

        $sorted = $assignments->sort(function (ZusatzpruefAssignment $a, ZusatzpruefAssignment $b) use ($collator) {
            $keyA = $a->day->date->format('Y-m-d') . substr((string) $a->start_time, 0, 5);
            $keyB = $b->day->date->format('Y-m-d') . substr((string) $b->start_time, 0, 5);
            if ($keyA !== $keyB) {
                return $keyA <=> $keyB;
            }
            $nameA = $a->zusatzpruefung->student->lastname . ', ' . $a->zusatzpruefung->student->firstname;
            $nameB = $b->zusatzpruefung->student->lastname . ', ' . $b->zusatzpruefung->student->firstname;

            return $collator->compare($nameA, $nameB);
        })->values();

        $schoolName = $school !== null ? $school->name : '';
        // Untertitel unter dem Schulnamen: der Ort. Ein Feld "Schulart" führt
        // das School-Modell nicht — anders als das Kolloquiums-Formular annimmt.
        $schoolSub = $school !== null ? (string) ($school->city ?? '') : '';
        $year      = $examPeriod->year ?? (int) date('Y');

        return $sorted->map(function (ZusatzpruefAssignment $a) use ($schoolName, $schoolSub, $year): array {
            $zp      = $a->zusatzpruefung;
            $student = $zp->student;
            $course  = $zp->studentExam->course;
            $subject = $course?->subject;

            $levelDisplay = match ($course?->level) {
                'erhoeht', 'erhoht', 'eA', 'erhöht' => 'erhöht',
                'grundlegend', 'gA'                 => 'grundlegend',
                default                             => null,
            };

            $fachStr = ($subject !== null ? $subject->name : '–')
                . ($levelDisplay !== null ? ' (' . $levelDisplay . ')' : '')
                . ($course?->course_code ? ' · ' . $course->course_code : '');

            // start_time ist der Beginn der Vorbereitungszeit; die Prüfung
            // schließt daran an (vgl. ZusatzpruefAssignment::endTime()).
            $prepMinutes = (int) $a->prep_duration_minutes;
            $examMinutes = (int) $a->exam_duration_minutes;
            $prepStart   = Carbon::createFromTimeString(substr((string) $a->start_time, 0, 5));
            $examStart   = (clone $prepStart)->addMinutes($prepMinutes);
            $examEnd     = (clone $examStart)->addMinutes($examMinutes);

            // Vorbereitungsraum ist optional konfigurierbar, der Prüfungsraum nicht
            $prepRoom = $a->dayRoom->prepRoom;

            return [
                'schoolName'       => $schoolName,
                'schoolSub'        => $schoolSub,
                'year'             => $year,
                'nachname'         => $student->lastname,
                'vorname'          => $student->firstname,
                'fachStr'          => $fachStr,
                'datumStr'         => $a->day->date->format('d.m.Y'),
                'prepStart'        => $prepStart->format('H:i'),
                'prepEnd'          => $examStart->format('H:i'),
                'prepMinutes'      => $prepMinutes,
                'examStart'        => $examStart->format('H:i'),
                'examEnd'          => $examEnd->format('H:i'),
                'examMinutes'      => $examMinutes,
                'prepRoom'         => $prepRoom !== null ? $prepRoom->room_number : '–',
                'examRoom'         => $a->dayRoom->examRoom->room_number,
                // Der Vorsitz wird nicht erfasst und bleibt zum Eintragen frei
                'vorsitz'          => '',
                'erstpruefer'      => $this->prueferNameByRole($zp, 'erstpruefer'),
                'zweitpruefer'     => $this->prueferNameByRole($zp, 'zweitkorrektor'),
                'weitere'          => $this->prueferNameByRole($zp, 'gast', true),
                'writtenPointsStr' => $zp->written_points !== null ? (string) $zp->written_points : '',
                'improvementOnly'  => (bool) $zp->improvement_only,
            ];
        })->all();
    }

    /**
     * Namen der Prüfer einer Zusatzprüfung für eine Rolle, formell aufbereitet.
     *
     * Ohne Treffer wird ein leerer String geliefert — das Feld bleibt dann im
     * gedruckten Formular frei und kann handschriftlich ergänzt werden. Ein
     * Platzhalter wie "–" wäre hier falsch: Er sähe aus wie "niemand".
     */
    private function prueferNameByRole(Zusatzpruefung $zp, string $role, bool $all = false): string
    {
        $names = $zp->pruefer
            ->where('role', $role)
            ->map(function (ZusatzpruefPruefer $p): string {
                if ($p->teacher !== null) {
                    return $p->teacher->genderedTitle() . $p->teacher->full_name;
                }

                return (string) ($p->name_override ?? '');
            })
            ->filter(fn (string $n): bool => $n !== '')
            ->values();

        if ($names->isEmpty()) {
            return '';
        }

        return $all ? $names->implode(', ') : (string) $names->first();
    }
}
