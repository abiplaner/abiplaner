<?php

namespace App\Services;

use App\Models\AbsenceMeasure;
use App\Models\Absence;
use App\Models\SchoolSetting;
use App\Models\Student;
use Carbon\Carbon;

/**
 * Auswertung der Absenzen je Schüler (Roadmap 8.2) inkl. Reset-Logik durch
 * dokumentierte Maßnahmen (Roadmap 8.3).
 *
 * Bewusst keine automatische "Verdachtsstufe" — nur Kennzahlen gegen
 * konfigurierbare Schwellenwerte. Die Bewertung bleibt bei der
 * Oberstufenkoordination.
 */
class AbsenceEvaluationService
{
    private const SETTING_PREFIX = 'absences.';

    public function __construct(
        private SchoolDaysService $schoolDays
    ) {}

    /**
     * @return array{krank_percent: float, unentschuldigt_count: int, verspaetet_count: int, schultage_bisher: int, schultage_automatisch: bool}
     */
    public function thresholds(): array
    {
        $override = (int) (SchoolSetting::get(self::SETTING_PREFIX . 'schultage_bisher_override') ?? 0);
        $automatic = $this->schoolDays->countSchoolDaysUntil();

        return [
            'krank_percent' => (float) (SchoolSetting::get(self::SETTING_PREFIX . 'krank_percent_threshold') ?? 10),
            'unentschuldigt_count' => (int) (SchoolSetting::get(self::SETTING_PREFIX . 'unentschuldigt_threshold') ?? 3),
            'verspaetet_count' => (int) (SchoolSetting::get(self::SETTING_PREFIX . 'verspaetet_threshold') ?? 5),
            'schultage_bisher' => $override > 0 ? $override : $automatic,
            'schultage_automatisch' => $override <= 0,
        ];
    }

    /**
     * Letztes Reset-Datum je Aspekt für einen Schüler (null = noch nie zurückgesetzt).
     *
     * @return array{krankheit: \Carbon\Carbon|null, unentschuldigt: \Carbon\Carbon|null, verspaetung: \Carbon\Carbon|null}
     */
    public function resetDates(Student $student): array
    {
        $latest = AbsenceMeasure::where('student_id', $student->id)
            ->whereIn('aspect', ['krankheit', 'unentschuldigt', 'verspaetung'])
            ->selectRaw('aspect, MAX(measure_date) as latest_date')
            ->groupBy('aspect')
            ->pluck('latest_date', 'aspect');

        return [
            'krankheit' => isset($latest['krankheit']) ? Carbon::parse($latest['krankheit']) : null,
            'unentschuldigt' => isset($latest['unentschuldigt']) ? Carbon::parse($latest['unentschuldigt']) : null,
            'verspaetung' => isset($latest['verspaetung']) ? Carbon::parse($latest['verspaetung']) : null,
        ];
    }

    /**
     * Aktueller Attestpflicht-Status (abiplaner-interne Entscheidung, siehe
     * Modul 8.3 — unabhängig vom Infoportal-Importstatus).
     *
     * @return array{active: bool, since: \Carbon\Carbon|null}
     */
    public function attestpflichtStatus(Student $student): array
    {
        $latest = AbsenceMeasure::where('student_id', $student->id)
            ->whereIn('type', ['attestpflicht_verhaengt', 'attestpflicht_aufgehoben'])
            ->orderByDesc('measure_date')
            ->orderByDesc('id')
            ->first();

        return [
            'active' => $latest !== null && $latest->type === 'attestpflicht_verhaengt',
            'since' => $latest?->measure_date,
        ];
    }

    /**
     * Kennzahlen für die Übersichtsliste (8.2), unter Berücksichtigung der
     * Reset-Logik: nur Absenzen NACH dem letzten Reset des jeweiligen
     * Aspekts zählen.
     *
     * @return array{krank_days: int, krank_percent: float|null, unentschuldigt_count: int, verspaetet_count: int, auffaellig: array<string>}
     */
    public function evaluate(Student $student, array $thresholds, array $resetDates): array
    {
        $krankQuery = $student->absences()->where('category', 'krank');
        if ($resetDates['krankheit']) {
            $krankQuery->where('date_from', '>', $resetDates['krankheit']);
        }
        $krankDays = (int) $krankQuery->sum('days');

        $unentschuldigtQuery = $student->absences()->where('category', 'fehlt')->where('excuse_status', 'nein');
        if ($resetDates['unentschuldigt']) {
            $unentschuldigtQuery->where('date_from', '>', $resetDates['unentschuldigt']);
        }
        $unentschuldigtCount = $unentschuldigtQuery->count();

        $verspaetetQuery = $student->absences()->where('category', 'verspaetet');
        if ($resetDates['verspaetung']) {
            $verspaetetQuery->where('date_from', '>', $resetDates['verspaetung']);
        }
        $verspaetetCount = $verspaetetQuery->count();

        $krankPercent = $thresholds['schultage_bisher'] > 0
            ? round($krankDays / $thresholds['schultage_bisher'] * 100, 1)
            : null;

        $auffaellig = [];
        if ($krankPercent !== null && $krankPercent >= $thresholds['krank_percent']) {
            $auffaellig[] = 'krankheit';
        }
        if ($unentschuldigtCount >= $thresholds['unentschuldigt_count']) {
            $auffaellig[] = 'unentschuldigt';
        }
        if ($verspaetetCount >= $thresholds['verspaetet_count']) {
            $auffaellig[] = 'verspaetung';
        }

        return [
            'krank_days' => $krankDays,
            'krank_percent' => $krankPercent,
            'unentschuldigt_count' => $unentschuldigtCount,
            'verspaetet_count' => $verspaetetCount,
            'auffaellig' => $auffaellig,
        ];
    }

    /**
     * Attestpflicht-Frist für eine einzelne Absenz mit ausstehendem Attest
     * (excuse_status = attestpflicht, siehe AbsenceCsvImportService).
     *
     * @return array{deadline: \Carbon\Carbon, overdue: bool}
     */
    public function attestDeadlineFor(Carbon $dateFrom): array
    {
        $deadline = $dateFrom->copy()->addDays(10);

        return [
            'deadline' => $deadline,
            'overdue' => $deadline->isPast(),
        ];
    }

    /**
     * Zusammenfassung offener Atteste für die Übersichtsliste: wie viele
     * Krankmeldungen mit Status "schriftlich (Attestpflicht)" (aus dem
     * Infoportal-Import, siehe AbsenceCsvImportService::normalizeExcuseStatus())
     * noch kein Attest nachgewiesen haben, und ob die 10-Tage-Frist bei
     * mindestens einer davon bereits überschritten ist.
     *
     * Unabhängig von attestpflichtStatus() (das ist die abiplaner-interne
     * Entscheidung, Modul 8.3) — hier geht es rein um das, was der Import
     * bereits zeigt.
     *
     * @return array{count: int, overdue: bool}
     */
    public function openAttestSummary(Student $student): array
    {
        $openAbsences = $student->absences()
            ->where('excuse_status', 'attestpflicht')
            ->get(['date_from']);

        $overdue = false;
        foreach ($openAbsences as $absence) {
            /** @var Absence $absence */
            if ($this->attestDeadlineFor($absence->date_from)['overdue']) {
                $overdue = true;
                break;
            }
        }

        return [
            'count' => $openAbsences->count(),
            'overdue' => $overdue,
        ];
    }

    /**
     * Statistik-Zusammenfassung für die Schüler-Detailseite: Gesamtzahlen
     * über die komplette erfasste Historie (NICHT reset-begrenzt, anders
     * als evaluate() für die Übersichtsliste — hier soll das vollständige
     * Bild sichtbar sein).
     *
     * @return array{krank_days: int, fehlt_hours: int, fehlt_unentschuldigt_hours: int, verspaetet_count: int, verspaetet_minutes: int}
     */
    public function detailStatistics(Student $student): array
    {
        $krankDays = (int) $student->absences()->where('category', 'krank')->sum('days');

        $fehltHours = (int) $student->absences()->where('category', 'fehlt')->sum('hours');
        $fehltUnentschuldigtHours = (int) $student->absences()
            ->where('category', 'fehlt')
            ->where('excuse_status', 'nein')
            ->sum('hours');

        $verspaetetCount = $student->absences()->where('category', 'verspaetet')->count();
        $verspaetetMinutes = (int) $student->absences()->where('category', 'verspaetet')->sum('minutes_late');

        return [
            'krank_days' => $krankDays,
            'fehlt_hours' => $fehltHours,
            'fehlt_unentschuldigt_hours' => $fehltUnentschuldigtHours,
            'verspaetet_count' => $verspaetetCount,
            'verspaetet_minutes' => $verspaetetMinutes,
        ];
    }
}
