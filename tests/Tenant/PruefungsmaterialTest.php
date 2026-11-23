<?php

namespace Tests\Tenant;

use App\Models\ExamMaterial;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use App\Models\WrittenExamYearDate;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Das Pruefungsmaterial — alle sieben Routen, wirklich abgerufen.
 *
 * Vorgeschichte (31.08.2026): Das Modul war vollstaendig tot, und zwar seit
 * jeher. Drei Masken (create, show, edit) gab es als Datei nicht, drei
 * Weiterleitungen zeigten auf den Routennamen `exam-materials.index`, den es
 * nie gab — und die Liste selbst scheiterte an etwas Drittem:
 *
 *     SQLSTATE[42S02]: Table 'abiplaner.exam_material_subject' doesn't exist
 *
 * `ExamMaterial::subjects()` war ein belongsToMany auf `Subject`. Subject liegt
 * ZENTRAL, Material und Zuordnungstabelle beim MANDANTEN; Eloquent baut die
 * Abfrage auf der Verbindung des Ziels und suchte die Zuordnungstabelle
 * deshalb in der zentralen Datenbank. Das traf `with('subjects')` in der
 * Liste, `subjects()->count()` beim Loeschen und jedes `sync()`.
 *
 * Aufgefallen ist nichts davon, weil das Modul in keinem Menue stand.
 *
 * Der Test ruft daher jede der sieben Routen wirklich ab und schreibt die
 * Zuordnung einmal durch — bis dorthin, wo sie gelesen wird: in die
 * Material-Checkliste der Aufsichtsliste.
 *
 * Ausfuehren:
 * docker compose exec abiplaner_app php vendor/bin/phpunit --filter Pruefungsmaterial
 */
class PruefungsmaterialTest extends TenantTestCase
{
    private const PLANNER_EMAIL = 'material-planer-9999@test.invalid';

    protected School $school;

    protected User $planner;

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

    }

    protected function tearDown(): void
    {

        User::where('email', self::PLANNER_EMAIL)->delete();

        parent::tearDown();
    }

    /**
     * Die Liste. Genau hier schlug der Verbindungsfehler zu — sie laedt die
     * Zahl der zugeordneten Faecher.
     */
    #[Test]
    public function die_uebersicht_antwortet_und_zaehlt_die_faecher(): void
    {
        $material = $this->material('Zweisprachiges Woerterbuch');
        $fach = $this->fach();
        $material->zuordnungenSetzen([$fach->id => ['is_required' => true, 'notes' => null]]);

        $this->material('Geodreieck');

        $antwort = $this->actingAs($this->planner)
            ->get(route('written-exams.materials.index'));

        $antwort->assertOk();
        $antwort->assertSee('Zweisprachiges Woerterbuch');
        $antwort->assertSee('Einem Fach zugeordnet');
        $antwort->assertSee('Keinem Fach zugeordnet');
    }

    #[Test]
    public function das_anlegen_speichert_die_fachzuordnung(): void
    {
        $fach = $this->fach();
        $anderes = $this->fach(1);

        $antwort = $this->actingAs($this->planner)->post(
            route('written-exams.materials.store'),
            [
                'name' => 'Formelsammlung',
                'icon' => '📐',
                'description' => 'Genehmigte Ausgabe',
                'is_default' => '1',
                'faecher' => [
                    $fach->id => ['zugeordnet' => '1', 'pflicht' => '1', 'notiz' => 'nur unkommentiert'],
                    // Nicht angekreuzt: darf nicht in der Zuordnungstabelle landen,
                    // auch wenn eine Notiz danebensteht.
                    $anderes->id => ['zugeordnet' => '0', 'notiz' => 'Resttext aus dem Formular'],
                ],
            ]
        );

        $antwort->assertRedirect(route('written-exams.materials.index'));

        $material = ExamMaterial::query()->where('name', 'Formelsammlung')->firstOrFail();
        $this->assertTrue($material->is_default);

        $zuordnungen = $material->zuordnungen();
        $this->assertSame([$fach->id], $zuordnungen->keys()->all());
        $this->assertTrue($zuordnungen->get($fach->id)->is_required);
        $this->assertSame('nur unkommentiert', $zuordnungen->get($fach->id)->notes);
    }

    /**
     * Gegenprobe zur Verbindung: Die Zeile steht wirklich beim Mandanten. Auf
     * der zentralen Verbindung gibt es die Tabelle nicht — genau daran ist das
     * Modul gescheitert.
     */
    #[Test]
    public function die_zuordnung_steht_in_der_mandantendatenbank(): void
    {
        $material = $this->material('Periodensystem');
        $fach = $this->fach();
        $material->zuordnungenSetzen([$fach->id => ['is_required' => false, 'notes' => null]]);

        $this->assertSame(
            1,
            DB::table(ExamMaterial::PIVOT)
                ->where('exam_material_id', $material->id)->count()
        );
    }

    #[Test]
    public function die_ansicht_zeigt_die_zuordnung_mit_notiz(): void
    {
        $material = $this->material('Historischer Weltatlas');
        $fach = $this->fach();
        $material->zuordnungenSetzen([
            $fach->id => ['is_required' => true, 'notes' => 'Ausgabe ohne Anmerkungen'],
        ]);

        $antwort = $this->actingAs($this->planner)
            ->get(route('written-exams.materials.show', $material));

        $antwort->assertOk();
        $antwort->assertSee('Historischer Weltatlas');
        $antwort->assertSee($fach->name);
        $antwort->assertSee('Ausgabe ohne Anmerkungen');
        $antwort->assertSee('erforderlich');
    }

    #[Test]
    public function das_formular_zeigt_die_bestehende_auswahl(): void
    {
        $material = $this->material('CAS-Rechner');
        $fach = $this->fach();
        $material->zuordnungenSetzen([$fach->id => ['is_required' => true, 'notes' => 'nur Modell X']]);

        $antwort = $this->actingAs($this->planner)
            ->get(route('written-exams.materials.edit', $material));

        $antwort->assertOk();
        $antwort->assertSee('nur Modell X');
        // Das Kaestchen des zugeordneten Fachs steht auf an.
        $antwort->assertSee('{ an: true }', false);

        $this->actingAs($this->planner)
            ->get(route('written-exams.materials.create'))
            ->assertOk();
    }

    #[Test]
    public function das_aendern_ersetzt_die_zuordnung(): void
    {
        $material = $this->material('Taschenrechner');
        $alt = $this->fach();
        $neu = $this->fach(1);

        $material->zuordnungenSetzen([$alt->id => ['is_required' => true, 'notes' => 'alt']]);

        $antwort = $this->actingAs($this->planner)->put(
            route('written-exams.materials.update', $material),
            [
                'name' => 'Wissenschaftlicher Taschenrechner',
                'faecher' => [
                    $neu->id => ['zugeordnet' => '1', 'pflicht' => '0', 'notiz' => ''],
                ],
            ]
        );

        $antwort->assertRedirect(route('written-exams.materials.index'));

        $material->refresh();
        $this->assertSame('Wissenschaftlicher Taschenrechner', $material->name);
        $this->assertFalse($material->is_default, 'Ohne Haken faellt die Grundausstattung weg.');

        $zuordnungen = $material->zuordnungen();
        $this->assertSame([$neu->id], $zuordnungen->keys()->all());
        $this->assertFalse($zuordnungen->get($neu->id)->is_required);
        $this->assertNull($zuordnungen->get($neu->id)->notes, 'Eine leere Notiz wird zu null, nicht zu "".');
    }

    #[Test]
    public function ein_zugeordnetes_material_wird_nicht_geloescht(): void
    {
        $material = $this->material('Woerterbuch Latein');
        $fach = $this->fach();
        $material->zuordnungenSetzen([$fach->id => ['is_required' => true, 'notes' => null]]);

        $antwort = $this->actingAs($this->planner)
            ->delete(route('written-exams.materials.destroy', $material));

        $antwort->assertRedirect(route('written-exams.materials.index'));
        $antwort->assertSessionHas('error');

        $this->assertNotNull(ExamMaterial::query()->find($material->id));
    }

    #[Test]
    public function ohne_zuordnung_laesst_sich_das_material_loeschen(): void
    {
        $material = $this->material('Farbstifte');

        $antwort = $this->actingAs($this->planner)
            ->delete(route('written-exams.materials.destroy', $material));

        $antwort->assertRedirect(route('written-exams.materials.index'));
        $antwort->assertSessionHas('success');

        $this->assertNull(ExamMaterial::query()->find($material->id));
    }

    /**
     * Der Zweck des ganzen Moduls: Was hier zugeordnet wird, steht in der
     * Material-Checkliste der Aufsichtsliste. Ohne diesen Test belegt der Rest
     * nur, dass Zeilen in einer Tabelle landen.
     */
    #[Test]
    public function die_zuordnung_erscheint_in_der_material_checkliste(): void
    {
        $termin = WrittenExamYearDate::query()->orderBy('id')->first();
        $this->assertNotNull($termin, 'Die zentralen Stammdaten brauchen einen Jahrestermin.');

        $faecherDesTermins = $termin->slot->subjects;
        $this->assertNotEmpty($faecherDesTermins, 'Der Jahrestermin braucht mindestens ein Fach.');

        $fach = $faecherDesTermins->first();

        $material = $this->material('Bedeutungswoerterbuch');
        $material->zuordnungenSetzen([
            $fach->id => ['is_required' => true, 'notes' => 'einsprachig'],
        ]);

        $gruppen = $termin->fresh()->materialsBySubject;

        $gruppe = $gruppen->firstWhere(fn (array $g): bool => $g['subject']->id === $fach->id);
        $this->assertNotNull($gruppe, 'Das Fach des Termins muss eine Materialgruppe haben.');

        $gefunden = $gruppe['materials']->firstWhere('id', $material->id);
        $this->assertNotNull($gefunden, 'Das zugeordnete Material fehlt in der Checkliste.');
        $this->assertTrue($gefunden->pivot_is_required);
        $this->assertSame('einsprachig', $gefunden->pivot_notes);
    }

    /**
     * Gegenprobe zur Vorgeschichte: Die Routennamen, auf die drei
     * Weiterleitungen zeigten, gibt es nicht — und die echten gibt es.
     */
    #[Test]
    public function die_routennamen_stimmen(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('exam-materials.index'),
            'Auf diesen Namen zeigten store(), update() und destroy() — es gab ihn nie.'
        );

        foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'] as $teil) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has('written-exams.materials.' . $teil),
                "Routenname written-exams.materials.{$teil} fehlt."
            );
        }
    }

    private function material(string $name): ExamMaterial
    {
        return ExamMaterial::create(['name' => $name, 'is_default' => false]);
    }

    private function fach(int $versatz = 0): Subject
    {
        $fach = Subject::query()->orderedForDisplay()->skip($versatz)->first();
        $this->assertNotNull($fach, 'Der zentrale Faecherkatalog ist leer.');

        return $fach;
    }
}
