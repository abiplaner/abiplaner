<?php

namespace Database\Seeders;

use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Models\Subject;
use App\Models\WrittenExamSlot;
use App\Models\WrittenExamYearDate;
use App\Services\ExternalApplicantStudentExamService;
use App\Services\ExternalApplicantValidationService;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Testschule mit realistisch verteilten, aber vollstaendig erfundenen Daten
 * (Roadmap 1.3).
 *
 * Grundgedanke: Nicht echte Daten anonymisieren, sondern echte *Struktur*
 * nachbilden. `database/seeders/data/testschule-profil.json` enthaelt die aus
 * einer realen Schule erhobenen Kennzahlen und Verteilungen — Kurse je Fach, Anteil
 * erhoehtes Anforderungsniveau, schriftliche und muendliche Pruefungen je Fach,
 * Zahl der Lehrkraefte je Fach, Kopplungsgruppen, Raum- und Aufsichtsstruktur.
 * Personen, Namen und Kennungen erzeugt Faker. Das Profil enthaelt keinerlei
 * Personenbezug und ist deshalb versionierbar.
 *
 * Deterministisch: derselbe `--seed` erzeugt denselben Datenbestand. Damit sind
 * Fehlerbilder reproduzierbar und Abweichungen zwischen zwei Laeufen ein
 * echtes Signal.
 *
 * Aufruf ueber `php artisan testschule:erstellen` (legt bei Bedarf auch den
 * Tenant an) oder direkt ueber
 * `php artisan tenants:seed --tenants=<nr> --class=TestschuleSeeder`.
 */
class TestschuleSeeder extends Seeder
{
    /** Vorgabewert, wenn kein Seed uebergeben wurde (Datum der Profilerhebung). */
    public const STANDARD_SEED = 20260825;

    /** Schuelerzahlen und Verteilungen aus dem Strukturprofil. */
    private array $profil;

    private Generator $faker;

    private int $examPeriodId;

    private ExamPeriod $examPeriod;

    /** @var array<string, int> Fachkuerzel => zentrale subject_id */
    private array $subjectIds = [];

    /** @var array<string, list<int>> Fachkuerzel => Lehrer-IDs mit diesem Fach */
    private array $teachersBySubject = [];

    /** @var list<int> */
    private array $teacherIds = [];

    /** @var list<array{id: int, code: string, subject: string, level: string, teacher_id: int}> */
    private array $courses = [];

    /** @var list<int> */
    private array $abiturientIds = [];

    /** @var list<array{id: int, room_number: string, room_type: string}> */
    private array $rooms = [];

    /** @var list<int> IDs der externen Bewerber, fuer den Sitzplan nach den Belegungen */
    private array $externeBewerberIds = [];

    /**
     * Zwischenspeicher — bewusst **Instanz**felder, nicht `static`.
     *
     * Ein Seeder laeuft in der Testsuite mehrfach im selben PHP-Prozess, und
     * zwar gegen verschiedene Mandanten. Ein statischer Cache haelt dann die
     * IDs der vorigen Schule fest und traegt sie in die naechste hinueber —
     * ein Fehler, der erst auffaellt, wenn ein Lauf einem anderen folgt.
     *
     * Gefunden bei der Suche nach dem Determinismus-Bruch vom 28.08.2026.
     * Dessen Ursache war er nicht (die lag in `Faker\Generator::__destruct()`,
     * siehe run()), aber falsch waren die statischen Caches trotzdem.
     *
     * @var array<string, list<int>>
     */
    private array $platzCache = [];

    /** @var array<string, int|null> */
    private array $terminCache = [];

    /** @var array<string, int|null> */
    private array $externesFachCache = [];

    public function run(): void
    {
        $this->guardEnvironment();

        $this->profil = $this->ladeProfil();
        $seed = (int) (config('testschule.seed') ?? self::STANDARD_SEED);

        // `Faker\Generator::__destruct()` ruft `seed()` ohne Argument und
        // damit `mt_srand()` ohne Saat: **jeder aufgeraeumte Faker-Generator
        // wuerfelt den globalen Zufallsgenerator neu**. Dieser Seeder zieht
        // aus genau diesem Generator, und Faker selbst zieht auch daraus.
        //
        // Der Befund vom 28.08.2026: Der Determinismus-Test fiel nur im
        // Klassenlauf, isoliert war er gruen. Ursache waren Faker-Generatoren
        // aus frueheren Tests, die als Muell liegen blieben. Irgendwann
        // mitten im Lauf raeumte die Zyklensammlung sie ab — gemessen
        // waehrend eines Insert-Blocks, und die Stelle wanderte von Lauf zu
        // Lauf. Ab dort lief der Seeder auf einer voellig anderen
        // Zufallsfolge; die Suche nach der alten Folge blieb ueber fuenf
        // Millionen Ziehungen erfolglos, es war also eine neue Saat und kein
        // Weiterzaehlen.
        //
        // Deshalb zwei Riegel: erst den Rueckstand einsammeln, solange das
        // noch nichts kostet, und dann die Zyklensammlung fuer die Dauer des
        // Laufs stilllegen. Ohne den zweiten Riegel kann jeder spaeter
        // entstehende Generator dasselbe noch einmal ausloesen.
        gc_collect_cycles();

        $this->faker = FakerFactory::create('de_DE');
        $this->faker->seed($seed);
        mt_srand($seed);

        $this->examPeriod = $this->resolveExamPeriod();
        $this->examPeriodId = (int) $this->examPeriod->id;

        $this->command->info("🧪 Testschule wird erzeugt (Seed {$seed})");
        $this->command->info("📆 Prüfungszeitraum: {$this->examPeriod->name} (ID {$this->examPeriodId})");
        $this->command->info('');

        $zyklensammlungLief = gc_enabled();
        gc_disable();

        try {
            $this->seedeBestand();
        } finally {
            if ($zyklensammlungLief) {
                gc_enable();
            }
        }

        $this->pruefeErgebnis();
    }

    /**
     * Der eigentliche Lauf — ausgelagert, damit run() die Zyklensammlung
     * sauber um ihn herum stilllegen und wieder anschalten kann.
     */
    private function seedeBestand(): void
    {
        DB::transaction(function (): void {
            $this->leereZieltabellen();
            $this->ladeFaecher();
            $this->seedSitzvorlagen();
            $this->seedRaeume();
            $this->seedLehrer();
            $this->seedSchueler();
            $this->seedKurse();
            $this->seedPruefungen();
            // Die externen Bewerber stehen vor dem Kolloquiumsplan, damit ihre
            // muendlichen Pruefungen mitgeplant werden: bildeSitzungen() fragt
            // student_exams ohne Ruecksicht auf student_id, und ein
            // Terminkonflikt mit einem externen Pruefling war genau der Fall,
            // den die Konflikterkennung einmal nicht gemeldet hat.
            $this->seedExterneBewerber();
            $this->seedWahlen();
            $this->seedAuszeichnungen();
            $this->seedPruefungsausschuss();
            $this->seedAbsenzen();
            $this->seedStundenplan();
            $this->seedKolloquiumsplan();
            $this->seedKolloquiumsaufsichten();
            $this->seedKolloquiumsthemen();
            $this->seedSchriftlichePruefungen();
            // Der Sitzplatz braucht die Belegungen, die es erst jetzt gibt.
            $this->seedExterneSitzplaetze();
            $this->seedZusatzpruefungen();
            $this->seedRandbedingungen();
        });
    }

    /**
     * Der Seeder erzeugt Massendaten und leert vorher Tabellen — auf einer
     * produktiven Instanz waere das ein Totalverlust. Deshalb ein harter Riegel
     * statt eines Hinweises.
     */
    private function guardEnvironment(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'TestschuleSeeder darf nicht in der Produktivumgebung laufen.'
            );
        }

        if (!\App\Models\School::query()->exists()) {
            throw new RuntimeException(
                'TestschuleSeeder ohne eingerichtete Schule aufgerufen. Aufruf über '
                . '"php artisan testschule:erstellen".'
            );
        }
    }

    private function ladeProfil(): array
    {
        $pfad = database_path('seeders/data/testschule-profil.json');

        if (!is_file($pfad)) {
            throw new RuntimeException("Strukturprofil nicht gefunden: {$pfad}");
        }

        $inhalt = file_get_contents($pfad);

        if ($inhalt === false) {
            throw new RuntimeException("Strukturprofil nicht lesbar: {$pfad}");
        }

        $profil = json_decode($inhalt, true);

        if (!is_array($profil)) {
            throw new RuntimeException("Strukturprofil ist kein gültiges JSON: {$pfad}");
        }

        return $profil;
    }

    private function resolveExamPeriod(): ExamPeriod
    {
        $examPeriod = ExamPeriod::query()->orderByDesc('year')->first();

        if (!$examPeriod instanceof ExamPeriod) {
            throw new RuntimeException(
                'Kein Prüfungszeitraum in der zentralen Datenbank vorhanden.'
            );
        }

        return $examPeriod;
    }

    /**
     * Alles, was dieser Seeder erzeugt, wird vorher entfernt — sonst waere ein
     * zweiter Lauf weder wiederholbar noch deterministisch. Die Reihenfolge
     * folgt den Fremdschluesseln von innen nach aussen.
     */
    private function leereZieltabellen(): void
    {
        $tabellen = [
            // Zusatzpruefungen von innen nach aussen: erst was auf die
            // Pruefung zeigt, dann die Pruefung, dann Raeume und Slots, dann
            // der Tag. zusatzpruef_exam_scores haengt an student_exams und
            // muss deshalb vor diesen fallen.
            'zusatzpruef_assignments',
            'zusatzpruef_pruefer',
            'zusatzpruef_aufsichten',
            'zusatzpruef_exam_scores',
            'zusatzpruef_student_deficits',
            'zusatzpruefungen',
            'zusatzpruef_day_timeslots',
            'zusatzpruef_day_rooms',
            'zusatzpruef_days',
            'colloquium_topics',
            'written_exam_seat_assignments',
            'written_exam_assignments',
            'written_exam_supervision_time_slots',
            'written_exam_supervision_grids',
            'supervisions',
            'supervision_grids',
            'exam_assignments',
            'student_exams',
            // Externe Bewerber: erst die Faecher (student_exams zeigt mit
            // external_exam_subject_id darauf), dann die Bewerber, dann die
            // Ersatzschulen. SoftDeletes spielen hier keine Rolle — DELETE
            // ueber den Query Builder entfernt die Zeile wirklich.
            'external_exam_subjects',
            'external_applicants',
            'ersatzschulen',
            // LK- und W-Seminar-Wahlen: erst die Wahlen, dann die Kurse.
            'lk_wahlen',
            'lk_kurse',
            'wseminar_wahlen',
            'wseminar_kurse',
            'lk_zuteilungs_historie',
            // Auszeichnungen und Pruefungsausschuss: erst die Zeilen, die auf
            // die Stammdaten zeigen.
            'awards',
            'award_types',
            'award_descriptions',
            'exam_board_meeting_attendees',
            'exam_board_meetings',
            // Absenzen vor den Importen: absences.absence_import_id ist
            // `restrict`, nicht `cascade`.
            'absences',
            'absence_imports',
            'absence_measures',
            'absence_carryovers',
            'timetable_entries',
            'school_events',
            'course_tracks',
            'oral_exam_day_rooms',
            'student_blocked_days',
            'teacher_unavailability',
            'courses',
            'students',
            'teacher_subject',
            'teachers',
            'room_seating_templates',
            'rooms',
            'seating_template_seats',
            'seating_templates',
        ];

        // Bewusst DELETE statt TRUNCATE: TRUNCATE loest in MySQL einen
        // impliziten Commit aus und beendet damit die umgebende Transaktion —
        // der anschliessende Commit laeuft dann in „There is no active
        // transaction". Mit DELETE bleibt der ganze Seeder atomar.
        // Portabel (MariaDB: SET FOREIGN_KEY_CHECKS, SQLite: PRAGMA foreign_keys).
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        foreach ($tabellen as $tabelle) {
            DB::table($tabelle)->delete();
        }

        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $this->command->info('🧹 Zieltabellen geleert (' . count($tabellen) . ' Tabellen).');
    }

    /**
     * Faecher liegen zentral und werden nicht erzeugt, sondern nur zugeordnet.
     * Faecher aus dem Profil, die zentral fehlen, werden uebersprungen statt
     * still falsch verknuepft.
     */
    private function ladeFaecher(): void
    {
        $zentral = Subject::query()->get()->keyBy('code');
        $fehlend = [];

        foreach ($this->profil['faecher'] as $fach) {
            $code = $fach['code'];

            if (!isset($zentral[$code])) {
                $fehlend[] = $code;

                continue;
            }

            $this->subjectIds[$code] = (int) $zentral[$code]->id;
        }

        if ($fehlend !== []) {
            $this->command->warn(
                '⚠️  Nicht zentral vorhandene Fächer übersprungen: ' . implode(', ', $fehlend)
            );
        }

        $this->command->info('📚 ' . count($this->subjectIds) . ' Fächer zugeordnet.');
    }

    /**
     * Sitzvorlagen samt Plaetzen. Jede Vorlage bekommt eine Lehrerpult-Zeile,
     * einen Mittelgang und einzelne gesperrte Plaetze — genau die Faelle, an
     * denen die Sitzverteilung in der Praxis haengt.
     */
    private function seedSitzvorlagen(): void
    {
        $jetzt = Carbon::now();
        $plaetze = 0;

        foreach ($this->profil['sitzvorlagen'] as $index => $vorlage) {
            $templateId = DB::table('seating_templates')->insertGetId([
                'name' => $vorlage['name'],
                'description' => 'Sitzvorlage der Testschule',
                'rows' => $vorlage['reihen'],
                'columns' => $vorlage['spalten'],
                'door_positions' => json_encode([['side' => $index % 2 === 0 ? 'left' : 'right', 'index' => 1]]),
                'orientation' => $index % 2 === 0 ? 'bottom' : 'top',
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);

            $sitze = [];
            $gangSpalte = (int) ceil($vorlage['spalten'] / 2);

            for ($reihe = 1; $reihe <= $vorlage['reihen']; $reihe++) {
                for ($spalte = 1; $spalte <= $vorlage['spalten']; $spalte++) {
                    $typ = 'available';

                    if ($reihe === 1 && $spalte === 1) {
                        $typ = 'teacher_desk';
                    } elseif ($spalte === $gangSpalte && $vorlage['spalten'] > 3) {
                        $typ = 'aisle';
                    } elseif ($reihe === $vorlage['reihen'] && $spalte === $vorlage['spalten']) {
                        $typ = 'blocked';
                    }

                    $sitze[] = [
                        'template_id' => $templateId,
                        'row_number' => $reihe,
                        'column_number' => $spalte,
                        'seat_type' => $typ,
                        'notes' => null,
                        'created_at' => $jetzt,
                        'updated_at' => $jetzt,
                    ];
                }
            }

            DB::table('seating_template_seats')->insert($sitze);
            $plaetze += count($sitze);
        }

        $this->command->info(
            '🪑 ' . count($this->profil['sitzvorlagen']) . ' Sitzvorlagen mit ' . $plaetze . ' Plätzen.'
        );
    }

    /**
     * Raeume gemaess Profil. Pruefungsraeume bekommen eine Standard-Sitzvorlage
     * und zusaetzlich eine Hoerverstaendnis-Variante — der Fall, der frueher zu
     * doppelt angelegten Raeumen gefuehrt hat (Roadmap 1.5).
     */
    private function seedRaeume(): void
    {
        $jetzt = Carbon::now();
        $vorlagen = DB::table('seating_templates')->orderBy('id')->pluck('id')->all();
        $nummer = 1;
        $varianten = 0;

        foreach ($this->profil['raeume'] as $typ => $anzahl) {
            for ($i = 0; $i < $anzahl; $i++) {
                $raumNummer = match ($typ) {
                    'exam' => 'R' . str_pad((string) (100 + $nummer), 3, '0', STR_PAD_LEFT),
                    'preparation' => 'V' . ($i + 1),
                    'waiting' => 'W' . ($i + 1),
                    'corridor' => 'Gang ' . ($i + 1),
                    default => 'S' . ($i + 1),
                };

                $vorlageId = $typ === 'exam' ? $vorlagen[$nummer % count($vorlagen)] : null;

                $raumId = DB::table('rooms')->insertGetId([
                    'room_number' => $raumNummer,
                    'room_type' => $typ,
                    'notes' => null,
                    'seating_template_id' => $vorlageId,
                    'is_active' => true,
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ]);

                $this->rooms[] = ['id' => $raumId, 'room_number' => $raumNummer, 'room_type' => $typ];

                if ($vorlageId !== null) {
                    DB::table('room_seating_templates')->insert([
                        [
                            'room_id' => $raumId,
                            'variant' => 'standard',
                            'seating_template_id' => $vorlageId,
                            'created_at' => $jetzt,
                            'updated_at' => $jetzt,
                        ],
                        [
                            'room_id' => $raumId,
                            'variant' => 'hoerverstaendnis',
                            'seating_template_id' => $vorlagen[($nummer + 1) % count($vorlagen)],
                            'created_at' => $jetzt,
                            'updated_at' => $jetzt,
                        ],
                    ]);
                    $varianten += 2;
                }

                $nummer++;
            }
        }

        $this->command->info(
            '🏢 ' . count($this->rooms) . ' Räume mit ' . $varianten . ' Sitzvarianten.'
        );
    }

    /**
     * Lehrkraefte mit Fachzuordnung. Die Zahl der Lehrkraefte je Fach stammt
     * aus dem Profil; jede Lehrkraft bekommt ein bis drei Faecher, damit
     * Kopplungen und Aufsichtsverteilung realistische Ueberschneidungen haben.
     */
    private function seedLehrer(): void
    {
        $jetzt = Carbon::now();
        $anzahl = (int) $this->profil['lehrer']['anzahl'];
        $kuerzel = [];

        for ($i = 0; $i < $anzahl; $i++) {
            $vorname = $this->faker->firstName();
            $nachname = $this->faker->lastName();

            $teacherId = DB::table('teachers')->insertGetId([
                'external_id' => $this->faker->uuid(),
                'shortcode' => $this->eindeutigesKuerzel($nachname, $kuerzel),
                'firstname' => $vorname,
                'lastname' => $nachname,
                'can_supervise' => true,
                'is_active' => true,
                'employment_type' => $this->faker->randomElement($this->profil['lehrer']['beschaeftigungsarten']),
                'hours' => $this->faker->randomElement($this->profil['lehrer']['wochenstunden']),
                'is_available_for_supervision' => true,
                'supervision_preference_weight' => 50,
                'max_supervisions_per_day' => (int) $this->profil['lehrer']['max_aufsichten_je_tag'],
                'title' => $this->faker->boolean((int) ($this->profil['lehrer']['anteil_amtsbezeichnung'] * 100))
                    ? $this->faker->randomElement($this->profil['lehrer']['amtsbezeichnungen'])
                    : null,
                'gender' => $this->faker->randomElement(['m', 'w']),
                'contact_phone' => null,
                'contact_email' => null,
                'contact_bycs' => false,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);

            $this->teacherIds[] = $teacherId;
        }

        // Fachzuordnung: je Fach so viele Lehrkraefte wie im Profil hinterlegt.
        $zuordnungen = [];

        foreach ($this->profil['faecher'] as $fach) {
            $code = $fach['code'];

            if (!isset($this->subjectIds[$code])) {
                continue;
            }

            $gewuenscht = min((int) $fach['lehrer'], count($this->teacherIds));
            $gewuenscht = max($gewuenscht, 2); // jedes Fach braucht Prüfer und Beisitzer
            $auswahl = $this->faker->randomElements($this->teacherIds, $gewuenscht);

            $this->teachersBySubject[$code] = array_values($auswahl);

            foreach ($auswahl as $teacherId) {
                $zuordnungen[] = [
                    'teacher_id' => $teacherId,
                    'subject_id' => $this->subjectIds[$code],
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ];
            }
        }

        foreach (array_chunk($zuordnungen, 500) as $block) {
            DB::table('teacher_subject')->insert($block);
        }

        $this->command->info(
            '👩‍🏫 ' . count($this->teacherIds) . ' Lehrkräfte mit ' . count($zuordnungen) . ' Fachzuordnungen.'
        );
    }

    /**
     * Eindeutiges Lehrerkuerzel aus dem Nachnamen.
     *
     * Umlaute werden umschrieben, bevor gekuerzt wird: der Unique-Index auf
     * `teachers.shortcode` liegt auf einer `..._unicode_ci`-Spalte, dort gelten
     * „Völ" und „Vol" als derselbe Wert. Eine Pruefung auf Zeichengleichheit in
     * PHP wuerde die Kollision also uebersehen und erst die Datenbank abbrechen
     * lassen.
     *
     * @param array<string, true> $vergeben
     */
    private function eindeutigesKuerzel(string $nachname, array &$vergeben): string
    {
        $umschrift = strtr($nachname, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        ]);

        $basis = preg_replace('/[^A-Za-z]/', '', $umschrift);
        $basis = ($basis === null || $basis === '') ? 'Xxx' : $basis;
        $basis = ucfirst(strtolower(substr($basis, 0, 3)));

        $kandidat = $basis;
        $zaehler = 1;

        while (isset($vergeben[strtolower($kandidat)])) {
            $kandidat = $basis . $zaehler;
            $zaehler++;
        }

        $vergeben[strtolower($kandidat)] = true;

        return substr($kandidat, 0, 10);
    }

    /**
     * Schueler aller drei Jahrgangsstufen. Nur die Jahrgangsstufe 13 sind
     * Prueflinge; 11 und 12 existieren, weil Absenzen, Kurswahlen und der
     * Schuljahreswechsel sie brauchen.
     */
    private function seedSchueler(): void
    {
        $jetzt = Carbon::now();
        $anteilNachteilsausgleich = (float) $this->profil['pruefung']['anteil_nachteilsausgleich'];
        $anteilNichtBestanden = (float) $this->profil['pruefung']['anteil_nicht_bestanden'];
        $reihen = [];

        foreach ($this->profil['jahrgaenge'] as $stufe => $anzahl) {
            for ($i = 0; $i < $anzahl; $i++) {
                $nachteilsausgleich = $this->faker->boolean((int) ($anteilNachteilsausgleich * 100));

                $reihen[] = [
                    'exam_period_id' => $this->examPeriodId,
                    'external_id' => $this->faker->uuid(),
                    'grade' => (string) $stufe,
                    'bestanden' => (string) $stufe === '13'
                        ? !$this->faker->boolean((int) ($anteilNichtBestanden * 100))
                        : true,
                    'firstname' => $this->faker->firstName(),
                    'lastname' => $this->faker->lastName(),
                    'class_name' => null,
                    'disability_compensation_percent' => $nachteilsausgleich
                        ? $this->faker->randomElement([25, 33, 50])
                        : null,
                    'disability_compensation_notes' => $nachteilsausgleich
                        ? 'Zeitzuschlag laut Bescheid (Testdatum)'
                        : null,
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ];
            }
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('students')->insert($block);
        }

        $this->abiturientIds = DB::table('students')
            ->where('grade', '13')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->command->info(
            '🎓 ' . count($reihen) . ' Schüler angelegt, davon ' . count($this->abiturientIds) . ' Prüflinge (Jgst. 13).'
        );
    }

    /**
     * Kurse je Fach, aufgeteilt nach Anforderungsniveau. Die Kurscodes folgen
     * der in Bayern ueblichen Schreibweise („2d2" grundlegend, „2D-L1"
     * erhoeht), damit die Importe und die Fachkuerzel-Erkennung
     * (StudentCsvImportService::extractSubjectCode) daran arbeiten koennen.
     */
    private function seedKurse(): void
    {
        $jetzt = Carbon::now();

        foreach ($this->profil['faecher'] as $fach) {
            $code = $fach['code'];

            if (!isset($this->subjectIds[$code])) {
                continue;
            }

            $lehrkraefte = $this->teachersBySubject[$code];

            foreach (['erhoeht' => 'kurse_erhoeht', 'grundlegend' => 'kurse_grundlegend'] as $niveau => $schluessel) {
                for ($n = 1; $n <= (int) $fach[$schluessel]; $n++) {
                    $kursCode = $niveau === 'erhoeht'
                        ? '2' . $code . '-L' . $n
                        : '2' . mb_strtolower($code) . $n;

                    $lehrer = $lehrkraefte[($n - 1) % count($lehrkraefte)];
                    $zweitkorrektor = $lehrkraefte[$n % count($lehrkraefte)];

                    $kursId = DB::table('courses')->insertGetId([
                        'exam_period_id' => $this->examPeriodId,
                        'subject_id' => $this->subjectIds[$code],
                        'course_code' => $kursCode,
                        'teacher_id' => $lehrer,
                        'second_corrector_id' => $zweitkorrektor === $lehrer ? null : $zweitkorrektor,
                        'level' => $niveau,
                        'created_at' => $jetzt,
                        'updated_at' => $jetzt,
                    ]);

                    $this->courses[] = [
                        'id' => $kursId,
                        'code' => $kursCode,
                        'subject' => $code,
                        'level' => $niveau,
                        'teacher_id' => $lehrer,
                    ];
                }
            }
        }

        $this->command->info('📗 ' . count($this->courses) . ' Kurse angelegt.');
    }

    /**
     * Fuenf Abiturpruefungen je Prüfling: drei schriftliche, zwei muendliche.
     *
     * Die Faecherwahl wird nicht gleichverteilt gewuerfelt, sondern nach den
     * im Profil hinterlegten Haeufigkeiten gezogen. Dadurch entstehen
     * dieselben Groessenverhaeltnisse wie in der Wirklichkeit: grosse
     * Deutsch- und Mathematikkohorten, ein langer Schwanz kleiner Faecher mit
     * ein bis zwei Prueflingen — genau die Faelle, an denen Planung und
     * Exporte gerne brechen.
     */
    private function seedPruefungen(): void
    {
        $jetzt = Carbon::now();
        $schriftlichGewichte = [];
        $muendlichGewichte = [];

        foreach ($this->profil['faecher'] as $fach) {
            $code = $fach['code'];

            if (!isset($this->subjectIds[$code])) {
                continue;
            }

            if ((int) $fach['schriftlich'] > 0) {
                $schriftlichGewichte[$code] = (int) $fach['schriftlich'];
            }

            if ((int) $fach['muendlich'] > 0) {
                $muendlichGewichte[$code] = (int) $fach['muendlich'];
            }
        }

        $reihen = [];
        $ohneTermin = 0;

        foreach ($this->abiturientIds as $studentId) {
            $schriftlich = $this->ziehe($schriftlichGewichte, (int) $this->profil['pruefung']['schriftlich_je_schueler'], []);
            $muendlich = $this->ziehe($muendlichGewichte, (int) $this->profil['pruefung']['muendlich_je_schueler'], $schriftlich);

            foreach ([['written', $schriftlich], ['oral', $muendlich]] as [$typ, $faecher]) {
                foreach ($faecher as $fachCode) {
                    $kurs = $this->waehleKurs($fachCode);

                    if ($kurs === null) {
                        continue;
                    }

                    $yearDateId = $typ === 'written'
                        ? $this->writtenExamYearDateId($fachCode, $kurs['level'])
                        : null;

                    if ($typ === 'written' && $yearDateId === null) {
                        $ohneTermin++;
                    }

                    $reihen[] = [
                        'student_id' => $studentId,
                        'course_id' => $kurs['id'],
                        'examiner_id' => $kurs['teacher_id'],
                        'assessor_id' => $typ === 'oral' ? $this->beisitzer($fachCode, $kurs['teacher_id']) : null,
                        'second_corrector_id' => $typ === 'written' ? $this->beisitzer($fachCode, $kurs['teacher_id']) : null,
                        'coupling_id' => null,
                        'exam_type' => $typ,
                        'preferred_week' => null,
                        'written_exam_year_date_id' => $yearDateId,
                        'external_exam_subject_id' => null,
                        'created_at' => $jetzt,
                        'updated_at' => $jetzt,
                    ];
                }
            }
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('student_exams')->insert($block);
        }

        $this->seedKopplungen();

        $meldung = '📝 ' . count($reihen) . ' Prüfungen angelegt';

        if ($ohneTermin > 0) {
            $meldung .= " ({$ohneTermin} schriftliche ohne zentralen Termin)";
        }

        $this->command->info($meldung . '.');
    }

    /**
     * Gewichtete Ziehung ohne Zuruecklegen.
     *
     * @param array<string, int> $gewichte
     * @param list<string>       $ausgeschlossen
     *
     * @return list<string>
     */
    private function ziehe(array $gewichte, int $anzahl, array $ausgeschlossen): array
    {
        foreach ($ausgeschlossen as $code) {
            unset($gewichte[$code]);
        }

        $gezogen = [];

        for ($i = 0; $i < $anzahl && $gewichte !== []; $i++) {
            $summe = array_sum($gewichte);
            $wurf = mt_rand(1, max(1, $summe));
            $laufend = 0;

            foreach ($gewichte as $code => $gewicht) {
                $laufend += $gewicht;

                if ($laufend >= $wurf) {
                    $gezogen[] = $code;
                    unset($gewichte[$code]);

                    break;
                }
            }
        }

        return $gezogen;
    }

    /**
     * @return array{id: int, code: string, subject: string, level: string, teacher_id: int}|null
     */
    private function waehleKurs(string $fachCode): ?array
    {
        $kandidaten = array_values(array_filter(
            $this->courses,
            fn (array $kurs) => $kurs['subject'] === $fachCode
        ));

        if ($kandidaten === []) {
            return null;
        }

        return $kandidaten[mt_rand(0, count($kandidaten) - 1)];
    }

    private function beisitzer(string $fachCode, int $nicht): ?int
    {
        $kandidaten = array_values(array_filter(
            $this->teachersBySubject[$fachCode] ?? [],
            fn (int $id) => $id !== $nicht
        ));

        if ($kandidaten === []) {
            return null;
        }

        return $kandidaten[mt_rand(0, count($kandidaten) - 1)];
    }

    /**
     * Zentraler schriftlicher Prüfungstermin zu Fach und Anforderungsniveau.
     *
     * Dieselbe Aufloesung wie StudentCsvImportService::findWrittenExamDateId —
     * ueber die Zuordnung Fach → WrittenExamSlot → juengster Jahrestermin.
     */
    private function writtenExamYearDateId(string $fachCode, string $niveau): ?int
    {
        $schluessel = $fachCode . '|' . $niveau;

        if (array_key_exists($schluessel, $this->terminCache)) {
            return $this->terminCache[$schluessel];
        }

        $slot = WrittenExamSlot::query()            ->whereHas('subjects', fn ($q) => $q->where('subject_id', $this->subjectIds[$fachCode]))
            ->where('requirement_level', $niveau)
            ->first();

        if (!$slot instanceof WrittenExamSlot) {
            return $this->terminCache[$schluessel] = null;
        }

        // Das Jahr des Prüfungszeitraums, nicht das neueste: Die Termindateien
        // bringen das Folgejahr oft schon mit (BayernTermine).
        $yearDate = WrittenExamYearDate::query()
            ->where('written_exam_slot_id', $slot->id)
            ->where('exam_year', (int) DB::table('exam_periods')->where('id', $this->examPeriodId)->value('year'))
            ->first();

        return $this->terminCache[$schluessel] = $yearDate instanceof WrittenExamYearDate ? (int) $yearDate->id : null;
    }

    /**
     * Inhaltliche Kopplungen: Prueflinge desselben Kurses werden zu Gruppen von
     * zwei bis vier zusammengefasst und spaeter gemeinsam geprueft. Das ist der
     * Normalfall beim Kolloquium und der Grund, weshalb die Tagesverteilung
     * nicht einfach „ein Prüfling je Slot" rechnen darf (Roadmap 4.5/4.7).
     */
    private function seedKopplungen(): void
    {
        $ziel = (int) $this->profil['kolloquium']['kopplungsgruppen'];
        $min = (int) $this->profil['kolloquium']['kopplung_groesse_min'];
        $max = (int) $this->profil['kolloquium']['kopplung_groesse_max'];

        $nachKurs = DB::table('student_exams')
            ->where('exam_type', 'oral')
            ->orderBy('course_id')
            ->orderBy('id')
            ->get(['id', 'course_id'])
            ->groupBy('course_id');

        $gruppen = 0;

        foreach ($nachKurs as $pruefungen) {
            $ids = $pruefungen->pluck('id')->all();

            while (count($ids) >= $min && $gruppen < $ziel) {
                $groesse = min(mt_rand($min, $max), count($ids));
                $gruppe = array_splice($ids, 0, $groesse);

                DB::table('student_exams')
                    ->whereIn('id', $gruppe)
                    ->update(['coupling_id' => $this->faker->uuid()]);

                $gruppen++;
            }

            if ($gruppen >= $ziel) {
                break;
            }
        }

        $this->command->info('🔗 ' . $gruppen . ' Kopplungsgruppen gebildet.');
    }

    /**
     * Kolloquiumsplan: Tagesraeume und die Zuordnung jeder muendlichen Pruefung
     * zu Slot und Raum.
     *
     * Gekoppelte Prueflinge werden gemeinsam gesetzt (ein Termin, ein Raum),
     * und je Slot darf eine Lehrkraft nur einmal vorkommen — dieselben
     * Nebenbedingungen, gegen die die Optimierung arbeitet.
     */
    private function seedKolloquiumsplan(): void
    {
        $jetzt = Carbon::now();

        $slots = DB::table('time_slots')
            ->where('exam_period_id', $this->examPeriodId)
            ->orderBy('date')
            ->orderBy('slot_number')
            ->get(['id', 'date', 'slot_number']);

        if ($slots->isEmpty()) {
            throw new RuntimeException(
                'Keine Zeitslots im Prüfungszeitraum. Läuft der TenantSeeder für diese Schule?'
            );
        }

        $pruefungsraeume = $this->raeumeVomTyp('exam');
        $vorbereitungsraeume = $this->raeumeVomTyp('preparation');
        $warteraeume = $this->raeumeVomTyp('waiting');
        $gangraeume = $this->raeumeVomTyp('corridor');

        $tage = $slots->pluck('date')->unique()->values();
        $raeumeJeTag = (int) $this->profil['kolloquium']['raeume_je_tag'];
        $pruefungsraeumeJeTag = max(1, $raeumeJeTag - count($vorbereitungsraeume) - count($warteraeume) - count($gangraeume));
        $pruefungsraeumeJeTag = min($pruefungsraeumeJeTag, count($pruefungsraeume));

        $tagesraeume = [];
        $reihen = [];

        foreach ($tage as $tag) {
            $sortierung = 0;
            $tagesPruefungsraeume = array_slice($pruefungsraeume, 0, $pruefungsraeumeJeTag);
            $tagesraeume[$tag] = $tagesPruefungsraeume;

            $zuweisungen = [
                'exam' => $tagesPruefungsraeume,
                'preparation' => $vorbereitungsraeume,
                'waiting' => $warteraeume,
                'corridor' => $gangraeume,
            ];

            foreach ($zuweisungen as $rolle => $raumIds) {
                foreach ($raumIds as $raumId) {
                    $reihen[] = [
                        'exam_period_id' => $this->examPeriodId,
                        'date' => $tag,
                        'room_id' => $raumId,
                        'room_role' => $rolle,
                        'sort_order' => $sortierung++,
                        'created_at' => $jetzt,
                        'updated_at' => $jetzt,
                    ];
                }
            }
        }

        DB::table('oral_exam_day_rooms')->insert($reihen);

        $sitzungen = $this->bildeSitzungen();
        $offen = $sitzungen;
        $zuordnungen = [];
        $ersterTag = $tage->first();
        $wochenwechsel = $tage->get((int) floor($tage->count() / 2));

        foreach ($slots as $slot) {
            if ($offen === []) {
                break;
            }

            $belegteLehrer = [];
            $woche = $slot->date < $wochenwechsel ? 1 : 2;
            $vorbereitungsraum = $vorbereitungsraeume === [] ? null : $vorbereitungsraeume[0];

            foreach ($tagesraeume[$slot->date] as $raumId) {
                $index = $this->naechsteSitzung($offen, $belegteLehrer);

                if ($index === null) {
                    continue;
                }

                $sitzung = $offen[$index];
                unset($offen[$index]);
                $offen = array_values($offen);

                foreach ($sitzung['lehrer'] as $lehrerId) {
                    $belegteLehrer[$lehrerId] = true;
                }

                foreach ($sitzung['pruefungen'] as $pruefungId) {
                    $zuordnungen[] = [
                        'student_exam_id' => $pruefungId,
                        'exam_week' => $woche,
                        'time_slot_id' => $slot->id,
                        'exam_room_id' => $raumId,
                        'prep_room_id' => $vorbereitungsraum,
                        'status' => 'planned',
                        'cancelled_reason' => null,
                        'replaces_id' => null,
                        'free_date' => null,
                        'free_time_start' => null,
                        'free_time_end' => null,
                        'is_locked' => false,
                        'lock_reason' => null,
                        'notes' => null,
                        'created_at' => $jetzt,
                        'updated_at' => $jetzt,
                    ];
                }
            }
        }

        foreach (array_chunk($zuordnungen, 200) as $block) {
            DB::table('exam_assignments')->insert($block);
        }

        $this->command->info(
            '🗓  ' . count($reihen) . ' Tagesräume, ' . count($zuordnungen) . ' Prüfungstermine gesetzt'
            . ($offen === [] ? '' : ' — ' . count($offen) . ' Sitzungen ohne Platz')
            . ' (erster Tag ' . $ersterTag . ').'
        );
    }

    /**
     * @return list<int>
     */
    private function raeumeVomTyp(string $typ): array
    {
        return array_values(array_map(
            fn (array $raum) => $raum['id'],
            array_filter($this->rooms, fn (array $raum) => $raum['room_type'] === $typ)
        ));
    }

    /**
     * Eine Sitzung ist entweder eine Kopplungsgruppe oder eine einzelne
     * Pruefung. Gekoppelte Prueflinge muessen zusammen bleiben.
     *
     * @return list<array{pruefungen: list<int>, lehrer: list<int>}>
     */
    private function bildeSitzungen(): array
    {
        $pruefungen = DB::table('student_exams')
            ->where('exam_type', 'oral')
            ->orderBy('id')
            ->get(['id', 'coupling_id', 'examiner_id', 'assessor_id']);

        $sitzungen = [];
        $gruppen = [];

        foreach ($pruefungen as $pruefung) {
            $lehrer = array_values(array_filter([
                (int) $pruefung->examiner_id,
                $pruefung->assessor_id === null ? null : (int) $pruefung->assessor_id,
            ]));

            if ($pruefung->coupling_id === null) {
                $sitzungen[] = ['pruefungen' => [(int) $pruefung->id], 'lehrer' => $lehrer];

                continue;
            }

            $gruppen[$pruefung->coupling_id]['pruefungen'][] = (int) $pruefung->id;
            $gruppen[$pruefung->coupling_id]['lehrer'] = array_unique(array_merge(
                $gruppen[$pruefung->coupling_id]['lehrer'] ?? [],
                $lehrer
            ));
        }

        foreach ($gruppen as $gruppe) {
            $sitzungen[] = [
                'pruefungen' => $gruppe['pruefungen'],
                'lehrer' => array_values($gruppe['lehrer']),
            ];
        }

        return $sitzungen;
    }

    /**
     * @param list<array{pruefungen: list<int>, lehrer: list<int>}> $offen
     * @param array<int, true>                                     $belegteLehrer
     */
    private function naechsteSitzung(array $offen, array $belegteLehrer): ?int
    {
        foreach ($offen as $index => $sitzung) {
            foreach ($sitzung['lehrer'] as $lehrerId) {
                if (isset($belegteLehrer[$lehrerId])) {
                    continue 2;
                }
            }

            return $index;
        }

        return null;
    }

    /**
     * Aufsichten der muendlichen Pruefungstage: Vorbereitungsraum, Warteraum
     * und Gang, dazu je Tag eine Reserve.
     */
    private function seedKolloquiumsaufsichten(): void
    {
        $jetzt = Carbon::now();

        $rasterId = DB::table('supervision_grids')->insertGetId([
            'exam_period_id' => $this->examPeriodId,
            'name' => 'Standardraster',
            'description' => 'Aufsichtsraster der Testschule',
            'color' => '#2563eb',
            'is_default' => true,
            'created_at' => $jetzt,
            'updated_at' => $jetzt,
        ]);

        DB::table('supervision_time_slots')
            ->where('exam_period_id', $this->examPeriodId)
            ->update(['supervision_grid_id' => $rasterId]);

        $aufsichtsslots = DB::table('supervision_time_slots')
            ->where('exam_period_id', $this->examPeriodId)
            ->orderBy('date')
            ->orderBy('slot_number')
            ->get(['id', 'date']);

        $rollen = [
            'preparation' => $this->raeumeVomTyp('preparation'),
            'waiting' => $this->raeumeVomTyp('waiting'),
            'corridor' => $this->raeumeVomTyp('corridor'),
        ];

        $reihen = [];
        $lehrerIndex = 0;

        foreach ($aufsichtsslots as $slot) {
            foreach ($rollen as $typ => $raumIds) {
                foreach ($raumIds as $raumId) {
                    $reihen[] = [
                        'time_slot_id' => null,
                        'supervision_time_slot_id' => $slot->id,
                        'room_id' => $raumId,
                        'teacher_id' => $this->teacherIds[$lehrerIndex++ % count($this->teacherIds)],
                        'supervision_type' => $typ,
                        'notes' => null,
                        'location_text' => null,
                        'written_exam_assignment_id' => null,
                        'written_exam_year_date_id' => null,
                        'written_exam_supervision_time_slot_id' => null,
                        'central_supervision_type_id' => null,
                        'created_at' => $jetzt,
                        'updated_at' => $jetzt,
                    ];
                }
            }

            $reihen[] = [
                'time_slot_id' => null,
                'supervision_time_slot_id' => $slot->id,
                'room_id' => null,
                'teacher_id' => $this->teacherIds[$lehrerIndex++ % count($this->teacherIds)],
                'supervision_type' => 'reserve',
                'notes' => null,
                'location_text' => 'Lehrerzimmer',
                'written_exam_assignment_id' => null,
                'written_exam_year_date_id' => null,
                'written_exam_supervision_time_slot_id' => null,
                'central_supervision_type_id' => null,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('supervisions')->insert($block);
        }

        $this->command->info('👮 ' . count($reihen) . ' Kolloquiumsaufsichten verteilt.');
    }

    /**
     * Schriftliche Pruefungen: Aufsichtsraster, Raumbelegungen je zentralem
     * Termin und die Sitzverteilung darin.
     *
     * Passt eine Kursgruppe nicht in einen Raum, wird sie geteilt und die
     * Teile bekommen ein `split_label` — der Fall, den die Exporte und die
     * Sitzplanung koennen muessen.
     */
    private function seedSchriftlichePruefungen(): void
    {
        $jetzt = Carbon::now();
        $raster = $this->seedSchriftlicheAufsichtsraster($jetzt);
        $hoerverstaendnisFaecher = $this->profil['schriftlich']['hoerverstaendnis_faecher'];

        $kursNachId = [];

        foreach ($this->courses as $kurs) {
            $kursNachId[$kurs['id']] = $kurs;
        }

        $pruefungen = DB::table('student_exams')
            ->where('exam_type', 'written')
            ->whereNotNull('written_exam_year_date_id')
            ->orderBy('written_exam_year_date_id')
            ->orderBy('course_id')
            ->orderBy('student_id')
            ->get(['student_id', 'course_id', 'written_exam_year_date_id']);

        $gruppen = [];

        foreach ($pruefungen as $pruefung) {
            $schluessel = $pruefung->written_exam_year_date_id . '|' . $pruefung->course_id;
            $gruppen[$schluessel][] = (int) $pruefung->student_id;
        }

        $pruefungsraeume = $this->raeumeVomTyp('exam');
        $belegteRaeume = [];
        $belegungen = 0;
        $sitzplaetze = 0;
        $hoerverstaendnis = 0;

        foreach ($gruppen as $schluessel => $studentIds) {
            [$yearDateId, $courseId] = array_map('intval', explode('|', $schluessel));
            $kurs = $kursNachId[$courseId] ?? null;

            if ($kurs === null) {
                continue;
            }

            $istHoerverstaendnis = in_array($kurs['subject'], $hoerverstaendnisFaecher, true);
            $variante = $istHoerverstaendnis ? 'hoerverstaendnis' : 'standard';

            $teile = [];
            $rest = $studentIds;

            while ($rest !== []) {
                $raumId = $this->naechsterFreierRaum($pruefungsraeume, $belegteRaeume, $yearDateId);
                $plaetze = $this->verfuegbarePlaetze($raumId, $variante);
                $kapazitaet = max(1, count($plaetze));
                $teile[] = ['raum' => $raumId, 'plaetze' => $plaetze, 'schueler' => array_splice($rest, 0, $kapazitaet)];
                $belegteRaeume[$yearDateId][$raumId] = true;
            }

            foreach ($teile as $index => $teil) {
                $belegungId = DB::table('written_exam_assignments')->insertGetId([
                    'written_exam_year_date_id' => $yearDateId,
                    'course_id' => $courseId,
                    'room_id' => $teil['raum'],
                    'student_ids' => json_encode($teil['schueler']),
                    'split_label' => count($teile) > 1 ? chr(65 + $index) : null,
                    'is_hoerverstaendnis' => $istHoerverstaendnis,
                    'capacity' => count($teil['plaetze']),
                    'notes' => null,
                    'required_supervisors' => (int) $this->profil['schriftlich']['aufsichten_je_belegung'],
                    'written_exam_supervision_grid_id' => $istHoerverstaendnis ? $raster['fremdsprache'] : $raster['standard'],
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ]);

                $belegungen++;

                if ($istHoerverstaendnis) {
                    $hoerverstaendnis++;
                }

                $sitze = [];

                foreach ($teil['schueler'] as $position => $studentId) {
                    if (!isset($teil['plaetze'][$position])) {
                        break;
                    }

                    $sitze[] = [
                        'written_exam_assignment_id' => $belegungId,
                        'student_id' => $studentId,
                        'guest_name' => null,
                        'seat_id' => $teil['plaetze'][$position],
                        'sort_order' => $position,
                        'assigned_at' => $jetzt,
                        'created_at' => $jetzt,
                        'updated_at' => $jetzt,
                    ];
                }

                if ($sitze !== []) {
                    DB::table('written_exam_seat_assignments')->insert($sitze);
                    $sitzplaetze += count($sitze);
                }
            }
        }

        $this->command->info(
            '✍️  ' . $belegungen . ' schriftliche Raumbelegungen (' . $hoerverstaendnis
            . ' mit Hörverstehen), ' . $sitzplaetze . ' Sitzplätze vergeben.'
        );
    }

    /**
     * @return array{standard: int, fremdsprache: int}
     */
    private function seedSchriftlicheAufsichtsraster(Carbon $jetzt): array
    {
        $anzahlSlots = (int) $this->profil['aufsicht']['schriftlich_zeitslots_je_raster'];
        $ids = [];

        // `written_exam_supervision_time_slots.slot_number` traegt einen
        // globalen Unique-Index, obwohl die Zeitslots einem Raster gehoeren.
        // Der Bestand loest das durch fortlaufende Nummerierung ueber alle
        // Raster hinweg (Raster 1: 1-8, Raster 2: 9-16); dieselbe Konvention
        // wird hier eingehalten.
        $laufendeNummer = 1;

        foreach ([
            'standard' => ['Standardaufsicht', false, '#0f766e'],
            'fremdsprache' => ['Fremdsprache mit Hörverstehen', true, '#b45309'],
        ] as $schluessel => [$name, $istFremdsprache, $farbe]) {
            $rasterId = DB::table('written_exam_supervision_grids')->insertGetId([
                'name' => $name,
                'description' => 'Aufsichtsraster der Testschule',
                'color' => $farbe,
                'is_default' => $schluessel === 'standard',
                'is_foreign_language' => $istFremdsprache,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);

            $slots = [];
            $start = Carbon::createFromTime(8, 0);

            for ($block = 1; $block <= $anzahlSlots; $block++) {
                $ende = $start->copy()->addMinutes(45);

                $slots[] = [
                    'slot_number' => $laufendeNummer++,
                    'written_exam_supervision_grid_id' => $rasterId,
                    'start_time' => $start->format('H:i:s'),
                    'end_time' => $ende->format('H:i:s'),
                    'label' => $block . '. Aufsichtsblock',
                    'notes' => null,
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ];

                $start = $ende;
            }

            DB::table('written_exam_supervision_time_slots')->insert($slots);
            $ids[$schluessel] = $rasterId;
        }

        return ['standard' => $ids['standard'], 'fremdsprache' => $ids['fremdsprache']];
    }

    /**
     * @param list<int>                        $raeume
     * @param array<int, array<int, true>>     $belegt
     */
    private function naechsterFreierRaum(array $raeume, array $belegt, int $yearDateId): int
    {
        if ($raeume === []) {
            throw new RuntimeException('Keine Prüfungsräume vorhanden — Profil prüfen.');
        }

        foreach ($raeume as $raumId) {
            if (!isset($belegt[$yearDateId][$raumId])) {
                return $raumId;
            }
        }

        // Mehr Kursgruppen als Räume an einem Termin: dann doppelt belegen.
        // Bewusst kein Abbruch — das ist ein realer Konflikt, den die
        // Konflikterkennung finden soll.
        return $raeume[0];
    }

    /**
     * Nutzbare Sitzplaetze eines Raums in der gewuenschten Variante.
     *
     * @return list<int>
     */
    private function verfuegbarePlaetze(int $raumId, string $variante): array
    {
        $schluessel = $raumId . '|' . $variante;

        if (isset($this->platzCache[$schluessel])) {
            return $this->platzCache[$schluessel];
        }

        $templateId = DB::table('room_seating_templates')
            ->where('room_id', $raumId)
            ->where('variant', $variante)
            ->value('seating_template_id');

        if ($templateId === null) {
            return $this->platzCache[$schluessel] = [];
        }

        $plaetze = DB::table('seating_template_seats')
            ->where('template_id', $templateId)
            ->where('seat_type', 'available')
            ->orderBy('row_number')
            ->orderBy('column_number')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->platzCache[$schluessel] = $plaetze;
    }

    /**
     * Zusatzpruefungen: Tage, Raeume, Zeitslots, Pruefungen, Zuweisungen,
     * Pruefer, Aufsichten und Noten.
     *
     * Warum dieser Baustein zuerst kam: Bis zum 28.08.2026 trug die Testschule
     * keine einzige Zusatzpruefung. Fuer die Zusammenlegung der
     * Faecher-Abbildung musste deshalb auf 21 Schueler des Vorjahres in der Pilotschule
     * ausgewichen werden, und die Sport-Verbesserungsrechnung liess sich nur
     * belegen, indem alle 16 moeglichen muendlichen Noten eines einzigen
     * echten Falls durchgerechnet wurden. Beide Fehlerklassen (11 und 12)
     * haette ein Testbestand gefunden, der diese Faelle traegt.
     *
     * Die Notenlage weicht bewusst von der Pilotschule ab: dort tragen nur vier von 40
     * Zusatzpruefungen beide Noten, weil der Bestand nicht zu Ende gepflegt
     * ist. Ein Testbestand soll die Rechnung tragen, nicht den Pflegezustand.
     */
    private function seedZusatzpruefungen(): void
    {
        $jetzt = Carbon::now();
        $kennzahlen = $this->profil['zusatzpruefung'];

        [$tage, $tagesraeume, $tagesslots] = $this->seedZusatzpruefTage($jetzt, $kennzahlen);

        // Alle schriftlichen Pruefungen sind moegliche Grundlage einer
        // Zusatzpruefung — nach § 47 GSO gibt es sie nur nach einer
        // schriftlichen Pruefung, im Sport deshalb nie zum Kolloquium.
        $schriftliche = DB::table('student_exams as se')
            ->join('courses as c', 'c.id', '=', 'se.course_id')
            ->where('se.exam_type', 'written')
            ->whereNotNull('se.student_id')
            ->orderBy('se.id')
            ->get(['se.id', 'se.student_id', 'se.examiner_id', 'se.second_corrector_id', 'c.subject_id'])
            ->all();

        $sportSubjectId = $this->subjectIds['Smw'] ?? null;
        $sportPruefungen = array_values(array_filter(
            $schriftliche,
            fn (object $p) => $sportSubjectId !== null && (int) $p->subject_id === $sportSubjectId
        ));

        $gewaehlt = $this->waehleZusatzpruefungen($schriftliche, $sportPruefungen, $kennzahlen);

        // ── Die Pruefungen selbst ────────────────────────────────────────
        $sortierung = [];
        $zpReihen = [];

        foreach ($gewaehlt as $pruefung) {
            $studentId = (int) $pruefung->student_id;
            $lfdNr = $sortierung[$studentId] ?? 0;
            $sortierung[$studentId] = $lfdNr + 1;

            $verbesserung = mt_rand(1, 100) <= (int) round((float) $kennzahlen['anteil_verbesserung'] * 100);
            $offen = mt_rand(1, 100) <= (int) round((float) $kennzahlen['anteil_offen'] * 100);
            $abwesend = mt_rand(1, 100) <= (int) round((float) $kennzahlen['anteil_abwesend'] * 100);

            $zpReihen[] = [
                'exam_period_id' => $this->examPeriodId,
                'student_id' => $studentId,
                'student_exam_id' => (int) $pruefung->id,
                'status' => 'pending',
                'notes' => null,
                'absent' => $abwesend,
                'written_points' => null,
                'target_points' => null,
                'locked' => false,
                'improvement_only' => $verbesserung,
                'improvement_points_needed' => $verbesserung ? mt_rand(1, 8) : null,
                'excluded_semester' => null,
                'focus_semester' => null,
                'sort_order' => $lfdNr,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
                '_offen' => $offen,
                '_examiner' => $pruefung->examiner_id === null ? null : (int) $pruefung->examiner_id,
                '_zweitkorrektor' => $pruefung->second_corrector_id === null ? null : (int) $pruefung->second_corrector_id,
                '_ist_sport' => $sportSubjectId !== null && (int) $pruefung->subject_id === $sportSubjectId,
            ];
        }

        $noten = $this->seedZusatzpruefNoten($jetzt, $zpReihen, $sportSubjectId);

        // Die Punktzahl der Zusatzpruefung ist die Note der schriftlichen
        // Pruefung, auf der sie aufsetzt — nicht eine zweite, eigene Zahl.
        foreach ($zpReihen as $i => $zeile) {
            $zpReihen[$i]['written_points'] = $noten[$zeile['student_exam_id']]['score'] ?? null;
            $zpReihen[$i]['target_points'] = $zeile['_offen'] || $zeile['absent']
                ? null
                : mt_rand(0, 15);
        }

        $zpIds = [];

        foreach ($zpReihen as $i => $zeile) {
            $datensatz = array_filter(
                $zeile,
                fn (string $spalte) => !str_starts_with($spalte, '_'),
                ARRAY_FILTER_USE_KEY
            );

            $zpIds[$i] = (int) DB::table('zusatzpruefungen')->insertGetId($datensatz);
        }

        $this->seedZusatzpruefPruefer($jetzt, $zpReihen, $zpIds);
        $platziert = $this->seedZusatzpruefZuweisungen($jetzt, $zpReihen, $zpIds, $tage, $tagesraeume, $tagesslots, $kennzahlen);
        $this->seedZusatzpruefAufsichten($jetzt, $tage, $tagesraeume, $tagesslots, $kennzahlen);

        $sportZp = count(array_filter($zpReihen, fn (array $z) => $z['_ist_sport']));

        $this->command->info(
            '🔁 ' . count($zpReihen) . ' Zusatzprüfungen angelegt (' . $platziert . ' mit Termin, '
            . $sportZp . ' im Sport), ' . count($noten) . ' Notenzeilen.'
        );
    }

    /**
     * Drei Pruefungstage im Anschluss an das Kolloquium, je mit Raeumen und
     * Zeitslots.
     *
     * @param array<string, mixed> $kennzahlen
     * @return array{0: list<int>, 1: array<int, list<int>>, 2: array<int, list<array{id: int, start: string}>>}
     */
    private function seedZusatzpruefTage(Carbon $jetzt, array $kennzahlen): array
    {
        $letzterKolloquiumstag = DB::table('time_slots')
            ->where('exam_period_id', $this->examPeriodId)
            ->max('date');

        $tag = $letzterKolloquiumstag !== null
            ? Carbon::parse((string) $letzterKolloquiumstag)->addDay()
            : Carbon::now()->addDays(30);

        $pruefungsraeume = $this->raeumeVomTyp('exam');
        $vorbereitungsraeume = $this->raeumeVomTyp('preparation');
        $raeumeJeTag = min((int) $kennzahlen['raeume_je_tag'], count($pruefungsraeume));

        $tage = [];
        $tagesraeume = [];
        $tagesslots = [];

        for ($n = 1; $n <= (int) $kennzahlen['tage']; $n++) {
            while ($tag->isWeekend()) {
                $tag = $tag->addDay();
            }

            $tagId = (int) DB::table('zusatzpruef_days')->insertGetId([
                'exam_period_id' => $this->examPeriodId,
                'date' => $tag->toDateString(),
                'label' => 'Zusatzprüfungstag ' . $n,
                'start_time' => (string) $kennzahlen['beginn'],
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);

            $tage[] = $tagId;
            $tagesraeume[$tagId] = [];

            foreach (array_slice($pruefungsraeume, 0, $raeumeJeTag) as $sortierung => $raumId) {
                $tagesraeume[$tagId][] = (int) DB::table('zusatzpruef_day_rooms')->insertGetId([
                    'zusatzpruef_day_id' => $tagId,
                    'exam_room_id' => $raumId,
                    'prep_room_id' => $vorbereitungsraeume === [] ? null : $vorbereitungsraeume[0],
                    'sort_order' => $sortierung,
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ]);
            }

            $tagesslots[$tagId] = [];
            $uhrzeit = Carbon::parse($tag->toDateString() . ' ' . $kennzahlen['beginn']);
            $abstand = (int) $kennzahlen['dauer_pruefung_minuten'] + (int) $kennzahlen['dauer_vorbereitung_minuten'];

            for ($s = 1; $s <= (int) $kennzahlen['slots_je_tag']; $s++) {
                $start = $uhrzeit->format('H:i');

                $tagesslots[$tagId][] = [
                    'id' => (int) DB::table('zusatzpruef_day_timeslots')->insertGetId([
                        'zusatzpruef_day_id' => $tagId,
                        'start_time' => $start,
                        'created_at' => $jetzt,
                        'updated_at' => $jetzt,
                    ]),
                    'start' => $start,
                ];

                $uhrzeit = $uhrzeit->addMinutes($abstand);
            }

            $tag = $tag->addDay();
        }

        return [$tage, $tagesraeume, $tagesslots];
    }

    /**
     * Welche schriftlichen Pruefungen eine Zusatzpruefung bekommen.
     *
     * Der Sportfall wird erzwungen, nicht gewuerfelt: bei drei schriftlichen
     * Sportpruefungen und einem Anteil von gut einem Drittel waere er in etwa
     * jedem dritten Lauf nicht dabei — und dann faende der Bestand genau den
     * Fehler nicht, fuer den er gebaut wurde.
     *
     * Ein Teil der Prueflinge bekommt zwei Zusatzpruefungen. Das ist kein
     * Beiwerk: Erst damit laeuft die Kombinationsrechnung des
     * `MindestpunkteService` im Fall N=2 — jede Paarung moeglicher muendlicher
     * Noten, die Entdopplung ueber `deduplicateN2()` und die Reihenfolge der
     * beiden Pruefungen. Mit nur einer Zusatzpruefung je Pruefling bleibt das
     * alles ungeprueft.
     *
     * @param list<object>         $schriftliche
     * @param list<object>         $sportPruefungen
     * @param array<string, mixed> $kennzahlen
     * @return list<object>
     */
    private function waehleZusatzpruefungen(array $schriftliche, array $sportPruefungen, array $kennzahlen): array
    {
        $anzahl = (int) round(count($this->abiturientIds) * (float) $kennzahlen['anteil_pruefling']);
        $gewaehlt = [];
        $belegt = [];

        if ($sportPruefungen !== []) {
            $sportfall = $sportPruefungen[mt_rand(0, count($sportPruefungen) - 1)];
            $gewaehlt[] = $sportfall;
            $belegt[(int) $sportfall->id] = true;
        }

        $rest = array_values(array_filter($schriftliche, fn (object $p) => !isset($belegt[(int) $p->id])));
        shuffle($rest);

        foreach ($rest as $pruefung) {
            if (count($gewaehlt) >= $anzahl) {
                break;
            }

            $gewaehlt[] = $pruefung;
            $belegt[(int) $pruefung->id] = true;
        }

        // Jeder fuenfte Prüfling bekommt eine zweite Zusatzprüfung in einem
        // anderen seiner schriftlichen Fächer.
        $zweite = [];

        foreach ($gewaehlt as $pruefung) {
            if (mt_rand(1, 5) !== 1) {
                continue;
            }

            foreach ($schriftliche as $kandidat) {
                if ((int) $kandidat->student_id !== (int) $pruefung->student_id) {
                    continue;
                }

                if (isset($belegt[(int) $kandidat->id])) {
                    continue;
                }

                $zweite[] = $kandidat;
                $belegt[(int) $kandidat->id] = true;
                break;
            }
        }

        return array_merge($gewaehlt, $zweite);
    }

    /**
     * Noten zu allen Pruefungen der betroffenen Prueflinge — und die
     * Praxisnote zu jeder Sportpruefung.
     *
     * Zwei Gruende, weshalb hier mehr Zeilen entstehen als es
     * Zusatzpruefungen gibt:
     *
     * Erstens braucht der Mindestpunkte-Rechner alle fuenf Faecher eines
     * Prueflings, nicht nur das eine mit der Zusatzpruefung — er beantwortet
     * „was fehlt noch zur Gesamtqualifikation". In der Pilotschule stehen deshalb 99
     * Notenzeilen fuer 40 Zusatzpruefungen.
     *
     * Zweitens bekommt eine Sportpruefung ihre Praxisnote **genau dann**, wenn
     * der Pruefling irgendwo eine Zusatzpruefung hat — dann braucht der
     * Mindestpunkte-Rechner alle fuenf Faecher, und ohne die Praxisnote gaebe
     * `scoreContribution()` fuer das Sportfach `null` zurueck (Fehlerklasse
     * 11). Ein Sport-Kolloquium ohne Zusatzpruefung braucht sie dagegen
     * nicht: Noten werden erst interessant, wenn jemand in die Zusatzpruefung
     * geht, und nach einem Kolloquium gibt es keine (Wilfried, 28.08.2026).
     * In der Pilotschule tragen deshalb genau vier der zehn Sport-Kolloquien eine
     * Praxisnote — die vier, deren Prueflinge anderswo eine Zusatzpruefung
     * haben. Der Bestand bildet das nach, statt es zu glaetten.
     *
     * @param list<array<string, mixed>> $zpReihen
     * @return array<int, array{score: int|null, practical_grade: int|null}>
     */
    private function seedZusatzpruefNoten(Carbon $jetzt, array $zpReihen, ?int $sportSubjectId): array
    {
        $prueflinge = array_values(array_unique(array_map(
            fn (array $zeile) => (int) $zeile['student_id'],
            $zpReihen
        )));

        $betroffene = DB::table('student_exams as se')
            ->join('courses as c', 'c.id', '=', 'se.course_id')
            ->whereIn('se.student_id', $prueflinge)
            ->whereNotNull('se.student_id')
            ->orderBy('se.id')
            ->get(['se.id', 'se.student_id', 'c.subject_id'])
            ->all();

        $noten = [];
        $reihen = [];

        foreach ($betroffene as $pruefung) {
            $istSport = $sportSubjectId !== null && (int) $pruefung->subject_id === $sportSubjectId;
            $score = mt_rand(0, 15);
            $praxis = $istSport ? mt_rand(3, 14) : null;

            $noten[(int) $pruefung->id] = ['score' => $score, 'practical_grade' => $praxis];

            $reihen[] = [
                'student_id' => (int) $pruefung->student_id,
                'student_exam_id' => (int) $pruefung->id,
                'exam_period_id' => $this->examPeriodId,
                'score' => $score,
                'practical_grade' => $praxis,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('zusatzpruef_exam_scores')->insert($block);
        }

        return $noten;
    }

    /**
     * Erstpruefer und Zweitkorrektor je Zusatzpruefung.
     *
     * @param list<array<string, mixed>> $zpReihen
     * @param array<int, int>            $zpIds
     */
    private function seedZusatzpruefPruefer(Carbon $jetzt, array $zpReihen, array $zpIds): void
    {
        $reihen = [];

        foreach ($zpReihen as $i => $zeile) {
            $besetzung = [
                ['erstpruefer', $zeile['_examiner']],
                ['zweitkorrektor', $zeile['_zweitkorrektor']],
            ];

            foreach ($besetzung as $sortierung => [$rolle, $lehrerId]) {
                if ($lehrerId === null) {
                    continue;
                }

                $reihen[] = [
                    'zusatzpruefung_id' => $zpIds[$i],
                    'teacher_id' => $lehrerId,
                    'role' => $rolle,
                    'name_override' => null,
                    'sort_order' => $sortierung,
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ];
            }
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('zusatzpruef_pruefer')->insert($block);
        }
    }

    /**
     * Termine: je Zusatzpruefung ein Raum in einem Zeitslot.
     *
     * Zwei Nebenbedingungen werden eingehalten statt behauptet: ein Raum
     * traegt je Zeitslot eine Pruefung, und weder ein Pruefer noch ein
     * Prüfling steht im selben Zeitslot an zwei Stellen. Was keinen Platz
     * findet, bleibt `pending` — das ist der Zustand, den die Uebersicht als
     * „noch zu planen" zeigt, und den es im Bestand geben muss.
     *
     * @param list<array<string, mixed>>                       $zpReihen
     * @param array<int, int>                                  $zpIds
     * @param list<int>                                        $tage
     * @param array<int, list<int>>                            $tagesraeume
     * @param array<int, list<array{id: int, start: string}>>  $tagesslots
     * @param array<string, mixed>                             $kennzahlen
     */
    private function seedZusatzpruefZuweisungen(
        Carbon $jetzt,
        array $zpReihen,
        array $zpIds,
        array $tage,
        array $tagesraeume,
        array $tagesslots,
        array $kennzahlen
    ): int {
        $offen = [];

        foreach ($zpReihen as $i => $zeile) {
            $offen[] = ['index' => $i, 'zeile' => $zeile];
        }

        // Ein Teil bleibt bewusst ungeplant. Die drei Tage bieten mehr Plaetze
        // als es Pruefungen gibt — ohne Zurueckhalten waere jede platziert und
        // der Zustand `pending`, den die Uebersicht als „noch zu planen"
        // fuehrt, kaeme im Bestand nicht vor.
        $zurueckhalten = (int) round(count($offen) * (float) $kennzahlen['anteil_ohne_termin']);
        $offen = array_slice($offen, 0, max(0, count($offen) - $zurueckhalten));

        $reihen = [];
        $platziert = [];

        foreach ($tage as $tagId) {
            foreach ($tagesslots[$tagId] as $slot) {
                $belegteLehrer = [];
                $belegtePrueflinge = [];

                foreach ($tagesraeume[$tagId] as $raumId) {
                    $treffer = null;

                    foreach ($offen as $position => $eintrag) {
                        $zeile = $eintrag['zeile'];
                        $lehrer = array_filter([$zeile['_examiner'], $zeile['_zweitkorrektor']]);

                        foreach ($lehrer as $lehrerId) {
                            if (isset($belegteLehrer[$lehrerId])) {
                                continue 2;
                            }
                        }

                        if (isset($belegtePrueflinge[$zeile['student_id']])) {
                            continue;
                        }

                        $treffer = $position;
                        break;
                    }

                    if ($treffer === null) {
                        continue;
                    }

                    $eintrag = $offen[$treffer];
                    unset($offen[$treffer]);
                    $offen = array_values($offen);

                    foreach (array_filter([$eintrag['zeile']['_examiner'], $eintrag['zeile']['_zweitkorrektor']]) as $lehrerId) {
                        $belegteLehrer[$lehrerId] = true;
                    }

                    $belegtePrueflinge[$eintrag['zeile']['student_id']] = true;
                    $platziert[] = $zpIds[$eintrag['index']];

                    $reihen[] = [
                        'zusatzpruefung_id' => $zpIds[$eintrag['index']],
                        'zusatzpruef_day_id' => $tagId,
                        'zusatzpruef_day_room_id' => $raumId,
                        'start_time' => $slot['start'],
                        'exam_duration_minutes' => (int) $kennzahlen['dauer_pruefung_minuten'],
                        'prep_duration_minutes' => (int) $kennzahlen['dauer_vorbereitung_minuten'],
                        'created_at' => $jetzt,
                        'updated_at' => $jetzt,
                    ];
                }
            }
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('zusatzpruef_assignments')->insert($block);
        }

        if ($platziert !== []) {
            DB::table('zusatzpruefungen')->whereIn('id', $platziert)->update(['status' => 'placed']);
        }

        return count($platziert);
    }

    /**
     * Aufsichten je Zusatzpruefungstag.
     *
     * Aufsicht fuehrt, wer an diesem Tag nicht selbst prueft — sonst stuende
     * dieselbe Lehrkraft zur selben Zeit im Pruefungs- und im Warteraum.
     *
     * @param list<int>                                       $tage
     * @param array<int, list<int>>                           $tagesraeume
     * @param array<int, list<array{id: int, start: string}>> $tagesslots
     * @param array<string, mixed>                            $kennzahlen
     */
    private function seedZusatzpruefAufsichten(
        Carbon $jetzt,
        array $tage,
        array $tagesraeume,
        array $tagesslots,
        array $kennzahlen
    ): void {
        $reihen = [];

        foreach ($tage as $tagId) {
            $pruefendeAmTag = DB::table('zusatzpruef_pruefer as p')
                ->join('zusatzpruef_assignments as a', 'a.zusatzpruefung_id', '=', 'p.zusatzpruefung_id')
                ->where('a.zusatzpruef_day_id', $tagId)
                ->whereNotNull('p.teacher_id')
                ->pluck('p.teacher_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $frei = array_values(array_diff($this->teacherIds, $pruefendeAmTag));

            if ($frei === [] || $tagesraeume[$tagId] === [] || $tagesslots[$tagId] === []) {
                continue;
            }

            for ($n = 0; $n < (int) $kennzahlen['aufsichten_je_tag']; $n++) {
                if (!isset($frei[$n])) {
                    break;
                }

                $reihen[] = [
                    'zusatzpruef_day_id' => $tagId,
                    'zusatzpruef_day_room_id' => $tagesraeume[$tagId][$n % count($tagesraeume[$tagId])],
                    'zusatzpruef_day_timeslot_id' => $tagesslots[$tagId][0]['id'],
                    'teacher_id' => $frei[$n],
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ];
            }
        }

        if ($reihen !== []) {
            DB::table('zusatzpruef_aufsichten')->insert($reihen);
        }
    }

    /**
     * Themenbereiche der Kolloquiumskurse (§ 46 Abs. 2 GSO).
     *
     * Je Kurs mit muendlichen Pruefungen vier Themenbereiche in 12/1 und vier
     * in 12/2. Die Titel sind bewusst generisch: der echte Themenbereich ist
     * Freitext der Fachschaft, und ein erfundener Fachtitel waere kein
     * besserer Testwert als ein erkennbar generischer — aber ein schlechterer
     * Hinweis darauf, dass es Testdaten sind.
     */
    private function seedKolloquiumsthemen(): void
    {
        $jetzt = Carbon::now();
        $anzahl = (int) $this->profil['kolloquium']['themenbereiche_je_semester'];

        $muster = [
            'Grundbegriffe und Methoden',
            'Historische Entwicklung',
            'Vertiefung am Beispiel',
            'Anwendungen und Verfahren',
            'Vergleichende Betrachtung',
            'Aktuelle Fragestellungen',
            'Quellen und ihre Deutung',
            'Zusammenhänge und Übertragung',
        ];

        $kurseMitKolloquium = DB::table('student_exams')
            ->where('exam_type', 'oral')
            ->whereNotNull('course_id')
            ->distinct()
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $kursCodes = [];

        foreach ($this->courses as $kurs) {
            $kursCodes[$kurs['id']] = $kurs['code'];
        }

        $reihen = [];

        foreach ($kurseMitKolloquium as $kursId) {
            foreach (['12/1', '12/2'] as $semesterNr => $semester) {
                for ($slot = 1; $slot <= $anzahl; $slot++) {
                    $index = ($semesterNr * $anzahl + $slot - 1) % count($muster);

                    $reihen[] = [
                        'course_id' => $kursId,
                        'semester' => $semester,
                        'slot' => $slot,
                        'title' => ($kursCodes[$kursId] ?? 'Kurs') . ' · ' . $muster[$index],
                        'created_at' => $jetzt,
                        'updated_at' => $jetzt,
                    ];
                }
            }
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('colloquium_topics')->insert($block);
        }

        $this->command->info(
            '📚 ' . count($reihen) . ' Themenbereiche für ' . count($kurseMitKolloquium) . ' Kolloquiumskurse angelegt.'
        );
    }

    /**
     * Randbedingungen, an denen die Planung in der Praxis scheitert:
     * gesperrte Tage einzelner Prueflinge und abwesende Lehrkraefte.
     */
    private function seedRandbedingungen(): void
    {
        $jetzt = Carbon::now();

        $tage = DB::table('time_slots')
            ->where('exam_period_id', $this->examPeriodId)
            ->distinct()
            ->orderBy('date')
            ->pluck('date')
            ->all();

        if ($tage === []) {
            return;
        }

        $gesperrt = [];

        foreach ($this->faker->randomElements($this->abiturientIds, min(5, count($this->abiturientIds))) as $studentId) {
            $gesperrt[] = [
                'student_id' => $studentId,
                'date' => $this->faker->randomElement($tage),
                'reason' => 'Attest (Testdatum)',
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];
        }

        DB::table('student_blocked_days')->insert($gesperrt);

        $abwesend = [];

        foreach ($this->faker->randomElements($this->teacherIds, min(20, count($this->teacherIds))) as $teacherId) {
            $ganztags = $this->faker->boolean(60);

            $abwesend[] = [
                'teacher_id' => $teacherId,
                'exam_period_id' => $this->examPeriodId,
                'date' => $this->faker->randomElement($tage),
                'all_day' => $ganztags,
                'time_from' => $ganztags ? null : '08:00:00',
                'time_to' => $ganztags ? null : '11:20:00',
                'reason' => $this->faker->randomElement(['Fortbildung', 'Dienstgang', 'Erkrankung']),
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];
        }

        DB::table('teacher_unavailability')->insert($abwesend);

        $this->command->info(
            '🚧 ' . count($gesperrt) . ' gesperrte Schülertage, ' . count($abwesend) . ' Lehrer-Abwesenheiten.'
        );
    }

    /**
     * Eingebaute Gegenproben.
     *
     * Ein Seeder, der stillschweigend halbe Daten erzeugt, ist schlimmer als
     * gar keiner — dann testet man gegen einen Bestand, den man fuer
     * vollstaendig haelt. Diese Proben laufen bei jedem Lauf.
     */
    private function pruefeErgebnis(): void
    {
        $fehler = [];

        // Nur eigene Schueler: ein externer Bewerber hat acht Pruefungsfaecher
        // und je nach Einbringung sechs oder acht Pruefungen, und sein
        // student_id ist null — ohne diese Einschraenkung zaehlte die Probe
        // alle Externen als einen einzigen Pruefling mit zu vielen
        // Pruefungen. (Dieselbe Falle wie Fehlerklasse 1, hier in der
        // Gegenprobe selbst.)
        $ohneFuenf = DB::table('student_exams')
            ->select('student_id')
            ->whereNotNull('student_id')
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) <> ?', [(int) $this->profil['pruefung']['pruefungen_je_schueler']])
            ->count();

        if ($ohneFuenf > 0) {
            $fehler[] = "{$ohneFuenf} Prüflinge haben nicht genau "
                . $this->profil['pruefung']['pruefungen_je_schueler'] . ' Prüfungen.';
        }

        $ohneTermin = DB::table('student_exams')
            ->where('exam_type', 'oral')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('exam_assignments')
                ->whereColumn('exam_assignments.student_exam_id', 'student_exams.id'))
            ->count();

        if ($ohneTermin > 0) {
            $fehler[] = "{$ohneTermin} mündliche Prüfungen ohne Termin.";
        }

        // Ein Raum darf in einem Slot nur eine Sitzung beherbergen. Gekoppelte
        // Prueflinge zaehlen dabei als eine Sitzung, ungekoppelte je einzeln.
        $doppelteRaeume = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM (
                SELECT ea.time_slot_id, ea.exam_room_id
                FROM exam_assignments ea
                JOIN student_exams se ON se.id = ea.student_exam_id
                GROUP BY ea.time_slot_id, ea.exam_room_id
                HAVING COUNT(DISTINCT COALESCE(se.coupling_id, CONCAT("einzel-", se.id))) > 1
            ) t'
        );

        if ($doppelteRaeume !== null && (int) $doppelteRaeume->anzahl > 0) {
            $fehler[] = (int) $doppelteRaeume->anzahl . ' Räume sind im selben Slot doppelt belegt.';
        }

        $kollisionen = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM (
                SELECT ea.time_slot_id, se.examiner_id
                FROM exam_assignments ea
                JOIN student_exams se ON se.id = ea.student_exam_id
                GROUP BY ea.time_slot_id, se.examiner_id
                HAVING COUNT(DISTINCT ea.exam_room_id) > 1
            ) t'
        );

        if ($kollisionen !== null && (int) $kollisionen->anzahl > 0) {
            $fehler[] = (int) $kollisionen->anzahl . ' Prüfer sind im selben Slot in mehreren Räumen.';
        }

        $sitzloseSchriftliche = DB::selectOne(
            'SELECT COUNT(*) AS anzahl
             FROM student_exams se
             WHERE se.exam_type = "written"
               AND se.student_id IS NOT NULL
               AND se.written_exam_year_date_id IS NOT NULL
               AND NOT EXISTS (
                   SELECT 1 FROM written_exam_seat_assignments sa
                   JOIN written_exam_assignments wa ON wa.id = sa.written_exam_assignment_id
                   WHERE sa.student_id = se.student_id
                     AND wa.course_id = se.course_id
                     AND wa.written_exam_year_date_id = se.written_exam_year_date_id
               )'
        );

        if ($sitzloseSchriftliche !== null && (int) $sitzloseSchriftliche->anzahl > 0) {
            $fehler[] = (int) $sitzloseSchriftliche->anzahl . ' schriftliche Prüfungen ohne Sitzplatz.';
        }

        foreach ($this->pruefeZusatzpruefungen() as $befund) {
            $fehler[] = $befund;
        }

        if ($fehler !== []) {
            throw new RuntimeException(
                'Testschule unvollständig erzeugt: ' . implode(' ', $fehler)
            );
        }

        $this->command->info('');
        $this->command->info('✅ Testschule vollständig erzeugt, alle Gegenproben bestanden.');
    }
    /**
     * Die Faecherwahl beider externer Bewerber (§ 61 GSO).
     *
     * Nachgewiesen regelkonform: dieselbe Kombination prueft
     * `ExternalApplicantTest::eine_zulaessige_wahl_besteht_die_regelpruefung()`.
     * Warum sie durchgeht: Mathematik auf Position 1 (E2), Deutsch und
     * Mathematik immer eA plus Englisch als Leistungsfach ergeben genau drei
     * (A6), Fach 4 grundlegend (B6), unter den Faechern 1 bis 3 stehen
     * Mathematik und zwei eA (B3), der erste Pruefungsteil deckt alle drei
     * Aufgabenfelder ab (B1), zwei Fremdsprachen und eine Naturwissenschaft
     * sind dabei (A2), und Franzoesisch steht muendlich und grundlegend, also
     * als zweite Fremdsprache zulaessig (C7).
     *
     * @var list<array{code: string, teil: string, nr: int}>
     */
    private const EXTERNE_FAECHERWAHL = [
        ['code' => 'M',   'teil' => 'part1_written', 'nr' => 1],
        ['code' => 'D',   'teil' => 'part1_written', 'nr' => 2],
        ['code' => 'E',   'teil' => 'part1_written', 'nr' => 3],
        ['code' => 'G',   'teil' => 'part1_written', 'nr' => 4],
        ['code' => 'Ph',  'teil' => 'part2_oral',    'nr' => 1],
        ['code' => 'F',   'teil' => 'part2_oral',    'nr' => 2],
        ['code' => 'B',   'teil' => 'part2_oral',    'nr' => 3],
        ['code' => 'Geo', 'teil' => 'part2_oral',    'nr' => 4],
    ];

    /**
     * Externe Bewerber nach § 61 GSO — einer nach Anlage 14, einer als
     * Ersatzschueler nach § 64 Abs. 2 (Anlage 15).
     *
     * Modul 2.1 ist die groesste frische Baustelle des Projekts und hatte bis
     * heute keinen einzigen Testbewerber: alles Durchklicken lief ueber einen
     * Probeeintrag in den Produktionsdaten, der am 28.08.2026 selbst repariert
     * werden musste, weil die neue Regelableitung aus § 61 ihn ungueltig
     * gemacht hatte.
     *
     * **Dieser Baustein arbeitet mit den echten Diensten, nicht mit rohen
     * Inserts.** Die uebrigen Bausteine schreiben Massendaten mit
     * `DB::table()->insert()`; hier waere das falsch. Eine Faecherwahl ist
     * erst brauchbar, wenn sie die Regelpruefung besteht, und die Pruefungen
     * entstehen nicht eins zu eins aus den Faechern — ein eingebrachtes Fach
     * nach § 64 Abs. 2 bleibt Pruefungsfach, erzeugt aber keine Pruefung. Aus
     * acht Faechern werden also sechs Pruefungen. Wer das nachbaut, baut die
     * Regel ein zweites Mal — mit der bekannten Wahrscheinlichkeit, sie falsch
     * zu treffen.
     */
    private function seedExterneBewerber(): void
    {
        $jetzt = Carbon::now();

        $fehlend = array_values(array_filter(
            array_column(self::EXTERNE_FAECHERWAHL, 'code'),
            fn (string $code) => !isset($this->subjectIds[$code])
        ));

        if ($fehlend !== []) {
            $this->command->warn(
                '⚠️  Externe Bewerber übersprungen, im Katalog fehlen: ' . implode(', ', $fehlend)
            );

            return;
        }

        $ersatzschuleId = (int) DB::table('ersatzschulen')->insertGetId([
            'name' => 'Private Musterschule Musterstadt',
            'ort' => 'Musterstadt',
            'notes' => 'Staatlich genehmigte Ersatzschule (§ 64 GSO) — Testbestand.',
            'created_at' => $jetzt,
            'updated_at' => $jetzt,
        ]);

        // Der erste Bewerber geht den gewoehnlichen Weg nach Anlage 14 und
        // traegt den Nachteilsausgleich — er bekommt deshalb auch den
        // Sitzplatz, auf dem sich die verlaengerte Arbeitszeit ausrechnet.
        $anlage14 = $this->seedExternerBewerber(
            nachname: 'Anlagevierzehn',
            ersatzschuleId: null,
            mitEinbringung: false,
            nachteilsausgleich: 25,
        );

        // Der zweite bringt zwei Faecher aus 13/II ein (§ 64 Abs. 2 S. 1) und
        // faellt damit unter Anlage 15. Er hat deshalb nur sechs Pruefungen.
        $anlage15 = $this->seedExternerBewerber(
            nachname: 'Anlagefuenfzehn',
            ersatzschuleId: $ersatzschuleId,
            mitEinbringung: true,
            nachteilsausgleich: null,
        );

        $noten = $this->seedExterneNoten($jetzt, $anlage14);
        $zpAngelegt = $this->seedExterneZusatzpruefung($jetzt, $anlage14, $noten);

        // Den Sitzplatz bekommt der Bewerber mit Nachteilsausgleich, und zwar
        // erst nach den schriftlichen Belegungen.
        $this->externeBewerberIds = [(int) $anlage14->id];

        $this->command->info(
            '🎓 2 externe Bewerber angelegt (Anlage 14 mit ' . $anlage15->examSubjects()->count()
            . ' Fächern, Anlage 15 mit Einbringung), '
            . count($noten) . ' Noten, ' . ($zpAngelegt ? '1' : 'keine') . ' Zusatzprüfung.'
        );
    }

    /**
     * Ein einzelner Bewerber samt Faecherwahl und Pruefungen.
     *
     * Die Wahl wird **nach dem Speichern gegen die Regelpruefung gehalten**
     * und bricht den Seeder ab, wenn sie nicht besteht. Ein Bewerber mit
     * ungueltiger Faecherwahl waere als Testbestand wertlos — man wuesste bei
     * jedem Befund nicht, ob die Anwendung falsch rechnet oder der Bestand
     * falsch ist.
     */
    private function seedExternerBewerber(
        string $nachname,
        ?int $ersatzschuleId,
        bool $mitEinbringung,
        ?int $nachteilsausgleich
    ): ExternalApplicant {
        $bewerber = ExternalApplicant::create([
            'first_name' => $this->faker->firstName(),
            'last_name' => $nachname,
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'birth_date' => $this->faker->dateTimeBetween('-24 years', '-19 years')->format('Y-m-d'),
            'exam_period_id' => $this->examPeriodId,
            'ersatzschule_id' => $ersatzschuleId,
            'leistungsfach_subject_id' => $this->subjectIds['E'],
            'zweite_fremdsprache_subject_id' => $this->subjectIds['F'],
            'disability_compensation_percent' => $nachteilsausgleich,
            'disability_compensation_notes' => $nachteilsausgleich === null
                ? null
                : 'Testbestand: ' . $nachteilsausgleich . ' % verlängerte Arbeitszeit.',
        ]);

        foreach (self::EXTERNE_FAECHERWAHL as $index => $fach) {
            // § 64 Abs. 2: eingebracht wird auf den Positionen sieben und
            // acht, und nie in einer Fremdsprache, im Leistungsfach oder in
            // Deutsch. Biologie und Geographie erfüllen das.
            $eingebracht = $mitEinbringung && $index >= 6;

            ExternalExamSubject::create([
                'external_applicant_id' => $bewerber->id,
                'subject_id' => $this->subjectIds[$fach['code']],
                'exam_part' => $fach['teil'],
                'subject_number' => $fach['nr'],
                'ergebnis_quelle' => $eingebracht ? 'einbringung' : 'pruefung',
                'eingebrachte_punktzahl' => $eingebracht ? mt_rand(6, 13) : null,
                'examiner_id' => $this->pruefer($fach['code']),
                'notes' => null,
            ]);
        }

        $bewerber->refresh();

        $pruefung = app(ExternalApplicantValidationService::class)
            ->validateSubjectSelection($bewerber);

        if ($pruefung['valid'] !== true) {
            throw new RuntimeException(
                'Die Fächerwahl des Testbewerbers ' . $nachname . ' besteht die Regelprüfung nicht: '
                . implode(' ', $pruefung['errors'])
            );
        }

        app(ExternalApplicantStudentExamService::class)->generateStudentExams($bewerber);

        return $bewerber;
    }

    /**
     * Eine Lehrkraft des Fachs als Prüferin oder Prüfer, sonst irgendeine.
     */
    private function pruefer(string $fachCode): ?int
    {
        $kandidaten = $this->teachersBySubject[$fachCode] ?? [];

        if ($kandidaten !== []) {
            return $kandidaten[mt_rand(0, count($kandidaten) - 1)];
        }

        return $this->teacherIds === [] ? null : $this->teacherIds[0];
    }

    /**
     * Noten zu allen Prüfungen eines Bewerbers.
     *
     * Für die § 62-Rechnung ist der ganze Satz nötig: Sie fragt in zwei
     * voneinander abgedichteten Teilen, ob die Prüfung bestanden ist — der
     * erste Teil bricht sie ab, wenn er nicht trägt (§ 62 Abs. 1 S. 4). Mit
     * halben Noten lässt sich das nicht durchrechnen.
     *
     * `student_id` bleibt null. Das ist kein Versehen: Für einen externen
     * Bewerber gibt es keinen Schüler, und genau daran ist die Anwendung
     * schon einmal gescheitert (Fehlerklasse 1).
     *
     * @return array<int, int> student_exam_id => Note
     */
    private function seedExterneNoten(Carbon $jetzt, ExternalApplicant $bewerber): array
    {
        $pruefungen = DB::table('student_exams as se')
            ->join('external_exam_subjects as ees', 'ees.id', '=', 'se.external_exam_subject_id')
            ->where('ees.external_applicant_id', $bewerber->id)
            ->orderBy('se.id')
            ->get(['se.id']);

        $noten = [];
        $reihen = [];

        foreach ($pruefungen as $pruefung) {
            // Sechs bis dreizehn Punkte: ein Bestand, der die Schwellen des
            // § 62 nicht schon durch Zufall reißt, aber auch nicht überall
            // komfortabel darüber liegt.
            $note = mt_rand(6, 13);
            $noten[(int) $pruefung->id] = $note;

            $reihen[] = [
                'student_id' => null,
                'student_exam_id' => (int) $pruefung->id,
                'exam_period_id' => $this->examPeriodId,
                'score' => $note,
                'practical_grade' => null,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];
        }

        if ($reihen !== []) {
            DB::table('zusatzpruef_exam_scores')->insert($reihen);
        }

        return $noten;
    }

    /**
     * Eine Zusatzprüfung für den externen Bewerber.
     *
     * Sie steht hier bewusst: `zusatzpruefungen.student_id` ist für einen
     * externen Bewerber null, und an dieser Nullbarkeit hingen 34 stille
     * Ausfälle (Fehlerklasse 1). Ein Testbestand ohne diesen Fall prüft die
     * Abfragen der Übersicht, der Konflikterkennung und der Niederschrift nur
     * für die halbe Wirklichkeit.
     *
     * @param array<int, int> $noten
     */
    private function seedExterneZusatzpruefung(Carbon $jetzt, ExternalApplicant $bewerber, array $noten): bool
    {
        $schriftliche = DB::table('student_exams as se')
            ->join('external_exam_subjects as ees', 'ees.id', '=', 'se.external_exam_subject_id')
            ->where('ees.external_applicant_id', $bewerber->id)
            ->where('se.exam_type', 'written')
            ->orderBy('ees.subject_number')
            ->first(['se.id', 'se.examiner_id']);

        if ($schriftliche === null) {
            return false;
        }

        $zpId = (int) DB::table('zusatzpruefungen')->insertGetId([
            'exam_period_id' => $this->examPeriodId,
            'student_id' => null,
            'student_exam_id' => (int) $schriftliche->id,
            'status' => 'pending',
            'notes' => null,
            'absent' => false,
            'written_points' => $noten[(int) $schriftliche->id] ?? null,
            'target_points' => null,
            'locked' => false,
            'improvement_only' => false,
            'improvement_points_needed' => null,
            'excluded_semester' => null,
            'focus_semester' => null,
            'sort_order' => 0,
            'created_at' => $jetzt,
            'updated_at' => $jetzt,
        ]);

        if ($schriftliche->examiner_id !== null) {
            DB::table('zusatzpruef_pruefer')->insert([
                'zusatzpruefung_id' => $zpId,
                'teacher_id' => (int) $schriftliche->examiner_id,
                'role' => 'erstpruefer',
                'name_override' => null,
                'sort_order' => 0,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);
        }

        return true;
    }

    /**
     * Ein Sitzplatz in einer bestehenden schriftlichen Raumbelegung.
     *
     * Entscheidung 28: Ein Platz trägt genau eine von drei Angaben — eigener
     * Schüler, externer Bewerber oder freier Gastname. Der Bewerber setzt sich
     * deshalb nicht in einen eigenen Raum, sondern auf einen freien Platz
     * einer Belegung, die an seinem Prüfungstag ohnehin stattfindet.
     *
     * Damit ist auch der Nachteilsausgleich im Bestand: Er hing an vier
     * Stellen (Sitzkarten, Aufsichtslisten, Aufsichtsmatrix, Sitzplan-PDF) an
     * einer Fallunterscheidung, die den Externen als null behandelte und ihm
     * rechnerisch 0 % gab.
     */
    private function seedExterneSitzplaetze(): void
    {
        $jetzt = Carbon::now();
        $vergeben = 0;

        foreach ($this->externeBewerberIds as $bewerberId) {
            $bewerber = ExternalApplicant::find($bewerberId);

            if ($bewerber instanceof ExternalApplicant && $this->setzeExternenSitzplatz($jetzt, $bewerber)) {
                $vergeben++;
            }
        }

        $this->command->info('🪑 ' . $vergeben . ' Sitzplätze für externe Bewerber vergeben.');
    }

    /**
     * Ein Sitzplatz für einen einzelnen Bewerber.
     */
    private function setzeExternenSitzplatz(Carbon $jetzt, ExternalApplicant $bewerber): bool
    {
        $termine = DB::table('student_exams as se')
            ->join('external_exam_subjects as ees', 'ees.id', '=', 'se.external_exam_subject_id')
            ->where('ees.external_applicant_id', $bewerber->id)
            ->where('se.exam_type', 'written')
            ->whereNotNull('se.written_exam_year_date_id')
            ->orderBy('ees.subject_number')
            ->pluck('se.written_exam_year_date_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Es genuegt nicht, die erste Belegung eines Termins zu nehmen: Der
        // Seeder fuellt jeden Raum bis zur Kapazitaet und splittet erst dann,
        // also ist die erste Belegung in aller Regel randvoll. Gesucht ist die
        // erste mit einem freien Platz — ueber alle Pruefungstage des
        // Bewerbers hinweg.
        $belegung = null;
        $vergeben = [];
        $frei = [];

        foreach ($termine as $terminId) {
            $kandidaten = DB::table('written_exam_assignments')
                ->where('written_exam_year_date_id', $terminId)
                ->orderBy('id')
                ->get(['id', 'room_id', 'is_hoerverstaendnis']);

            foreach ($kandidaten as $kandidat) {
                $belegt = DB::table('written_exam_seat_assignments')
                    ->where('written_exam_assignment_id', (int) $kandidat->id)
                    ->pluck('seat_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $variante = $kandidat->is_hoerverstaendnis ? 'hoerverstaendnis' : 'standard';
                $offen = array_values(array_diff(
                    $this->verfuegbarePlaetze((int) $kandidat->room_id, $variante),
                    $belegt
                ));

                if ($offen !== []) {
                    $belegung = $kandidat;
                    $vergeben = $belegt;
                    $frei = $offen;

                    break 2;
                }
            }
        }

        if ($belegung === null) {
            return false;
        }

        DB::table('written_exam_seat_assignments')->insert([
            'written_exam_assignment_id' => (int) $belegung->id,
            'student_id' => null,
            'external_applicant_id' => $bewerber->id,
            'guest_name' => null,
            'seat_id' => $frei[0],
            'sort_order' => count($vergeben),
            'assigned_at' => $jetzt,
            'created_at' => $jetzt,
            'updated_at' => $jetzt,
        ]);

        return true;
    }

    /**
     * LK- und W-Seminar-Wahlen der Jahrgangsstufen 11 und 12.
     *
     * Bis heute der einzige Bereich des Projekts **ganz ohne Testdaten** — der
     * Zuteilungsalgorithmus lief gegen keinen realistischen Bestand.
     *
     * Der Baustein bildet **zwei Schuljahre** ab, weil beide Zustaende
     * gebraucht werden und in der Pilotschule auch beide vorliegen:
     *
     * - **Jahrgangsstufe 11** waehlt im laufenden Schuljahr. Ihre Wahlen sind
     *   erfasst, aber **nicht zugeteilt** (`prioritaet_erhalten` null,
     *   `zuteilung_lk_kurs_id` null). Das ist der Ausgangszustand, auf dem
     *   eine Zuteilung ueberhaupt erst arbeitet — in der Pilotschule stehen alle 128
     *   LK-Wahlen genau so.
     * - **Jahrgangsstufe 12** hat im Vorjahr gewaehlt; dort ist die Zuteilung
     *   **durchgefuehrt**. Das ist der Zustand, den Listen und Auswertungen
     *   sehen.
     *
     * Zwei Eigenheiten sind aus der Pilotschule uebernommen, weil sie den interessanten
     * Fall tragen:
     *
     * - **Es wird auf Faecher gewaehlt, fuer die es keinen Kurs gibt.** In
     *   der Pilotschule nennen zwoelf Erstwahlen ein Fach, aber nur zehn haben einen
     *   Kurs — Biologie und Informatik gehen leer aus. Genau daran muss eine
     *   Zuteilung scheitern koennen; ein Bestand, in dem jede Wahl aufgeht,
     *   pruefte den Fall nie.
     * - **Ein Kurs ist extern** (`ist_extern`), naemlich ein Fach, das die
     *   eigene Schule nicht fuehrt. Es kommt deshalb nicht aus dem
     *   Strukturprofil, sondern direkt aus dem zentralen Katalog.
     */
    private function seedWahlen(): void
    {
        $jetzt = Carbon::now();
        $kennzahlen = $this->profil['wahlen'];

        $schuljahr = $this->schuljahr();
        $vorjahr = $this->schuljahr(-1);

        $schuelerNachStufe = [];

        foreach (['11', '12'] as $stufe) {
            $schuelerNachStufe[$stufe] = DB::table('students')
                ->where('exam_period_id', $this->examPeriodId)
                ->where('grade', $stufe)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        // Jahrgangsstufe 12 hat im Vorjahr gewaehlt und ist zugeteilt,
        // Jahrgangsstufe 11 waehlt gerade.
        $lkKurse = [
            '12' => $this->seedLkKurse($jetzt, $vorjahr, $kennzahlen),
            '11' => $this->seedLkKurse($jetzt, $schuljahr, $kennzahlen),
        ];

        $lkWahlen = 0;
        $lkWahlen += $this->seedLkWahlen($jetzt, $vorjahr, $schuelerNachStufe['12'], $lkKurse['12'], $kennzahlen, true);
        $lkWahlen += $this->seedLkWahlen($jetzt, $schuljahr, $schuelerNachStufe['11'], $lkKurse['11'], $kennzahlen, false);

        $seminare = $this->seedWseminare($jetzt, $schuljahr, $kennzahlen);
        $wWahlen = $this->seedWseminarWahlen($jetzt, $schuljahr, $schuelerNachStufe['11'], $seminare, $kennzahlen);

        $this->command->info(
            '🗳  ' . array_sum(array_map('count', $lkKurse)) . ' LK-Kurse und ' . $lkWahlen
            . ' LK-Wahlen (Jgst. 12 zugeteilt, Jgst. 11 offen), '
            . count($seminare) . ' W-Seminare mit ' . $wWahlen . ' Wahlen.'
        );
    }

    /**
     * Das Schuljahr des Pruefungszeitraums, etwa "2026/2027".
     *
     * `$versatz` blickt zurueck: -1 ergibt das Vorjahr, in dem die heutige
     * Jahrgangsstufe 12 ihre Wahl getroffen hat.
     */
    private function schuljahr(int $versatz = 0): string
    {
        $jahr = (int) $this->examPeriod->year + $versatz;

        return ($jahr - 1) . '/' . $jahr;
    }

    /**
     * Die Leistungsfachkurse eines Schuljahres.
     *
     * @param array<string, mixed> $kennzahlen
     * @return array<string, int> Fachkuerzel => lk_kurs_id
     */
    private function seedLkKurse(Carbon $jetzt, string $schuljahr, array $kennzahlen): array
    {
        $kurse = [];

        foreach ($kennzahlen['lk_faecher'] as $code) {
            if (!isset($this->subjectIds[$code])) {
                continue;
            }

            $kurse[$code] = (int) DB::table('lk_kurse')->insertGetId([
                'schuljahr' => $schuljahr,
                'jahrgang' => '11',
                'subject_id' => $this->subjectIds[$code],
                'kurs_nummer' => 1,
                'ist_extern' => false,
                'lehrer_id' => $this->pruefer($code),
                'notizen' => null,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);
        }

        // Der externe Kurs: ein Fach, das die eigene Schule nicht fuehrt und
        // das deshalb auch nicht im Strukturprofil steht. Ohne ihn bliebe
        // `ist_extern` im ganzen Bestand false.
        $externesFach = Subject::query()            ->where('code', (string) $kennzahlen['lk_externes_fach'])
            ->first();

        if ($externesFach instanceof Subject) {
            $kurse[$externesFach->code] = (int) DB::table('lk_kurse')->insertGetId([
                'schuljahr' => $schuljahr,
                'jahrgang' => '11',
                'subject_id' => (int) $externesFach->id,
                'kurs_nummer' => 1,
                'ist_extern' => true,
                'lehrer_id' => null,
                'notizen' => 'Kooperation mit einer Nachbarschule (Testbestand).',
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);
        }

        return $kurse;
    }

    /**
     * Die Wahlen einer Jahrgangsstufe, wahlweise mit Zuteilung.
     *
     * Die Zuteilung ist bewusst einfach gehalten und rechnet **nicht** den
     * Algorithmus der Anwendung nach: Es gibt fuer Leistungsfachkurse keine
     * Kapazitaetsgrenze im Schema, also bekommt jede Waehlerin ihren Kurs,
     * sofern es ihn gibt — Erstwahl vor Zweitwahl, sonst `keine`. Der
     * Testbestand soll den Ausgangszustand und ein plausibles Ergebnis
     * tragen, nicht die Entscheidung vorwegnehmen.
     *
     * @param list<int>            $schueler
     * @param array<string, int>   $kurse
     * @param array<string, mixed> $kennzahlen
     */
    private function seedLkWahlen(
        Carbon $jetzt,
        string $schuljahr,
        array $schueler,
        array $kurse,
        array $kennzahlen,
        bool $zuteilen
    ): int {
        $gewichte = [];

        foreach ($kennzahlen['lk_erstwahl_gewichte'] as $code => $gewicht) {
            if (isset($this->subjectIds[$code]) || isset($kurse[$code])) {
                $gewichte[$code] = (int) $gewicht;
            }
        }

        if ($gewichte === [] || $schueler === []) {
            return 0;
        }

        $externesFach = (string) $kennzahlen['lk_externes_fach'];
        $reihen = [];

        foreach ($schueler as $schuelerId) {
            $erst = $this->ziehe($gewichte, 1, [])[0] ?? null;

            if ($erst === null) {
                continue;
            }

            $zweit = mt_rand(1, 100) <= (int) round((float) $kennzahlen['lk_anteil_zweitwahl'] * 100)
                ? ($this->ziehe($gewichte, 1, [$erst])[0] ?? null)
                : null;

            $zuteilung = null;
            $prioritaet = null;

            if ($zuteilen) {
                if (isset($kurse[$erst])) {
                    $zuteilung = $kurse[$erst];
                    $prioritaet = 'erstwahl';
                } elseif ($zweit !== null && isset($kurse[$zweit])) {
                    $zuteilung = $kurse[$zweit];
                    $prioritaet = 'zweitwahl';
                } else {
                    $prioritaet = 'keine';
                }
            }

            $reihen[] = [
                'schuljahr' => $schuljahr,
                'jahrgang' => '11',
                'schueler_id' => $schuelerId,
                'erstwahl_subject_id' => $this->fachIdFuerWahl($erst),
                'is_erstwahl_extern' => $erst === $externesFach,
                'zweitwahl_subject_id' => $zweit === null ? null : $this->fachIdFuerWahl($zweit),
                'is_zweitwahl_extern' => $zweit === $externesFach,
                'zuteilung_lk_kurs_id' => $zuteilung,
                'prioritaet_erhalten' => $prioritaet,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('lk_wahlen')->insert($block);
        }

        return count($reihen);
    }

    /**
     * Die W-Seminare eines Schuljahres.
     *
     * Die Titel sind erfunden, aber im Ton der echten: ein W-Seminar heisst
     * nicht „Deutsch 1", sondern traegt ein Thema.
     *
     * @param array<string, mixed> $kennzahlen
     * @return list<array{id: int, code: string}>
     */
    private function seedWseminare(Carbon $jetzt, string $schuljahr, array $kennzahlen): array
    {
        $themen = [
            ['D', 'Erzählte Zeit — Gegenwartsliteratur im Vergleich'],
            ['E', 'Shakespeare on Screen'],
            ['G', 'Stadt und Bürgertum im Mittelalter'],
            ['Geo', 'Wasser als knappe Ressource'],
            ['WR', 'Marktmacht und Wettbewerb'],
            ['C', 'Chemie der Werkstoffe'],
            ['B', 'Ökosysteme unter Druck'],
            ['Ph', 'Vom Pendel zum Chaos'],
            ['Inf', 'Algorithmen des Alltags'],
            ['Ku', 'Bild und Botschaft — Plakatkunst'],
            ['PuG', 'Beteiligung jenseits der Wahlurne'],
        ];

        $seminare = [];
        $nummerJeFach = [];

        foreach ($themen as [$code, $titel]) {
            if (count($seminare) >= (int) $kennzahlen['wseminare']) {
                break;
            }

            if (!isset($this->subjectIds[$code])) {
                continue;
            }

            $nummerJeFach[$code] = ($nummerJeFach[$code] ?? 0) + 1;

            $seminare[] = [
                'id' => (int) DB::table('wseminar_kurse')->insertGetId([
                    'schuljahr' => $schuljahr,
                    'jahrgang' => '11',
                    'subject_id' => $this->subjectIds[$code],
                    'titel' => $titel,
                    'beschreibung' => null,
                    'lehrer_id' => $this->pruefer($code),
                    'max_teilnehmer' => (int) $kennzahlen['wseminar_max_teilnehmer'],
                    'kurs_nummer' => $nummerJeFach[$code],
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ]),
                'code' => $code,
            ];
        }

        return $seminare;
    }

    /**
     * Drei Wuensche je Schueler und eine Zuteilung, die die Plaetze achtet.
     *
     * `max_teilnehmer` ist hier — anders als beim Leistungsfach — eine echte
     * Grenze. Sie wird eingehalten statt behauptet: Wer keinen seiner drei
     * Wuensche mehr bekommt, bleibt ohne Zuteilung (`keine`). In der Pilotschule sind
     * das fuenf von 128.
     *
     * @param list<int>                          $schueler
     * @param list<array{id: int, code: string}> $seminare
     * @param array<string, mixed>               $kennzahlen
     */
    private function seedWseminarWahlen(
        Carbon $jetzt,
        string $schuljahr,
        array $schueler,
        array $seminare,
        array $kennzahlen
    ): int {
        if ($seminare === [] || $schueler === []) {
            return 0;
        }

        $plaetze = [];

        foreach ($seminare as $seminar) {
            $plaetze[$seminar['id']] = (int) $kennzahlen['wseminar_max_teilnehmer'];
        }

        // Die Wuensche sind nicht gleichverteilt, und daran haengt der ganze
        // interessante Teil: In der Pilotschule gibt es bei 165 Plaetzen fuer 128
        // Schueler trotzdem 23 Drittwahlen und fuenf, die leer ausgehen. Das
        // geht nur, wenn sich die Wuensche auf wenige Seminare draengen. Mit
        // einer Gleichverteilung (shuffle) bekamen im ersten Lauf 120 von 127
        // ihre Erstwahl, und die Faelle „Drittwahl" und „kein Platz" kamen
        // im Bestand gar nicht vor.
        $beliebtheit = [];

        foreach ($seminare as $rang => $seminar) {
            $beliebtheit[(string) $seminar['id']] = max(1, 40 - $rang * 3);
        }

        $reihen = [];

        foreach ($schueler as $schuelerId) {
            $wunsch = array_map('intval', $this->ziehe($beliebtheit, 3, []));

            if (count($wunsch) < 3) {
                continue;
            }

            $zuteilung = null;
            $prioritaet = 'keine';
            $rang = ['erstwahl', 'zweitwahl', 'drittwahl'];

            foreach ($wunsch as $position => $seminarId) {
                if ($plaetze[$seminarId] > 0) {
                    $plaetze[$seminarId]--;
                    $zuteilung = $seminarId;
                    $prioritaet = $rang[$position];

                    break;
                }
            }

            $reihen[] = [
                'schuljahr' => $schuljahr,
                'jahrgang' => '11',
                'schueler_id' => $schuelerId,
                'erstwahl_wseminar_id' => $wunsch[0],
                'zweitwahl_wseminar_id' => $wunsch[1],
                'drittwahl_wseminar_id' => $wunsch[2],
                'zuteilung_wseminar_kurs_id' => $zuteilung,
                'prioritaet_erhalten' => $prioritaet,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('wseminar_wahlen')->insert($block);
        }

        return count($reihen);
    }

    /**
     * Die zentrale subject_id eines Fachkuerzels — auch fuer Faecher, die
     * nicht im Strukturprofil stehen (das externe Leistungsfach).
     */
    private function fachIdFuerWahl(string $code): ?int
    {
        if (isset($this->subjectIds[$code])) {
            return $this->subjectIds[$code];
        }

        if (!array_key_exists($code, $this->externesFachCache)) {
            $fach = Subject::query()->where('code', $code)->first();
            $this->externesFachCache[$code] = $fach instanceof Subject ? (int) $fach->id : null;
        }

        return $this->externesFachCache[$code];
    }

    /**
     * Auszeichnungen zur Abiturfeier.
     *
     * Die Preisnamen sind **erfunden**. Die echten aus der Pilotschule tragen
     * Stifternamen, Vereinsnamen und den Namen der Schule — sie waeren
     * Personen- und Einrichtungsbezug in einem versionierten Profil, und
     * genau den soll dieses Profil nicht enthalten. Uebernommen ist allein
     * die Struktur: rund fuenfzehn Arten, ein knappes Dutzend davon
     * tatsaechlich vergeben, einige Preise mit Rangfolge, wenige Vorschlaege
     * als Kandidat, und mindestens eine Art, die in diesem Jahr **keinen
     * Traeger** findet (`no_winner_note`) — der Fall, den eine Preisliste
     * ausweisen muss, statt ihn zu verschweigen.
     */
    private function seedAuszeichnungen(): void
    {
        $jetzt = Carbon::now();
        $kennzahlen = $this->profil['auszeichnungen'];

        $beschreibungIds = [];

        foreach ($kennzahlen['beschreibungen'] as $beschreibung) {
            $beschreibungIds[] = (int) DB::table('award_descriptions')->insertGetId([
                'description' => (string) $beschreibung,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);
        }

        $arten = [
            ['Preis für herausragende Leistungen in Mathematik', 'Fachpreis', true, true],
            ['Preis für herausragende Leistungen in Deutsch', 'Fachpreis', false, false],
            ['Sprachenpreis', 'Fachpreis', false, false],
            ['Naturwissenschaftlicher Förderpreis', 'Fachbereich MINT', true, false],
            ['Preis der Fachschaft Geschichte', 'Fachpreis', false, false],
            ['Preis für die beste Seminararbeit', 'Seminararbeit', true, true],
            ['Anerkennung für eine besondere Seminararbeit', 'Seminararbeit', false, false],
            ['Zertifikat für soziales Engagement', null, false, true],
            ['Preis für besonderes Engagement in der Schülermitverantwortung', null, false, false],
            ['Sportpreis', 'Fachpreis', false, true],
            ['Musikpreis', 'Fachpreis', false, false],
            ['Kunstpreis', 'Fachpreis', false, false],
            ['Preis für den besten Gesamtdurchschnitt', null, true, true],
            ['Förderpreis Informatik', 'Fachbereich MINT', false, false],
            ['Anerkennung für langjährige Mitwirkung im Schulchor', null, false, false],
        ];

        $artIds = [];
        $reihen = [];

        foreach (array_slice($arten, 0, (int) $kennzahlen['preisarten']) as $sortierung => [$name, $kategorie, $mitRang, $sachpreis]) {
            // Die letzte Art bleibt in diesem Jahr ohne Trägerin oder Träger.
            $ohneTraeger = $sortierung === count($arten) - 1;

            $artIds[] = [
                'id' => (int) DB::table('award_types')->insertGetId([
                    'name' => $name,
                    'category' => $kategorie,
                    'teacher_id' => $this->teacherIds === [] ? null : $this->teacherIds[$sortierung % count($this->teacherIds)],
                    'active' => true,
                    'no_winner_note' => $ohneTraeger
                        ? 'In diesem Jahrgang nicht vergeben — keine Bewerbung eingegangen.'
                        : null,
                    'certificate_in_envelope' => $sortierung % 3 === 0,
                    'has_physical_prize' => $sachpreis,
                    'sort_order' => $sortierung,
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ]),
                'rang' => $mitRang,
                'vergeben' => !$ohneTraeger,
            ];
        }

        $vergebbar = array_values(array_filter($artIds, fn (array $art) => $art['vergeben']));

        if ($vergebbar === [] || $this->abiturientIds === []) {
            $this->command->warn('⚠️  Keine Auszeichnungen erzeugt (keine Preisart oder kein Prüfling).');

            return;
        }

        $anzahl = (int) round(count($this->abiturientIds) * (float) $kennzahlen['anteil_ausgezeichnet']);
        $prueflinge = $this->abiturientIds;
        shuffle($prueflinge);

        $vergabe = [];
        $rangJeArt = [];

        foreach (array_slice($prueflinge, 0, $anzahl) as $position => $studentId) {
            $art = $vergebbar[$position % count($vergebbar)];

            // Der UNIQUE geht über (Zeitraum, Prüfling, Preisart) — dieselbe
            // Art zweimal an dieselbe Person gäbe es in der Wirklichkeit auch
            // nicht.
            $schluessel = $studentId . '|' . $art['id'];

            if (isset($vergabe[$schluessel])) {
                continue;
            }

            $vergabe[$schluessel] = true;

            $rang = null;

            if ($art['rang']) {
                $rangJeArt[$art['id']] = ($rangJeArt[$art['id']] ?? 0) + 1;
                $rang = $rangJeArt[$art['id']];
            }

            $reihen[] = [
                'exam_period_id' => $this->examPeriodId,
                'student_id' => $studentId,
                'award_type_id' => $art['id'],
                'award_description_id' => $beschreibungIds === []
                    ? null
                    : $beschreibungIds[$position % count($beschreibungIds)],
                'notes' => null,
                'rank' => $rang,
                'is_candidate' => mt_rand(1, 100) <= (int) round((float) $kennzahlen['anteil_kandidat'] * 100),
                'sort_order' => $position % 10,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('awards')->insert($block);
        }

        $this->command->info(
            '🏅 ' . count($artIds) . ' Preisarten, ' . count($reihen) . ' Auszeichnungen vergeben ('
            . count($artIds) - count($vergebbar) . ' Art ohne Träger).'
        );
    }

    /**
     * Sitzungen des Abiturpruefungsausschusses.
     *
     * In der Pilotschule ist die Tabelle leer — das Feature ist dort noch nicht in
     * Gebrauch. Der Testbestand traegt trotzdem zwei Sitzungen, weil die
     * Niederschrift sonst gegen gar keine Daten laeuft.
     */
    private function seedPruefungsausschuss(): void
    {
        $jetzt = Carbon::now();
        $kennzahlen = $this->profil['pruefungsausschuss'];

        if (count($this->teacherIds) < 3) {
            return;
        }

        $tag = Carbon::parse($this->examPeriod->week_1_start ?? Carbon::now()->toDateString());
        $raeume = $this->raeumeVomTyp('exam');
        $sitzungen = 0;
        $teilnehmer = 0;

        for ($n = 1; $n <= (int) $kennzahlen['sitzungen']; $n++) {
            $sitzungId = (int) DB::table('exam_board_meetings')->insertGetId([
                'exam_period_id' => $this->examPeriodId,
                'number' => $n,
                'date' => $tag->copy()->subDays(14 * (3 - $n))->toDateString(),
                'start_time' => '14:00',
                'end_time' => '16:00',
                'location' => $raeume === [] ? 'Lehrerzimmer' : 'Raum der Schulleitung',
                'chair_teacher_id' => $this->teacherIds[0],
                'secretary_teacher_id' => $this->teacherIds[1],
                'agenda' => json_encode([
                    'Feststellung der Beschlussfähigkeit',
                    $n === 1 ? 'Zulassung zur Abiturprüfung' : 'Feststellung der Prüfungsergebnisse',
                    'Verschiedenes',
                ], JSON_UNESCAPED_UNICODE),
                'attachments' => json_encode([], JSON_UNESCAPED_UNICODE),
                'notes' => null,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);

            $sitzungen++;
            $reihen = [];
            $anzahl = min((int) $kennzahlen['teilnehmer_je_sitzung'], count($this->teacherIds));

            for ($i = 0; $i < $anzahl; $i++) {
                $reihen[] = [
                    'exam_board_meeting_id' => $sitzungId,
                    'teacher_id' => $this->teacherIds[$i],
                    'name_override' => null,
                    'sort_order' => $i,
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ];
            }

            // Ein Mitglied ohne Lehrkraft-Datensatz: die Vertretung der
            // Schulaufsicht sitzt im Ausschuss, steht aber nicht im
            // Kollegium. Dafür gibt es name_override.
            $reihen[] = [
                'exam_board_meeting_id' => $sitzungId,
                'teacher_id' => null,
                'name_override' => 'Ministerialbeauftragte (Vertretung der Schulaufsicht)',
                'sort_order' => $anzahl,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];

            DB::table('exam_board_meeting_attendees')->insert($reihen);
            $teilnehmer += count($reihen);
        }

        $this->command->info(
            '⚖️  ' . $sitzungen . ' Sitzungen des Prüfungsausschusses mit ' . $teilnehmer . ' Teilnehmern.'
        );
    }

    /**
     * Absenzen der ganzen Oberstufe, mit Importen und Massnahmen.
     *
     * Der groesste Datenblock des Seeders: In der Pilotschule stehen 5110 Absenzen auf
     * 238 Schueler — im Schnitt 21,5 je Kopf, der hoechste Wert liegt bei 75.
     * Die Verteilung ist uebernommen: gut die Haelfte Krankmeldungen, ein
     * Viertel Verspaetungen, ein knappes Fuenftel unentschuldigtes Fehlen.
     *
     * **Der `source_key` wird gebildet wie im Importdienst**
     * (`AbsenceCsvImportService::buildSourceKey()`): sha1 ueber Zeitraum, Art,
     * Stunden und Minuten, dazu Namensschluessel und laufende Nummer. Er ist
     * zusammen mit `student_id` eindeutig und entscheidet beim naechsten
     * Import darueber, ob eine Zeile als dieselbe erkannt wird. Ein Seeder,
     * der ihn anders bildet, erzeugt einen Bestand, den ein spaeterer Import
     * verdoppelt statt aktualisiert.
     *
     * **`absences.absence_import_id` ist `restrict`** — jede Absenz braucht
     * einen Import, und beim Leeren muessen die Absenzen vor den Importen
     * fallen. Die Reihenfolge in leereZieltabellen() beruecksichtigt das.
     */
    private function seedAbsenzen(): void
    {
        $jetzt = Carbon::now();
        $kennzahlen = $this->profil['absenzen'];

        $schueler = DB::table('students')
            ->where('exam_period_id', $this->examPeriodId)
            ->orderBy('id')
            ->get(['id', 'firstname', 'lastname', 'grade'])
            ->all();

        if ($schueler === []) {
            return;
        }

        $beginn = Carbon::parse($this->examPeriod->week_1_start ?? Carbon::now()->toDateString())
            ->subMonths(9)
            ->startOfMonth();

        // Zwei Importe wie in der Pilotschule: ein erster Lauf und ein zweiter, der
        // ueberschneidende Zeitraeume nachliefert.
        $importIds = [];

        for ($n = 1; $n <= (int) $kennzahlen['importe']; $n++) {
            $von = $beginn->copy()->addWeeks(($n - 1) * 12);
            $bis = $von->copy()->addWeeks(20);

            $importIds[] = (int) DB::table('absence_imports')->insertGetId([
                'original_filename' => 'Absenzenbericht_Oberstufe_' . $von->format('Y_m_d') . '.csv',
                'row_count' => 0,
                'matched_count' => 0,
                'created_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
                'filtered_count' => 0,
                'period_from' => $von->toDateString(),
                'period_to' => $bis->toDateString(),
                'imported_by_user_id' => null,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);
        }

        $kategorien = $kennzahlen['kategorien'];
        $entschuldigung = $kennzahlen['entschuldigung'];
        $reihen = [];
        $jeImport = array_fill_keys($importIds, 0);
        $auffaellige = [];

        foreach ($schueler as $person) {
            // Nicht jeder ist gleich oft weg: Der Schnitt liegt bei gut zwanzig,
            // aber ein Zehntel des Jahrgangs traegt ein Vielfaches davon — und
            // genau diese Faelle sind es, die eine Absenzenliste sortieren und
            // eine Attestpflicht ausloesen soll.
            //
            // Die Obergrenze des Normalfalls ist so gewaehlt, dass der
            // Gesamtschnitt trotz der Ausreisser beim Profilwert landet: Ein
            // Zehntel zieht mit einem Erwartungswert von rund 58, die
            // uebrigen neun Zehntel muessen deshalb bei etwa 17 liegen, nicht
            // bei 21. Ohne diese Korrektur kam der erste Lauf auf 9385 statt
            // der erwarteten 7850 Absenzen — ein Fuenftel zu viel.
            $anzahl = mt_rand(1, 100) <= 10
                ? mt_rand((int) $kennzahlen['je_schueler_schnitt'] * 2, (int) $kennzahlen['je_schueler_max'])
                : mt_rand(1, (int) round((int) $kennzahlen['je_schueler_schnitt'] * 1.6));

            $unentschuldigt = 0;
            $laufend = 0;

            for ($i = 0; $i < $anzahl; $i++) {
                $kategorie = $this->ziehe($kategorien, 1, [])[0] ?? 'krank';
                $status = $this->ziehe($entschuldigung, 1, [])[0] ?? 'offen';
                $tag = $beginn->copy()->addDays(mt_rand(0, 280));

                while ($tag->isWeekend()) {
                    $tag = $tag->addDay();
                }

                [$tage, $stunden, $minuten, $verspaetung, $von, $bis, $art] =
                    $this->absenzWerte($kategorie, $tag);

                if ($status === 'nein') {
                    $unentschuldigt++;
                }

                $importId = $importIds[$i % count($importIds)];
                $jeImport[$importId]++;
                $laufend++;

                $reihen[] = [
                    'student_id' => (int) $person->id,
                    'absence_import_id' => $importId,
                    'source_key' => sha1(
                        implode('|', [$tag->toDateString(), $bis->toDateString(), $art, $stunden, $minuten])
                        . '|' . mb_strtolower($person->lastname . ',' . $person->firstname) . '#' . $laufend
                    ),
                    'date_from' => $tag->toDateString(),
                    'date_to' => $bis->toDateString(),
                    'days' => $tage,
                    'hours' => $stunden,
                    'minutes' => $minuten,
                    'art_raw' => $art,
                    'category' => $kategorie,
                    'hour_from' => $von,
                    'hour_to' => $bis === $tag ? $von : null,
                    'minutes_late' => $verspaetung,
                    'excuse_status' => $status,
                    'reason' => null,
                    'is_school_absence' => false,
                    'source_recorded_at' => $jetzt,
                    'source_recorded_by' => null,
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ];
            }

            if ($unentschuldigt >= (int) $kennzahlen['massnahme_ab_unentschuldigt']) {
                $auffaellige[] = (int) $person->id;
            }
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('absences')->insert($block);
        }

        foreach ($jeImport as $importId => $anzahl) {
            DB::table('absence_imports')->where('id', $importId)->update([
                'row_count' => $anzahl,
                'matched_count' => $anzahl,
                'created_count' => $anzahl,
            ]);
        }

        $massnahmen = $this->seedAbsenzMassnahmen($jetzt, $auffaellige, $kennzahlen);
        $uebertraege = $this->seedAbsenzUebertraege($jetzt, $schueler, $kennzahlen);

        $this->command->info(
            '🤒 ' . count($reihen) . ' Absenzen aus ' . count($importIds) . ' Importen, '
            . $massnahmen . ' Maßnahmen, ' . $uebertraege . ' Vorjahresübertrag.'
        );
    }

    /**
     * Die Werte einer einzelnen Absenz, je nach Art.
     *
     * Eine Verspaetung dauert Minuten und keinen Tag, eine Krankmeldung
     * dauert Tage und keine Stunden, ein unentschuldigtes Fehlen einzelne
     * Stunden. In der Pilotschule sieht man das an den Mittelwerten: krank 1,09 Tage,
     * fehlt 1,36 Stunden, verspaetet ausschliesslich Minuten.
     *
     * @return array{0: int, 1: int, 2: int, 3: ?int, 4: ?int, 5: Carbon, 6: string}
     */
    private function absenzWerte(string $kategorie, Carbon $tag): array
    {
        return match ($kategorie) {
            'verspaetet' => [0, 0, 0, mt_rand(3, 45), mt_rand(1, 8), $tag, 'Verspätung'],
            'fehlt' => [0, mt_rand(1, 6), 0, null, mt_rand(1, 8), $tag, 'unentschuldigt gefehlt'],
            'sonstige' => [0, mt_rand(0, 2), 0, null, null, $tag, 'sonstiger Grund'],
            default => (function () use ($tag): array {
                $tage = mt_rand(1, 5);

                return [$tage, 0, 0, null, null, $tag->copy()->addDays($tage - 1), 'krank'];
            })(),
        };
    }

    /**
     * Massnahmen fuer die auffaelligen Faelle.
     *
     * @param list<int>            $auffaellige
     * @param array<string, mixed> $kennzahlen
     */
    private function seedAbsenzMassnahmen(Carbon $jetzt, array $auffaellige, array $kennzahlen): int
    {
        if ($auffaellige === []) {
            return 0;
        }

        $arten = ['attestpflicht_verhaengt', 'gespraech', 'verweis'];
        $reihen = [];

        foreach (array_slice($auffaellige, 0, (int) $kennzahlen['massnahmen_max']) as $position => $studentId) {
            $reihen[] = [
                'student_id' => $studentId,
                'type' => $arten[$position % count($arten)],
                'aspect' => $position % 2 === 0 ? 'unentschuldigt' : 'krankheit',
                'measure_date' => $jetzt->copy()->subDays(mt_rand(10, 120))->toDateString(),
                'note' => null,
                'created_by_user_id' => null,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];
        }

        DB::table('absence_measures')->insert($reihen);

        return count($reihen);
    }

    /**
     * Der Uebertrag aus dem Vorjahr.
     *
     * `absence_carryovers` traegt **bewusst keinen Fremdschluessel** auf den
     * Schueler: Der Uebertrag stammt aus einem Schuljahr, in dem es den
     * heutigen Datensatz noch nicht gab, und wird erst nachtraeglich
     * zugeordnet (`matched_student_id`, `match_method`). Der Bestand traegt
     * deshalb beides — zugeordnete Zeilen und eine, die offen bleibt.
     *
     * @param list<object>         $schueler
     * @param array<string, mixed> $kennzahlen
     */
    private function seedAbsenzUebertraege(Carbon $jetzt, array $schueler, array $kennzahlen): int
    {
        $vorjahr = $this->schuljahr(-1);
        $kandidaten = array_values(array_filter(
            $schueler,
            fn (object $person) => in_array($person->grade, ['12', '13'], true)
        ));

        if ($kandidaten === []) {
            return 0;
        }

        $reihen = [];
        $anzahl = min((int) $kennzahlen['uebertraege'], count($kandidaten));

        for ($i = 0; $i < $anzahl; $i++) {
            $person = $kandidaten[$i];
            // Der letzte bleibt ohne Zuordnung: die Klaerungsliste braucht
            // einen Fall, sonst ist sie im Testbestand immer leer.
            $zugeordnet = $i < $anzahl - 1;

            $reihen[] = [
                'firstname' => $person->firstname,
                'lastname' => $person->lastname,
                'external_id' => null,
                'source_school_year' => $vorjahr,
                'source_grade' => $person->grade === '13' ? '12' : '11',
                'sick_days' => mt_rand(0, 25),
                'sick_percent' => mt_rand(0, 180) / 10,
                'unexcused_hours' => mt_rand(0, 14),
                'tardiness_count' => mt_rand(0, 12),
                'tardiness_minutes' => mt_rand(0, 240),
                'had_attest_requirement' => $i % 7 === 0,
                'matched_student_id' => $zugeordnet ? (int) $person->id : null,
                'matched_at' => $zugeordnet ? $jetzt : null,
                'match_method' => $zugeordnet ? 'auto' : null,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('absence_carryovers')->insert($block);
        }

        return count($reihen);
    }

    /**
     * Stundenplan und Schuljahresereignisse.
     *
     * Der Hauptkonsument ist `TeacherFreeDayTest`: Die Kolloquiumsplanung
     * leitet aus `timetable_entries` ab, an welchen Tagen eine Lehrkraft
     * ohnehin nicht an der Schule ist, und schont diese Tage. Ein Raster ohne
     * echte Luecken waere fuer diese Frage wertlos — deshalb bekommt ein Teil
     * des Kollegiums bewusst einen freien Wochentag.
     *
     * **Das Schuljahr steht hier im Kurzformat** (`2026/27`), waehrend die
     * Wahl-Tabellen die lange Form fuehren (`2026/2027`). Das ist keine
     * Nachlaessigkeit dieses Bausteins, sondern der Bestand: die Spalte
     * `timetable_entries.school_year` hat den Vorgabewert `2024/25`, in der Pilotschule
     * stehen 2670 Zeilen auf `2025/26`. Wer hier die lange Form schriebe,
     * bekaeme einen Stundenplan, den die Anwendung nicht findet.
     *
     * **`ImportTimetable.php` taugt nicht als Vorlage:** Der Befehl schreibt
     * eine Spalte `teacher_code`, die es in dieser Tabelle nicht mehr gibt,
     * und setzt `teacher_id` nie — jeder Import landet still im catch-Zweig
     * (Befund vom 28.08.2026).
     */
    private function seedStundenplan(): void
    {
        $jetzt = Carbon::now();
        $kennzahlen = $this->profil['stundenplan'];
        $schuljahr = $this->schuljahrKurz();

        if ($this->teacherIds === []) {
            return;
        }

        $klassen = $kennzahlen['klassen'];
        $faecher = array_column($this->profil['faecher'], 'code');
        $raeume = array_values(array_map(
            fn (array $raum) => $raum['room_number'],
            $this->rooms
        ));

        $reihen = [];
        $freieTage = 0;

        foreach ($this->teacherIds as $lehrerId) {
            // Ein Teil des Kollegiums hat einen freien Tag. Ohne diesen Fall
            // prueft die Kolloquiumsplanung ihre eigene Schonung nie.
            $freierTag = mt_rand(1, 100) <= (int) round((float) $kennzahlen['anteil_freier_tag'] * 100)
                ? mt_rand(1, 5)
                : null;

            if ($freierTag !== null) {
                $freieTage++;
            }

            $stunden = mt_rand(
                (int) $kennzahlen['stunden_min'],
                (int) $kennzahlen['stunden_max']
            );

            // Belegte Zeitpunkte dieser Lehrkraft: niemand steht zweimal in
            // derselben Stunde.
            $belegt = [];
            $versuche = 0;

            while (count($belegt) < $stunden && $versuche < $stunden * 8) {
                $versuche++;
                $tag = mt_rand(1, 5);

                if ($tag === $freierTag) {
                    continue;
                }

                $stunde = mt_rand(1, (int) $kennzahlen['stunden_je_tag']);
                $schluessel = $tag . '-' . $stunde;

                if (isset($belegt[$schluessel])) {
                    continue;
                }

                $belegt[$schluessel] = true;

                // mt_rand statt array_rand: Der Seeder ist deterministisch,
                // weil mt_srand() den Generator setzt. array_rand() zieht aus
                // demselben Generator, verbraucht dabei aber eine andere Zahl
                // von Werten — der Determinismus-Test fiel prompt, und zwar
                // nicht nur hier: die verschobene Zufallsfolge aenderte auch
                // die danach erzeugten Zusatzpruefungen und Noten (28.08.2026).
                $fach = $faecher[mt_rand(0, count($faecher) - 1)];

                $reihen[] = [
                    'teacher_id' => $lehrerId,
                    'class_name' => $klassen[mt_rand(0, count($klassen) - 1)],
                    'course_code' => null,
                    'subject' => $fach,
                    'room' => $raeume === [] ? null : $raeume[mt_rand(0, count($raeume) - 1)],
                    'day_of_week' => $tag,
                    'period' => $stunde,
                    'school_year' => $schuljahr,
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ];
            }
        }

        foreach (array_chunk($reihen, 200) as $block) {
            DB::table('timetable_entries')->insert($block);
        }

        $ereignisse = $this->seedSchuljahresereignisse($jetzt, $schuljahr, $kennzahlen);

        $this->command->info(
            '📅 ' . count($reihen) . ' Stundenplaneinträge (' . $freieTage
            . ' Lehrkräfte mit freiem Tag), ' . $ereignisse . ' Schuljahresereignisse.'
        );
    }

    /**
     * Das Schuljahr im Kurzformat, etwa "2026/27".
     */
    private function schuljahrKurz(): string
    {
        $jahr = (int) $this->examPeriod->year;

        return ($jahr - 1) . '/' . substr((string) $jahr, -2);
    }

    /**
     * Ferien, Feiertage und Veranstaltungen.
     *
     * `blocks_exams` ist das Feld, auf das es ankommt: Eine SMV-Fahrt hindert
     * die Schule nicht daran, Pruefungen anzusetzen, Herbstferien schon. In
     * der Pilotschule sind von 17 Veranstaltungen 13 blockierend und vier nicht — der
     * Bestand traegt beides.
     *
     * @param array<string, mixed> $kennzahlen
     */
    private function seedSchuljahresereignisse(Carbon $jetzt, string $schuljahr, array $kennzahlen): int
    {
        $start = Carbon::parse($this->examPeriod->week_1_start ?? Carbon::now()->toDateString())
            ->subMonths(9)
            ->startOfMonth();

        $ereignisse = [
            ['Herbstferien', 'ferien', 40, 5, true, null],
            ['Weihnachtsferien', 'ferien', 95, 14, true, null],
            ['Buß- und Bettag', 'feiertag', 60, 1, true, null],
            ['Tag der Deutschen Einheit', 'feiertag', 10, 1, true, null],
            ['Exkursion Q13', 'exkursion', 35, 1, true, ['13']],
            ['Exkursion Q12', 'exkursion', 36, 1, true, ['12']],
            ['SMV-Fahrt', 'veranstaltung', 45, 5, false, null],
            ['Konzert der Schule', 'veranstaltung', 100, 1, false, null],
            ['Informationsabend Oberstufe', 'veranstaltung', 70, 1, false, ['11']],
            ['Studien- und Berufsmesse', 'veranstaltung', 80, 1, true, ['12', '13']],
            ['Projekttag', 'veranstaltung', 120, 1, true, null],
            ['Praktikumswoche', 'veranstaltung', 150, 5, true, ['11']],
        ];

        $reihen = [];

        foreach ($ereignisse as [$name, $typ, $versatz, $dauer, $blockiert, $stufen]) {
            $von = $start->copy()->addDays($versatz);

            $reihen[] = [
                'school_year' => $schuljahr,
                'name' => $name,
                'type' => $typ,
                'start_date' => $von->toDateString(),
                'end_date' => $dauer > 1 ? $von->copy()->addDays($dauer - 1)->toDateString() : $von->toDateString(),
                'description' => null,
                'blocks_exams' => $blockiert,
                'affected_grades' => $stufen === null ? null : json_encode($stufen),
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ];
        }

        DB::table('school_events')->insert($reihen);

        return count($reihen);
    }

    /**
     * Gegenproben zu den Zusatzpruefungen und den Themenbereichen.
     *
     * Jede ist als Verletzung formuliert — erwartet wird null. Die dritte und
     * vierte sind der eigentliche Grund fuer diesen Baustein: sie halten den
     * Sportfall fest, an dem die Rechnung zweimal falsch war.
     *
     * @return list<string>
     */
    private function pruefeZusatzpruefungen(): array
    {
        $fehler = [];

        $ohneTermin = DB::table('zusatzpruefungen')
            ->where('status', 'placed')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('zusatzpruef_assignments')
                ->whereColumn('zusatzpruef_assignments.zusatzpruefung_id', 'zusatzpruefungen.id'))
            ->count();

        if ($ohneTermin > 0) {
            $fehler[] = "{$ohneTermin} platzierte Zusatzprüfungen ohne Termin.";
        }

        $doppelteRaeume = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM (
                SELECT a.zusatzpruef_day_room_id, a.start_time
                FROM zusatzpruef_assignments a
                GROUP BY a.zusatzpruef_day_room_id, a.start_time
                HAVING COUNT(*) > 1
            ) t'
        );

        if ($doppelteRaeume !== null && (int) $doppelteRaeume->anzahl > 0) {
            $fehler[] = (int) $doppelteRaeume->anzahl . ' Zusatzprüfungsräume sind zur selben Zeit doppelt belegt.';
        }

        // Die Sportfaecher werden ueber die zentral vergebenen subject_ids
        // gesucht, nicht ueber einen Join auf `subjects`: der Fächerkatalog
        // liegt in der zentralen Datenbank, im Mandanten gibt es die Tabelle
        // nicht. Ein Join darauf laeuft in „Table school_XXXX.subjects
        // doesn't exist" — und zwar erst zur Laufzeit.
        $sportSubjectIds = array_values(array_filter(
            array_map(fn (string $code) => $this->subjectIds[$code] ?? null, ['Sw', 'Sm', 'Smw'])
        ));

        if ($sportSubjectIds === []) {
            return $fehler;
        }

        // Eine Sportpruefung braucht ihre Praxisnote genau dann, wenn der
        // Pruefling in eine Zusatzpruefung geht: dort rechnet der
        // Mindestpunkte-Rechner ueber alle fuenf Faecher, und ohne sie gibt
        // scoreContribution() fuer das Sportfach null zurueck
        // (Fehlerklasse 11). Ein Sport-Kolloquium ohne Zusatzpruefung braucht
        // keine — deshalb prueft diese Probe nur den Kreis, in dem sie
        // gebraucht wird, statt einen Zustand zu verlangen, den es in echten
        // Bestaenden nicht gibt.
        $sportOhnePraxis = DB::table('student_exams as se')
            ->join('courses as c', 'c.id', '=', 'se.course_id')
            ->leftJoin('zusatzpruef_exam_scores as sc', 'sc.student_exam_id', '=', 'se.id')
            ->whereIn('c.subject_id', $sportSubjectIds)
            ->whereNull('sc.practical_grade')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('zusatzpruefungen as zp')
                ->whereColumn('zp.student_id', 'se.student_id'))
            ->count();

        if ($sportOhnePraxis > 0) {
            $fehler[] = "{$sportOhnePraxis} Sportprüfungen von Prüflingen mit Zusatzprüfung ohne Praxisnote.";
        }

        $sportZp = DB::table('zusatzpruefungen as zp')
            ->join('student_exams as se', 'se.id', '=', 'zp.student_exam_id')
            ->join('courses as c', 'c.id', '=', 'se.course_id')
            ->whereIn('c.subject_id', $sportSubjectIds)
            ->count();

        if ($sportZp === 0) {
            $fehler[] = 'Keine einzige Zusatzprüfung im Sport — der Fall, für den dieser Baustein gebaut wurde.';
        }

        $mehrfach = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM (
                SELECT student_id FROM zusatzpruefungen
                WHERE student_id IS NOT NULL
                GROUP BY student_id HAVING COUNT(*) > 1
            ) t'
        );

        if ($mehrfach === null || (int) $mehrfach->anzahl === 0) {
            $fehler[] = 'Kein Prüfling mit mehreren Zusatzprüfungen — dann bleibt die Kombinationsrechnung für N=2 ungeprüft.';
        }

        $ohneNoten = DB::table('zusatzpruefungen as zp')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('zusatzpruef_exam_scores as sc')
                ->whereColumn('sc.student_exam_id', 'zp.student_exam_id'))
            ->count();

        if ($ohneNoten > 0) {
            $fehler[] = "{$ohneNoten} Zusatzprüfungen ohne Note zur zugehörigen schriftlichen Prüfung.";
        }

        // ── Externe Bewerber ─────────────────────────────────────────────
        //
        // Sie brauchen eigene Proben, weil die obigen ueber student_id gehen
        // und der bei einem externen Bewerber null ist.

        $bewerber = DB::table('external_applicants')->count();

        if ($bewerber === 0) {
            $fehler[] = 'Kein externer Bewerber im Bestand — Modul 2.1 bliebe ungeprüft.';

            return $fehler;
        }

        // Aus acht Pruefungsfaechern werden sechs Pruefungen, sobald zwei
        // eingebracht sind (§ 64 Abs. 2 S. 1). Diese Probe haelt beides
        // zusammen: die Zahl der Faecher und die Zahl der daraus entstandenen
        // Pruefungen.
        $falscheZahl = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM (
                SELECT ees.external_applicant_id,
                       COUNT(*) AS faecher,
                       SUM(ees.ergebnis_quelle = "einbringung") AS eingebracht,
                       COUNT(se.id) AS pruefungen
                FROM external_exam_subjects ees
                LEFT JOIN student_exams se ON se.external_exam_subject_id = ees.id
                GROUP BY ees.external_applicant_id
                HAVING faecher <> 8 OR pruefungen <> 8 - eingebracht
            ) t'
        );

        if ($falscheZahl !== null && (int) $falscheZahl->anzahl > 0) {
            $fehler[] = (int) $falscheZahl->anzahl
                . ' externe Bewerber haben nicht acht Fächer oder eine falsche Zahl Prüfungen.';
        }

        $ohneErsatzschule = DB::table('external_exam_subjects as ees')
            ->join('external_applicants as ea', 'ea.id', '=', 'ees.external_applicant_id')
            ->where('ees.ergebnis_quelle', 'einbringung')
            ->whereNull('ea.ersatzschule_id')
            ->count();

        if ($ohneErsatzschule > 0) {
            $fehler[] = "{$ohneErsatzschule} eingebrachte Fächer ohne Ersatzschule (§ 64 Abs. 2 gilt nur für sie).";
        }

        // Die Einbringung steht auf den Positionen sieben und acht — also im
        // zweiten Pruefungsteil auf den Faechern drei und vier.
        $falschePosition = DB::table('external_exam_subjects')
            ->where('ergebnis_quelle', 'einbringung')
            ->where(fn ($q) => $q->where('exam_part', '<>', 'part2_oral')
                ->orWhereNotIn('subject_number', [3, 4]))
            ->count();

        if ($falschePosition > 0) {
            $fehler[] = "{$falschePosition} eingebrachte Fächer stehen nicht auf Position sieben oder acht.";
        }

        $keineAnlage15 = DB::table('external_applicants as ea')
            ->whereNotNull('ea.ersatzschule_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('external_exam_subjects as ees')
                ->whereColumn('ees.external_applicant_id', 'ea.id')
                ->where('ees.ergebnis_quelle', 'einbringung'))
            ->count();

        if ($keineAnlage15 > 0) {
            $fehler[] = 'Kein Bewerber nach Anlage 15 im Bestand — der § 64-Weg bliebe ungeprüft.';
        }

        // Eine Zusatzpruefung ohne student_id: genau der Fall, an dem 34
        // Abfragen mit whereHas('student') still vorbeigelaufen sind.
        $externeZp = DB::table('zusatzpruefungen')->whereNull('student_id')->count();

        if ($externeZp === 0) {
            $fehler[] = 'Keine Zusatzprüfung eines externen Bewerbers — dann bleibt die nullable student_id ungeprüft.';
        }

        // ── LK- und W-Seminar-Wahlen ─────────────────────────────────────

        $ohneLkWahl = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM students s
             WHERE s.grade IN ("11", "12")
               AND NOT EXISTS (SELECT 1 FROM lk_wahlen w WHERE w.schueler_id = s.id)'
        );

        if ($ohneLkWahl !== null && (int) $ohneLkWahl->anzahl > 0) {
            $fehler[] = (int) $ohneLkWahl->anzahl . ' Schüler der Stufen 11 und 12 ohne Leistungsfachwahl.';
        }

        // Die Jahrgangsstufe 11 waehlt gerade: ihre Wahlen duerfen keine
        // Zuteilung tragen. Ohne diesen Zustand gaebe es im Bestand nichts,
        // worauf eine Zuteilung ueberhaupt angewendet werden koennte.
        $offeneWahlen = DB::table('lk_wahlen')
            ->whereNull('prioritaet_erhalten')
            ->whereNull('zuteilung_lk_kurs_id')
            ->count();

        if ($offeneWahlen === 0) {
            $fehler[] = 'Keine offene Leistungsfachwahl — dann gibt es keinen Ausgangszustand für die Zuteilung.';
        }

        $halbZugeteilt = DB::table('lk_wahlen')
            ->where(fn ($q) => $q->whereNull('prioritaet_erhalten')->whereNotNull('zuteilung_lk_kurs_id'))
            ->orWhere(fn ($q) => $q->where('prioritaet_erhalten', '<>', 'keine')
                ->whereNull('zuteilung_lk_kurs_id'))
            ->count();

        if ($halbZugeteilt > 0) {
            $fehler[] = "{$halbZugeteilt} Leistungsfachwahlen sind halb zugeteilt (Kurs ohne Priorität oder umgekehrt).";
        }

        // max_teilnehmer ist beim W-Seminar eine echte Grenze.
        $ueberbelegt = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM (
                SELECT k.id
                FROM wseminar_kurse k
                JOIN wseminar_wahlen w ON w.zuteilung_wseminar_kurs_id = k.id
                GROUP BY k.id, k.max_teilnehmer
                HAVING COUNT(*) > k.max_teilnehmer
            ) t'
        );

        if ($ueberbelegt !== null && (int) $ueberbelegt->anzahl > 0) {
            $fehler[] = (int) $ueberbelegt->anzahl . ' W-Seminare sind über ihre Teilnehmerzahl hinaus belegt.';
        }

        // Eine Zuteilung muss einer der drei Wuensche sein, und die
        // ausgewiesene Prioritaet muss zu der Stelle passen, an der sie steht.
        // Das ist die eigentliche Probe: Eine Zuteilung, die irgendein
        // Seminar nennt, sähe im Bestand genauso aus wie eine richtige.
        $falschePrioritaet = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM wseminar_wahlen w
             WHERE w.zuteilung_wseminar_kurs_id IS NOT NULL
               AND NOT (
                   (w.prioritaet_erhalten = "erstwahl"  AND w.zuteilung_wseminar_kurs_id = w.erstwahl_wseminar_id)
                OR (w.prioritaet_erhalten = "zweitwahl" AND w.zuteilung_wseminar_kurs_id = w.zweitwahl_wseminar_id)
                OR (w.prioritaet_erhalten = "drittwahl" AND w.zuteilung_wseminar_kurs_id = w.drittwahl_wseminar_id)
               )'
        );

        if ($falschePrioritaet !== null && (int) $falschePrioritaet->anzahl > 0) {
            $fehler[] = (int) $falschePrioritaet->anzahl
                . ' W-Seminar-Zuteilungen passen nicht zur ausgewiesenen Priorität.';
        }

        // Alle vier Ausgaenge muessen vorkommen — sonst prueft der Bestand
        // den Zuteilungsalgorithmus nur zur Haelfte. Der Fall „kein Platz"
        // entsteht nur, wenn sich die Wuensche auf wenige Seminare draengen.
        $ausgaenge = DB::table('wseminar_wahlen')
            ->select('prioritaet_erhalten')
            ->distinct()
            ->pluck('prioritaet_erhalten')
            ->filter()
            ->all();

        $fehlend = array_diff(['erstwahl', 'zweitwahl', 'drittwahl', 'keine'], $ausgaenge);

        if ($fehlend !== []) {
            $fehler[] = 'Im W-Seminar-Bestand fehlen die Ausgänge: ' . implode(', ', $fehlend) . '.';
        }

        $externerKurs = DB::table('lk_kurse')->where('ist_extern', true)->count();

        if ($externerKurs === 0) {
            $fehler[] = 'Kein externer Leistungsfachkurs — dann bleibt ist_extern im Bestand ungeprüft.';
        }

        // ── Auszeichnungen und Prüfungsausschuss ─────────────────────────

        // awards.award_type_id trägt keinen Fremdschlüssel — die Tabellen
        // lagen bis Juni zentral. Eine verwaiste Auszeichnung fiele deshalb
        // erst auf dem Ausdruck auf.
        $verwaisteAuszeichnung = DB::table('awards as a')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('award_types as t')
                ->whereColumn('t.id', 'a.award_type_id'))
            ->count();

        if ($verwaisteAuszeichnung > 0) {
            $fehler[] = "{$verwaisteAuszeichnung} Auszeichnungen zeigen auf eine Preisart, die es nicht gibt.";
        }

        $ohneTraeger = DB::table('award_types')->whereNotNull('no_winner_note')->count();

        if ($ohneTraeger === 0) {
            $fehler[] = 'Keine Preisart ohne Träger — dann bleibt die Meldung "nicht vergeben" ungeprüft.';
        }

        // Wo ein Rang geführt wird, muss er je Preisart bei 1 beginnen und
        // lückenlos sein: „2. Preis" ohne ersten wäre ein Druckfehler.
        $rangLuecken = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM (
                SELECT award_type_id
                FROM awards
                WHERE rank IS NOT NULL
                GROUP BY award_type_id
                HAVING MIN(rank) <> 1 OR MAX(rank) <> COUNT(*)
            ) t'
        );

        if ($rangLuecken !== null && (int) $rangLuecken->anzahl > 0) {
            $fehler[] = (int) $rangLuecken->anzahl . ' Preisarten haben eine lückenhafte Rangfolge.';
        }

        $sitzungOhneTeilnehmer = DB::table('exam_board_meetings as m')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('exam_board_meeting_attendees as a')
                ->whereColumn('a.exam_board_meeting_id', 'm.id'))
            ->count();

        if ($sitzungOhneTeilnehmer > 0) {
            $fehler[] = "{$sitzungOhneTeilnehmer} Sitzungen des Prüfungsausschusses ohne Teilnehmer.";
        }

        // Ein Mitglied ohne Lehrkraft-Datensatz (name_override): die
        // Schulaufsicht sitzt im Ausschuss, steht aber nicht im Kollegium.
        $freiesMitglied = DB::table('exam_board_meeting_attendees')
            ->whereNull('teacher_id')
            ->whereNotNull('name_override')
            ->count();

        if ($freiesMitglied === 0) {
            $fehler[] = 'Kein Ausschussmitglied ohne Lehrkraft-Datensatz — dann bleibt name_override ungeprüft.';
        }

        // ── Absenzen ─────────────────────────────────────────────────────

        $absenzen = DB::table('absences')->count();

        if ($absenzen === 0) {
            $fehler[] = 'Keine Absenzen im Bestand.';
        } else {
            // Der Bestand soll dem Profil entsprechen, nicht nur irgendwie
            // gefuellt sein. Ein Fuenftel Abweichung nach oben hatte der
            // erste Lauf, weil die Ausreisser den Schnitt hoben.
            $schueler = DB::table('students')->count();
            $soll = $schueler * (int) $this->profil['absenzen']['je_schueler_schnitt'];

            if ($schueler > 0 && ($absenzen < $soll * 0.75 || $absenzen > $soll * 1.25)) {
                $fehler[] = "Die Zahl der Absenzen ({$absenzen}) weicht mehr als ein Viertel vom Profil ({$soll}) ab.";
            }
        }

        // absence_import_id ist `restrict`: eine Absenz ohne Import kann es
        // nicht geben, und ein Import ohne Absenzen waere ein leerer Lauf.
        $ohneImport = DB::table('absences as a')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('absence_imports as i')
                ->whereColumn('i.id', 'a.absence_import_id'))
            ->count();

        if ($ohneImport > 0) {
            $fehler[] = "{$ohneImport} Absenzen ohne zugehörigen Import.";
        }

        // Der source_key entscheidet beim naechsten Import, ob eine Zeile als
        // dieselbe erkannt wird. Zusammen mit student_id muss er eindeutig
        // sein — sonst verdoppelt ein Import den Bestand, statt ihn zu
        // aktualisieren.
        $doppelterKey = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM (
                SELECT student_id, source_key FROM absences
                GROUP BY student_id, source_key HAVING COUNT(*) > 1
            ) t'
        );

        if ($doppelterKey !== null && (int) $doppelterKey->anzahl > 0) {
            $fehler[] = (int) $doppelterKey->anzahl . ' doppelte source_key je Schüler — ein Import würde sie verdoppeln.';
        }

        // Jede Kategorie hat ihre eigene Masseinheit: eine Verspaetung dauert
        // Minuten, eine Krankmeldung Tage. Ein Bestand, der das vermischt,
        // laesst jede Auswertung sinnlos aussehen.
        $falscheEinheit = DB::table('absences')
            ->where(fn ($q) => $q->where('category', 'verspaetet')->whereNull('minutes_late'))
            ->orWhere(fn ($q) => $q->where('category', 'krank')->where('days', 0))
            ->count();

        if ($falscheEinheit > 0) {
            $fehler[] = "{$falscheEinheit} Absenzen tragen nicht die Maßeinheit ihrer Kategorie.";
        }

        $ohneZuordnung = DB::table('absence_carryovers')->whereNull('matched_student_id')->count();

        if ($ohneZuordnung === 0) {
            $fehler[] = 'Jeder Vorjahresübertrag ist zugeordnet — dann bleibt die Klärungsliste immer leer.';
        }

        // ── Stundenplan ──────────────────────────────────────────────────

        // Niemand steht zweimal in derselben Stunde.
        $doppelstunde = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM (
                SELECT teacher_id, day_of_week, period
                FROM timetable_entries
                WHERE teacher_id IS NOT NULL
                GROUP BY teacher_id, day_of_week, period
                HAVING COUNT(*) > 1
            ) t'
        );

        if ($doppelstunde !== null && (int) $doppelstunde->anzahl > 0) {
            $fehler[] = (int) $doppelstunde->anzahl . ' Lehrkräfte stehen zweimal in derselben Stunde.';
        }

        // Ohne freie Tage prueft die Kolloquiumsplanung ihre eigene Schonung
        // nie — sie leitet sie allein aus diesem Raster ab.
        $mitFreiemTag = DB::selectOne(
            'SELECT COUNT(*) AS anzahl FROM (
                SELECT teacher_id FROM timetable_entries
                WHERE teacher_id IS NOT NULL
                GROUP BY teacher_id
                HAVING COUNT(DISTINCT day_of_week) < 5
            ) t'
        );

        if ($mitFreiemTag === null || (int) $mitFreiemTag->anzahl === 0) {
            $fehler[] = 'Keine Lehrkraft mit freiem Tag — dann bleibt die Schonung freier Tage ungeprüft.';
        }

        // Das Schuljahr steht in dieser Tabelle im Kurzformat (2026/27),
        // waehrend die Wahl-Tabellen die lange Form fuehren. Ein Stundenplan
        // in der langen Form waere fuer die Anwendung unsichtbar.
        $falschesFormat = DB::table('timetable_entries')
            ->where('school_year', 'not like', '____/__')
            ->count();

        if ($falschesFormat > 0) {
            $fehler[] = "{$falschesFormat} Stundenplaneinträge tragen das Schuljahr nicht im Kurzformat.";
        }

        $blockierend = DB::table('school_events')->where('blocks_exams', true)->count();
        $nichtBlockierend = DB::table('school_events')->where('blocks_exams', false)->count();

        if ($blockierend === 0 || $nichtBlockierend === 0) {
            $fehler[] = 'Die Schuljahresereignisse decken nicht beide Fälle von blocks_exams ab.';
        }

        $externerSitzplatz = DB::table('written_exam_seat_assignments')
            ->whereNotNull('external_applicant_id')
            ->count();

        if ($externerSitzplatz === 0) {
            $fehler[] = 'Kein externer Bewerber im Sitzplan — dann bleibt der Nachteilsausgleich auf den Ausdrucken ungeprüft.';
        }

        $themenlos = DB::table('student_exams as se')
            ->where('se.exam_type', 'oral')
            ->whereNotNull('se.course_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('colloquium_topics as ct')
                ->whereColumn('ct.course_id', 'se.course_id'))
            ->count();

        if ($themenlos > 0) {
            $fehler[] = "{$themenlos} Kolloquien in Kursen ohne Themenbereiche.";
        }

        return $fehler;
    }
}
