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
use App\Models\TimetableEntry;
use App\Support\Grundausstattung;
use App\Support\SchoolYear;
use Illuminate\Support\Facades\DB;

/**
 * Einrichtungsstand einer Schule (Roadmap 6.2, neu geschnitten 23.11.2026).
 *
 * Zwei Gruppen:
 *
 * - **Einlesen** (Pflicht, zählt in den Fortschritt): die drei Dateien aus
 *   ASV und Untis — Lehrkräfte, Schüler, Stundenplan. Kurse und
 *   Prüfungszuordnungen legt der Schülerimport selbst an; sie sind keine
 *   eigene Aufgabe, sondern stehen im Detail der Schülerzeile.
 * - **Anpassen** (zählt nicht): was die Einrichtung schon vorbelegt —
 *   Schulstammdaten, Räume, Sitzpläne, Zeitslots, Schuleinstellungen. Das ist
 *   da und funktioniert, soll aber an die Schule angepasst werden.
 *
 * Eine Administratorgruppe gibt es nicht mehr: Seit der Desktop-Fassung
 * richtet die Schule sich selbst ein (der erste Nutzer ist Super-Admin).
 *
 * Bewusst eine Checkliste und kein linearer Wizard: Die Daten treffen aus
 * ASV über Tage verteilt ein. Wo eine Reihenfolge sinnvoll ist (erst die
 * Lehrkräfte), steht der Grund als Hinweis an der Zeile.
 */
class SetupChecklistService
{
    public const STATUS_DONE = 'done';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_OPEN = 'open';

    public const GRUPPE_EINLESEN = 'einlesen';
    public const GRUPPE_ANPASSEN = 'anpassen';

    /**
     * Zu welchem Punkt es einen Import-Leitfaden gibt. Die Checkliste sagt,
     * dass etwas fehlt; der Leitfaden sagt, wie die Datei dafür aussieht.
     *
     * @var array<string, string>
     */
    private const LEITFAEDEN = [
        'lehrkraefte' => ImportLeitfadenService::LEHRKRAEFTE,
        'schueler' => ImportLeitfadenService::SCHUELER,
        'stundenplan' => ImportLeitfadenService::STUNDENPLAN,
    ];

    public function __construct(
        private readonly CurrentExamPeriodService $examPeriods,
    ) {
    }

    /**
     * Alle Punkte, erst die einzulesenden, dann die anzupassenden.
     *
     * @return list<array{key: string, title: string, description: string, status: string, detail: string, route: string|null, hint: string|null, gruppe: string, required: bool, guide: string|null}>
     */
    public function items(): array
    {
        $items = [
            $this->lehrkraefte(),
            $this->schueler(),
            $this->stundenplan(),
            $this->schulstammdaten(),
            $this->raeume(),
            $this->sitzvorlagen(),
            $this->zeitslots(),
            $this->schuleinstellungen(),
        ];

        return array_map(
            fn (array $item): array => $item + [
                'required' => $item['gruppe'] === self::GRUPPE_EINLESEN,
                'guide' => self::LEITFAEDEN[$item['key']] ?? null,
            ],
            $items
        );
    }

    /**
     * Fortschritt über die Pflichtpunkte, also die einzulesenden Dateien.
     *
     * @param list<array<string, mixed>>|null $items bereits berechnete Punkte
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

    // ------------------------------------------------------------------
    // Einlesen
    // ------------------------------------------------------------------

    private function lehrkraefte(): array
    {
        $gesamt = Teacher::count();
        $platzhalter = $gesamt === 0 ? 0 : Teacher::where('shortcode', 'EXTERN')->count();

        return [
            'key' => 'lehrkraefte',
            'title' => 'Lehrkräfte',
            'description' => 'Aus ASV: Kürzel, Name, Amtsbezeichnung, Lehrbefähigungen.',
            'status' => match (true) {
                $gesamt === 0 => self::STATUS_OPEN,
                $platzhalter > 0 && $gesamt === $platzhalter => self::STATUS_PARTIAL,
                default => self::STATUS_DONE,
            },
            'detail' => $gesamt . ' Lehrkräfte' . ($platzhalter > 0 ? ', davon ' . $platzhalter . ' Platzhalter' : ''),
            'route' => 'teachers.import.form',
            'hint' => $gesamt === 0 ? 'Zuerst einlesen — sonst legt der Schülerimport für unbekannte Kürzel den Platzhalter „EXTERN" an.' : null,
            'gruppe' => self::GRUPPE_EINLESEN,
        ];
    }

    /**
     * Schüler samt allem, was der Import mit anlegt: Kurse und die fünf
     * Prüfungen je Prüfling. Teilweise erledigt, wenn ein Kurs ohne Lehrkraft
     * dasteht oder ein Prüfling ohne Prüfung.
     */
    private function schueler(): array
    {
        $periodId = $this->examPeriods->currentId();

        if ($periodId === null) {
            $schueler = $prueflinge = $kurse = $ohneLehrer = $ohnePruefung = $zuordnungen = 0;
        } else {
            $schueler = Student::where('exam_period_id', $periodId)->count();
            $prueflingIds = Student::where('exam_period_id', $periodId)->where('grade', '13')->pluck('id');
            $prueflinge = $prueflingIds->count();
            $kurse = Course::where('exam_period_id', $periodId)->count();
            $ohneLehrer = Course::where('exam_period_id', $periodId)->whereNull('teacher_id')->count();
            $zuordnungen = $prueflinge === 0 ? 0 : StudentExam::whereIn('student_id', $prueflingIds)->count();
            $ohnePruefung = $prueflinge === 0 ? 0
                : $prueflinge - StudentExam::whereIn('student_id', $prueflingIds)->distinct()->count('student_id');
        }

        $hinweise = array_filter([
            $ohneLehrer > 0 ? $ohneLehrer . ' Kurse ohne Lehrkraft' : null,
            $ohnePruefung > 0 ? $ohnePruefung . ' Prüflinge ohne Prüfung' : null,
        ]);

        return [
            'key' => 'schueler',
            'title' => 'Schüler und Kurse',
            'description' => 'Aus ASV: der Jahrgang mit seinen fünf Abiturprüfungen. Kurse und Prüfungszuordnungen entstehen dabei mit.',
            'status' => match (true) {
                $schueler === 0 => self::STATUS_OPEN,
                $hinweise !== [] => self::STATUS_PARTIAL,
                default => self::STATUS_DONE,
            },
            'detail' => $schueler === 0
                ? 'Noch keine Schüler im aktuellen Prüfungszeitraum'
                : "{$schueler} Schüler ({$prueflinge} in Q13), {$kurse} Kurse, {$zuordnungen} Prüfungen",
            'route' => 'import.form',
            'hint' => $hinweise !== [] ? implode(', ', $hinweise) . ' — in der Kurs- bzw. Schülerliste nachtragen.' : null,
            'gruppe' => self::GRUPPE_EINLESEN,
        ];
    }

    private function stundenplan(): array
    {
        $schuljahr = SchoolYear::current();
        $anzahl = TimetableEntry::where('school_year', $schuljahr)->count();

        return [
            'key' => 'stundenplan',
            'title' => 'Stundenplan',
            'description' => 'Aus Untis: Daraus liest die Kolloquiumsplanung, an welchen Tagen eine Lehrkraft ohnehin nicht da ist.',
            'status' => $anzahl > 0 ? self::STATUS_DONE : self::STATUS_OPEN,
            'detail' => $anzahl > 0 ? "{$anzahl} Einträge für {$schuljahr}" : "Noch kein Stundenplan für {$schuljahr}",
            'route' => 'timetable.upload',
            'hint' => null,
            'gruppe' => self::GRUPPE_EINLESEN,
        ];
    }

    // ------------------------------------------------------------------
    // Anpassen — von der Einrichtung vorbelegt
    // ------------------------------------------------------------------

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

        $fehlend = array_keys(array_filter($felder, fn ($wert) => $wert === null || trim((string) $wert) === ''));

        return [
            'key' => 'schulstammdaten',
            'title' => 'Schulstammdaten',
            'description' => 'Anschrift und Kontakt für Niederschriften und Exporte.',
            'status' => $fehlend === [] ? self::STATUS_DONE : self::STATUS_PARTIAL,
            'detail' => $fehlend === [] ? (string) $school?->name : 'Es fehlt: ' . implode(', ', $fehlend),
            'route' => 'school-settings.index',
            'hint' => null,
            'gruppe' => self::GRUPPE_ANPASSEN,
        ];
    }

    private function raeume(): array
    {
        $gesamt = Room::count();
        // Die Namen der Grundausstattung (TenantSeeder) sind erfunden. Stehen
        // sie noch alle da, ist die Vorgabe nicht angepasst.
        $vorgabe = $gesamt === 0 ? 0 : Room::whereIn('room_number', Grundausstattung::raumnummern())->count();

        return [
            'key' => 'raeume',
            'title' => 'Räume',
            'description' => 'Prüfungs-, Vorbereitungs- und Warteräume, Fluraufsicht.',
            'status' => match (true) {
                $gesamt === 0 => self::STATUS_OPEN,
                $vorgabe === $gesamt => self::STATUS_PARTIAL,
                default => self::STATUS_DONE,
            },
            'detail' => $gesamt . ' Räume' . ($vorgabe > 0 ? ', ' . ($vorgabe === $gesamt ? 'alle' : $vorgabe) . ' mit Vorgabenamen' : ''),
            'route' => 'rooms.index',
            'hint' => null,
            'gruppe' => self::GRUPPE_ANPASSEN,
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

        return [
            'key' => 'sitzvorlagen',
            'title' => 'Sitzpläne',
            'description' => 'Bestuhlung der Prüfungsräume für die schriftlichen Prüfungen.',
            'status' => match (true) {
                $vorlagen === 0 => self::STATUS_OPEN,
                $pruefungsraeume > 0 && $mitVorlage < $pruefungsraeume => self::STATUS_PARTIAL,
                default => self::STATUS_DONE,
            },
            'detail' => $vorlagen . ' Sitzpläne, ' . $mitVorlage . ' von ' . $pruefungsraeume . ' Prüfungsräumen zugeordnet',
            'route' => 'seating-templates.index',
            'hint' => null,
            'gruppe' => self::GRUPPE_ANPASSEN,
        ];
    }

    private function zeitslots(): array
    {
        $periodId = $this->examPeriods->currentId();
        $anzahl = $periodId === null ? 0 : TimeSlot::where('exam_period_id', $periodId)->count();
        $tage = $periodId === null ? 0 : TimeSlot::where('exam_period_id', $periodId)->distinct()->count('date');

        return [
            'key' => 'zeitslots',
            'title' => 'Zeitslots der Kolloquien',
            'description' => 'Prüfungstage und Slots im aktuellen Prüfungszeitraum.',
            'status' => $anzahl > 0 ? self::STATUS_DONE : self::STATUS_OPEN,
            'detail' => $anzahl . ' Slots an ' . $tage . ' Tagen',
            'route' => 'planning.time-slots.index',
            'hint' => null,
            'gruppe' => self::GRUPPE_ANPASSEN,
        ];
    }

    private function schuleinstellungen(): array
    {
        $anzahl = SchoolSetting::whereNotNull('value')->count();

        return [
            'key' => 'schuleinstellungen',
            'title' => 'Schuleinstellungen',
            'description' => 'Hinweistexte für Aufsichten, Schlusstexte der Pläne, Schwellenwerte der Absenzen.',
            'status' => self::STATUS_DONE,
            'detail' => $anzahl > 0 ? $anzahl . ' Einstellungen gesetzt' : 'Es gelten die Vorgabewerte',
            'route' => 'school-settings.index',
            'hint' => null,
            'gruppe' => self::GRUPPE_ANPASSEN,
        ];
    }

    private function school(): ?School
    {
        $schule = app(AktuelleSchule::class)->schule();

        return $schule !== null ? School::find($schule->getKey()) : null;
    }
}
