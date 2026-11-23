<?php

namespace App\Services;

use App\Models\ExamBoardMeeting;
use App\Models\ExamPeriod;
use App\Models\School;
use App\Models\Teacher;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Erzeugt die Niederschrift einer Ausschusssitzung als Word-Dokument (Modul 4.1).
 *
 * Bewusst .docx und nicht PDF: Der größere Teil einer solchen Niederschrift ist
 * Fließtext, der in der Sitzung entsteht. Abiplaner liefert das Gerüst und die
 * Angaben, die es besser weiß als der Mensch — Teilnehmerkreis, Tagesordnung
 * und vor allem die Namenslisten nach den Zusatzprüfungen; ausformuliert wird
 * anschließend in Word.
 *
 * Die Kopfangaben stehen in einer rahmenlosen Tabelle statt in Tabulatoren:
 * Word rendert Tabulatorpositionen aus fremd erzeugten Dokumenten unzuverlässig,
 * eine Tabelle bleibt in jeder Zoomstufe und bei jeder Namenslänge ausgerichtet.
 */
class ExamBoardMinutesWriter
{
    private const FONT = 'Arial';
    private const SIZE = 11;

    public function __construct(
        private readonly ExamBoardMinutesService $minutes,
    ) {
    }

    /**
     * Schreibt das Dokument in eine temporäre Datei und gibt deren Pfad zurück.
     */
    public function write(ExamBoardMeeting $meeting, ExamPeriod $examPeriod, ?School $school): string
    {
        $word = new PhpWord();
        $word->setDefaultFontName(self::FONT);
        $word->setDefaultFontSize(self::SIZE);

        $word->addParagraphStyle('tight',   ['spaceAfter' => 0, 'spaceBefore' => 0]);
        $word->addParagraphStyle('center',  ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        $section = $word->addSection([
            'marginTop'    => 1134, // 2 cm in Twips
            'marginBottom' => 1134,
            'marginLeft'   => 1417,
            'marginRight'  => 1134,
        ]);

        $bold = ['bold' => true];

        // ── Kopf ───────────────────────────────────────────────────────
        $section->addText($school !== null ? $school->name : '', $bold, 'tight');
        $section->addTextBreak(2);

        $section->addText('Niederschrift', ['bold' => true, 'size' => 14], 'center');
        $section->addText('über die', [], 'center');
        $section->addText($meeting->heading(), $bold, 'center');
        $section->addText('Abiturprüfung ' . ($examPeriod->year ?? date('Y')), [], 'center');
        $section->addTextBreak(1);
        $section->addText('vom ' . $meeting->date->format('d.m.Y'), [], 'center');
        $section->addTextBreak(2);

        // ── Rahmendaten ────────────────────────────────────────────────
        $data = $section->addTable(['borderSize' => 0, 'cellMargin' => 0]);

        $this->row($data, 'Beginn:', $meeting->start_time !== null ? $meeting->start_time . ' Uhr' : '');
        $this->row($data, 'Ende:',   $meeting->end_time   !== null ? $meeting->end_time   . ' Uhr' : '');
        $this->row($data, 'Ort:',    (string) ($meeting->location ?? ''));
        $this->row($data, $this->chairLabel($meeting->chair) . ':', $this->shortName($meeting->chair));
        $this->row($data, 'Anwesend:', $this->attendeeList($meeting));
        $this->row($data, 'Schriftführer' . ($meeting->secretary?->gender === 'w' ? 'in' : '') . ':',
            $this->shortName($meeting->secretary));

        $section->addTextBreak(1);

        // ── Tagesordnung ───────────────────────────────────────────────
        $agenda = array_values(array_filter($meeting->agenda ?? [], fn ($t) => trim((string) $t) !== ''));
        $topTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
        $row = $topTable->addRow();
        $row->addCell(2400)->addText('Tagesordnung:', [], 'tight');
        $cell = $row->addCell(6600);
        if ($agenda === []) {
            $cell->addText('', [], 'tight');
        }
        foreach ($agenda as $i => $top) {
            $cell->addText(($i + 1) . '. ' . $top, [], 'tight');
        }

        $section->addTextBreak(2);

        // ── Unterschriften ─────────────────────────────────────────────
        $sig = $section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
        $sigRow = $sig->addRow();
        $sigRow->addCell(4500)->addText($this->chairSignatureLabel($meeting->chair), [], 'tight');
        $sigRow->addCell(4500)->addText(
            $meeting->secretary?->gender === 'w' ? 'Die Schriftführerin' : 'Der Schriftführer',
            null,
            'tight'
        );

        $sig2 = $sig->addRow();
        $sig2->addCell(4500)->addText(str_repeat('.', 30), [], 'tight');
        $sig2->addCell(4500)->addText(str_repeat('.', 30), [], 'tight');

        $sig3 = $sig->addRow();
        $sig3->addCell(4500)->addText($this->shortName($meeting->chair), [], 'tight');
        $sig3->addCell(4500)->addText($this->shortName($meeting->secretary), [], 'tight');

        $section->addTextBreak(2);

        // ── Anlagen ────────────────────────────────────────────────────
        $attachments = array_values(array_filter($meeting->attachments ?? [], fn ($t) => trim((string) $t) !== ''));
        if ($attachments !== []) {
            $section->addText('Anlagen:', [], 'tight');
            foreach ($attachments as $a) {
                $section->addListItem($a, 0, [], null, 'tight');
            }
            $section->addTextBreak(1);
        }

        // ── Tagesordnungspunkte mit den berechneten Angaben ────────────
        $results = $this->minutes->results($examPeriod);

        foreach ($agenda as $i => $top) {
            $section->addText('Zu ' . ($i + 1) . '.)', $bold, 'tight');
            $section->addTextBreak(1);

            foreach ($this->paragraphsFor($top, $results) as $p) {
                $section->addText($p, [], 'tight');
                $section->addTextBreak(1);
            }
        }

        if ($meeting->notes !== null && trim($meeting->notes) !== '') {
            $section->addTextBreak(1);
            foreach (preg_split('/\R/', $meeting->notes) ?: [] as $line) {
                $section->addText($line, [], 'tight');
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'apa_') . '.docx';
        $word->save($path, 'Word2007');

        return $path;
    }

    /**
     * Vorformulierter Text zu einem Tagesordnungspunkt.
     *
     * Nur der Punkt zur Feststellung des Bestehens lässt sich aus den Daten
     * ableiten; alle anderen bekommen eine leere Zeile zum Weiterschreiben.
     *
     * @param  array<string, mixed>  $results
     * @return list<string>
     */
    private function paragraphsFor(string $top, array $results): array
    {
        $normalized = mb_strtolower($top);
        $isBestehen = str_contains($normalized, 'besteh') || str_contains($normalized, 'feststellung');

        if (! $isBestehen) {
            return [''];
        }

        $out = [];

        if (count($results['passed']) > 0) {
            $out[] = 'Folgende Schülerinnen und Schüler, die zwecks Bestehens der Abiturprüfung '
                . 'angetreten sind, haben ihr Ziel erreicht: ' . implode(', ', $results['passed']);
        } elseif (count($results['failed']) === 0 && count($results['incomplete']) === 0) {
            // Nur dann ist die Aussage belastbar: Es gibt niemanden in dieser
            // Kategorie, und es fehlt auch kein Ergebnis, das noch einen ergäbe.
            $out[] = 'Zum Bestehen der Abiturprüfung ist niemand angetreten.';
        }

        if (count($results['failed']) > 0) {
            $out[] = 'Nicht bestanden haben: ' . implode(', ', $results['failed']);
        }

        $improvementAll = array_merge($results['improved'], $results['notImproved']);
        if (count($improvementAll) > 0) {
            sort($improvementAll, SORT_LOCALE_STRING);
            $text = 'Folgende Schülerinnen und Schüler sind zur Notenverbesserung angetreten: '
                . implode(', ', $improvementAll) . '.';

            if (count($results['notImproved']) === 0) {
                $text .= ' Alle haben ihr Ziel erreicht.';
            } elseif (count($results['improved']) === 0) {
                $text .= ' Keine und keiner hat das Ziel erreicht.';
            } else {
                $text .= ' Alle Schülerinnen und Schüler mit Ausnahme von '
                    . implode(', ', $results['notImproved']) . ' haben ihr Ziel erreicht.';
            }

            $out[] = $text;
        }

        if (count($results['incomplete']) > 0) {
            $out[] = '[Zu prüfen: Für folgende Prüflinge ist noch kein vollständiges Ergebnis '
                . 'erfasst: ' . implode(', ', $results['incomplete']) . ']';
        }

        // Der Gesamtschnitt setzt die vollständige Gesamtqualifikation voraus,
        // die Abiplaner nicht führt — die Zahl bleibt zum Eintragen offen.
        $out[] = 'Der Gesamtschnitt nach den mündlichen Zusatzprüfungen beträgt ______ '
            . '(Vorjahr ______).';

        return $out;
    }

    private function row(\PhpOffice\PhpWord\Element\Table $table, string $label, string $value): void
    {
        $row = $table->addRow();
        $row->addCell(2400)->addText($label, [], 'tight');
        $row->addCell(6600)->addText($value, [], 'tight');
    }

    private function chairLabel(?Teacher $chair): string
    {
        return $chair?->gender === 'w' ? 'Vorsitzende' : 'Vorsitzender';
    }

    private function chairSignatureLabel(?Teacher $chair): string
    {
        return $chair?->gender === 'w' ? 'Die Vorsitzende' : 'Der Vorsitzende';
    }

    /**
     * Kurzform für die Niederschrift: abgekürzte Amtsbezeichnung + Nachname.
     */
    private function shortName(?Teacher $teacher): string
    {
        if ($teacher === null) {
            return '';
        }

        return trim($teacher->genderedTitleShort() . $teacher->lastname);
    }

    private function attendeeList(ExamBoardMeeting $meeting): string
    {
        $names = $meeting->attendees
            ->map(function ($a): string {
                if ($a->teacher !== null) {
                    return $this->shortName($a->teacher);
                }

                return trim((string) ($a->name_override ?? ''));
            })
            ->filter(fn (string $n): bool => $n !== '')
            ->values()
            ->all();

        return implode(', ', $names);
    }
}
