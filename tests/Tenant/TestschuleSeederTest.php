<?php

namespace Tests\Tenant;

use Database\Seeders\TestschuleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Modul 1.3 – Testschule mit Faker-Daten aus Struktur-Profil.
 *
 * Der Seeder bringt eigene Gegenproben mit und wirft, wenn der erzeugte
 * Bestand unvollstaendig ist. Dieser Test stellt sicher, dass er ueberhaupt
 * durchlaeuft, dass die Groessenordnungen aus dem Profil ankommen und dass
 * derselbe Seed denselben Bestand erzeugt — sonst waeren Abweichungen
 * zwischen zwei Testlaeufen nicht mehr aussagekraeftig.
 */
class TestschuleSeederTest extends TenantTestCase
{
    public function test_seeder_erzeugt_vollstaendigen_pruefungsjahrgang(): void
    {
        $school = $this->createTestSchool();


        try {
            $this->seedTestschule();

            $prueflinge = DB::table('students')->where('grade', '13')->count();
            // Nur eigene Schueler: die externen Bewerber bringen seit Etappe B
            // vierzehn weitere Pruefungen mit, und ihr student_id ist null.
            $pruefungen = DB::table('student_exams')
                ->whereNotNull('student_id')->count();
            $muendlich = DB::table('student_exams')
                ->whereNotNull('student_id')->where('exam_type', 'oral')->count();
            $termine = DB::table('exam_assignments as ea')
                ->join('student_exams as se', 'se.id', '=', 'ea.student_exam_id')
                ->whereNotNull('se.student_id')
                ->count();
            $kopplungen = DB::table('student_exams')
                ->whereNotNull('coupling_id')->distinct()->count('coupling_id');
            $belegungen = DB::table('written_exam_assignments')->count();
            $sitzplaetze = DB::table('written_exam_seat_assignments')->count();
        } finally {
        }

        $profil = $this->profil();

        $this->assertSame((int) $profil['jahrgaenge']['13'], $prueflinge);
        $this->assertSame(
            $prueflinge * (int) $profil['pruefung']['pruefungen_je_schueler'],
            $pruefungen,
            'Jeder Prüfling braucht genau fünf Prüfungen.'
        );
        $this->assertSame($muendlich, $termine, 'Jede mündliche Prüfung braucht einen Termin.');
        $this->assertSame((int) $profil['kolloquium']['kopplungsgruppen'], $kopplungen);
        $this->assertGreaterThan(0, $belegungen, 'Keine schriftlichen Raumbelegungen erzeugt.');
        $this->assertGreaterThan(0, $sitzplaetze, 'Keine Sitzplätze vergeben.');
    }

    public function test_gleicher_seed_erzeugt_denselben_bestand(): void
    {
        $school = $this->createTestSchool();


        try {
            $this->seedTestschule();
            $ersterLauf = $this->fingerabdruck();

            $this->seedTestschule();
            $zweiterLauf = $this->fingerabdruck();
        } finally {
        }

        $this->assertSame(
            $ersterLauf,
            $zweiterLauf,
            'Zwei Läufe mit demselben Seed müssen denselben Datenbestand erzeugen.'
        );
    }

    /**
     * Der Grund, weshalb der Zusatzpruefungs-Baustein gebaut wurde.
     *
     * Zwei Faelle haben in den Runden vom 27. und 28.08.2026 gefehlt und
     * mussten deshalb an Echtdaten der Pilotschule nachgerechnet werden:
     *
     * - Eine Zusatzpruefung in Sport. Dort zaehlt die Praxisnote auf beiden
     *   Seiten der Verbesserungsrechnung mit; ohne sie rechnen
     *   getPointsDelta(), getExamResultPoints() und getCombinedPoints() nicht
     *   (Fehlerklassen 11 und 12).
     * - Ein Prüfling mit mehreren Zusatzpruefungen. Erst damit laeuft die
     *   Kombinationsrechnung des MindestpunkteService im Fall N=2.
     *
     * Dazu die Praxisnote an *jeder* Sportpruefung, auch am Kolloquium ohne
     * Zusatzpruefung: fehlt sie dort, traegt das Fach in Modal und
     * Niederschrift gar nichts bei.
     */
    public function test_bestand_traegt_die_sport_und_mehrfachfaelle(): void
    {
        $school = $this->createTestSchool();


        try {
            $this->seedTestschule();

            // Der Fächerkatalog liegt zentral. `DB::table('subjects')` würde
            // im initialisierten Tenant gegen school_9999 laufen — dieselbe
            // Falle wie in den Gegenproben des Seeders, deshalb hier wie dort
            // ausdrücklich über die zentrale Verbindung.
            $sportIds = \App\Models\Subject::query()
                ->whereIn('code', ['Sw', 'Sm', 'Smw'])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $sportPruefungen = DB::table('student_exams as se')
                ->join('courses as c', 'c.id', '=', 'se.course_id')
                ->whereIn('c.subject_id', $sportIds)
                ->count();

            // Eine Praxisnote braucht nur, wer in eine Zusatzpruefung geht: dort
            // rechnet der Mindestpunkte-Rechner ueber alle fuenf Faecher. Nach
            // einem Kolloquium gibt es keine Zusatzpruefung, also auch keinen
            // Bedarf (Wilfried, 28.08.2026) — in der Pilotschule tragen genau vier der
            // zehn Sport-Kolloquien eine, naemlich die, deren Pruefling
            // anderswo eine Zusatzpruefung hat.
            $sportOhnePraxis = DB::table('student_exams as se')
                ->join('courses as c', 'c.id', '=', 'se.course_id')
                ->leftJoin('zusatzpruef_exam_scores as sc', 'sc.student_exam_id', '=', 'se.id')
                ->whereIn('c.subject_id', $sportIds)
                ->whereNull('sc.practical_grade')
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('zusatzpruefungen as zp')
                    ->whereColumn('zp.student_id', 'se.student_id'))
                ->count();

            $sportZusatzpruefungen = DB::table('zusatzpruefungen as zp')
                ->join('student_exams as se', 'se.id', '=', 'zp.student_exam_id')
                ->join('courses as c', 'c.id', '=', 'se.course_id')
                ->whereIn('c.subject_id', $sportIds)
                ->count();

            $mehrfach = DB::table('zusatzpruefungen')
                ->select('student_id')
                ->whereNotNull('student_id')
                ->groupBy('student_id')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();

            $ohneTermin = DB::table('zusatzpruefungen')
                ->where('status', 'pending')
                ->count();

            $themenbereiche = DB::table('colloquium_topics')->count();
        } finally {
        }

        $this->assertGreaterThan(0, $sportPruefungen, 'Der Bestand trägt kein Sportfach.');
        $this->assertSame(
            0,
            $sportOhnePraxis,
            'Wer eine Zusatzprüfung hat, braucht auch im Sport die Praxisnote.'
        );
        $this->assertGreaterThan(
            0,
            $sportZusatzpruefungen,
            'Ohne Sport-Zusatzprüfung bleibt die Verbesserungsrechnung im Sport ungeprüft.'
        );
        $this->assertGreaterThan(
            0,
            $mehrfach,
            'Ohne Prüfling mit zwei Zusatzprüfungen bleibt die Kombinationsrechnung für N=2 ungeprüft.'
        );
        $this->assertGreaterThan(
            0,
            $ohneTermin,
            'Der Zustand "noch zu planen" muss im Bestand vorkommen.'
        );
        $this->assertGreaterThan(0, $themenbereiche, 'Keine Themenbereiche erzeugt.');
    }

    /**
     * Modul 2.1 im Testbestand: beide Wege eines externen Bewerbers.
     *
     * Bis zum 28.08.2026 gab es keinen einzigen Testbewerber — alles
     * Durchklicken lief ueber einen Probeeintrag in den Produktionsdaten, der
     * am selben Tag selbst repariert werden musste, weil die neue
     * Regelableitung aus § 61 ihn ungueltig gemacht hatte.
     *
     * Der Test haelt die vier Eigenschaften fest, an denen die Anwendung schon
     * gescheitert ist:
     *
     * - Aus acht Pruefungsfaechern werden sechs Pruefungen, sobald zwei
     *   eingebracht sind (§ 64 Abs. 2 S. 1).
     * - Eine Zusatzpruefung ohne `student_id` — der Fall, an dem 34 Abfragen
     *   mit `whereHas('student')` still vorbeigelaufen sind.
     * - Ein externer Bewerber im Sitzplan, mit Nachteilsausgleich.
     * - Die muendlichen Pruefungen der Externen haben Termine: `bildeSitzungen()`
     *   fragt `student_exams` ohne Ruecksicht auf `student_id`, und ein
     *   Terminkonflikt mit einem externen Pruefling war einmal unsichtbar.
     */
    public function test_bestand_traegt_externe_bewerber_nach_anlage_14_und_15(): void
    {
        $school = $this->createTestSchool();


        try {
            $this->seedTestschule();

            $bewerber = DB::table('external_applicants')->count();
            $mitErsatzschule = DB::table('external_applicants')
                ->whereNotNull('ersatzschule_id')->count();
            $mitNachteilsausgleich = DB::table('external_applicants')
                ->whereNotNull('disability_compensation_percent')->count();

            $faecherJeBewerber = DB::table('external_exam_subjects')
                ->select('external_applicant_id')
                ->groupBy('external_applicant_id')
                ->havingRaw('COUNT(*) <> 8')
                ->get()
                ->count();

            $eingebracht = DB::table('external_exam_subjects')
                ->where('ergebnis_quelle', 'einbringung')->count();

            $pruefungenAnlage15 = DB::table('student_exams as se')
                ->join('external_exam_subjects as ees', 'ees.id', '=', 'se.external_exam_subject_id')
                ->join('external_applicants as ea', 'ea.id', '=', 'ees.external_applicant_id')
                ->whereNotNull('ea.ersatzschule_id')
                ->count();

            $externeZp = DB::table('zusatzpruefungen')
                ->whereNull('student_id')->count();

            $externerSitzplatz = DB::table('written_exam_seat_assignments')
                ->whereNotNull('external_applicant_id')->count();

            $externeOhneTermin = DB::table('student_exams as se')
                ->whereNull('se.student_id')
                ->where('se.exam_type', 'oral')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('exam_assignments as ea')
                    ->whereColumn('ea.student_exam_id', 'se.id'))
                ->count();
        } finally {
        }

        $this->assertSame(2, $bewerber, 'Erwartet werden zwei Bewerber: Anlage 14 und Anlage 15.');
        $this->assertSame(1, $mitErsatzschule);
        $this->assertSame(1, $mitNachteilsausgleich);
        $this->assertSame(0, $faecherJeBewerber, 'Jeder Bewerber braucht genau acht Prüfungsfächer.');
        $this->assertSame(2, $eingebracht, '§ 64 Abs. 2 verlangt genau zwei eingebrachte Fächer.');
        $this->assertSame(
            6,
            $pruefungenAnlage15,
            'Aus acht Fächern werden sechs Prüfungen, wenn zwei eingebracht sind.'
        );
        $this->assertGreaterThan(0, $externeZp, 'Ohne Zusatzprüfung ohne student_id bleibt Fehlerklasse 1 ungeprüft.');
        $this->assertGreaterThan(0, $externerSitzplatz, 'Kein externer Bewerber im Sitzplan.');
        $this->assertSame(
            0,
            $externeOhneTermin,
            'Auch die Kolloquien externer Bewerber brauchen einen Termin — sonst bleibt die Konflikterkennung ungeprüft.'
        );
    }

    /**
     * LK- und W-Seminar-Wahlen — der Bereich, der bis zum 28.08.2026 ganz
     * ohne Testdaten war.
     *
     * Der Test haelt fest, was den Bestand fuer den Zuteilungsalgorithmus
     * brauchbar macht: **beide Zustaende** (eine Stufe hat gewaehlt und ist
     * zugeteilt, eine hat gewaehlt und wartet), eine Zuteilung, die zu den
     * Wuenschen passt, eingehaltene Teilnehmerzahlen und **alle vier
     * Ausgaenge** — bis hin zu dem, der leer ausgeht.
     */
    public function test_bestand_traegt_lk_und_wseminar_wahlen(): void
    {
        $school = $this->createTestSchool();


        try {
            $this->seedTestschule();

            $lkKurse = DB::table('lk_kurse')->count();
            $externeKurse = DB::table('lk_kurse')->where('ist_extern', true)->count();
            $schuljahre = DB::table('lk_kurse')->distinct()->count('schuljahr');

            $offeneWahlen = DB::table('lk_wahlen')
                ->whereNull('prioritaet_erhalten')->count();
            $zugeteilteWahlen = DB::table('lk_wahlen')
                ->whereNotNull('zuteilung_lk_kurs_id')->count();

            $ausgaenge = DB::table('wseminar_wahlen')
                ->select('prioritaet_erhalten')
                ->distinct()
                ->pluck('prioritaet_erhalten')
                ->filter()
                ->sort()
                ->values()
                ->all();

            $ueberbelegt = DB::selectOne(
                'SELECT COUNT(*) AS anzahl FROM (
                    SELECT k.id FROM wseminar_kurse k
                    JOIN wseminar_wahlen w ON w.zuteilung_wseminar_kurs_id = k.id
                    GROUP BY k.id, k.max_teilnehmer
                    HAVING COUNT(*) > k.max_teilnehmer
                ) t'
            );

            $falschePrioritaet = DB::selectOne(
                'SELECT COUNT(*) AS anzahl FROM wseminar_wahlen w
                 WHERE w.zuteilung_wseminar_kurs_id IS NOT NULL
                   AND NOT (
                       (w.prioritaet_erhalten = "erstwahl"  AND w.zuteilung_wseminar_kurs_id = w.erstwahl_wseminar_id)
                    OR (w.prioritaet_erhalten = "zweitwahl" AND w.zuteilung_wseminar_kurs_id = w.zweitwahl_wseminar_id)
                    OR (w.prioritaet_erhalten = "drittwahl" AND w.zuteilung_wseminar_kurs_id = w.drittwahl_wseminar_id)
                   )'
            );
        } finally {
        }

        $this->assertGreaterThan(0, $lkKurse, 'Keine Leistungsfachkurse erzeugt.');
        $this->assertGreaterThan(0, $externeKurse, 'Ohne externen Kurs bleibt ist_extern ungeprüft.');
        $this->assertSame(2, $schuljahre, 'Erwartet werden zwei Schuljahre: die Stufen 11 und 12.');
        $this->assertGreaterThan(
            0,
            $offeneWahlen,
            'Ohne offene Wahl gibt es keinen Ausgangszustand, auf den eine Zuteilung angewendet werden könnte.'
        );
        $this->assertGreaterThan(0, $zugeteilteWahlen, 'Ohne zugeteilte Wahl fehlt der Zustand danach.');
        $this->assertSame(
            ['drittwahl', 'erstwahl', 'keine', 'zweitwahl'],
            $ausgaenge,
            'Alle vier Ausgänge müssen vorkommen — sonst prüft der Bestand den Algorithmus nur zur Hälfte.'
        );
        $this->assertSame(0, (int) $ueberbelegt->anzahl, 'Kein W-Seminar darf über max_teilnehmer belegt sein.');
        $this->assertSame(
            0,
            (int) $falschePrioritaet->anzahl,
            'Jede Zuteilung muss einer der drei Wünsche sein, und die Priorität muss dazu passen.'
        );
    }

    /**
     * Auszeichnungen und Prüfungsausschuss.
     *
     * Beide Bereiche hatten bis zum 28.08.2026 keine Testdaten; die
     * Sitzungen sind auch in der Pilotschule nicht in Gebrauch, die Niederschrift lief
     * also gegen gar nichts.
     *
     * Festgehalten werden die drei Faelle, die eine Preisliste tragen muss:
     * eine Preisart **ohne Traeger**, eine lueckenlose Rangfolge dort, wo
     * Raenge gefuehrt werden, und ein Ausschussmitglied, das **nicht im
     * Kollegium** steht (die Schulaufsicht).
     */
    public function test_bestand_traegt_auszeichnungen_und_pruefungsausschuss(): void
    {
        $school = $this->createTestSchool();


        try {
            $this->seedTestschule();

            $arten = DB::table('award_types')->count();
            $vergaben = DB::table('awards')->count();
            $ohneTraeger = DB::table('award_types')
                ->whereNotNull('no_winner_note')->count();

            $verwaist = DB::table('awards as a')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('award_types as t')
                    ->whereColumn('t.id', 'a.award_type_id'))
                ->count();

            $rangLuecken = DB::selectOne(
                'SELECT COUNT(*) AS anzahl FROM (
                    SELECT award_type_id FROM awards WHERE rank IS NOT NULL
                    GROUP BY award_type_id
                    HAVING MIN(rank) <> 1 OR MAX(rank) <> COUNT(*)
                ) t'
            );

            $sitzungen = DB::table('exam_board_meetings')->count();
            $freiesMitglied = DB::table('exam_board_meeting_attendees')
                ->whereNull('teacher_id')->whereNotNull('name_override')->count();
        } finally {
        }

        $this->assertGreaterThan(0, $arten, 'Keine Preisarten erzeugt.');
        $this->assertGreaterThan(0, $vergaben, 'Keine Auszeichnungen vergeben.');
        $this->assertGreaterThan(0, $ohneTraeger, 'Ohne eine Preisart ohne Träger bleibt no_winner_note ungeprüft.');
        $this->assertSame(0, $verwaist, 'awards.award_type_id trägt keinen Fremdschlüssel — verwaiste Zeilen fallen sonst erst auf dem Ausdruck auf.');
        $this->assertSame(0, (int) $rangLuecken->anzahl, 'Eine Rangfolge ohne ersten Preis wäre ein Druckfehler.');
        $this->assertGreaterThan(0, $sitzungen, 'Keine Sitzung des Prüfungsausschusses.');
        $this->assertGreaterThan(
            0,
            $freiesMitglied,
            'Ohne Mitglied ausserhalb des Kollegiums bleibt name_override ungeprüft.'
        );
    }

    /**
     * Absenzen und Stundenplan.
     *
     * Der groesste Datenblock des Bestands und der, an dem die
     * Kolloquiumsplanung ihre freien Tage abliest.
     *
     * Festgehalten sind die vier Eigenschaften, ohne die beide Bereiche im
     * Test nichts wert waeren: der `source_key` ist je Schueler eindeutig
     * (sonst verdoppelt ein spaeterer Import den Bestand statt ihn zu
     * aktualisieren), jede Kategorie traegt ihre eigene Masseinheit, es gibt
     * Lehrkraefte mit freiem Tag, und niemand steht zweimal in derselben
     * Stunde.
     */
    public function test_bestand_traegt_absenzen_und_stundenplan(): void
    {
        $school = $this->createTestSchool();


        try {
            $this->seedTestschule();

            $absenzen = DB::table('absences')->count();
            $importe = DB::table('absence_imports')->count();
            $massnahmen = DB::table('absence_measures')->count();
            $offeneUebertraege = DB::table('absence_carryovers')
                ->whereNull('matched_student_id')->count();

            $doppelterKey = DB::selectOne(
                'SELECT COUNT(*) AS anzahl FROM (
                    SELECT student_id, source_key FROM absences
                    GROUP BY student_id, source_key HAVING COUNT(*) > 1
                ) t'
            );

            $falscheEinheit = DB::table('absences')
                ->where(fn ($q) => $q->where('category', 'verspaetet')->whereNull('minutes_late'))
                ->orWhere(fn ($q) => $q->where('category', 'krank')->where('days', 0))
                ->count();

            $stunden = DB::table('timetable_entries')->count();

            $doppelstunde = DB::selectOne(
                'SELECT COUNT(*) AS anzahl FROM (
                    SELECT teacher_id, day_of_week, period FROM timetable_entries
                    WHERE teacher_id IS NOT NULL
                    GROUP BY teacher_id, day_of_week, period HAVING COUNT(*) > 1
                ) t'
            );

            $mitFreiemTag = DB::selectOne(
                'SELECT COUNT(*) AS anzahl FROM (
                    SELECT teacher_id FROM timetable_entries
                    WHERE teacher_id IS NOT NULL
                    GROUP BY teacher_id HAVING COUNT(DISTINCT day_of_week) < 5
                ) t'
            );

            $schuljahre = DB::table('timetable_entries')
                ->distinct()->pluck('school_year')->all();

            $blockierend = DB::table('school_events')
                ->where('blocks_exams', true)->count();
            $nichtBlockierend = DB::table('school_events')
                ->where('blocks_exams', false)->count();
        } finally {
        }

        $this->assertGreaterThan(1000, $absenzen, 'Der Absenzenbestand ist zu klein für eine Auswertung.');
        $this->assertGreaterThan(0, $importe);
        $this->assertGreaterThan(0, $massnahmen);
        $this->assertGreaterThan(
            0,
            $offeneUebertraege,
            'Ohne offenen Übertrag bleibt die Klärungsliste immer leer.'
        );
        $this->assertSame(
            0,
            (int) $doppelterKey->anzahl,
            'Doppelte source_key je Schüler: ein Import würde den Bestand verdoppeln statt ihn zu aktualisieren.'
        );
        $this->assertSame(0, $falscheEinheit, 'Jede Kategorie trägt ihre eigene Maßeinheit.');

        $this->assertGreaterThan(1000, $stunden, 'Der Stundenplan ist zu dünn.');
        $this->assertSame(0, (int) $doppelstunde->anzahl, 'Niemand steht zweimal in derselben Stunde.');
        $this->assertGreaterThan(
            0,
            (int) $mitFreiemTag->anzahl,
            'Ohne freie Tage prüft die Kolloquiumsplanung ihre eigene Schonung nie.'
        );

        foreach ($schuljahre as $jahr) {
            $this->assertMatchesRegularExpression(
                '#^\d{4}/\d{2}$#',
                (string) $jahr,
                'timetable_entries führt das Schuljahr im Kurzformat — in der langen Form wäre der Plan unsichtbar.'
            );
        }

        $this->assertGreaterThan(0, $blockierend);
        $this->assertGreaterThan(0, $nichtBlockierend, 'Nicht jede Veranstaltung blockiert Prüfungen.');
    }

    private function seedTestschule(): void
    {
        config(['testschule.seed' => TestschuleSeeder::STANDARD_SEED]);

        $seeder = new TestschuleSeeder();
        $seeder->setCommand($this->kommandoAttrappe());
        $seeder->run();
    }

    /**
     * Der Seeder meldet seinen Fortschritt über `$this->command`. Im Test gibt
     * es kein Artisan-Kommando, deshalb eine stille Attrappe — sonst müsste der
     * Seeder seine Ausgabe umständlich abschaltbar machen.
     */
    private function kommandoAttrappe(): \Illuminate\Console\Command
    {
        return new class extends \Illuminate\Console\Command
        {
            public function info($string, $verbosity = null): void
            {
            }

            public function warn($string, $verbosity = null): void
            {
            }

            public function line($string, $style = null, $verbosity = null): void
            {
            }
        };
    }

    /**
     * Inhaltlicher Fingerabdruck ohne IDs: Die Autowerte laufen zwischen zwei
     * Läufen weiter, der erzeugte Inhalt muss aber identisch sein.
     */
    private function fingerabdruck(): string
    {
        // MariaDB kannte CRC32(); unter SQLite stellen wir es nach.
        $pdo = DB::getPdo();
        $crc = static fn ($text): int => crc32((string) $text);
        $pdo instanceof \Pdo\Sqlite
            ? $pdo->createFunction('CRC32', $crc, 1)
            : $pdo->sqliteCreateFunction('CRC32', $crc, 1);

        $zeile = DB::selectOne(<<<'SQL'
            SELECT
                (SELECT SUM(CRC32(CONCAT(shortcode, firstname, lastname, IFNULL(title, '')))) FROM teachers) AS lehrer,
                (SELECT SUM(CRC32(CONCAT(firstname, lastname, grade, bestanden))) FROM students) AS schueler,
                (SELECT SUM(CRC32(CONCAT(exam_type, IFNULL(coupling_id, '-')))) FROM student_exams) AS pruefungen,
                (SELECT COUNT(*) FROM exam_assignments) AS termine,
                (SELECT COUNT(*) FROM written_exam_assignments) AS belegungen,
                (SELECT SUM(CRC32(CONCAT(status, sort_order, absent, improvement_only,
                    IFNULL(written_points, '-'), IFNULL(target_points, '-'),
                    IFNULL(improvement_points_needed, '-')))) FROM zusatzpruefungen) AS zusatzpruefungen,
                (SELECT SUM(CRC32(CONCAT(IFNULL(score, '-'), IFNULL(practical_grade, '-'))))
                    FROM zusatzpruef_exam_scores) AS noten,
                (SELECT SUM(CRC32(CONCAT(semester, slot, title))) FROM colloquium_topics) AS themenbereiche,
                (SELECT SUM(CRC32(CONCAT(first_name, last_name, birth_date,
                    ersatzschule_id IS NULL, IFNULL(disability_compensation_percent, '-'))))
                    FROM external_applicants) AS externe,
                (SELECT SUM(CRC32(CONCAT(exam_part, subject_number, subject_id, ergebnis_quelle,
                    IFNULL(eingebrachte_punktzahl, '-')))) FROM external_exam_subjects) AS externe_faecher,
                (SELECT SUM(CRC32(CONCAT(schuljahr, jahrgang, subject_id, kurs_nummer, ist_extern)))
                    FROM lk_kurse) AS lk_kurse,
                (SELECT SUM(CRC32(CONCAT(schuljahr, IFNULL(erstwahl_subject_id, '-'),
                    IFNULL(zweitwahl_subject_id, '-'), IFNULL(prioritaet_erhalten, '-'))))
                    FROM lk_wahlen) AS lk_wahlen,
                (SELECT SUM(CRC32(CONCAT(titel, subject_id, max_teilnehmer, kurs_nummer)))
                    FROM wseminar_kurse) AS wseminare,
                (SELECT SUM(CRC32(IFNULL(prioritaet_erhalten, '-'))) FROM wseminar_wahlen) AS wseminar_wahlen,
                (SELECT SUM(CRC32(CONCAT(name, IFNULL(category, '-'), active,
                    no_winner_note IS NULL, sort_order))) FROM award_types) AS preisarten,
                (SELECT SUM(CRC32(CONCAT(IFNULL(rank, '-'), is_candidate, sort_order))) FROM awards) AS auszeichnungen,
                (SELECT SUM(CRC32(CONCAT(number, date, IFNULL(location, '-')))) FROM exam_board_meetings) AS sitzungen,
                (SELECT SUM(CRC32(CONCAT(source_key, category, excuse_status, days, hours))) FROM absences) AS absenzen,
                (SELECT SUM(CRC32(CONCAT(day_of_week, period, class_name, IFNULL(subject, '-')))) 
                    FROM timetable_entries) AS stundenplan,
                (SELECT SUM(CRC32(CONCAT(name, type, start_date, blocks_exams))) FROM school_events) AS ereignisse
            SQL
        );

        return json_encode((array) $zeile) ?: '';
    }

    /**
     * @return array<string, mixed>
     */
    private function profil(): array
    {
        $inhalt = file_get_contents(database_path('seeders/data/testschule-profil.json'));
        $profil = json_decode((string) $inhalt, true);

        $this->assertIsArray($profil, 'Strukturprofil nicht lesbar.');

        return $profil;
    }
}
