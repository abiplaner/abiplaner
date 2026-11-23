<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamBoardMeeting;
use App\Models\ExamBoardMeetingAttendee;
use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Teacher;
use App\Models\Zusatzpruefung;
use App\Services\AbiturExamDataService;
use App\Services\ExamBoardMinutesService;
use App\Services\ExamBoardMinutesWriter;
use App\Services\MindestpunkteService;

/**
 * Modul 4.1 – Niederschrift des Abiturprüfungsausschusses.
 *
 * Der heikle Teil ist nicht das Dokument, sondern die Einordnung der
 * Prüflinge: In einer amtlichen Niederschrift darf niemand als „bestanden"
 * geführt werden, dessen Ergebnis noch gar nicht feststeht.
 */
class ExamBoardMinutesTest extends TenantTestCase
{
    private ExamPeriod $examPeriod;
    private int $suffix = 0;

    private function boot(): void
    {
        $school = $this->createTestSchool();
        $this->examPeriod = ExamPeriod::first();
    }

    private function makeStudent(string $lastname, string $firstname = 'Vor'): Student
    {
        return Student::create([
            'exam_period_id' => $this->examPeriod->id,
            'external_id'    => 'APA-' . (++$this->suffix),
            'firstname'      => $firstname,
            'lastname'       => $lastname,
            'grade'          => '13',
        ]);
    }

    private function makeZp(
        Student $student,
        bool $absent = false,
        bool $improvementOnly = false,
        ?int $written = 4,
        ?int $target = null,
        ?int $improvementNeeded = null,
    ): Zusatzpruefung {
        $s = ++$this->suffix;

        $teacher = Teacher::create([
            'shortcode' => 'A' . $s,
            'firstname' => 'Test',
            'lastname'  => 'Lehrer' . $s,
        ]);

        $course = Course::create([
            'exam_period_id' => $this->examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => 'apa' . $s,
            'teacher_id'     => $teacher->id,
            'level'          => 'grundlegend',
        ]);

        $studentExam = StudentExam::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'examiner_id' => $teacher->id,
            'exam_type'   => 'written',
        ]);

        return Zusatzpruefung::create([
            'exam_period_id'            => $this->examPeriod->id,
            'student_id'                => $student->id,
            'student_exam_id'           => $studentExam->id,
            'status'                    => 'placed',
            'absent'                    => $absent,
            'improvement_only'          => $improvementOnly,
            'written_points'            => $written,
            'target_points'             => $target,
            'improvement_points_needed' => $improvementNeeded,
        ]);
    }

    private function results(): array
    {
        return app(ExamBoardMinutesService::class)->results($this->examPeriod);
    }

    /**
     * Solange die mündliche Note fehlt, steht kein Ergebnis fest. Ein solcher
     * Prüfling darf weder als bestanden noch als durchgefallen im Protokoll
     * auftauchen — er gehört in die Liste der offenen Fälle.
     */
    public function test_ohne_muendliche_note_gilt_das_ergebnis_als_offen(): void
    {
        $this->boot();

        $student = $this->makeStudent('Offen');
        $this->makeZp($student, target: null);

        $r = $this->results();

        $this->assertSame(['Offen'], $r['incomplete']);
        $this->assertSame([], $r['passed']);
        $this->assertSame([], $r['failed']);

    }

    public function test_nicht_angetretene_werden_gesondert_gefuehrt(): void
    {
        $this->boot();

        $student = $this->makeStudent('Fehlend');
        $this->makeZp($student, absent: true);

        $r = $this->results();

        $this->assertSame(['Fehlend'], $r['absent']);
        $this->assertSame([], $r['incomplete']);

    }

    /**
     * Bei einer Verbesserungsprüfung ist das Ziel die geforderte Punktzahl,
     * nicht das Bestehen — die Prüflinge haben das Abitur bereits.
     */
    public function test_verbesserungspruefung_erreicht_und_verfehlt(): void
    {
        $this->boot();

        $mindest = app(MindestpunkteService::class);

        // Für 8 Punkte schriftlich und eine geforderte Verbesserung von 4
        // Punkten (4-fache Wertung) einmal genau treffen, einmal knapp
        // darunter bleiben. Bezugsgröße der Verbesserung ist der Beitrag OHNE
        // Zusatzpruefung, also written × 4 (Zusatzpruefung::getPointsDelta()):
        //
        //   ohne ZP       8 × 4                    = 32
        //   muendlich 10  round((2·8 + 10) × 4/3)  = 35  →  +3, zu wenig
        //   muendlich 11  round((2·8 + 11) × 4/3)  = 36  →  +4, genau das Ziel
        //
        // Die Schwelle steht als Zahl da statt als Aufruf einer Hilfsmethode —
        // die beiden Zusicherungen darunter halten sie an die Kernformel
        // gebunden, und die Formel selbst ist der Punkt, der sich nicht
        // unbemerkt aendern darf. (Die frueher hier gerufene
        // calcImprovementMinOral() ist am 31.08.2026 entfallen: Sie hatte
        // keinen Produktivaufruf mehr und kannte den Sportfall nicht.)
        $needed  = 4;
        $written = 8;
        $minOral = 11;

        $this->assertSame(36, $mindest->calcZpContribution($written, $minOral));
        $this->assertSame(35, $mindest->calcZpContribution($written, $minOral - 1));

        $gut = $this->makeStudent('Gutgemacht');
        $this->makeZp($gut, improvementOnly: true, written: $written,
            target: $minOral, improvementNeeded: $needed);

        $knapp = $this->makeStudent('Knappdaneben');
        $this->makeZp($knapp, improvementOnly: true, written: $written,
            target: $minOral - 1, improvementNeeded: $needed);

        $r = $this->results();

        $this->assertSame(['Gutgemacht'], $r['improved']);
        $this->assertSame(['Knappdaneben'], $r['notImproved']);

    }

    /**
     * Gleiche Nachnamen im Jahrgang bekommen den Anfangsbuchstaben des
     * Vornamens — auch dann, wenn nur einer der beiden zur Zusatzprüfung
     * angetreten ist. Sonst stünde im Protokoll ein mehrdeutiger Name.
     */
    public function test_gleiche_nachnamen_im_jahrgang_werden_unterschieden(): void
    {
        $this->boot();

        $angetreten = $this->makeStudent('Tran', 'Franz');
        $this->makeStudent('Tran', 'Maria');   // nur im Jahrgang, ohne ZP
        $this->makeZp($angetreten, target: null);

        $r = $this->results();

        $this->assertSame(['Tran F.'], $r['incomplete']);

    }

    /**
     * Das Word-Dokument trägt den Kopf, die Tagesordnung und die berechneten
     * Namenslisten. Der Ergebnis-Service wird dafür durch feste Werte ersetzt,
     * damit dieser Test nur die Formulierung prüft.
     */
    public function test_protokoll_enthaelt_kopf_tagesordnung_und_namenslisten(): void
    {
        $this->boot();

        $vorsitz = Teacher::create([
            'shortcode' => 'VS1', 'firstname' => 'Vera', 'lastname' => 'Vetter',
            'title' => 'Studiendirektor', 'gender' => 'w',
        ]);
        $schrift = Teacher::create([
            'shortcode' => 'SF1', 'firstname' => 'Karl', 'lastname' => 'Kreil',
            'title' => 'Oberstudienrat', 'gender' => 'm',
        ]);

        $meeting = ExamBoardMeeting::create([
            'exam_period_id'       => $this->examPeriod->id,
            'number'               => 6,
            'date'                 => '2026-06-19',
            'start_time'           => '09:45',
            'end_time'             => '10:30',
            'location'             => 'A204',
            'chair_teacher_id'     => $vorsitz->id,
            'secretary_teacher_id' => $schrift->id,
            'agenda'               => [
                'Feststellung des Bestehens der Abiturprüfung nach den mündlichen Zusatzprüfungen',
                'Sonstiges',
            ],
            'attachments'          => ['Abiturrangliste'],
        ]);

        foreach ([$vorsitz, $schrift] as $i => $t) {
            ExamBoardMeetingAttendee::create([
                'exam_board_meeting_id' => $meeting->id,
                'teacher_id'            => $t->id,
                'sort_order'            => $i,
            ]);
        }
        ExamBoardMeetingAttendee::create([
            'exam_board_meeting_id' => $meeting->id,
            'name_override'         => 'MB Dr. Muster',
            'sort_order'            => 2,
        ]);

        $stub = new class(app(AbiturExamDataService::class), app(MindestpunkteService::class))
            extends ExamBoardMinutesService
        {
            public function results(ExamPeriod $examPeriod): array
            {
                return [
                    'passed'      => ['Bayram', 'Gerlach'],
                    'failed'      => ['Valenti'],
                    'improved'    => ['Cordes'],
                    'notImproved' => ['Bäumler'],
                    'incomplete'  => [],
                    'absent'      => [],
                    'counts'      => [],
                ];
            }
        };

        $meeting->load(['chair', 'secretary', 'attendees.teacher']);
        $path = (new ExamBoardMinutesWriter($stub))->write($meeting, $this->examPeriod, null);

        $this->assertFileExists($path);
        $text = $this->docxText($path);
        @unlink($path);

        $this->assertStringContainsString('6. Sitzung des Abiturprüfungsausschusses', $text);
        $this->assertStringContainsString('vom 19.06.2026', $text);
        $this->assertStringContainsString('09:45 Uhr', $text);
        $this->assertStringContainsString('A204', $text);

        // Amtsbezeichnungen abgekürzt und geschlechtsspezifisch
        $this->assertStringContainsString('StDin Vetter', $text);
        $this->assertStringContainsString('OStR Kreil', $text);
        $this->assertStringContainsString('Die Vorsitzende', $text);
        // Gäste ohne Lehrer-Datensatz stehen mit in der Anwesenheitsliste
        $this->assertStringContainsString('MB Dr. Muster', $text);

        // Berechnete Namenslisten
        $this->assertStringContainsString('haben ihr Ziel erreicht: Bayram, Gerlach', $text);
        $this->assertStringContainsString('Nicht bestanden haben: Valenti', $text);
        $this->assertStringContainsString('mit Ausnahme von Bäumler', $text);

        // Der Gesamtschnitt wird bewusst nicht berechnet
        $this->assertStringContainsString('Gesamtschnitt', $text);
        $this->assertStringContainsString('______', $text);

    }

    /**
     * Steht für niemanden ein Ergebnis fest, darf das Protokoll nicht
     * behaupten, es sei niemand angetreten.
     */
    public function test_offene_ergebnisse_werden_nicht_als_niemand_angetreten_ausgegeben(): void
    {
        $this->boot();

        $meeting = ExamBoardMeeting::create([
            'exam_period_id' => $this->examPeriod->id,
            'number'         => 1,
            'date'           => '2026-06-19',
            'agenda'         => ['Feststellung des Bestehens der Abiturprüfung'],
        ]);

        $stub = new class(app(AbiturExamDataService::class), app(MindestpunkteService::class))
            extends ExamBoardMinutesService
        {
            public function results(ExamPeriod $examPeriod): array
            {
                return [
                    'passed' => [], 'failed' => [], 'improved' => [], 'notImproved' => [],
                    'incomplete' => ['Zoegerlich'], 'absent' => [], 'counts' => [],
                ];
            }
        };

        $meeting->load(['chair', 'secretary', 'attendees.teacher']);
        $path = (new ExamBoardMinutesWriter($stub))->write($meeting, $this->examPeriod, null);
        $text = $this->docxText($path);
        @unlink($path);

        $this->assertStringNotContainsString('ist niemand angetreten', $text);
        $this->assertStringContainsString('Zoegerlich', $text);

    }

    /**
     * Liest den sichtbaren Text aus einem .docx.
     */
    private function docxText(string $path): string
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'DOCX konnte nicht geöffnet werden');
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        // <w:t>-Inhalte aneinanderhängen, Absätze durch Zeilenumbrüche trennen
        $xml = str_replace('</w:p>', "</w:p>\n", $xml);
        $text = preg_replace('/<[^>]+>/', '', $xml) ?? '';

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
