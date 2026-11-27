<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Room;
use App\Models\Course;
use App\Models\ExamAssignment;
use App\Models\CalendarEvent;
use App\Models\GlobalCalendarEvent;
use App\Models\Zusatzpruefung;
use App\Services\TenantBackupService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected TenantBackupService $backupService
    ) {}

    public function index()
    {
        $user = auth()->user();

        // Prüfungszeiträume (zentral)
        $periodService = app(\App\Services\CurrentExamPeriodService::class);
        $examPeriods = $periodService->all();

        $activeExamPeriod = $periodService->current();

        $stats = [];
        if ($activeExamPeriod) {
            // Nur der Abiturjahrgang: Im Zeitraum stehen auch Q11 und Q12,
            // die Q12 mit Prüfungen in ihren künftigen Abiturfächern.
            $studentIds = \App\Models\Student::where('exam_period_id', $activeExamPeriod->id)->derStufe('13')->pluck('id');

            // --- Mündliche Prüfungen ---
            $totalOralExams = \App\Models\StudentExam::whereIn('student_id', $studentIds)
                ->where('exam_type', 'oral')->count();
            // Distinct student_exam_ids zählen (Krankheitsverlegungen erzeugen mehrere Assignments)
            $plannedOralExams = ExamAssignment::whereHas('studentExam', fn($q) =>
                $q->whereIn('student_id', $studentIds)->where('exam_type', 'oral')
            )->distinct('student_exam_id')->count('student_exam_id');

            // --- Schriftliche Prüfungen ---
            $totalWrittenExams = \App\Models\StudentExam::whereIn('student_id', $studentIds)
                ->where('exam_type', 'written')->count();
            $writtenScheduled = \App\Models\StudentExam::whereIn('student_id', $studentIds)
                ->where('exam_type', 'written')
                ->whereNotNull('written_exam_year_date_id')->count();
            // Schüler mit mind. einer schriftlichen Prüfung
            $writtenStudentsTotal = \App\Models\Student::whereIn('id', $studentIds)
                ->whereHas('exams', fn($q) => $q->where('exam_type', 'written'))
                ->count();
            // Schüler bei denen ALLE schriftlichen Prüfungen einen Sitzplatz haben
            $writtenSeated = \App\Models\Student::whereIn('id', $studentIds)
                ->whereHas('exams', fn($q) => $q->where('exam_type', 'written'))
                ->get('id')
                ->filter(function ($s) {
                    $total  = \App\Models\StudentExam::where('student_id', $s->id)->where('exam_type', 'written')->count();
                    $seated = \App\Models\WrittenExamSeatAssignment::where('student_id', $s->id)->count();
                    return $total > 0 && $seated >= $total;
                })
                ->count();

            // --- Zusatzprüfungen ---
            //
            // Über die eigene exam_period_id der Zusatzprüfung statt über den
            // Schüler: `zusatzpruefungen.student_id` ist für externe Bewerber
            // null, ihre Zusatzprüfungen fehlten in beiden Kacheln des
            // Dashboards. Die Spalte am Datensatz ist ohnehin die direktere
            // Auskunft.
            $totalZP = Zusatzpruefung::where('exam_period_id', $activeExamPeriod->id)->count();
            $placedZP = Zusatzpruefung::where('exam_period_id', $activeExamPeriod->id)
                ->where('status', 'placed')
                ->count();

            $stats = [
                'total_students'        => $studentIds->count(),
                'total_teachers'        => Teacher::count(),
                'total_rooms'           => Room::count(),
                'total_courses'         => Course::where('exam_period_id', $activeExamPeriod->id)->derStufe('13')->count(),
                // Mündlich
                'oral_exams'            => $totalOralExams,
                'oral_planned'          => $plannedOralExams,
                'oral_unplanned'        => $totalOralExams - $plannedOralExams,
                // Schriftlich
                'written_exams'         => $totalWrittenExams,
                'written_scheduled'     => $writtenScheduled,
                'written_seated'        => $writtenSeated,
                'written_students_total' => $writtenStudentsTotal,
                'written_unscheduled'   => $totalWrittenExams - $writtenScheduled,
                // Zusatzprüfungen
                'zp_total'              => $totalZP,
                'zp_placed'             => $placedZP,
                'zp_pending'            => $totalZP - $placedZP,
            ];
        }

        // --- Kalender: nächste 30 Tage ---
        $today = Carbon::today();
        $until = $today->copy()->addDays(30);

        $calendarEvents = $this->loadCalendarEventsInRange(CalendarEvent::class, $today, $until, false);
        $globalEvents   = $this->loadCalendarEventsInRange(GlobalCalendarEvent::class, $today, $until, true);

        // Kolloquiumswochen als Pseudo-Events einmischen
        $periodEvents = collect();
        if ($activeExamPeriod) {
            foreach ([
                ['Kolloquiumswoche 1', $activeExamPeriod->week_1_start, $activeExamPeriod->week_1_end, '#1d4ed8'],
                ['Kolloquiumswoche 2', $activeExamPeriod->week_2_start, $activeExamPeriod->week_2_end, '#1d4ed8'],
            ] as [$label, $start, $end]) {
                if ($end >= $today && $start <= $until) {
                    $periodEvents->push([
                        'title'     => $label,
                        'type'      => 'exam_oral',
                        'type_name' => 'Kolloquium',
                        'color'     => '#1d4ed8',
                        'date'      => $start,
                        'end_date'  => $end,
                        'is_global' => false,
                    ]);
                }
            }
        }

        $upcomingEvents = $calendarEvents
            ->concat($globalEvents)
            ->concat($periodEvents)
            ->sortBy('date')
            ->values();

        // --- Schulname ---
        /** @var \App\Models\School|null $school */
        $school     = app(\App\Services\AktuelleSchule::class)->schule();
        $schoolName = $school !== null ? $school->name : '';

        // --- Letztes Backup ---
        $lastBackup = null;
        try {
            if ($school) {
                $backups = $this->backupService->listBackups($school);
                if (!empty($backups)) {
                    $lastBackup = collect($backups)->sortByDesc('created_at')->first();
                }
            }
        } catch (\Throwable) {
            // Backup-Info optional – kein Fehler werfen
        }

        // --- Einrichtung (Modul 6.2, 23.11.2026) ---
        // Die drei Dateien, ohne die nicht geplant werden kann, gleich auf der
        // Startseite — mit Anleitung, Vorlage und Maske. Was die Einrichtung
        // vorbelegt (Räume, Zeitslots …), steht nur auf der Einrichtungsseite.
        $checklist = app(\App\Services\SetupChecklistService::class);
        $setupItems = $checklist->items();
        $setupProgress = $checklist->progress($setupItems);
        $importe = array_values(array_filter(
            $setupItems,
            fn (array $item) => $item['gruppe'] === \App\Services\SetupChecklistService::GRUPPE_EINLESEN
        ));

        return view('dashboard', compact(
            'examPeriods', 'activeExamPeriod', 'stats',
            'upcomingEvents', 'lastBackup', 'schoolName', 'setupProgress',
            'importe'
        ));
    }

    /**
     * Lädt Kalenderereignisse (schul- oder mandantenweit) im Zeitraum
     * [$today, $until] – inklusive mehrtägiger Ereignisse, die vor $today
     * beginnen und bis mindestens $today laufen. Genutzt für CalendarEvent
     * und GlobalCalendarEvent gleichermaßen, die sich nur im `is_global`-Flag
     * der Ausgabe unterscheiden.
     *
     * @param class-string<CalendarEvent>|class-string<GlobalCalendarEvent> $modelClass
     */
    private function loadCalendarEventsInRange(string $modelClass, Carbon $today, Carbon $until, bool $isGlobal): \Illuminate\Support\Collection
    {
        return $modelClass::where(function ($q) use ($today, $until) {
                $q->whereBetween('event_date', [$today, $until])
                  ->orWhere(fn($q2) =>
                      $q2->where('event_date', '<=', $today)
                         ->where('end_date', '>=', $today)
                  );
            })
            ->orderBy('event_date')
            ->get()
            ->map(fn($e) => [
                'title'      => $e->title,
                'type'       => $e->event_type,
                'type_name'  => $e->event_type_name,
                'color'      => $e->color,
                'date'       => $e->event_date,
                'end_date'   => $e->end_date,
                'is_global'  => $isGlobal,
            ]);
    }
}
