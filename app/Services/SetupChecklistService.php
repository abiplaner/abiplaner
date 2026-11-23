<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Room;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\SeatingTemplate;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use App\Support\Grundausstattung;
use Illuminate\Support\Facades\DB;

/**
 * Einrichtungsstand einer Schule (Roadmap 6.2).
 *
 * Beantwortet die Frage, die eine frisch angelegte Schule sonst niemandem
 * beantwortet: Was ist schon da, was fehlt noch, und wo geht es weiter?
 *
 * Bewusst eine Checkliste und kein linearer Wizard. Beim Schuljahreswechsel
 * (Modul 1.1) ist die Reihenfolge zwingend — Backup vor Loeschen. Hier ist sie
 * es kaum: Der Schuelerimport legt Kurse und fehlende Lehrkraefte selbst mit an,
 * technisch erzwingt also nichts eine Reihenfolge. Und die Daten treffen aus ASV
 * ueber Tage verteilt ein. Wo eine Reihenfolge trotzdem sinnvoll ist, steht der
 * Grund als Hinweis an der Zeile, statt den naechsten Schritt zu sperren.
 *
 * Die Liste zerfaellt in zwei Bereiche: Was der Administrator zentral erledigt
 * (Schulstammdaten, Zugaenge) und was die Schule selbst macht. Beides wird
 * angezeigt, aber nur das Zweite ist verlinkt — den Rest kann der
 * Oberstufenkoordinator nicht selbst reparieren, er soll aber sehen, worauf er
 * wartet.
 */
class SetupChecklistService
{
    public const STATUS_DONE = 'done';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_OPEN = 'open';

    public const SCOPE_ADMIN = 'admin';
    public const SCOPE_SCHULE = 'schule';

    public function __construct(
        private readonly CurrentExamPeriodService $examPeriods,
    ) {
    }

    /**
     * Zu welchem Punkt es einen Import-Leitfaden gibt (Modul 6.2, zweite
     * Stufe). Die Checkliste sagt, dass etwas fehlt; der Leitfaden sagt, wie
     * die Datei dafuer aussieht.
     *
     * @var array<string, string>
     */
    private const LEITFAEDEN = [
        'lehrkraefte' => ImportLeitfadenService::LEHRKRAEFTE,
        'schueler' => ImportLeitfadenService::SCHUELER,
    ];

    /**
     * Alle Punkte der Einrichtung, in sinnvoller Bearbeitungsreihenfolge.
     *
     * @return list<array{key: string, title: string, description: string, status: string, detail: string, route: string|null, hint: string|null, scope: string, required: bool, guide: string|null}>
     */
    public function items(): array
    {
        $items = [
            $this->schulstammdaten(),
            $this->benutzerkonten(),
            $this->raeume(),
            $this->sitzvorlagen(),
            $this->zeitslots(),
            $this->lehrkraefte(),
            $this->schueler(),
            $this->kurse(),
            $this->pruefungszuordnungen(),
            $this->schuleinstellungen(),
        ];

        return array_map(
            fn (array $item): array => $item + ['guide' => self::LEITFAEDEN[$item['key']] ?? null],
            $items
        );
    }

    /**
     * Fortschritt ueber die Pflichtpunkte. Optionale Punkte zaehlen nicht mit,
     * sonst erreicht eine vollstaendig eingerichtete Schule nie 100 Prozent.
     *
     * @return array{erledigt: int, gesamt: int, prozent: int, offen: int}
     */
    public function progress(?array $items = null): array
    {
        $pflicht = array_values(array_filter($items ?? $this->items(), fn (array $item) => $item['required']));
        $gesamt = count($pflicht);
        $erledigt = count(array_filter($pflicht, fn (array $item) => $item['status'] === self::STATUS_DONE));

        return [
            'erledigt' => $erledigt,
            'gesamt' => $gesamt,
            'prozent' => $gesamt === 0 ? 100 : (int) round($erledigt / $gesamt * 100),
            'offen' => $gesamt - $erledigt,
        ];
    }

    public function isComplete(): bool
    {
        return $this->progress()['offen'] === 0;
    }

    private function schulstammdaten(): array
    {
        $school = $this->school();

        if ($school !== null) {
            app(YearChangeWizardService::class)->syncSchoolMasterData($school);
            $school->refresh();
        }

        $felder = [
            'Anschrift' => $school?->address,
            'Ort' => $school?->city,
            'Postleitzahl' => $school?->postal_code,
            'E-Mail' => $school?->email,
        ];

        $schulname = $school !== null ? $school->name : 'unbekannt';
        $fehlend = array_keys(array_filter($felder, fn ($wert) => $wert === null || trim((string) $wert) === ''));
        $status = match (true) {
            $school === null => self::STATUS_OPEN,
            $fehlend === [] => self::STATUS_DONE,
            count($fehlend) === count($felder) => self::STATUS_OPEN,
            default => self::STATUS_PARTIAL,
        };

        return [
            'key' => 'schulstammdaten',
            'title' => 'Schulstammdaten',
            'description' => 'Name, Anschrift und Kontakt der Schule. Erscheinen auf Niederschriften und Exporten.',
            'status' => $status,
            'detail' => $fehlend === []
                ? $schulname
                : 'Es fehlt: ' . implode(', ', $fehlend),
            'route' => 'school-settings.index',
            'hint' => 'Kann zentral oder in den Schuleinstellungen gepflegt werden; wird beim Schuljahreswechsel übernommen.',
            'scope' => self::SCOPE_ADMIN,
            'required' => true,
        ];
    }

    private function benutzerkonten(): array
    {
        $school = $this->school();

        $anzahl = $school === null ? 0 : User::query()
            ->where('school_id', $school->getKey())
            // Auch der Super-Admin der Schule plant (User::isSchoolPlanner()).
            ->whereIn('role', ['planner', 'admin', 'super_admin'])
            ->count();

        return [
            'key' => 'benutzerkonten',
            'title' => 'Zugänge',
            'description' => 'Mindestens ein Konto mit Planerrechten, damit die Schule selbst arbeiten kann.',
            'status' => $anzahl > 0 ? self::STATUS_DONE : self::STATUS_OPEN,
            'detail' => $anzahl === 1 ? '1 Konto' : $anzahl . ' Konten',
            'route' => null,
            'hint' => 'Wird zentral vom Administrator angelegt.',
            'scope' => self::SCOPE_ADMIN,
            'required' => true,
        ];
    }

    private function raeume(): array
    {
        $gesamt = Room::count();
        // Die Vorgabenamen der Grundausstattung (TenantSeeder) sind erfunden.
        // Stehen sie noch alle unverändert da, ist der Punkt nicht erledigt,
        // auch wenn "Räume vorhanden" formal stimmt.
        $vorgabe = $gesamt === 0 ? 0 : Room::whereIn('room_number', Grundausstattung::raumnummern())->count();

        $status = match (true) {
            $gesamt === 0 => self::STATUS_OPEN,
            $vorgabe === $gesamt => self::STATUS_PARTIAL,
            default => self::STATUS_DONE,
        };

        $detail = $gesamt . ' Räume';

        if ($vorgabe > 0) {
            $detail .= ', davon ' . $vorgabe . ' noch mit Vorgabenamen';
        }

        return [
            'key' => 'raeume',
            'title' => 'Räume',
            'description' => 'Prüfungs-, Vorbereitungs- und Warteräume sowie Gangaufsichten.',
            'status' => $status,
            'detail' => $detail,
            'route' => 'rooms.index',
            'hint' => $vorgabe > 0
                ? 'Die vorhandenen Räume sind Platzhalter aus der Grundausstattung — bitte durch die echten Raumnummern ersetzen.'
                : null,
            'scope' => self::SCOPE_SCHULE,
            'required' => true,
        ];
    }

    private function sitzvorlagen(): array
    {
        $vorlagen = SeatingTemplate::count();
        $pruefungsraeume = Room::where('room_type', 'exam')->count();

        $mitVorlage = $pruefungsraeume === 0 ? 0 : DB::table('room_seating_templates')
            ->join('rooms', 'rooms.id', '=', 'room_seating_templates.room_id')
            ->where('rooms.room_type', 'exam')
            ->where('room_seating_templates.variant', 'standard')
            ->distinct()
            ->count('rooms.id');

        $status = match (true) {
            $vorlagen === 0 => self::STATUS_OPEN,
            $pruefungsraeume > 0 && $mitVorlage < $pruefungsraeume => self::STATUS_PARTIAL,
            default => self::STATUS_DONE,
        };

        return [
            'key' => 'sitzvorlagen',
            'title' => 'Sitzvorlagen',
            'description' => 'Bestuhlung der Prüfungsräume — Grundlage der Sitzverteilung bei den schriftlichen Prüfungen.',
            'status' => $status,
            'detail' => $vorlagen . ' Vorlagen, ' . $mitVorlage . ' von ' . $pruefungsraeume . ' Prüfungsräumen zugeordnet',
            'route' => 'seating-templates.index',
            'hint' => $vorlagen === 0
                ? 'Erst die Räume anlegen, dann die Bestuhlung — die Vorlage hängt am Raum.'
                : null,
            'scope' => self::SCOPE_SCHULE,
            'required' => true,
        ];
    }

    private function zeitslots(): array
    {
        $periodId = $this->examPeriods->currentId();
        $anzahl = $periodId === null ? 0 : TimeSlot::where('exam_period_id', $periodId)->count();
        $tage = $periodId === null ? 0 : TimeSlot::where('exam_period_id', $periodId)->distinct()->count('date');

        return [
            'key' => 'zeitslots',
            'title' => 'Zeitslots für die Kolloquien',
            'description' => 'Prüfungstage und die Slots je Tag im aktuellen Prüfungszeitraum.',
            'status' => $anzahl > 0 ? self::STATUS_DONE : self::STATUS_OPEN,
            'detail' => $anzahl . ' Slots an ' . $tage . ' Tagen',
            'route' => 'planning.time-slots.index',
            'hint' => null,
            'scope' => self::SCOPE_SCHULE,
            'required' => true,
        ];
    }

    private function lehrkraefte(): array
    {
        $gesamt = Teacher::count();
        $platzhalter = $gesamt === 0 ? 0 : Teacher::where('shortcode', 'EXTERN')->count();

        $status = match (true) {
            $gesamt === 0 => self::STATUS_OPEN,
            $platzhalter > 0 && $gesamt === $platzhalter => self::STATUS_PARTIAL,
            default => self::STATUS_DONE,
        };

        return [
            'key' => 'lehrkraefte',
            'title' => 'Lehrkräfte',
            'description' => 'Stammdaten inklusive Fächern und Aufsichtsbereitschaft.',
            'status' => $status,
            'detail' => $gesamt . ' Lehrkräfte'
                . ($platzhalter > 0 ? ', davon ' . $platzhalter . ' Platzhalter' : ''),
            'route' => 'teachers.import.form',
            'hint' => 'Vor dem Schülerimport einlesen: Sonst legt dieser für unbekannte Kürzel den Platzhalter „EXTERN" an.',
            'scope' => self::SCOPE_SCHULE,
            'required' => true,
        ];
    }

    private function schueler(): array
    {
        $periodId = $this->examPeriods->currentId();
        $anzahl = $periodId === null ? 0 : Student::where('exam_period_id', $periodId)->count();
        $prueflinge = $periodId === null ? 0 : Student::where('exam_period_id', $periodId)
            ->where('grade', '13')->count();

        return [
            'key' => 'schueler',
            'title' => 'Schüler',
            'description' => 'Der Jahrgang des aktuellen Prüfungszeitraums, per CSV aus ASV.',
            'status' => $anzahl > 0 ? self::STATUS_DONE : self::STATUS_OPEN,
            'detail' => $anzahl . ' Schüler, davon ' . $prueflinge . ' in Jahrgangsstufe 13',
            'route' => 'import.form',
            'hint' => null,
            'scope' => self::SCOPE_SCHULE,
            'required' => true,
        ];
    }

    private function kurse(): array
    {
        $periodId = $this->examPeriods->currentId();
        $anzahl = $periodId === null ? 0 : Course::where('exam_period_id', $periodId)->count();
        $ohneLehrer = $periodId === null ? 0 : Course::where('exam_period_id', $periodId)
            ->whereNull('teacher_id')->count();

        return [
            'key' => 'kurse',
            'title' => 'Kurse',
            'description' => 'Werden beim Schülerimport aus den Kurscodes mit angelegt.',
            'status' => match (true) {
                $anzahl === 0 => self::STATUS_OPEN,
                $ohneLehrer > 0 => self::STATUS_PARTIAL,
                default => self::STATUS_DONE,
            },
            'detail' => $anzahl . ' Kurse'
                . ($ohneLehrer > 0 ? ', ' . $ohneLehrer . ' ohne Lehrkraft' : ''),
            'route' => 'courses.index',
            'hint' => null,
            'scope' => self::SCOPE_SCHULE,
            'required' => true,
        ];
    }

    private function pruefungszuordnungen(): array
    {
        $periodId = $this->examPeriods->currentId();

        if ($periodId === null) {
            $gesamt = 0;
            $ohne = 0;
        } else {
            $studentIds = Student::where('exam_period_id', $periodId)->where('grade', '13')->pluck('id');
            $gesamt = StudentExam::whereIn('student_id', $studentIds)->count();
            $ohne = $studentIds->count() === 0 ? 0 : $studentIds->count() - StudentExam::whereIn('student_id', $studentIds)
                ->distinct()->count('student_id');
        }

        return [
            'key' => 'pruefungszuordnungen',
            'title' => 'Prüfungszuordnungen',
            'description' => 'Welcher Prüfling in welchem Kurs schriftlich oder mündlich geprüft wird.',
            'status' => match (true) {
                $gesamt === 0 => self::STATUS_OPEN,
                $ohne > 0 => self::STATUS_PARTIAL,
                default => self::STATUS_DONE,
            },
            'detail' => $gesamt . ' Zuordnungen'
                . ($ohne > 0 ? ', ' . $ohne . ' Prüflinge ohne' : ''),
            'route' => 'students.index',
            'hint' => 'Kommt mit dem Schülerimport; einzelne Fälle lassen sich in der Schülerliste nachtragen.',
            'scope' => self::SCOPE_SCHULE,
            'required' => true,
        ];
    }

    private function schuleinstellungen(): array
    {
        $anzahl = SchoolSetting::whereNotNull('value')->count();

        return [
            'key' => 'schuleinstellungen',
            'title' => 'Schuleinstellungen',
            'description' => 'Hinweistexte für Aufsichten, Schlusstexte der Pläne, Schwellenwerte der Absenzen.',
            'status' => $anzahl > 0 ? self::STATUS_DONE : self::STATUS_OPEN,
            'detail' => $anzahl > 0 ? $anzahl . ' Einstellungen gesetzt' : 'Es gelten die Vorgabewerte',
            'route' => 'school-settings.index',
            'hint' => 'Optional — die Vorgabewerte sind brauchbar und lassen sich jederzeit anpassen.',
            'scope' => self::SCOPE_SCHULE,
            'required' => false,
        ];
    }

    private function school(): ?School
    {
        // Frisch aus der zentralen DB, nicht die im Kontext gehaltene Instanz
        // (bisheriges Verhalten: School::find über den Tenant-Schlüssel).
        $schule = app(AktuelleSchule::class)->schule();

        return $schule !== null ? School::find($schule->getKey()) : null;
    }
}
