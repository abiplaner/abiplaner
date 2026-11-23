<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\ExamPeriod;
use App\Models\Room;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Teacher;
use App\Models\ZusatzpruefAssignment;
use App\Models\ZusatzpruefDay;
use App\Models\ZusatzpruefDayRoom;
use App\Models\ZusatzpruefPruefer;
use App\Models\Zusatzpruefung;
use App\Services\ZusatzpruefNiederschriftService;

/**
 * Modul 4.1 – Niederschriften über die mündlichen Zusatzprüfungen.
 *
 * Geprüft wird die Auswahl (wer bekommt überhaupt ein Blatt?), die
 * Zeitrechnung (Vorbereitung geht der Prüfung voraus) und die Reihenfolge
 * des Stapels. Genau diese drei Punkte fielen sonst erst auf, wenn die
 * Formulare schon gedruckt in der Mappe liegen.
 */
class ZusatzpruefNiederschriftTest extends TenantTestCase
{
    private ExamPeriod $examPeriod;
    private int $suffix = 0;

    private function boot(): void
    {
        $school = $this->createTestSchool();
        $this->examPeriod = ExamPeriod::first();
    }

    private function makeDay(string $date): ZusatzpruefDay
    {
        return ZusatzpruefDay::create([
            'exam_period_id' => $this->examPeriod->id,
            'date'           => $date,
            'start_time'     => '08:00',
        ]);
    }

    private function makeDayRoom(ZusatzpruefDay $day, string $examRoom, ?string $prepRoom): ZusatzpruefDayRoom
    {
        $exam = Room::create(['room_number' => $examRoom]);
        $prep = $prepRoom !== null ? Room::create(['room_number' => $prepRoom]) : null;

        return ZusatzpruefDayRoom::create([
            'zusatzpruef_day_id' => $day->id,
            'exam_room_id'       => $exam->id,
            'prep_room_id'       => $prep?->id,
            'sort_order'         => 0,
        ]);
    }

    /**
     * Legt eine vollständige, platzierte Zusatzprüfung an.
     */
    private function makePruefung(
        ZusatzpruefDay $day,
        ZusatzpruefDayRoom $dayRoom,
        string $lastname,
        string $startTime = '09:00',
        bool $absent = false,
        ?int $writtenPoints = 4,
    ): Zusatzpruefung {
        $s = ++$this->suffix;

        $teacher = Teacher::create([
            'shortcode' => 'T' . $s,
            'firstname' => 'Test',
            'lastname'  => 'Lehrer' . $s,
        ]);

        $course = Course::create([
            'exam_period_id' => $this->examPeriod->id,
            'subject_id'     => 1,
            'course_code'    => 'zp' . $s,
            'teacher_id'     => $teacher->id,
            'level'          => 'grundlegend',
        ]);

        $student = Student::create([
            'exam_period_id' => $this->examPeriod->id,
            'external_id'    => 'ZP-' . $s,
            'firstname'      => 'Vor',
            'lastname'       => $lastname,
            'grade'          => '13',
        ]);

        $studentExam = StudentExam::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'examiner_id' => $teacher->id,
            'exam_type'   => 'written',
        ]);

        $zp = Zusatzpruefung::create([
            'exam_period_id'  => $this->examPeriod->id,
            'student_id'      => $student->id,
            'student_exam_id' => $studentExam->id,
            'status'          => 'placed',
            'absent'          => $absent,
            'written_points'  => $writtenPoints,
        ]);

        ZusatzpruefAssignment::create([
            'zusatzpruefung_id'       => $zp->id,
            'zusatzpruef_day_id'      => $day->id,
            'zusatzpruef_day_room_id' => $dayRoom->id,
            'start_time'              => $startTime,
            'exam_duration_minutes'   => 20,
            'prep_duration_minutes'   => 30,
        ]);

        return $zp->fresh();
    }

    private function entries(?array $dayIds = null, array $ids = []): array
    {
        return app(ZusatzpruefNiederschriftService::class)
            ->entries($this->examPeriod, $dayIds, $ids);
    }

    /**
     * Wer nicht angetreten ist, bekommt kein Formular — sonst läge im Stapel
     * ein Blatt für eine Prüfung, die nie stattfindet.
     */
    public function test_nicht_angetretene_prueflinge_bekommen_kein_formular(): void
    {
        $this->boot();

        $day     = $this->makeDay('2026-06-16');
        $dayRoom = $this->makeDayRoom($day, 'C003', 'C101');

        $this->makePruefung($day, $dayRoom, 'Anwesend');
        $this->makePruefung($day, $dayRoom, 'Abwesend', '10:00', absent: true);

        $entries = $this->entries();

        $this->assertCount(1, $entries);
        $this->assertSame('Anwesend', $entries[0]['nachname']);

    }

    /**
     * start_time ist der Beginn der Vorbereitungszeit; die Prüfung schließt
     * daran an. Eine Verwechslung würde die Lehrkraft eine halbe Stunde zu
     * früh im Prüfungsraum erwarten lassen.
     */
    public function test_vorbereitungszeit_geht_der_pruefung_voraus(): void
    {
        $this->boot();

        $day     = $this->makeDay('2026-06-16');
        $dayRoom = $this->makeDayRoom($day, 'C003', 'C101');
        $this->makePruefung($day, $dayRoom, 'Zeitler', '09:00');

        $e = $this->entries()[0];

        $this->assertSame('09:00', $e['prepStart']);
        $this->assertSame('09:30', $e['prepEnd']);
        $this->assertSame('09:30', $e['examStart']);
        $this->assertSame('09:50', $e['examEnd']);
        $this->assertSame(30, $e['prepMinutes']);
        $this->assertSame(20, $e['examMinutes']);
        $this->assertSame('C101', $e['prepRoom']);
        $this->assertSame('C003', $e['examRoom']);

    }

    /**
     * Ohne konfigurierten Vorbereitungsraum bleibt ein Gedankenstrich stehen —
     * der Prüfungsraum ist dagegen immer gesetzt.
     */
    public function test_fehlender_vorbereitungsraum_wird_als_strich_ausgewiesen(): void
    {
        $this->boot();

        $day     = $this->makeDay('2026-06-16');
        $dayRoom = $this->makeDayRoom($day, 'C003', null);
        $this->makePruefung($day, $dayRoom, 'Raumlos');

        $e = $this->entries()[0];

        $this->assertSame('–', $e['prepRoom']);
        $this->assertSame('C003', $e['examRoom']);

    }

    /**
     * Der Stapel wird in der Reihenfolge des Prüfungstages abgearbeitet:
     * Datum, dann Uhrzeit, dann Nachname.
     */
    public function test_stapel_ist_nach_tag_zeit_und_name_sortiert(): void
    {
        $this->boot();

        $tag1 = $this->makeDay('2026-06-16');
        $tag2 = $this->makeDay('2026-06-17');
        $r1   = $this->makeDayRoom($tag1, 'A001', 'A101');
        $r2   = $this->makeDayRoom($tag2, 'B001', 'B101');

        $this->makePruefung($tag2, $r2, 'Zweitertag', '08:00');
        $this->makePruefung($tag1, $r1, 'Spaet',      '11:00');
        $this->makePruefung($tag1, $r1, 'Baecker',    '09:00');
        $this->makePruefung($tag1, $r1, 'Ahrens',     '09:00');

        $namen = array_column($this->entries(), 'nachname');

        $this->assertSame(['Ahrens', 'Baecker', 'Spaet', 'Zweitertag'], $namen);

    }

    /**
     * Der Tagesfilter des Export-Dialogs darf nur den gewählten Tag liefern.
     */
    public function test_tagesfilter_grenzt_den_export_ein(): void
    {
        $this->boot();

        $tag1 = $this->makeDay('2026-06-16');
        $tag2 = $this->makeDay('2026-06-17');
        $r1   = $this->makeDayRoom($tag1, 'A001', 'A101');
        $r2   = $this->makeDayRoom($tag2, 'B001', 'B101');

        $this->makePruefung($tag1, $r1, 'Erstertag');
        $this->makePruefung($tag2, $r2, 'Zweitertag');

        $entries = $this->entries([$tag2->id]);

        $this->assertCount(1, $entries);
        $this->assertSame('Zweitertag', $entries[0]['nachname']);

    }

    /**
     * Prüfer werden nach Rolle einsortiert. Eine unbesetzte Rolle bleibt leer
     * (und nicht "–"): Das Feld soll auf dem Papier ausgefüllt werden können,
     * ein Strich läse sich wie "niemand".
     */
    public function test_pruefer_werden_nach_rolle_zugeordnet(): void
    {
        $this->boot();

        $day     = $this->makeDay('2026-06-16');
        $dayRoom = $this->makeDayRoom($day, 'C003', 'C101');
        $zp      = $this->makePruefung($day, $dayRoom, 'Rollen');

        $erst = Teacher::create([
            'shortcode' => 'ER1',
            'firstname' => 'Erika',
            'lastname'  => 'Erst',
            'title'     => 'Studienrat',
            'gender'    => 'w',
        ]);
        $zweit = Teacher::create([
            'shortcode' => 'ZW1',
            'firstname' => 'Zacharias',
            'lastname'  => 'Zweit',
        ]);

        ZusatzpruefPruefer::create([
            'zusatzpruefung_id' => $zp->id,
            'teacher_id'        => $erst->id,
            'role'              => 'erstpruefer',
            'sort_order'        => 0,
        ]);
        ZusatzpruefPruefer::create([
            'zusatzpruefung_id' => $zp->id,
            'teacher_id'        => $zweit->id,
            'role'              => 'zweitkorrektor',
            'sort_order'        => 1,
        ]);
        // Gast ohne Teacher-Datensatz — nur über name_override erfasst
        ZusatzpruefPruefer::create([
            'zusatzpruefung_id' => $zp->id,
            'teacher_id'        => null,
            'role'              => 'gast',
            'name_override'     => 'Frau Gast',
            'sort_order'        => 2,
        ]);

        $e = $this->entries()[0];

        $this->assertSame('Studienrätin Erika Erst', $e['erstpruefer']);
        $this->assertSame('Zacharias Zweit', $e['zweitpruefer']);
        $this->assertSame('Frau Gast', $e['weitere']);
        // Der Vorsitz wird nirgends erfasst und bleibt zum Eintragen frei
        $this->assertSame('', $e['vorsitz']);

    }

    /**
     * Die schriftliche Punktzahl ist bekannt und wird vorgedruckt; fehlt sie
     * (etwa bei einer reinen Verbesserungsprüfung), bleibt die Zeile leer.
     */
    public function test_schriftliche_punktzahl_wird_vorgedruckt_oder_bleibt_leer(): void
    {
        $this->boot();

        $day     = $this->makeDay('2026-06-16');
        $dayRoom = $this->makeDayRoom($day, 'C003', 'C101');

        $this->makePruefung($day, $dayRoom, 'Apunkte', '09:00', writtenPoints: 7);
        $this->makePruefung($day, $dayRoom, 'Bpunkte', '10:00', writtenPoints: null);

        $entries = $this->entries();

        $this->assertSame('7', $entries[0]['writtenPointsStr']);
        $this->assertSame('',  $entries[1]['writtenPointsStr']);

    }
}
