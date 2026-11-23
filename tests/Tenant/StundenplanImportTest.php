<?php

namespace Tests\Tenant;

use App\Models\School;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Services\TimetableImportService;

/**
 * Der Stundenplan-Import — bis zum 28.08.2026 ohne einen einzigen Test.
 *
 * Genau deshalb blieb unbemerkt, dass `ImportTimetable.php` die längst
 * entfernte Spalte `teacher_code` schrieb, `teacher_id` nie setzte und jeden
 * Stapel still im catch-Zweig verlor — während `importCombined()` dem Nutzer
 * „Import erfolgreich abgeschlossen" meldete.
 *
 * Die Tests fahren gegen den Dienst, den seit dem Umbau **alle** Wege benutzen
 * (geführte Maske, kombinierter Import, Kommandozeile). Gegen den alten Code
 * fallen sie: Dort steht nach dem Import keine Zeile in der Tabelle.
 */
class StundenplanImportTest extends TenantTestCase
{
    private const SCHULJAHR = '2026/27';

    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tmpFiles = [];

        parent::tearDown();
    }

    /**
     * Schreibt eine Datei im Untis-Exportformat, wie sie
     * TimetableImportPreviewService::parseFile() liest: kommagetrennt,
     * mindestens sieben Felder, Klasse in Spalte 2, Lehrerkürzel in Spalte 3,
     * Kurscode 4, Raum 5, Wochentag 6 (1–5), Stunde 7 (1–12).
     *
     * @param list<array{0: string, 1: string, 2: string, 3: string, 4: int|string, 5: int|string}> $zeilen
     */
    private function untisDatei(array $zeilen): string
    {
        $inhalt = '';

        foreach ($zeilen as $i => [$klasse, $kuerzel, $kurs, $raum, $tag, $stunde]) {
            $inhalt .= implode(',', [$i + 1, $klasse, $kuerzel, $kurs, $raum, $tag, $stunde]) . "\n";
        }

        $pfad = sys_get_temp_dir() . '/untis_test_' . uniqid() . '.txt';
        file_put_contents($pfad, $inhalt);
        $this->tmpFiles[] = $pfad;

        return $pfad;
    }

    private function dienst(): TimetableImportService
    {
        return app(TimetableImportService::class);
    }

    private function lehrkraft(string $kuerzel, string $nachname): Teacher
    {
        return Teacher::create([
            'shortcode' => $kuerzel,
            'firstname' => 'Test',
            'lastname'  => $nachname,
        ]);
    }

    /**
     * Der Kern des Befundes: Ein Import muss Zeilen schreiben, und jede Zeile
     * muss die Lehrkraft über `teacher_id` führen. Der alte Befehl schrieb
     * `teacher_code` — eine Spalte, die es nicht mehr gibt.
     */
    public function test_import_schreibt_zeilen_und_setzt_die_lehrerzuordnung(): void
    {
        $school = $this->createTestSchool();


        try {
            $hmb = $this->lehrkraft('HMB', 'Hamberger');
            $trz = $this->lehrkraft('MUS', 'Mustermann');

            $datei = $this->untisDatei([
                ['Q12', 'HMB', '1D-gA', 'F43', 1, 3],
                ['Q12', 'MUS', '2M-eA', 'F12', 2, 1],
                ['Q13', 'HMB', '1D-gA', 'F43', 4, 5],
            ]);

            $ergebnis = $this->dienst()->importFile($datei, self::SCHULJAHR);

            $this->assertSame(3, $ergebnis['imported'], 'Alle drei Zeilen müssen geschrieben werden.');
            $this->assertSame(0, $ergebnis['without_teacher']);
            $this->assertSame(3, TimetableEntry::where('school_year', self::SCHULJAHR)->count());

            $eintrag = TimetableEntry::where('school_year', self::SCHULJAHR)
                ->where('class_name', 'Q12')
                ->where('day_of_week', 1)
                ->firstOrFail();

            $this->assertSame($hmb->id, $eintrag->teacher_id, 'teacher_id muss aus dem Kürzel aufgelöst sein.');
            $this->assertSame('Deutsch', $eintrag->subject);
            $this->assertSame('F43', $eintrag->room);
            $this->assertSame(3, $eintrag->period);

            // Die Beziehung trägt — daran hängt jede Auswertung, von der
            // Lehrer-Stundenplanansicht bis zu den freien Wochentagen.
            $this->assertSame('HMB', $eintrag->teacher?->shortcode);

            $zweiter = TimetableEntry::where('school_year', self::SCHULJAHR)
                ->where('day_of_week', 2)
                ->firstOrFail();

            $this->assertSame($trz->id, $zweiter->teacher_id);
            $this->assertSame('Mathematik', $zweiter->subject);
        } finally {
        }
    }

    /**
     * Ein unbekanntes Kürzel darf die Zeile nicht verschlucken — sie wird mit
     * `teacher_id = null` geschrieben —, aber der Aufrufer muss davon
     * erfahren. Sonst steht ein halber Stundenplan in der Datenbank und
     * niemand weiß es.
     */
    public function test_unbekanntes_kuerzel_wird_geschrieben_und_gemeldet(): void
    {
        $school = $this->createTestSchool();


        try {
            $this->lehrkraft('HMB', 'Hamberger');

            $datei = $this->untisDatei([
                ['Q12', 'HMB', '1D-gA', 'F43', 1, 3],
                ['Q12', 'XYZ', '1B-gA', 'F44', 1, 4],
            ]);

            $ergebnis = $this->dienst()->importFile($datei, self::SCHULJAHR);

            $this->assertSame(2, $ergebnis['imported']);
            $this->assertSame(1, $ergebnis['without_teacher'], 'Die Zeile ohne bekannte Lehrkraft muss gezählt werden.');

            $verwaist = TimetableEntry::where('school_year', self::SCHULJAHR)
                ->whereNull('teacher_id')
                ->firstOrFail();

            $this->assertSame('F44', $verwaist->room);
        } finally {
        }
    }

    /**
     * Untis exportiert Zeilen, die kein Unterricht sind: Tag oder Stunde auf
     * 0, Pseudo-Klassen wie „NA" (Raumblockierung). Sie gehören übersprungen,
     * nicht importiert und auch nicht als Fehler gezählt.
     */
    public function test_sonderzeilen_landen_nicht_im_stundenplan(): void
    {
        $school = $this->createTestSchool();


        try {
            $this->lehrkraft('HMB', 'Hamberger');

            $datei = $this->untisDatei([
                ['Q12', 'HMB', '1D-gA', 'F43', 1, 3],
                ['Q12', 'HMB', '1D-gA', 'F43', 0, 0],   // Sonderzeile
                ['NA',  'HMB', '',      'F99', 3, 2],   // Raumblockierung
            ]);

            $ergebnis = $this->dienst()->importFile($datei, self::SCHULJAHR);

            $this->assertSame(1, $ergebnis['imported']);
            $this->assertSame(0, $ergebnis['stats']['errors'], 'Sonderzeilen sind keine Fehler.');
            $this->assertSame(1, TimetableEntry::where('school_year', self::SCHULJAHR)->count());
        } finally {
        }
    }

    /**
     * `--truncate` räumt das Schuljahr auf, das importiert wird — und nur
     * dieses. Ein Stundenplan des Vorjahres muss stehen bleiben, sonst
     * verliert die Schule beim Jahreswechsel ihre Vergleichsdaten.
     */
    public function test_truncate_raeumt_nur_das_importierte_schuljahr(): void
    {
        $school = $this->createTestSchool();


        try {
            $hmb = $this->lehrkraft('HMB', 'Hamberger');

            TimetableEntry::create([
                'teacher_id'  => $hmb->id,
                'class_name'  => 'Q12',
                'course_code' => '1D-gA',
                'subject'     => 'Deutsch',
                'room'        => 'F43',
                'day_of_week' => 5,
                'period'      => 6,
                'school_year' => '2025/26',
            ]);

            TimetableEntry::create([
                'teacher_id'  => $hmb->id,
                'class_name'  => 'Q12',
                'course_code' => '1D-gA',
                'subject'     => 'Deutsch',
                'room'        => 'F43',
                'day_of_week' => 5,
                'period'      => 5,
                'school_year' => self::SCHULJAHR,
            ]);

            $datei = $this->untisDatei([
                ['Q12', 'HMB', '1D-gA', 'F43', 1, 3],
            ]);

            $this->dienst()->importFile($datei, self::SCHULJAHR, false, true);

            $this->assertSame(
                1,
                TimetableEntry::where('school_year', '2025/26')->count(),
                'Das Vorjahr darf von --truncate nicht betroffen sein.'
            );
            $this->assertSame(1, TimetableEntry::where('school_year', self::SCHULJAHR)->count());
            $this->assertSame(
                3,
                TimetableEntry::where('school_year', self::SCHULJAHR)->firstOrFail()->period,
                'Die alte Zeile des Schuljahres muss der neuen gewichen sein.'
            );
        } finally {
        }
    }

    /**
     * Der Kommandozeilen-Weg, Ende zu Ende: `--school` schaltet den Mandanten
     * selbst ein. Vorher lief der Befehl gegen die zuletzt gesetzte
     * Verbindung — im Zweifel die zentrale Datenbank.
     */
    public function test_der_befehl_importiert_in_den_angegebenen_mandanten(): void
    {
        $school = $this->createTestSchool();

        try {
            $this->lehrkraft('HMB', 'Hamberger');
        } finally {
        }

        $datei = $this->untisDatei([
            ['Q12', 'HMB', '1D-gA', 'F43', 1, 3],
            ['Q13', 'HMB', '2M-eA', 'F12', 3, 2],
        ]);

        $this->artisan('timetable:import', [
            'file'     => $datei,
            '--year'   => self::SCHULJAHR,
            '--school' => (string) $school->getKey(),
        ])->assertExitCode(0);

        try {
            $this->assertSame(2, TimetableEntry::where('school_year', self::SCHULJAHR)->count());
            $this->assertSame(
                0,
                TimetableEntry::whereNull('teacher_id')->count(),
                'Auch auf der Kommandozeile muss teacher_id gesetzt werden.'
            );
        } finally {
        }
    }

    /**
     * Zwei Abbruchbedingungen, die der alte Befehl nicht kannte: ohne
     * Mandanten wüsste er nicht, wohin er schreibt, und ohne Schuljahr
     * schriebe er in den Vorgabewert „2024/25" — einen Stundenplan, den die
     * Anwendung anschließend nirgends findet.
     */
    public function test_der_befehl_bricht_ohne_mandant_und_ohne_schuljahr_ab(): void
    {
        $datei = $this->untisDatei([
            ['Q12', 'HMB', '1D-gA', 'F43', 1, 3],
        ]);

        $this->artisan('timetable:import', [
            'file'   => $datei,
            '--year' => self::SCHULJAHR,
        ])->assertExitCode(1);

        $school = $this->createTestSchool();

        $this->artisan('timetable:import', [
            'file'     => $datei,
            '--school' => (string) $school->getKey(),
        ])->assertExitCode(1);
    }
}
