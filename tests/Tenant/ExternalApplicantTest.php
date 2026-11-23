<?php

namespace Tests\Tenant;

use App\Models\Ersatzschule;
use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Models\Room;
use App\Models\School;
use App\Models\StudentExam;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Zusatzpruefung;
use App\Models\ZusatzpruefAssignment;
use App\Models\ZusatzpruefDay;
use App\Models\ZusatzpruefDayRoom;
use App\Models\ZusatzpruefPruefer;
use App\Services\ExternalApplicantValidationService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Externe Bewerber (§61 GSO) — portiert aus tests/Feature/ExternalApplicantTest.php
 * (Modul 6.1-Altlast, behoben 07/2026).
 *
 * Läuft bewusst als Tenant-Test gegen die echte MariaDB der Testschule 9999:
 * external_applicants/external_exam_subjects sind Tenant-Tabellen, die Routen
 * liegen in routes/tenant.php hinter InitializeTenancyByUser — beides ist in
 * der SQLite-Feature-Schicht nicht abbildbar (dort schlug die alte Fassung
 * mit 403/fehlender Tabelle fehl).
 *
 * Die StudentExam-Generierung war bis zur Septemberrunde (26.08.2026) gegen
 * das Schema defekt und deshalb nicht portiert: student_exams hatte kein
 * external_exam_subject_id, student_id/course_id waren NOT NULL. Seit
 * "student_exams öffnen" trägt die Prüfung den Schlüssel auf das Prüfungsfach;
 * die drei Tests aus_der_faecherwahl_entstehen_acht_pruefungen,
 * ein_zweites_speichern_verdoppelt_die_pruefungen_nicht und
 * mit_dem_pruefungsfach_faellt_die_pruefung decken das ab.
 *
 * Weitere 2.1-Befunde aus der Portierung (07/2026):
 * - show-Route ohne View (external-applicants/show.blade.php fehlte, 500er,
 *   nirgends verlinkt) — Route und Controller-Methode am 26.08.2026 entfernt,
 *   Detailansicht ist die edit-Seite
 * - first_name/last_name NOT NULL → Namens-Anonymisierung derzeit unmöglich
 */
class ExternalApplicantTest extends TenantTestCase
{
    private const TEST_PERIOD_YEAR = 2097;
    private const TEST_PERIOD_YEAR_2 = 2098;
    private const TEST_USER_EMAIL = 'planner-9999@test.invalid';

    protected School $school;
    protected User $planner;
    protected ExamPeriod $examPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        // Idempotenz: Reste eines abgebrochenen Laufs zuerst entfernen
        $this->cleanupCentralTestData();

        $this->school = $this->createTestSchool();

        // exam_periods ist ZENTRAL — Testperiode mit fiktivem Jahr weit in der
        // Zukunft, damit echte Perioden (z. B. einer echten Schule) unberührt bleiben
        $this->examPeriod = $this->createExamPeriod(self::TEST_PERIOD_YEAR);

        // users ist ebenfalls zentral; school_id referenziert schools.school_number
        $this->planner = User::factory()->create([
            'email' => self::TEST_USER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

        // Tenancy für die Testdaten-Anlage initialisieren. Die HTTP-Requests
        // laufen über InitializeTenancyByUser (routes/tenant.php, Nicht-Produktion),
        // die eine bereits passende Tenancy unverändert durchreicht.
    }

    protected function tearDown(): void
    {

        // Zentrale Testdaten VOR dem parent-Cleanup entfernen: users.school_id
        // hat einen FK auf schools.school_number — der User muss weg, bevor
        // cleanupTestSchool() die Schule per Query Builder löscht (ON DELETE
        // CASCADE griffe zwar, aber wir räumen explizit und nachvollziehbar auf).
        $this->cleanupCentralTestData();

        parent::tearDown();
    }

    private function cleanupCentralTestData(): void
    {
        User::where('email', self::TEST_USER_EMAIL)->delete();
        ExamPeriod::whereIn('year', [self::TEST_PERIOD_YEAR, self::TEST_PERIOD_YEAR_2])->delete();
    }

    private function createExamPeriod(int $year): ExamPeriod
    {
        return ExamPeriod::create([
            'name' => "Testperiode 9999 ({$year})",
            'year' => $year,
            'week_1_start' => "{$year}-05-04",
            'week_1_end' => "{$year}-05-08",
            'week_2_start' => "{$year}-05-11",
            'week_2_end' => "{$year}-05-15",
        ]);
    }

    // ------------------------------------------------------------------
    // CRUD
    // ------------------------------------------------------------------

    #[Test]
    public function can_create_external_applicant(): void
    {
        $response = $this->actingAs($this->planner)
            ->post(route('external-applicants.store'), [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'email' => 'max@example.com',
                'phone' => '0123456789',
                'birth_date' => '2005-01-15',
                'exam_period_id' => $this->examPeriod->id,
                'notes' => 'Testnotiz',
            ]);

        // Store leitet zur Fächerwahl weiter
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('external_applicants', [
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'exam_period_id' => $this->examPeriod->id,
        ]);
    }

    #[Test]
    public function external_applicant_index_page_is_displayed(): void
    {
        ExternalApplicant::factory()
            ->count(3)
            ->forExamPeriod($this->examPeriod)
            ->create();

        $response = $this->actingAs($this->planner)
            ->get(route('external-applicants.index', ['year' => self::TEST_PERIOD_YEAR]));

        $response->assertOk();
        $response->assertViewIs('external-applicants.index');
        $response->assertViewHas('applicants');
        $this->assertCount(3, $response->viewData('applicants'));
    }

    #[Test]
    public function can_view_external_applicant_edit_form(): void
    {
        // Hinweis (Befund 07/2026, → Modul 2.1): Die show-Route lud eine View,
        // die es nie gab (500er), und war nirgends verlinkt; sie wurde am
        // 26.08.2026 entfernt. Detailansicht ist die Bearbeitungsansicht.
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $response = $this->actingAs($this->planner)
            ->get(route('external-applicants.edit', $applicant));

        $response->assertOk();
        $response->assertViewIs('external-applicants.edit');
        $response->assertSee($applicant->first_name);
        $response->assertSee($applicant->last_name);
    }

    #[Test]
    public function can_update_external_applicant(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create([
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
            ]);

        $response = $this->actingAs($this->planner)
            ->put(route('external-applicants.update', $applicant), [
                'first_name' => 'Maximilian',
                'last_name' => 'Musterfrau',
                'email' => 'new@example.com',
                'phone' => '9876543210',
                'birth_date' => '2005-01-15',
                'exam_period_id' => $this->examPeriod->id,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('external-applicants.index'));

        $this->assertDatabaseHas('external_applicants', [
            'id' => $applicant->id,
            'first_name' => 'Maximilian',
            'last_name' => 'Musterfrau',
            'email' => 'new@example.com',
        ]);
    }

    #[Test]
    public function can_delete_external_applicant(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $response = $this->actingAs($this->planner)
            ->delete(route('external-applicants.destroy', $applicant));

        $response->assertRedirect(route('external-applicants.index'));

        $this->assertSoftDeleted('external_applicants', [
            'id' => $applicant->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Fächerwahl (§61 GSO)
    // ------------------------------------------------------------------

    #[Test]
    public function can_select_exam_subjects(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $response = $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertEquals(8, $applicant->examSubjects()->count());
        $this->assertEquals(4, $applicant->writtenSubjects()->count());
        $this->assertEquals(4, $applicant->oralSubjects()->count());

        // Seit 2026_08_26 hängt jedes Prüfungsfach am zentralen Fächerkatalog.
        $this->assertEquals(0, $applicant->examSubjects()->whereNull('subject_id')->count());

        $deutsch = $applicant->examSubjects->first(fn ($fach) => $fach->subject?->code === 'D');
        $this->assertNotNull($deutsch);
        $this->assertSame(2, $deutsch->position());
    }

    /**
     * Der Gegenbeweis zur Notiz im Klassenkopf: seit student_exams offen ist,
     * entstehen aus der Fächerwahl tatsächlich Prüfungen. Vorher lief der
     * Dienst acht Mal in denselben SQL-Fehler, fing ihn und meldete Erfolg.
     */
    #[Test]
    public function aus_der_faecherwahl_entstehen_acht_pruefungen(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $pruefungen = $this->pruefungenVon($applicant);

        $this->assertCount(8, $pruefungen);
        $this->assertSame(4, $pruefungen->where('exam_type', 'written')->count());
        $this->assertSame(4, $pruefungen->where('exam_type', 'oral')->count());

        // Ein externer Bewerber hat weder Schülerdatensatz noch Kurs.
        $this->assertCount(
            0,
            $pruefungen->filter(fn (StudentExam $p) => $p->student_id !== null || $p->course_id !== null)
        );

        // Fach, Niveau und Name stehen an ihrer Quelle und werden von dort
        // geholt — auf student_exams gibt es keine Kopien mehr.
        $mathe = $pruefungen->first(fn (StudentExam $p) => $p->getSubjectName() === 'Mathematik');
        $this->assertNotNull($mathe);
        $this->assertTrue($mathe->isExternalApplicant());
        $this->assertSame('erhoeht', $mathe->getLevel());
        $this->assertSame($applicant->full_name, $mathe->getStudentName());

        // Der Prüfungszeitraum wird abgeleitet, nicht gespeichert: für Externe
        // über den Bewerber. Die alte HasOneThrough-Beziehung über students
        // lieferte hier immer null.
        $this->assertSame($this->examPeriod->id, $mathe->pruefungszeitraumId());

        $geschichte = $pruefungen->first(fn (StudentExam $p) => $p->getSubjectName() === 'Geschichte');
        $this->assertNotNull($geschichte);
        $this->assertSame('grundlegend', $geschichte->getLevel());
    }

    /**
     * Ein zweites Speichern darf die Prüfungen weder verdoppeln noch neu
     * anlegen.
     *
     * Das ist keine Kosmetik: seit die Prüfung über external_exam_subject_id
     * an der Fächerwahl hängt (ON DELETE CASCADE), nähme ein Löschen der
     * Fächerwahl die Prüfungen mit — und über deren eigene Kaskaden die
     * geplanten Termine (exam_assignments) und Zusatzprüfungen. Behält die
     * Prüfung ihre ID, hat die Kaskade nicht zugeschlagen.
     */
    #[Test]
    public function ein_zweites_speichern_behaelt_die_pruefungen(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $vorher = $this->pruefungenVon($applicant)
            ->mapWithKeys(fn (StudentExam $p): array => [$p->getSubjectName() => $p->id]);

        $this->assertCount(8, $vorher);

        // Ein einziges Fach wird getauscht: Geographie gegen Chemie, beide
        // im zweiten Prüfungsteil auf Position 8.
        $this->actingAs($this->planner)
            ->put(
                route('external-applicants.subjects.update', $applicant),
                $this->faecherwahlPayload([8 => 'Chemie'])
            )
            ->assertSessionHas('success');

        $nachher = $this->pruefungenVon($applicant)
            ->mapWithKeys(fn (StudentExam $p): array => [$p->getSubjectName() => $p->id]);

        $this->assertCount(8, $nachher);

        foreach (['Mathematik', 'Deutsch', 'Englisch', 'Geschichte', 'Physik', 'Französisch', 'Biologie'] as $fach) {
            $this->assertSame(
                $vorher[$fach],
                $nachher[$fach],
                "Die Prüfung in {$fach} wurde neu angelegt statt behalten."
            );
        }

        // Nur das tatsächlich weggefallene Fach verliert seine Prüfung.
        $this->assertArrayNotHasKey('Geographie', $nachher->all());
        $this->assertArrayHasKey('Chemie', $nachher->all());
        $this->assertNull(StudentExam::find($vorher['Geographie']));
    }

    /**
     * Die Übersicht zählt die bereits angelegten Prüfungen über die Beziehung.
     *
     * Die frühere Fassung las `examSubjects.student_exam_id` — eine Spalte,
     * die mit `drop_spiegelspalten_from_external_exam_subjects` gefallen ist.
     * Der Zugriff lieferte seitdem stumm null: die Spalte „Geplant" stand
     * dauerhaft auf 0/8, und der Knopf „Planen" erschien auch dann noch, wenn
     * alle acht Prüfungen längst existierten.
     */
    #[Test]
    public function die_uebersicht_zaehlt_angelegte_pruefungen(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $this->assertCount(8, $this->pruefungenVon($applicant));

        $antwort = $this->actingAs($this->planner)
            ->get(route('external-applicants.index', ['year' => self::TEST_PERIOD_YEAR]))
            ->assertOk();

        $bewerber = collect($antwort->viewData('applicants'))
            ->firstWhere('id', $applicant->id);

        $this->assertNotNull($bewerber);
        $this->assertSame(8, $bewerber->subject_count);
        $this->assertSame(8, $bewerber->planned_count, 'Die angelegten Prüfungen wurden nicht gezählt.');
    }

    /**
     * Der § 62-Kalkulator ist vom Bewerber aus erreichbar — auch ohne
     * Zusatzprüfung. Im Zusatzprüfungs-Modul hängt der Knopf an der Liste der
     * Zusatzprüfungen; wer keine hat, steht dort nicht. Der zweite
     * Prüfungsteil betrifft aber jeden externen Prüfling.
     */
    #[Test]
    public function der_mindestpunkte_kalkulator_ist_ohne_zusatzpruefung_erreichbar(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $this->assertSame(
            0,
            Zusatzpruefung::whereIn(
                'student_exam_id',
                $this->pruefungenVon($applicant)->pluck('id')
            )->count(),
            'Der Testfall setzt einen Bewerber ohne Zusatzprüfung voraus.'
        );

        $this->actingAs($this->planner)
            ->get(route('external-applicants.mindestpunkte', $applicant))
            ->assertOk()
            ->assertSee('§ 62', false)
            ->assertViewHas('faecherwahlVollstaendig', true);
    }

    /**
     * Ohne vollständige Fächerwahl bleibt die Seite erreichbar und sagt, dass
     * nichts zu rechnen ist — statt zu werfen.
     */
    #[Test]
    public function der_kalkulator_meldet_eine_unvollstaendige_faecherwahl(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->get(route('external-applicants.mindestpunkte', $applicant))
            ->assertOk()
            ->assertViewHas('faecherwahlVollstaendig', false);
    }

    /**
     * Leistungsfach ist nicht dasselbe wie erhöhtes Anforderungsniveau.
     *
     * § 61 Abs. 1 S. 5: Deutsch und Mathematik werden bei externen Bewerbern
     * **immer** auf erhöhtem Niveau geprüft, dazu kommt genau ein
     * Leistungsfach. Wer beides gleichsetzt, findet bei jedem Externen drei
     * Leistungsfächer statt einem — und die Oberfläche klebte entsprechend an
     * Deutsch und Mathematik ein „(LF)", das dort nicht hingehört.
     */
    #[Test]
    public function nur_das_leistungsfach_ist_leistungsfach_nicht_jedes_ea_fach(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $pruefungen = $this->pruefungenVon($applicant)
            ->keyBy(fn (StudentExam $p): string => (string) $p->getSubjectName());

        // Alle drei liegen auf erhöhtem Niveau …
        foreach (['Mathematik', 'Deutsch', 'Englisch'] as $fach) {
            $this->assertSame('erhoeht', $pruefungen[$fach]->getLevel(), "{$fach} sollte eA sein.");
        }

        // … Leistungsfach ist aber nur Englisch.
        $this->assertTrue($pruefungen['Englisch']->istLeistungsfach());
        $this->assertFalse($pruefungen['Deutsch']->istLeistungsfach());
        $this->assertFalse($pruefungen['Mathematik']->istLeistungsfach());

        // Und die grundlegenden Fächer erst recht nicht.
        $this->assertFalse($pruefungen['Geschichte']->istLeistungsfach());
        $this->assertFalse($pruefungen['Physik']->istLeistungsfach());

        $this->assertCount(
            1,
            $pruefungen->filter(fn (StudentExam $p): bool => $p->istLeistungsfach()),
            'Ein externer Bewerber hat genau ein Leistungsfach.'
        );
    }

    /**
     * Wird das Leistungsfach gewechselt, wandert das Kennzeichen mit.
     *
     * Physik steht auf Position 5 — dem einzigen Fach des zweiten
     * Prüfungsteils, das nach Anlage 14 auf erhöhtem Niveau liegen darf.
     * Geschichte auf Position 4 ginge nicht: dort lässt § 61 Abs. 2 S. 6 nur
     * grundlegendes Niveau zu, und die Validierung weist die Wahl zu Recht ab.
     */
    #[Test]
    public function das_leistungsfach_kennzeichen_folgt_der_wahl(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(
                route('external-applicants.subjects.update', $applicant),
                $this->faecherwahlPayload([], 'Physik')
            )
            ->assertSessionHas('success');

        $pruefungen = $this->pruefungenVon($applicant)
            ->keyBy(fn (StudentExam $p): string => (string) $p->getSubjectName());

        $this->assertTrue($pruefungen['Physik']->istLeistungsfach());
        $this->assertSame('erhoeht', $pruefungen['Physik']->getLevel());
        $this->assertFalse($pruefungen['Englisch']->istLeistungsfach());
        $this->assertSame('grundlegend', $pruefungen['Englisch']->getLevel());
    }

    /**
     * Fällt ein Prüfungsfach, fällt die Prüfung mit — das erledigt der
     * Fremdschlüssel (ON DELETE CASCADE) und nicht mehr aufräumender Code.
     */
    #[Test]
    public function mit_dem_pruefungsfach_faellt_die_pruefung(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $fach = $applicant->examSubjects()->firstOrFail();
        $pruefungId = StudentExam::where('external_exam_subject_id', $fach->id)->value('id');
        $this->assertNotNull($pruefungId);

        $fach->delete();

        $this->assertNull(StudentExam::find($pruefungId));
        $this->assertCount(7, $this->pruefungenVon($applicant));
    }

    /**
     * Mit dem Bewerber fallen seine Prüfungen.
     *
     * Das Model löscht weich: `delete()` setzt nur `deleted_at`, die
     * Datenbank sieht keine Löschung und räumt von sich aus nichts ab. Ein
     * Model-Ereignis nimmt deshalb die Prüfungsfächer mit, und über deren
     * Kaskade fallen Prüfungen, Termine und Zusatzprüfungen.
     */
    #[Test]
    public function mit_dem_bewerber_fallen_seine_pruefungen(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $this->assertCount(8, $this->pruefungenVon($applicant));

        $applicant->delete();

        $this->assertCount(0, $this->pruefungenVon($applicant));
        $this->assertSame(0, ExternalExamSubject::where('external_applicant_id', $applicant->id)->count());
        $this->assertNotNull(
            ExternalApplicant::withTrashed()->find($applicant->id),
            'Der Bewerber selbst bleibt als weich gelöschter Datensatz erhalten.'
        );
    }

    /**
     * Auch ein externer Bewerber kann einen Nachteilsausgleich haben, und die
     * Prüfungszeiten verlängern sich für ihn wie für einen Schüler.
     */
    #[Test]
    public function der_nachteilsausgleich_gilt_auch_fuer_externe_bewerber(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['disability_compensation_percent' => 25]);

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $pruefung = $this->pruefungenVon($applicant)->first();

        $this->assertNotNull($pruefung);
        $this->assertTrue($pruefung->hatNachteilsausgleich());
        $this->assertSame(25, $pruefung->nachteilsausgleichProzent());
        $this->assertSame(25, $pruefung->kompensierteDauer(20));
        $this->assertSame($applicant->id, $pruefung->pruefling()?->id);

        // Ohne Nachteilsausgleich bleibt es bei der Regelzeit.
        $ohne = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['disability_compensation_percent' => null]);

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $ohne), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $ohnePruefung = $this->pruefungenVon($ohne)->first();

        $this->assertNotNull($ohnePruefung);
        $this->assertFalse($ohnePruefung->hatNachteilsausgleich());
        $this->assertSame(20, $ohnePruefung->kompensierteDauer(20));
    }

    /**
     * Die Zusatzprüfungen kennen den externen Bewerber.
     *
     * Vor der Septemberrunde war die Strecke für ihn nicht erreichbar: die
     * Routen banden `{student}` an einen Schülerdatensatz, den er nicht hat,
     * und `zusatzpruefungen.student_id` war NOT NULL.
     */
    #[Test]
    public function ein_externer_bewerber_bekommt_eine_zusatzpruefung(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $mathe = $this->pruefungenVon($applicant)
            ->first(fn (StudentExam $p) => $p->getSubjectName() === 'Mathematik');

        $this->assertNotNull($mathe);
        $this->assertSame('written', $mathe->exam_type);

        $schluessel = 'e' . $applicant->id;

        $this->actingAs($this->planner)
            ->postJson(route('zusatzpruefungen.store'), [
                'pruefling'        => $schluessel,
                'student_exam_ids' => [$mathe->id],
            ])
            ->assertOk();

        $zp = Zusatzpruefung::where('student_exam_id', $mathe->id)->first();

        $this->assertNotNull($zp);
        $this->assertNull($zp->student_id, 'Ein externer Bewerber hat keinen Schülerdatensatz.');
        $this->assertTrue($zp->istExtern());
        $this->assertSame($applicant->full_name, $zp->prueflingsName());

        // Die Fächerliste kommt über den Prüflingsschlüssel, nicht über eine
        // students-ID.
        $this->actingAs($this->planner)
            ->getJson(route('zusatzpruefungen.pruefling.all-exams', ['pruefling' => $schluessel]))
            ->assertOk()
            ->assertJsonCount(8);

        $this->actingAs($this->planner)
            ->deleteJson(route('zusatzpruefungen.pruefling.destroy-all', ['pruefling' => $schluessel]))
            ->assertOk();

        $this->assertNull(Zusatzpruefung::find($zp->id));
    }

    /**
     * Mathematik wird auch ohne Kurs erkannt.
     *
     * Die Frage entscheidet, welche Anmeldung ein Pruefling ausfuellt: In
     * Mathematik wird ein Stoffgebiet gestrichen (Stochastik oder Geometrie),
     * sonst ein Ausbildungsabschnitt. Zwei von sechs Lesern fragten
     * `$se->course->subject->code === 'M'` und lagen fuer externe Bewerber
     * immer falsch — im Pruefungsplan und in der Lehreruebersicht fehlte das
     * gestrichene Stoffgebiet deshalb ganz, weil die Beschriftung dort nur
     * Ausbildungsabschnitte kennt und "stochastik" nicht darunter faellt.
     */
    #[Test]
    public function mathematik_wird_auch_ohne_kurs_erkannt(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $pruefungen = $this->pruefungenVon($applicant);
        $mathe = $pruefungen->first(fn (StudentExam $p) => $p->getSubjectName() === 'Mathematik');
        $deutsch = $pruefungen->first(fn (StudentExam $p) => $p->getSubjectName() === 'Deutsch');

        $this->assertNotNull($mathe);
        $this->assertNotNull($deutsch);

        // Ohne diese Zusicherung prueft der Test den Sonderfall gar nicht:
        // Bei einem Schueler mit Kurs waere auch die alte Fassung richtig.
        $this->assertNull($mathe->course, 'Ein externer Bewerber hat keinen Kurs — daran hing der Fehler.');

        $this->assertTrue($mathe->istMathematik());
        $this->assertFalse($deutsch->istMathematik());

        // Und der Weg, auf dem die Masken es erfahren.
        $this->actingAs($this->planner)
            ->postJson(route('zusatzpruefungen.store'), [
                'pruefling' => 'e' . $applicant->id,
                'student_exam_ids' => [$mathe->id],
            ])
            ->assertOk();

        $this->actingAs($this->planner)
            ->getJson(route('zusatzpruefungen.pruefling.mindestpunkte-modal-data', [
                'pruefling' => 'e' . $applicant->id,
            ]))
            ->assertOk()
            ->assertJsonPath('pruefungen.0.isMathematik', true);

        // Und der Weg, auf dem es wirklich schiefging: die Lehreruebersicht.
        // Sie las den Kurs und hielt jede Pruefung eines Externen fuer
        // Nicht-Mathematik; die Streichung fiel dann aus der Spalte, weil
        // "stochastik" unter den Ausbildungsabschnitten nicht vorkommt.
        $zp = Zusatzpruefung::where('student_exam_id', $mathe->id)->firstOrFail();
        $zp->update(['excluded_semester' => 'stochastik']);

        $pruefer = Teacher::create([
            'shortcode' => 'MTH',
            'firstname' => 'Maria',
            'lastname' => 'Theorem',
        ]);
        $tag = ZusatzpruefDay::create([
            'exam_period_id' => $this->examPeriod->id,
            'date' => $this->examPeriod->week_1_start,
            'start_time' => '08:00',
        ]);
        $raum = ZusatzpruefDayRoom::create([
            'zusatzpruef_day_id' => $tag->id,
            'exam_room_id' => Room::create(['room_number' => 'ZP-Extern-1'])->id,
            'prep_room_id' => null,
            'sort_order' => 0,
        ]);
        ZusatzpruefAssignment::create([
            'zusatzpruefung_id' => $zp->id,
            'zusatzpruef_day_id' => $tag->id,
            'zusatzpruef_day_room_id' => $raum->id,
            'start_time' => '09:00',
            'exam_duration_minutes' => 20,
            'prep_duration_minutes' => 30,
        ]);
        ZusatzpruefPruefer::create([
            'zusatzpruefung_id' => $zp->id,
            'teacher_id' => $pruefer->id,
            'role' => 'erstpruefer',
            'sort_order' => 0,
        ]);

        $uebersicht = $this->actingAs($this->planner)
            ->getJson(route('zusatzpruefungen.teacher-overview'))
            ->assertOk();

        $eintraege = collect($uebersicht->json())
            ->flatMap(fn (array $zeile): array => $zeile['entries'] ?? [])
            ->where('type', 'pruefung');

        $mathEintrag = $eintraege->firstWhere('subject', 'Mathematik');

        $this->assertNotNull($mathEintrag, 'Die Pruefung des externen Bewerbers fehlt in der Lehreruebersicht.');
        $this->assertTrue(
            $mathEintrag['is_math'],
            'Die Lehreruebersicht haelt die Mathematikpruefung eines Externen fuer ein gewoehnliches Fach — '
            . 'dann faellt das gestrichene Stoffgebiet aus dem Ausdruck.'
        );
        $this->assertSame('stochastik', $mathEintrag['excluded_semester']);
    }

    #[Test]
    public function das_mindestpunkte_modal_kennt_den_externen_bewerber(): void
    {
        [$applicant, $mathe] = $this->bewerberMitZusatzpruefung();
        $schluessel = 'e' . $applicant->id;

        $antwort = $this->actingAs($this->planner)
            ->getJson(route('zusatzpruefungen.pruefling.mindestpunkte-modal-data', ['pruefling' => $schluessel]))
            ->assertOk();

        $antwort->assertJsonPath('studentId', $schluessel);
        $antwort->assertJsonPath('studentName', $applicant->full_name);
        // Die Gesamtpunktzahl von Hand gibt es nur für Schüler.
        $antwort->assertJsonPath('curTotal', null);
        $this->assertCount(8, $antwort->json('exams'));

        // Eine Einzelnote speichern — der Schlüssel ist student_exam_id allein,
        // student_id ist für einen externen Bewerber null.
        $this->actingAs($this->planner)
            ->patchJson(
                route('zusatzpruefungen.pruefling.exam-score.store', ['pruefling' => $schluessel]),
                ['student_exam_id' => $mathe->id, 'score' => 9]
            )
            ->assertOk();

        $note = \App\Models\ZusatzpruefExamScore::where('student_exam_id', $mathe->id)->first();

        $this->assertNotNull($note);
        $this->assertSame(9, $note->score);
        $this->assertNull($note->student_id);
    }

    #[Test]
    public function eine_fremde_pruefung_wird_nicht_unter_falschem_namen_benotet(): void
    {
        [$applicant, $mathe] = $this->bewerberMitZusatzpruefung();

        $zweiter = ExternalApplicant::factory()->forExamPeriod($this->examPeriod)->create();

        $this->actingAs($this->planner)
            ->patchJson(
                route('zusatzpruefungen.pruefling.exam-score.store', ['pruefling' => 'e' . $zweiter->id]),
                ['student_exam_id' => $mathe->id, 'score' => 15]
            )
            ->assertStatus(422);

        $this->assertNull(\App\Models\ZusatzpruefExamScore::where('student_exam_id', $mathe->id)->first());
    }

    #[Test]
    public function die_zusatzpruefungs_exporte_brechen_mit_einem_externen_pruefling_nicht_ab(): void
    {
        [$applicant] = $this->bewerberMitZusatzpruefung();

        foreach ([
            'zusatzpruefungen.export.student-list-pdf',
            'zusatzpruefungen.export.mindestpunkte-pdf',
            'zusatzpruefungen.export.vorbereitungsprotokoll-pdf',
            'zusatzpruefungen.export.plan-pdf',
            'zusatzpruefungen.export.teacher-list-pdf',
        ] as $name) {
            $this->actingAs($this->planner)
                ->get(route($name))
                ->assertOk();
        }

        $this->actingAs($this->planner)
            ->get(route('zusatzpruefungen.export.komplett-pdf', ['pruefling' => 'e' . $applicant->id]))
            ->assertOk();
    }

    /**
     * Ein externer Bewerber mit vollständiger Fächerwahl und einer
     * Zusatzprüfung in Mathematik.
     *
     * @return array{ExternalApplicant, StudentExam}
     */
    private function bewerberMitZusatzpruefung(): array
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $mathe = $this->pruefungenVon($applicant)
            ->first(fn (StudentExam $p) => $p->getSubjectName() === 'Mathematik');

        $this->assertNotNull($mathe);

        $this->actingAs($this->planner)
            ->postJson(route('zusatzpruefungen.store'), [
                'pruefling'        => 'e' . $applicant->id,
                'student_exam_ids' => [$mathe->id],
            ])
            ->assertOk();

        return [$applicant, $mathe];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, StudentExam>
     */
    private function pruefungenVon(ExternalApplicant $applicant)
    {
        return StudentExam::externalApplicants()
            ->whereHas('externalExamSubject', fn ($q) => $q->where('external_applicant_id', $applicant->id))
            ->get();
    }

    /**
     * Eine nach § 61 und Anlage 14 zulässige Wahl.
     *
     * Erster Teil: Mathematik als Fach 1 (Anlage 14 legt die Position fest),
     * Deutsch, das Leistungsfach Englisch — alle drei auf erhöhtem Niveau —,
     * dazu Geschichte auf grundlegendem Niveau als viertes Fach. Die vier
     * decken die drei Aufgabenfelder ab.
     * Zweiter Teil: Physik, Französisch, Biologie, Geographie, alle
     * grundlegend; damit steht nur eine Fremdsprache im ersten Teil.
     *
     * Bis 26.08.2026 stand hier Mathematik auf Fach 2 — die alte Maske fixierte
     * es dort. Nach Anlage 14 ist das nicht zulässig.
     *
     * Die Maske übergibt seit dem Neuaufbau subject_id statt Fachnamen und
     * das Leistungsfach einmal für die ganze Wahl.
     *
     * Seit Entscheidung 32 (27.08.2026) benennt der Prüfling die zweite
     * Fremdsprache selbst — ohne diese Angabe meldet C7 einen Verstoß. Hier
     * ist es Französisch; fällt es durch eine Ersetzung aus der Wahl, bleibt
     * die Angabe leer.
     *
     * @param array<int, string> $ersetzungen Position (1–8) => Fachname
     * @return array<string, mixed>
     */
    private function faecherwahlPayload(
        array $ersetzungen = [],
        string $leistungsfach = 'Englisch',
        string $zweiteFremdsprache = 'Französisch',
    ): array {
        $namen = ['Mathematik', 'Deutsch', 'Englisch', 'Geschichte', 'Physik', 'Französisch', 'Biologie', 'Geographie'];

        foreach ($ersetzungen as $position => $name) {
            $namen[$position - 1] = $name;
        }

        $katalog = Subject::query()
            ->whereIn('name', array_merge($namen, [$leistungsfach, $zweiteFremdsprache]))
            ->get()
            ->keyBy('name');

        $subjects = [];

        foreach ($namen as $index => $name) {
            $subjects[] = [
                'subject_id' => $katalog[$name]->id,
                'exam_part' => $index < 4 ? 'part1_written' : 'part2_oral',
                'subject_number' => ($index % 4) + 1,
            ];
        }

        return [
            'subjects' => $subjects,
            'leistungsfach_subject_id' => $katalog[$leistungsfach]->id,
            'zweite_fremdsprache_subject_id' => in_array($zweiteFremdsprache, $namen, true)
                ? $katalog[$zweiteFremdsprache]->id
                : null,
        ];
    }

    #[Test]
    public function eine_zulaessige_wahl_besteht_die_regelpruefung(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload());

        $applicant->refresh()->load('examSubjects');
        $ergebnis = app(ExternalApplicantValidationService::class)
            ->validateSubjectSelection($applicant);

        $this->assertTrue($ergebnis['valid'], implode(' | ', $ergebnis['errors']));
    }

    #[Test]
    public function die_faecherwahl_maske_wird_ausgeliefert(): void
    {
        // Ohne diesen Fall fiele ein Fehler in der Blade-Vorlage nirgends auf —
        // die übrigen Tests der Fächerwahl gehen alle über PUT und POST.
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $response = $this->actingAs($this->planner)
            ->get(route('external-applicants.subjects.edit', $applicant));

        $response->assertOk();
        $response->assertViewIs('external-applicants.subjects.edit');
        $response->assertSee('Leistungsfach');
        $response->assertSee('neben Deutsch und Mathematik');
        $response->assertSee('Erster Prüfungsteil', false);

        // § 61 Abs. 1 S. 4: Der Katalog der Maske führt nur Abiturprüfungsfächer.
        $response->assertDontSee('Beratungslehrer');
        $response->assertDontSee('Deutsch als Zweitsprache');
    }

    /**
     * Die Maske muss ein gespeichertes Leistungsfach auch anzeigen.
     *
     * Die Optionen des Feldes kamen aus einem x-for. Beim Setzen von x-model
     * gab es sie noch nicht, das Feld blieb auf "-- noch nicht festgelegt --"
     * stehen — und weil es als leeres Feld mitgeschickt wird, schrieb das
     * nächste Speichern null zurück. Schon das blosse Öffnen der Seite genügte
     * also, um die Zuweisung zu verlieren, und mit ihr das erhöhte
     * Anforderungsniveau nach § 61 Abs. 1 S. 5. Beim Durchklicken am
     * 26.08.2026 an Echtdaten gefunden.
     */
    #[Test]
    public function die_maske_traegt_das_gespeicherte_leistungsfach(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $englischId = Subject::query()->where('name', 'Englisch')->value('id');
        $this->assertSame($englischId, $applicant->fresh()->leistungsfach_subject_id);

        $this->actingAs($this->planner)
            ->get(route('external-applicants.subjects.edit', $applicant))
            ->assertOk()
            ->assertSee('<option value="' . $englischId . '" selected>Englisch</option>', false);
    }

    #[Test]
    public function die_maske_prueft_ueber_den_server(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        // Ohne festgelegtes Leistungsfach fehlt das dritte Fach auf erhöhtem
        // Anforderungsniveau — § 61 Abs. 1 S. 5.
        $payload = $this->faecherwahlPayload();
        $payload['leistungsfach_subject_id'] = null;

        $ohne = $this->actingAs($this->planner)
            ->postJson(route('external-applicants.subjects.pruefen', $applicant), $payload);

        $ohne->assertOk();
        $ohne->assertJsonPath('valid', false);
        $this->assertContains('A6', array_column($ohne->json('verstoesse'), 'id'));

        $mit = $this->actingAs($this->planner)
            ->postJson(route('external-applicants.subjects.pruefen', $applicant), $this->faecherwahlPayload());

        $mit->assertOk();
        $mit->assertJsonPath('valid', true);

        // Die Prüfung speichert nichts.
        $this->assertEquals(0, $applicant->examSubjects()->count());
    }

    #[Test]
    public function ein_fach_ausserhalb_des_katalogs_wird_abgewiesen(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        // Eine Fach-ID, die es im zentralen Katalog nicht gibt. Vor der
        // Einführung von subject_id wäre jeder Freitext klaglos gespeichert
        // worden; ein exists-Regel greift hier nicht, weil subjects zentral
        // liegt und die Regel gegen die Mandantendatenbank liefe.
        $payload = $this->faecherwahlPayload();
        $payload['subjects'][7]['subject_id'] = 999999;

        $response = $this->actingAs($this->planner)
            ->from(route('external-applicants.subjects.edit', $applicant))
            ->put(route('external-applicants.subjects.update', $applicant), $payload);

        $response->assertRedirect(route('external-applicants.subjects.edit', $applicant));
        $response->assertSessionHas('error');

        // Nichts gespeichert: die Prüfung läuft vor der Transaktion.
        $this->assertEquals(0, $applicant->examSubjects()->count());
    }

    #[Test]
    public function ein_sportfach_sprengt_das_aufgabenfeld_nicht(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        // Solange external_exam_subjects eine eigene aufgabenfeld-Spalte hatte,
        // stürzte das Insert bei einem Sportfach ab: das Enum dort kannte nur
        // die drei Aufgabenfelder, der Katalog zusätzlich 'sport'. Die Spalte
        // ist gefallen; der Fall bleibt als Wächter stehen.
        $response = $this->actingAs($this->planner)
            ->put(
                route('external-applicants.subjects.update', $applicant),
                $this->faecherwahlPayload([8 => 'Sport männlich'])
            );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $sport = $applicant->examSubjects->first(fn ($fach) => $fach->subject?->code === 'Sm');
        $this->assertNotNull($sport);
        $this->assertSame(8, $sport->position());
    }

    // ------------------------------------------------------------------
    // Validierungs-Service
    // ------------------------------------------------------------------

    #[Test]
    public function validation_detects_missing_subjects(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        // Nur 3 Fächer statt 8 (unique constraint: Fach nur einmal pro Bewerber)
        foreach (['Deutsch', 'Mathematik', 'Englisch'] as $i => $subject) {
            ExternalExamSubject::factory()->fach($subject)->create([
                'external_applicant_id' => $applicant->id,
                'subject_number' => $i + 1,
            ]);
        }

        $validator = app(ExternalApplicantValidationService::class);
        $result = $validator->validateSubjectSelection($applicant);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    #[Test]
    public function validation_requires_exactly_three_leistungsfaecher(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        // Acht Fächer, aber kein Leistungsfach festgelegt: damit sind nur
        // Deutsch und Mathematik auf erhöhtem Niveau, das dritte Fach nach
        // § 61 Abs. 1 S. 5 fehlt.
        $written = ['Deutsch', 'Mathematik', 'Englisch', 'Geschichte'];
        $oral = ['Physik', 'Französisch', 'Biologie', 'Geographie'];

        foreach ($written as $i => $subject) {
            ExternalExamSubject::factory()->fach($subject)->create([
                'external_applicant_id' => $applicant->id,
                'exam_part' => 'part1_written',
                'subject_number' => $i + 1,
            ]);
        }

        foreach ($oral as $i => $subject) {
            ExternalExamSubject::factory()->fach($subject)->create([
                'external_applicant_id' => $applicant->id,
                'exam_part' => 'part2_oral',
                'subject_number' => $i + 1,
            ]);
        }

        $validator = app(ExternalApplicantValidationService::class);
        $result = $validator->validateSubjectSelection($applicant);

        $this->assertFalse($result['valid']);
        $this->assertContains(
            'A6',
            array_column($result['verstoesse'], 'id'),
            'Erwarteter Verstoss gegen § 61 Abs. 1 S. 5 fehlt: ' . json_encode($result['errors'])
        );
    }

    // ------------------------------------------------------------------
    // Export & Datenschutz
    // ------------------------------------------------------------------

    #[Test]
    public function pdf_export_is_accessible(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        ExternalExamSubject::factory()->fach('Deutsch')->written()->create([
            'external_applicant_id' => $applicant->id,
            'subject_number' => 1,
        ]);

        $response = $this->actingAs($this->planner)
            ->get(route('external-applicants.export-pdf', $applicant));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function external_applicants_are_anonymized_after_deadline(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create([
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'email' => 'max@example.com',
                'phone' => '0123456789',
                'anonymize_at' => now()->subDay(),
                'is_anonymized' => false,
            ]);

        // Anonymisierungs-Command existiert noch nicht (Modul 2.1) — hier wird
        // nur das aktuell mögliche Datenmodell abgesichert: Kontaktdaten sind
        // nullable, das Flag setzbar. BEFUND (07/2026, → Modul 2.1): first_name/
        // last_name sind NOT NULL — vollständige PII-Anonymisierung (Namen
        // nullen, wie im ursprünglichen Konzept und im anonymized()-Factory-
        // State vorgesehen) erfordert eine Schema- oder Platzhalter-Entscheidung.
        $applicant->update([
            'email' => null,
            'phone' => null,
            'is_anonymized' => true,
        ]);

        $applicant->refresh();

        $this->assertTrue($applicant->is_anonymized);
        $this->assertNull($applicant->email);
        $this->assertNull($applicant->phone);

        // Der active()-Scope (Basis der Index-Ansicht) blendet Anonymisierte aus
        $this->assertEquals(0, ExternalApplicant::active()->count());
    }

    // ------------------------------------------------------------------
    // Jahresfilter (cross-DB: Tenant-Tabelle → zentrale exam_periods)
    // ------------------------------------------------------------------

    #[Test]
    public function can_filter_applicants_by_year(): void
    {
        $secondPeriod = $this->createExamPeriod(self::TEST_PERIOD_YEAR_2);

        ExternalApplicant::factory()->forExamPeriod($this->examPeriod)->create();
        ExternalApplicant::factory()->count(2)->forExamPeriod($secondPeriod)->create();

        $response = $this->actingAs($this->planner)
            ->get(route('external-applicants.index', ['year' => self::TEST_PERIOD_YEAR_2]));

        $response->assertOk();
        $applicants = $response->viewData('applicants');

        $this->assertCount(2, $applicants);
        $this->assertTrue(
            $applicants->every(fn (ExternalApplicant $a) => $a->exam_period_id === $secondPeriod->id)
        );
    }

    // ------------------------------------------------------------------
    // § 64 Abs. 2 / Anlage 15 — Ersatzschüler (Entscheidungen 31–39)
    // ------------------------------------------------------------------

    /**
     * Ein Bewerber einer Ersatzschule mit gespeicherter Fächerwahl, in der die
     * Prüfungsfächer sieben und acht eingebracht statt geprüft werden.
     */
    private function ersatzschueler(): ExternalApplicant
    {
        $schule = Ersatzschule::create(['name' => 'Testersatzschule 9999', 'ort' => 'Musterstadt']);

        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['ersatzschule_id' => $schule->id]);

        $payload = $this->faecherwahlPayload();
        // Positionen 7 und 8 sind Biologie und Geographie — keine
        // Fremdsprache, nicht Deutsch, nicht das Leistungsfach.
        $payload['subjects'][6]['ergebnis_quelle'] = 'einbringung';
        $payload['subjects'][6]['eingebrachte_punktzahl'] = 11;
        $payload['subjects'][7]['ergebnis_quelle'] = 'einbringung';
        $payload['subjects'][7]['eingebrachte_punktzahl'] = 9;

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $payload)
            ->assertSessionHas('success');

        return $applicant->refresh();
    }

    #[Test]
    public function aus_acht_pruefungsfaechern_werden_sechs_pruefungen(): void
    {
        // § 64 Abs. 2 S. 1 ersetzt die mündliche Prüfung, nicht das Fach: es
        // bleiben acht Prüfungsfächer, von denen zwei nicht geprüft werden.
        $applicant = $this->ersatzschueler();

        $this->assertCount(8, $applicant->examSubjects()->get());
        $this->assertCount(6, $this->pruefungenVon($applicant));

        $eingebracht = $applicant->load('examSubjects')->eingebrachteFaecher();

        $this->assertCount(2, $eingebracht);
        $this->assertSame([7, 8], $eingebracht->map->position()->sort()->values()->all());
        $this->assertNull($eingebracht->first()->studentExam);
    }

    #[Test]
    public function das_bewertungsschema_folgt_aus_den_eingebrachten_faechern(): void
    {
        // Entscheidung 34: kein Schemafeld. § 64 Abs. 2 S. 1 sagt "kann" —
        // eine Ersatzschule allein macht noch keine Anlage 15.
        $ersatzschueler = $this->ersatzschueler();

        $this->assertSame('anlage_15', $ersatzschueler->load('examSubjects')->bewertungsschema());

        $ohneEinbringung = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['ersatzschule_id' => $ersatzschueler->ersatzschule_id]);

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $ohneEinbringung),
                $this->faecherwahlPayload());

        $this->assertTrue($ohneEinbringung->refresh()->istErsatzschueler());
        $this->assertSame('anlage_14', $ohneEinbringung->load('examSubjects')->bewertungsschema());
    }

    #[Test]
    public function wandert_ein_fach_in_die_einbringung_faellt_seine_pruefung_weg(): void
    {
        // Sonst belegte die Prüfung weiter einen Platz im Kolloquiumsplan —
        // für einen Termin, der nicht stattfindet.
        $schule = Ersatzschule::create(['name' => 'Testersatzschule 9998']);

        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['ersatzschule_id' => $schule->id]);

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $this->assertCount(8, $this->pruefungenVon($applicant));

        $payload = $this->faecherwahlPayload();
        $payload['subjects'][6]['ergebnis_quelle'] = 'einbringung';
        $payload['subjects'][6]['eingebrachte_punktzahl'] = 11;
        $payload['subjects'][7]['ergebnis_quelle'] = 'einbringung';
        $payload['subjects'][7]['eingebrachte_punktzahl'] = 9;

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $payload)
            ->assertSessionHas('success');

        $this->assertCount(6, $this->pruefungenVon($applicant));
    }

    #[Test]
    public function die_masken_und_der_ausdruck_zeigen_die_einbringung(): void
    {
        // Ein Durchklicken ersetzt das nicht, aber es faengt ab, was beim
        // Durchklicken zuerst auffiele: eine Blade-Vorlage, die nicht mehr
        // aufgeht. Der PDF-Export rendert dabei wirklich durch dompdf.
        $applicant = $this->ersatzschueler();

        $faecherwahl = $this->actingAs($this->planner)
            ->get(route('external-applicants.subjects.edit', $applicant));

        $faecherwahl->assertOk();
        $faecherwahl->assertSee('Zweite Fremdsprache');
        $faecherwahl->assertSee('Ergebnis aus 13/II einbringen');

        $stammdaten = $this->actingAs($this->planner)
            ->get(route('external-applicants.edit', $applicant));

        $stammdaten->assertOk();
        $stammdaten->assertSee('Ersatzschule');
        $stammdaten->assertSee('Testersatzschule 9999');

        $pdf = $this->actingAs($this->planner)
            ->get(route('external-applicants.export-pdf', $applicant));

        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    #[Test]
    public function ohne_ersatzschule_wird_die_einbringung_abgewiesen(): void
    {
        // Dieselbe Wahl, nur ohne Ersatzschule: G5 meldet, die Fächerwahl
        // wird gespeichert, aber es entstehen keine Prüfungen.
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $payload = $this->faecherwahlPayload();
        $payload['subjects'][6]['ergebnis_quelle'] = 'einbringung';
        $payload['subjects'][7]['ergebnis_quelle'] = 'einbringung';

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $payload)
            ->assertSessionHas('warning');

        $applicant->refresh()->load('examSubjects');
        $ergebnis = app(ExternalApplicantValidationService::class)
            ->validateSubjectSelection($applicant);

        $this->assertFalse($ergebnis['valid']);
        $this->assertStringContainsString('keine Ersatzschule hinterlegt',
            implode(' ', $ergebnis['errors']));
    }

    /**
     * Die Kolloquiumsplanung traegt ungeplante Pruefungen externer Bewerber.
     *
     * Bis zum 29.08.2026 nicht: Die Kursueberschrift der Seitenleiste las
     * `$exams->first()->course->subject->color`, und ein externer Bewerber hat
     * keinen Kurs — "Attempt to read property subject on null", die ganze
     * Seite mit 500. Der Controller war laengst auf beide Faelle eingestellt
     * (Sortierung und Gruppierung gehen ueber getSubjectLabel()); nur die View
     * ist beim Oeffnen von student_exams nicht mitgezogen worden.
     *
     * Der Test faehrt den ganzen Weg: Faecherwahl speichern, damit acht
     * Pruefungen entstehen — vier davon muendlich und alle ohne Termin —, dann
     * /planning aufrufen.
     */
    #[Test]
    public function die_planung_traegt_ungeplante_kolloquien_externer_bewerber(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create(['last_name' => 'Aussenstehend']);

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        $muendlich = $this->pruefungenVon($applicant)
            ->where('exam_type', 'oral')
            ->filter(fn (StudentExam $p): bool => $p->assignment === null);

        $this->assertCount(4, $muendlich, 'Vier ungeplante Kolloquien sind die Voraussetzung des Falls.');

        $antwort = $this->actingAs($this->planner)->get(route('planning.index'));

        $antwort->assertOk();
        // Der Name steht in der Seitenleiste — die Schleife ist also gelaufen
        // und nicht vorher ausgestiegen.
        $antwort->assertSee('Aussenstehend', false);
    }

    /**
     * Die Farbe des Fachs steht an einer Stelle und trifft beide Faelle.
     *
     * Der Rueckfallwert gehoert in fachfarbe(), nicht an jeden Leser: Die
     * Karte des einzelnen Prueflings hatte ein eigenes `?? '#999'` und malte
     * Externe deshalb grau, obwohl ihr Fach samt Farbe bekannt ist. Ein
     * unterdrueckter Fehler ist keine Fallunterscheidung.
     */
    #[Test]
    public function die_fachfarbe_kennt_auch_externe_pruefungen(): void
    {
        $applicant = ExternalApplicant::factory()
            ->forExamPeriod($this->examPeriod)
            ->create();

        $this->actingAs($this->planner)
            ->put(route('external-applicants.subjects.update', $applicant), $this->faecherwahlPayload())
            ->assertSessionHas('success');

        foreach ($this->pruefungenVon($applicant) as $pruefung) {
            $farbe = $pruefung->fachfarbe();

            $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $farbe);
            $this->assertNotSame(
                '#999999',
                $farbe,
                'Das Fach ist bekannt — die Prüfung darf nicht auf die Ersatzfarbe fallen.'
            );
        }
    }
}
