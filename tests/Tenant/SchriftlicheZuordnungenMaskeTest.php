<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\Room;
use App\Models\RoomSeatingTemplate;
use App\Models\School;
use App\Models\SeatingTemplate;
use App\Models\SeatingTemplateSeat;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WrittenExamAssignment;
use App\Models\WrittenExamYearDate;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die Kurs- und Raumzuordnungen einer schriftlichen Prüfung müssen antworten.
 *
 * Diese Maske ist von nirgends verlinkt — erreichbar nur über die Adresse und
 * als Ziel der Weiterleitung nach destroy(). Deshalb ist über Jahre niemandem
 * aufgefallen, dass sie **überhaupt nicht rendert**. Am 30.08.2026 lagen dort
 * gleich drei Fehler übereinander:
 *
 * 1. Drei Routennamen, die es nie gab (written-exam-assignments.* statt
 *    written-exams.assignments.*) — RouteNotFoundException beim Rendern.
 * 2. Ein $examDate, das der Controller nie übergibt (er gibt
 *    $writtenExamYearDate), samt Zugriffen auf ->name und ->start_time, die
 *    beide am Slot stehen.
 * 3. `$assignment->layout` — eine Beziehung, die es auf
 *    WrittenExamAssignment nicht gibt. Sie lieferte still null, und
 *    ->getTotalCapacity() darauf beendete die Seite mit 500.
 *
 * Der dritte kam erst zum Vorschein, als die ersten beiden weg waren: Ich hatte
 * die Maske repariert gemeldet, ohne sie je gerendert zu haben. Dieser Test
 * ruft sie deshalb wirklich ab.
 *
 * Ausfuehren:
 * docker compose exec abiplaner-app php vendor/bin/phpunit --filter SchriftlicheZuordnungenMaske
 */
class SchriftlicheZuordnungenMaskeTest extends TenantTestCase
{
    private const PLANNER_EMAIL = 'zuordnungen-planer-9999@test.invalid';

    protected School $school;

    protected User $planner;

    protected WrittenExamYearDate $termin;

    protected function setUp(): void
    {
        parent::setUp();

        User::where('email', self::PLANNER_EMAIL)->delete();

        $this->school = $this->createTestSchool();
        $this->planner = User::factory()->create([
            'email' => self::PLANNER_EMAIL,
            'role' => 'planner',
            'school_id' => $this->school->school_number,
        ]);

        $termin = WrittenExamYearDate::query()->orderBy('id')->first();
        $this->assertNotNull($termin, 'Die zentralen Stammdaten brauchen einen Jahrestermin.');
        $this->termin = $termin;

    }

    protected function tearDown(): void
    {

        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    /**
     * Der Fall aus der Produktion: ein Raum ohne Sitzplanvorlage. Genau daran
     * ist die Seite gescheitert — die Kapazität war nicht 0, sondern ein
     * Aufruf auf null.
     */
    #[Test]
    public function die_maske_antwortet_auch_ohne_sitzplanvorlage(): void
    {
        $this->zuweisung($this->raum('OHV'));

        $antwort = $this->actingAs($this->planner)
            ->get(route('written-exams.assignments.index', $this->termin));

        $antwort->assertOk();
        $antwort->assertSee('Kurs- und Raumzuordnungen');
        // Ohne Vorlage gibt es keine Plätze — und keinen Absturz.
        $antwort->assertSee('0 / 0');
        // Der Raum steht wirklich da. Die Spalte las vorher $room->name —
        // rooms hat keine solche Spalte, sie blieb in jeder Zeile leer.
        $antwort->assertSee('OHV');
    }

    /**
     * Gegenprobe: Mit Vorlage steht dort auch wirklich eine Kapazität. Ohne
     * sie wäre der Test darüber auch dann grün, wenn die Maske überall 0
     * anzeigte.
     */
    #[Test]
    public function mit_sitzplanvorlage_steht_die_kapazitaet_da(): void
    {
        $raum = $this->raum('MHV');
        $vorlage = $this->vorlageMitPlaetzen(6);

        RoomSeatingTemplate::create([
            'room_id' => $raum->id,
            'seating_template_id' => $vorlage->id,
            'variant' => RoomSeatingTemplate::VARIANT_STANDARD,
        ]);

        $this->zuweisung($raum);

        $antwort = $this->actingAs($this->planner)
            ->get(route('written-exams.assignments.index', $this->termin));

        $antwort->assertOk();
        $antwort->assertSee('0 / 6');
        $antwort->assertSee($vorlage->name);
        $antwort->assertSee('MHV');
    }

    /**
     * Die drei Routennamen, an denen die Maske vorher scheiterte, müssen
     * existieren. Sonst wirft schon das Rendern.
     */
    #[Test]
    public function die_verwendeten_routennamen_gibt_es(): void
    {
        foreach ([
            'written-exams.assignments.show',
            'written-exams.assignments.destroy',
            'written-exams.dates.show',
            'written-exams.dates.day-assign',
        ] as $name) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($name),
                "Routenname {$name} fehlt — die Maske verweist darauf."
            );
        }

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('written-exam-assignments.show'));

        // Am 30.08.2026 entfallen: Die Masken dahinter lasen ein
        // $writtenExamDate, das der Controller nie uebergab, und antworteten
        // seit jeher mit 500. Angelegt wird ueber die Tages-Zuordnung.
        foreach ([
            'written-exams.assignments.create',
            'written-exams.assignments.store',
            'written-exams.assignments.edit',
            'written-exams.assignments.update',
            'written-exams.assignments.edit-all',
            'written-exams.assignments.update-all',
            // Zeigten auf Controller-Methoden, die es nie gab.
            'written-exams.assignments.auto-assign',
            'written-exams.assignments.clear-assignments',
        ] as $name) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Route::has($name),
                "Routenname {$name} ist wieder da — er fuehrt ins Leere."
            );
        }
    }

    private function raum(string $nummer): Room
    {
        return Room::create([
            'room_number' => $nummer,
            'room_type' => 'exam',
            'is_active' => true,
        ]);
    }

    private function zuweisung(Room $raum): WrittenExamAssignment
    {
        $lehrkraft = Teacher::create([
            'shortcode' => 'ZM' . substr($raum->room_number, 0, 1),
            'firstname' => 'Test',
            'lastname' => 'Zuordnung',
        ]);

        $kurs = Course::create([
            'exam_period_id' => 1,
            'subject_id' => Subject::query()->value('id'),
            'course_code' => 'zm' . strtolower($raum->room_number),
            'teacher_id' => $lehrkraft->id,
            'level' => 'grundlegend',
        ]);

        return WrittenExamAssignment::create([
            'written_exam_year_date_id' => $this->termin->id,
            'course_id' => $kurs->id,
            'room_id' => $raum->id,
            'is_hoerverstaendnis' => false,
        ]);
    }

    private function vorlageMitPlaetzen(int $anzahl): SeatingTemplate
    {
        $vorlage = SeatingTemplate::create([
            'name' => 'Zuordnungs-Vorlage',
            'rows' => 1,
            'columns' => $anzahl,
        ]);

        for ($spalte = 1; $spalte <= $anzahl; $spalte++) {
            SeatingTemplateSeat::create([
                'template_id' => $vorlage->id,
                'row_number' => 1,
                'column_number' => $spalte,
                'seat_type' => 'available',
            ]);
        }

        return $vorlage->fresh();
    }
}
