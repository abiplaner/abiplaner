<?php

/**
 * Planungsseiten der Schule (nach Anmeldung).
 */

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamPeriodController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\OralExamDayRoomController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\TeacherImportController;
use App\Http\Controllers\SimpleRosterImportController;
use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\AbsenceImportController;
use App\Http\Controllers\AbsenceMeasureController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SlotTemplateController;
use App\Http\Controllers\SlotTemplateItemController;
use App\Http\Controllers\SupervisionController;
use App\Http\Controllers\SupervisionGridController;
use App\Http\Controllers\SupervisionTemplateController;
use App\Http\Controllers\CouplingController;
use App\Http\Controllers\WrittenExamDateController;
use App\Http\Controllers\WrittenExamAssignmentController;
use App\Http\Controllers\ExternalApplicantController;
use App\Http\Controllers\ExternalApplicantSubjectsController;
use App\Http\Controllers\SupervisionDistributionController;
use App\Http\Controllers\WrittenExamSupervisionTimeSlotController;
use App\Http\Controllers\WseminarWahlController;
use App\Http\Controllers\WseminarKursController;
use App\Http\Controllers\WseminarZuteilungController;
use App\Http\Controllers\SchoolSettingsController;
use App\Http\Controllers\SupportController;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureUserHasSchool;
use Illuminate\Support\Facades\Route;


$middleware = [
    'web',
    'auth', 
    'verified', 
    EnsureUserHasSchool::class,
    'track.last-page',
    // Schreibschutz für Archiv-Perioden (Modul 1.1): blockiert schreibende
    // Requests, sobald per Umschalter eine Alt-Periode betrachtet wird.
    \App\Http\Middleware\PreventArchivePeriodWrites::class,
];


Route::middleware($middleware)->group(function () {
    
    // Perioden-Umschalter (Modul 1.1) — für alle angemeldeten Rollen,
    // da die Archivansicht ohnehin schreibgeschützt ist
    Route::post('/exam-periods/switch', [ExamPeriodController::class, 'switchPeriod'])->name('exam-periods.switch');
    Route::post('/exam-periods/switch/reset', [ExamPeriodController::class, 'switchReset'])->name('exam-periods.switch-reset');

    // Dashboard - mit Prüfungszeitraum-Verwaltung
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Support-Meldungen (Modul 6.4, Stufe 1) — für ALLE angemeldeten Rollen.
    // Wer einen Fehler sieht, darf ihn melden; eine Rollenprüfung hier hiesse,
    // dass ausgerechnet der Beisitzer den Fehler nicht melden kann, der ihn
    // sieht.
    //
    // Im Mandanten und nicht zentral, obwohl die Fallakte zentral liegt: Der
    // Kontext (Einrichtungsstand, letzte Schritte) braucht den initialisierten
    // Mandanten. Die festen Pfade stehen VOR {fall}, sonst schluckt der
    // Platzhalter sie.
    // Lokale Edition: Support-Meldungen vorerst abgeschaltet (Entscheidung
    // Wilfried, 19.11.2026) — die Fallakte läge in zentral.sqlite auf dem
    // Schulrechner, wo niemand sie liest.
    Route::middleware('abgeschaltet')->group(function () {
    Route::get('/support/kontext', [SupportController::class, 'kontext'])->name('support.kontext');
    Route::get('/support/vorschlag', [SupportController::class, 'vorschlag'])->name('support.vorschlag');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    // Gegen versehentliche Mehrfachmeldung und gegen Missbrauch — die
    // Anwendung steht offen im Netz.
    Route::post('/support', [SupportController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('support.store');
    Route::get('/support/{fall}', [SupportController::class, 'show'])->name('support.show');
    // Bildschirmfotos (Entscheidung 48): Der Fall steht im Pfad und wird gegen
    // den Anhang geprüft — sonst wäre die Policy am Fall wirkungslos, sobald
    // jemand eine fremde Anhang-Id unter den eigenen Fall hängt.
    Route::get('/support/{fall}/anhang/{anhang}', [SupportController::class, 'anhang'])
        ->name('support.anhang');
    Route::post('/support/{fall}/nachricht', [SupportController::class, 'nachricht'])
        ->middleware('throttle:20,1')
        ->name('support.nachricht');
    }); // Ende abgeschaltet (Support)
    
    // Prüfungszeitraum-Verwaltung (nur auf Dashboard)
    Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
        Route::post('/exam-periods', [ExamPeriodController::class, 'store'])->name('exam-periods.store');
        Route::put('/exam-periods/{examPeriod}', [ExamPeriodController::class, 'update'])->name('exam-periods.update');
        Route::delete('/exam-periods/{examPeriod}', [ExamPeriodController::class, 'destroy'])->name('exam-periods.destroy');
        Route::post('/exam-periods/{examPeriod}/activate', [ExamPeriodController::class, 'activate'])->name('exam-periods.activate');
        Route::post('/exam-periods/{examPeriod}/regenerate-timeslots', [ExamPeriodController::class, 'regenerateTimeSlots'])->name('exam-periods.regenerate-timeslots');
    });
    
    // Planung (Planner+) - arbeitet mit aktivem Prüfungszeitraum
    Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
        // Hauptplanungsbereich
        Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
        Route::post('/planning/auto-schedule', [PlanningController::class, 'autoSchedule'])->name('planning.auto');
        Route::post('/planning/assign', [PlanningController::class, 'assignExam'])->name('planning.assign');
        Route::delete('/exam-periods/{examPeriod}/planning/assignments/{assignment}', [PlanningController::class, 'removeAssignment'])->name('planning.remove');
        Route::post('/planning/validate', [PlanningController::class, 'validate'])->name('planning.validate');
        Route::get('/planning/{examPeriod}/export-teachers-pdf', [PlanningController::class, 'exportTeachersPdf'])->name('planning.export-teachers-pdf');
        Route::post('/planning/{examPeriod}/export-warnings-pdf', [PlanningController::class, 'exportWarningsPdf'])->name('planning.export-warnings-pdf');
        Route::get('/planning/{examPeriod}/export-colloquium-pdf', [PlanningController::class, 'exportColloquiumPdf'])->name('planning.export-colloquium-pdf');
        Route::get('/planning/{examPeriod}/export-colloquium-excel', [PlanningController::class, 'exportColloquiumExcel'])->name('planning.export-colloquium-excel');
        Route::get('/planning/{examPeriod}/export-room-day-schedule-pdf', [PlanningController::class, 'exportRoomDaySchedulePdf'])->name('planning.export-room-day-schedule-pdf');
        Route::get('/planning/{examPeriod}/export-students-pdf', [PlanningController::class, 'exportStudentsPdf'])->name('planning.export-students-pdf');
        Route::get('/planning/{examPeriod}/export-prep-rooms-pdf', [PlanningController::class, 'exportPrepRoomsPdf'])->name('planning.export-prep-rooms-pdf');
        Route::get('/planning/{examPeriod}/export-compact-matrix-pdf', [PlanningController::class, 'exportCompactMatrixPdf'])->name('planning.export-compact-matrix-pdf');
        Route::get('/planning/{examPeriod}/niederschrift-auswahl', [PlanningController::class, 'niederschriftAuswahl'])->name('planning.niederschrift-auswahl');
        Route::get('/planning/{examPeriod}/export-niederschrift-pdf', [PlanningController::class, 'exportNiederschriftPdf'])->name('planning.export-niederschrift-pdf');
        Route::get('/planning/{examPeriod}/export-aufgabenabgabe-pdf', [PlanningController::class, 'exportAufgabenabgabePdf'])->name('planning.export-aufgabenabgabe-pdf');
        Route::get('/planning/{examPeriod}/export-daily-subjects-pdf', [PlanningController::class, 'exportDailySubjectsPdf'])->name('planning.export-daily-subjects-pdf');
        Route::get('/planning/{examPeriod}/export-vorbereitungsprotokoll-pdf', [PlanningController::class, 'exportVorbereitungsprotokollPdf'])->name('planning.export-vorbereitungsprotokoll-pdf');
        
        // Räume & Tage für Kolloquium
        Route::get('/planning/rooms', [OralExamDayRoomController::class, 'index'])->name('planning.rooms.index');
        Route::post('/planning/rooms/toggle', [OralExamDayRoomController::class, 'toggle'])->name('planning.rooms.toggle');

        // Optimierungssimulation (Modul 4.6) – reine Entscheidungsunterstützung
        Route::get('/planning/simulation', [SimulationController::class, 'index'])->name('planning.simulation.index');
        Route::post('/planning/simulation', [SimulationController::class, 'run'])->name('planning.simulation.run');
        Route::get('/planning/simulation/export/pdf', [SimulationController::class, 'exportPdf'])->name('planning.simulation.export.pdf');

        // Formulare Kolloquium
        Route::group(['prefix' => 'kolloquium/forms', 'as' => 'kolloquium.forms.'], function () {
            Route::get('/', [\App\Http\Controllers\OralExamFormController::class, 'index'])->name('index');
            Route::get('/fachausschuesse/pdf', [\App\Http\Controllers\OralExamFormController::class, 'fachausschuessePdf'])->name('fachausschuesse.pdf');
            Route::get('/fachausschuesse/excel', [\App\Http\Controllers\OralExamFormController::class, 'fachausschuesseExcel'])->name('fachausschuesse.excel');
        });

        // Abiturprüfungsausschuss: Sitzungen und Niederschriften (Modul 4.1)
        Route::group(['prefix' => 'exam-board', 'as' => 'exam-board.'], function () {
            Route::get('/', [\App\Http\Controllers\ExamBoardMeetingController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\ExamBoardMeetingController::class, 'store'])->name('store');
            Route::put('/{examBoardMeeting}', [\App\Http\Controllers\ExamBoardMeetingController::class, 'update'])->name('update');
            Route::delete('/{examBoardMeeting}', [\App\Http\Controllers\ExamBoardMeetingController::class, 'destroy'])->name('destroy');
            Route::get('/{examBoardMeeting}/protokoll', [\App\Http\Controllers\ExamBoardMeetingController::class, 'exportDocx'])->name('protokoll');
        });

        // Themenkatalog Kolloquium (Modul 4.7)
        Route::group(['prefix' => 'kolloquium/topics', 'as' => 'kolloquium.topics.'], function () {
            Route::get('/', [\App\Http\Controllers\ColloquiumTopicController::class, 'index'])->name('index');
            Route::get('/form/pdf', [\App\Http\Controllers\ColloquiumTopicController::class, 'formPdf'])->name('form.pdf');
            Route::get('/couplings', [\App\Http\Controllers\ColloquiumTopicController::class, 'couplings'])->name('couplings');
            Route::post('/couplings', [\App\Http\Controllers\ColloquiumTopicController::class, 'saveCouplings'])->name('couplings.save');
            Route::post('/preview', [\App\Http\Controllers\ColloquiumTopicController::class, 'preview'])->name('preview');
            Route::post('/', [\App\Http\Controllers\ColloquiumTopicController::class, 'store'])->name('store');
            Route::delete('/{course}', [\App\Http\Controllers\ColloquiumTopicController::class, 'destroy'])->name('destroy');
        });
        
        // Planung zurücksetzen
        Route::post('/planning/{examPeriod}/reset', [PlanningController::class, 'resetPlanning'])->name('planning.reset');
        Route::delete('/planning/{examPeriod}/reset-assignments', [PlanningController::class, 'resetAssignments'])->name('planning.reset-assignments');
        
        // Prüfung fixieren/entsperren
        Route::post('/exam-periods/{examPeriod}/planning/assignments/{assignment}/toggle-lock', [PlanningController::class, 'toggleLock'])->name('planning.toggle-lock');
        Route::post('/planning/assignments/{assignment}/lock', [PlanningController::class, 'lockAssignment'])->name('planning.lock');
        Route::post('/planning/assignments/{assignment}/unlock', [PlanningController::class, 'unlockAssignment'])->name('planning.unlock');
        
        // Prüfungswoche wechseln
        Route::post('/exam-periods/{examPeriod}/planning/assignments/{assignment}/swap-week', [PlanningController::class, 'swapWeek'])->name('planning.swap-week');

        // Erkrankung / Nachtermin
        Route::post('/exam-periods/{examPeriod}/planning/assignments/{assignment}/illness-reschedule', [PlanningController::class, 'illnessReschedule'])->name('planning.illness-reschedule');
        Route::post('/exam-periods/{examPeriod}/planning/assignments/{assignment}/illness-reschedule-outside', [PlanningController::class, 'illnessRescheduleOutside'])->name('planning.illness-reschedule-outside');
        Route::patch('/exam-periods/{examPeriod}/planning/assignments/{assignment}/update-outside', [PlanningController::class, 'updateOutsideAssignment'])->name('planning.update-outside');

        // Vorgezogene mündliche Prüfungen externer Bewerber (§ 61 Abs. 4).
        // Entfernt werden sie über planning.remove wie jede andere Zuweisung.
        Route::post('/exam-periods/{examPeriod}/planning/vorgezogen', [PlanningController::class, 'storeVorgezogen'])->name('planning.vorgezogen.store');
        Route::patch('/exam-periods/{examPeriod}/planning/vorgezogen/{assignment}', [PlanningController::class, 'updateVorgezogen'])->name('planning.vorgezogen.update');
        Route::post('/exam-periods/{examPeriod}/planning/assignments/{assignment}/revert-illness', [PlanningController::class, 'revertIllness'])->name('planning.revert-illness');
        Route::post('/exam-periods/{examPeriod}/planning/assignments/{assignment}/cancel-illness', [PlanningController::class, 'cancelAsIllness'])->name('planning.cancel-illness');
        
        // Import (Schueler-CSV)
        Route::get('/import', [StudentImportController::class, 'showForm'])->name('import.form');
        // Die Vorschau entsteht per POST. Wer sie neu lädt oder ihre Adresse
        // aus dem Verlauf holt, schickt ein GET — ohne diese Zeile ein 404.
        // Sie braucht einen Namen: route:cache tauft unbenannte Routen sonst
        // auf generated::<zufall>, und in der Routenliste steht dann eine
        // Zeile, der niemand ansieht, wozu sie gehört (29.08.2026).
        Route::get('/import/preview', fn () => redirect()->route('import.form'))
            ->name('import.preview.neuladen');
        Route::post('/import/preview', [StudentImportController::class, 'preview'])->name('import.preview');
        Route::post('/import/diff-preview', [StudentImportController::class, 'diffPreview'])->name('import.diff-preview');
        Route::post('/import', [StudentImportController::class, 'import'])->name('import.process');

        // Import (einfache Jahrgangsliste, ohne Kurse — z. B. Q12)
        Route::post('/import/simple-preview', [SimpleRosterImportController::class, 'preview'])->name('import.simple-preview');
        Route::post('/import/simple', [SimpleRosterImportController::class, 'import'])->name('import.simple-process');

        // Absenzenmanagement (Roadmap 8.1)
        Route::get('/absences', [AbsenceController::class, 'index'])->name('absences.index');
        Route::get('/absences/export/action-needed-pdf', [AbsenceController::class, 'actionNeededPdf'])->name('absences.export.action-needed-pdf');
        Route::get('/absences/students/{student}', [AbsenceController::class, 'show'])->name('absences.show');
        Route::get('/absences/import', [AbsenceImportController::class, 'showForm'])->name('absences.import.form');
        Route::post('/absences/import/preview', [AbsenceImportController::class, 'preview'])->name('absences.import.preview');
        Route::post('/absences/import', [AbsenceImportController::class, 'import'])->name('absences.import.process');
        Route::post('/absences/students/{student}/measures', [AbsenceMeasureController::class, 'store'])->name('absences.measures.store');
        Route::delete('/absences/students/{student}/measures/{measure}', [AbsenceMeasureController::class, 'destroy'])->name('absences.measures.destroy');
        
        // Sicherungen (nur Schuladmin): Wiederherstellen und Zurücksetzen
        // ersetzen die ganze Datenbank samt Benutzerkonten.
        Route::middleware(CheckRole::class . ':admin')->group(function () {
            Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
            Route::post('/backups/create', [BackupController::class, 'create'])->name('backups.create');
            Route::post('/backups/restore', [BackupController::class, 'restore'])->name('backups.restore');
            Route::post('/backups/delete', [BackupController::class, 'delete'])->name('backups.delete');
            Route::post('/backups/download', [BackupController::class, 'download'])->name('backups.download');
            Route::post('/backups/ordner', [BackupController::class, 'ordnerOeffnen'])->name('backups.ordner');
            Route::post('/backups/zuruecksetzen', [BackupController::class, 'zuruecksetzen'])->name('backups.zuruecksetzen');
        });

        // Schuljahreswechsel-Wizard (Modul 1.1, Phase 4)
        Route::prefix('schuljahreswechsel')->name('year-change.')->group(function () {
            Route::get('/', [\App\Http\Controllers\YearChangeWizardController::class, 'index'])->name('index');
            Route::post('/start', [\App\Http\Controllers\YearChangeWizardController::class, 'start'])->name('start');
            Route::post('/vorlagen-uebernehmen', [\App\Http\Controllers\YearChangeWizardController::class, 'copyTemplates'])->name('copy-templates');
            Route::post('/steps/{step}/run', [\App\Http\Controllers\YearChangeWizardController::class, 'run'])
                ->whereNumber('step')->name('run');
            Route::post('/steps/{step}/complete', [\App\Http\Controllers\YearChangeWizardController::class, 'complete'])
                ->whereNumber('step')->name('complete');
            Route::post('/match/manual', [\App\Http\Controllers\YearChangeWizardController::class, 'manualMatch'])->name('match-manual');
            Route::get('/bericht', [\App\Http\Controllers\YearChangeWizardController::class, 'report'])->name('report');
            Route::post('/finish', [\App\Http\Controllers\YearChangeWizardController::class, 'finish'])->name('finish');
            Route::post('/reset', [\App\Http\Controllers\YearChangeWizardController::class, 'reset'])->name('reset');
        });
        // (Tote Routen export.pdf / export.excel am 19.11.2026 entfernt: sie
        // zeigten auf ExportController::exportPdf()/exportExcel(), die es nicht
        // gibt, und waren nirgends verlinkt.)
    });
    
    // Kursverwaltung (Planner+)
    Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/export-pdf', [CourseController::class, 'exportPdf'])->name('courses.export-pdf');
        Route::get('/courses/export-written-pdf', [CourseController::class, 'exportWrittenPdf'])->name('courses.export-written-pdf');
        Route::get('/courses/export-combined-pdf', [CourseController::class, 'exportCombinedPdf'])->name('courses.export-combined-pdf');
        Route::get('/courses/export-combined-excel', [CourseController::class, 'exportCombinedExcel'])->name('courses.export-combined-excel');
        Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::post('/courses/{course}/assign-assessor', [CourseController::class, 'assignAssessor'])->name('courses.assign-assessor');
        Route::post('/courses/{course}/assign-second-corrector', [CourseController::class, 'assignSecondCorrector'])->name('courses.assign-second-corrector');
        Route::post('/student-exams/{studentExam}/assign-assessor', [CourseController::class, 'assignAssessorToStudent'])->name('student-exams.assign-assessor');
        Route::post('/student-exams/{studentExam}/assign-second-corrector', [CourseController::class, 'assignSecondCorrectorToStudent'])->name('student-exams.assign-second-corrector');
        Route::patch('/courses/{course}/update-teacher', [CourseController::class, 'updateTeacher'])->name('courses.update-teacher');
        Route::patch('/courses/{course}/update-code', [CourseController::class, 'updateCourseCode'])->name('courses.update-code');
        
        // Kopplungsverwaltung
        Route::get('/courses/{course}/couplings', [CouplingController::class, 'index'])->name('couplings.index');
        Route::post('/couplings', [CouplingController::class, 'store'])->name('couplings.store');
        Route::delete('/couplings', [CouplingController::class, 'destroy'])->name('couplings.destroy');
    });
    
    // Lehrer-Verwaltung (Planner+)
    Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
        Route::post('/teachers/bulk-destroy', [TeacherController::class, 'bulkDestroy'])->name('teachers.bulk-destroy');
        Route::resource('teachers', TeacherController::class);
        Route::get('/teachers/{teacher}/availability', [TeacherController::class, 'getAvailability'])->name('teachers.availability');
        Route::get('/teachers/{teacher}/timetable', [TeacherController::class, 'timetable'])->name('teachers.timetable');
        Route::post('/teachers/{teacher}/unavailability', [TeacherController::class, 'setUnavailability'])->name('teachers.unavailability');
        Route::delete('/teachers/{teacher}/unavailability/{unavailability}', [TeacherController::class, 'destroyUnavailability'])->name('teachers.unavailability.destroy');
        Route::get('/teachers-unavailability-overview', [TeacherController::class, 'unavailabilityOverview'])->name('teachers.unavailability.overview');
        Route::get('/teachers-unavailability-overview/pdf', [TeacherController::class, 'exportUnavailabilityPdf'])->name('teachers.unavailability.overview.pdf');
        
        // Lehrer-Import
        Route::get('/teachers-import', [TeacherImportController::class, 'showTeacherImportForm'])->name('teachers.import.form');
        Route::post('/teachers-import/preview', [TeacherImportController::class, 'previewTeachers'])->name('teachers.import.preview');
        Route::post('/teachers-import/diff-preview', [TeacherImportController::class, 'diffPreview'])->name('teachers.import.diff-preview');
        Route::post('/teachers-import', [TeacherImportController::class, 'importTeachers'])->name('teachers.import');
    });
    
    // Stundenraster-Verwaltung (Planner+)
    Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
        Route::get('/school-periods', [\App\Http\Controllers\SchoolPeriodController::class, 'index'])->name('school-periods.index');
        Route::post('/school-periods', [\App\Http\Controllers\SchoolPeriodController::class, 'store'])->name('school-periods.store');
    });

    // Schuleinstellungen (Planner+)
    Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
        Route::get('/school-settings', [SchoolSettingsController::class, 'index'])->name('school-settings.index');
        Route::post('/school-settings', [SchoolSettingsController::class, 'update'])->name('school-settings.update');
    });

    // Einrichtung / Onboarding (Modul 6.2)
    Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
        Route::get('/einrichtung', [\App\Http\Controllers\SetupController::class, 'index'])->name('setup.index');
        Route::get('/einrichtung/importe', [\App\Http\Controllers\SetupController::class, 'importe'])->name('setup.imports');
        Route::get('/einrichtung/importe/{schluessel}/vorlage', [\App\Http\Controllers\SetupController::class, 'vorlage'])
            ->name('setup.imports.template');
        Route::get('/einrichtung/importe/{schluessel}/asv-bericht/{datei}', [\App\Http\Controllers\SetupController::class, 'berichtsvorlage'])
            ->name('setup.imports.asv-report');
    });

    // Hilfesystem: liegt seit dem 29.08.2026 in routes/central.php — die
    // Kapitel sind zentraler Bestand und brauchen keinen Mandanten mehr.

    // Raum-Verwaltung (Planner+)
    Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
        Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create');
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::get('/rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
        Route::get('/rooms/occupancy', [RoomController::class, 'occupancy'])->name('rooms.occupancy');
        Route::get('/rooms/{examPeriod}/export-occupancy-pdf', [RoomController::class, 'exportOccupancyPdf'])->name('rooms.export-occupancy-pdf');
        
        // Sitzplan-Vorlagen (wiederverwendbare Vorlagen)
        Route::prefix('rooms/seating-templates')->name('seating-templates.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SeatingTemplateController::class, 'index'])
                ->name('index');
            Route::get('/create', [\App\Http\Controllers\SeatingTemplateController::class, 'create'])
                ->name('create');
            Route::post('/', [\App\Http\Controllers\SeatingTemplateController::class, 'store'])
                ->name('store');
            Route::get('/{seatingTemplate}/edit', [\App\Http\Controllers\SeatingTemplateController::class, 'edit'])
                ->name('edit');
            Route::put('/{seatingTemplate}', [\App\Http\Controllers\SeatingTemplateController::class, 'update'])
                ->name('update');
            Route::delete('/{seatingTemplate}', [\App\Http\Controllers\SeatingTemplateController::class, 'destroy'])
                ->name('destroy');
            
            // AJAX-Endpoints für Template-Editor
            Route::post('/{seatingTemplate}/update-seat', [\App\Http\Controllers\SeatingTemplateController::class, 'updateSeat'])
                ->name('update-seat');
            Route::post('/{seatingTemplate}/update-doors', [\App\Http\Controllers\SeatingTemplateController::class, 'updateDoors'])
                ->name('update-doors');
            Route::post('/{seatingTemplate}/resize', [\App\Http\Controllers\SeatingTemplateController::class, 'resize'])
                ->name('resize');
        });
    });
    
    // Zeitslot-Verwaltung (Planner+) - Unterpunkt von Planning
    Route::middleware(CheckRole::class . ':planner,admin')->prefix('planning')->group(function () {
        Route::get('/time-slots', [\App\Http\Controllers\TimeSlotController::class, 'index'])->name('planning.time-slots.index');
        Route::post('/exam-periods/{examPeriod}/time-slots', [\App\Http\Controllers\TimeSlotController::class, 'store'])->name('planning.time-slots.store');
        Route::post('/exam-periods/{examPeriod}/time-slots/assign-template', [\App\Http\Controllers\TimeSlotController::class, 'assignTemplate'])->name('planning.time-slots.assign-template');
        Route::put('/time-slots/{timeSlot}', [\App\Http\Controllers\TimeSlotController::class, 'update'])->name('planning.time-slots.update');
        Route::delete('/time-slots/{timeSlot}', [\App\Http\Controllers\TimeSlotController::class, 'destroy'])->name('planning.time-slots.destroy');
    });
    
    // Zeitslot-Template-Verwaltung (Planner+)
    Route::middleware(CheckRole::class . ':planner,admin')->prefix('exam-periods/{examPeriod}')->group(function () {
        Route::get('/slot-templates', [SlotTemplateController::class, 'index'])->name('slot-templates.index');
        Route::get('/slot-templates/create', [SlotTemplateController::class, 'create'])->name('slot-templates.create');
        Route::post('/slot-templates', [SlotTemplateController::class, 'store'])->name('slot-templates.store');
        Route::get('/slot-templates/{slotTemplate}/edit', [SlotTemplateController::class, 'edit'])->name('slot-templates.edit');
        Route::put('/slot-templates/{slotTemplate}', [SlotTemplateController::class, 'update'])->name('slot-templates.update');
        Route::delete('/slot-templates/{slotTemplate}', [SlotTemplateController::class, 'destroy'])->name('slot-templates.destroy');
        Route::post('/slot-templates/{slotTemplate}/apply', [SlotTemplateController::class, 'applyToDates'])->name('slot-templates.apply');
        
        // Template Items
        Route::post('/slot-templates/{slotTemplate}/items', [SlotTemplateItemController::class, 'store'])->name('slot-template-items.store');
        Route::put('/slot-templates/{slotTemplate}/items/{slotTemplateItem}', [SlotTemplateItemController::class, 'update'])->name('slot-template-items.update');
        Route::delete('/slot-templates/{slotTemplate}/items/{slotTemplateItem}', [SlotTemplateItemController::class, 'destroy'])->name('slot-template-items.destroy');
    });
    
    // Aufsichtsplanung (Planner+)
    Route::middleware(CheckRole::class . ':planner,admin')->prefix('exam-periods/{examPeriod}/supervisions')->group(function () {
        Route::get('/', [SupervisionController::class, 'index'])->name('supervisions.index');
        Route::get('/export-pdf', [SupervisionController::class, 'exportPdf'])->name('supervisions.export-pdf');
        // Neue Export-Routen (Gesamtplan, Lehrkräfte, Unterschriftsbögen)
        Route::get('/export/overview-pdf', [SupervisionController::class, 'exportOverviewPdf'])->name('supervisions.export.overview-pdf');
        Route::get('/export/overview-excel', [SupervisionController::class, 'exportOverviewExcel'])->name('supervisions.export.overview-excel');
        Route::get('/export/teacher-list-pdf', [SupervisionController::class, 'exportTeacherListPdf'])->name('supervisions.export.teacher-list-pdf');
        Route::get('/export/signature-sheets-pdf', [SupervisionController::class, 'exportSignatureSheetsPdf'])->name('supervisions.export.signature-sheets-pdf');
        Route::get('/teachers/search', [SupervisionController::class, 'searchTeachers'])->name('supervisions.teachers.search');
        Route::get('/coverage', [SupervisionController::class, 'coverage'])->name('supervisions.coverage');
        Route::post('/assign', [SupervisionController::class, 'assign'])->name('supervisions.assign');
        Route::post('/assign-corridor', [SupervisionController::class, 'assignCorridor'])->name('supervisions.assign-corridor');
        Route::put('/{supervision}/corridor', [SupervisionController::class, 'updateCorridor'])->name('supervisions.update-corridor');
        Route::delete('/{supervision}', [SupervisionController::class, 'destroy'])->name('supervisions.destroy');

        // Aufsichtsraster
        Route::get('/grids', [SupervisionGridController::class, 'index'])->name('supervisions.grids.index');
        Route::post('/grids', [SupervisionGridController::class, 'store'])->name('supervisions.grids.store');
        Route::put('/grids/{supervisionGrid}', [SupervisionGridController::class, 'update'])->name('supervisions.grids.update');
        Route::delete('/grids/{supervisionGrid}', [SupervisionGridController::class, 'destroy'])->name('supervisions.grids.destroy');
        Route::post('/grids/assign-room', [SupervisionGridController::class, 'assignRoom'])->name('supervisions.grids.assign-room');
        Route::get('/grids/room-assignments', [SupervisionGridController::class, 'getRoomAssignments'])->name('supervisions.grids.room-assignments');

        // Supervision-Zeitslots
        Route::get('/time-slots', [\App\Http\Controllers\SupervisionTimeSlotController::class, 'index'])->name('supervisions.time-slots.index');
        Route::post('/time-slots', [\App\Http\Controllers\SupervisionTimeSlotController::class, 'store'])->name('supervisions.time-slots.store');
        Route::post('/time-slots/copy', [\App\Http\Controllers\SupervisionTimeSlotController::class, 'copy'])->name('supervisions.time-slots.copy');
        Route::delete('/time-slots/destroy-all', [\App\Http\Controllers\SupervisionTimeSlotController::class, 'destroyAll'])->name('supervisions.time-slots.destroy-all');
        Route::put('/time-slots/{supervisionTimeSlot}', [\App\Http\Controllers\SupervisionTimeSlotController::class, 'update'])->name('supervisions.time-slots.update');
        Route::delete('/time-slots/{supervisionTimeSlot}', [\App\Http\Controllers\SupervisionTimeSlotController::class, 'destroy'])->name('supervisions.time-slots.destroy');

        // Templates
        Route::get('/templates', [SupervisionTemplateController::class, 'index'])->name('supervisions.templates.index');
        Route::post('/templates', [SupervisionTemplateController::class, 'store'])->name('supervisions.templates.store');
        Route::post('/templates/{template}/apply', [SupervisionTemplateController::class, 'apply'])->name('supervisions.templates.apply');
        Route::delete('/templates/{template}', [SupervisionTemplateController::class, 'destroy'])->name('supervisions.templates.destroy');
    });
    
    // Schülerliste (alle können sehen)
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/subjects', [StudentController::class, 'subjectOverview'])->name('students.subjects');
    Route::get('/students/export/pdf', [StudentController::class, 'exportPdf'])->name('students.export.pdf');
    Route::get('/students/export/excel', [StudentController::class, 'exportExcel'])->name('students.export.excel');
    Route::get('/students/subjects/export/pdf', [StudentController::class, 'exportSubjectsPdf'])->name('students.subjects.export.pdf');
    Route::get('/students/subjects/export/excel', [StudentController::class, 'exportSubjectsExcel'])->name('students.subjects.export.excel');
    
    // Schülerverwaltung (nur Planner+)
    Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
        Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
        // Gesperrte Tage (AJAX)
        Route::post('/students/{student}/blocked-days', [StudentController::class, 'storeBlockedDay'])->name('students.blocked-days.store');
        Route::delete('/students/{student}/blocked-days/{blockedDay}', [StudentController::class, 'destroyBlockedDay'])->name('students.blocked-days.destroy');
    });
    
    // ========================================
    // Externe Bewerber (§61 GSO)
    // ========================================
    
    // Übersicht für alle Rollen
    Route::get('/external-applicants', [\App\Http\Controllers\ExternalApplicantController::class, 'index'])
        ->name('external-applicants.index');
    
    // Verwaltung nur für Planner+
    Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
        // WICHTIG: /create MUSS VOR /{externalApplicant} stehen!
        Route::get('/external-applicants/create', [\App\Http\Controllers\ExternalApplicantController::class, 'create'])
            ->name('external-applicants.create');
        Route::post('/external-applicants', [\App\Http\Controllers\ExternalApplicantController::class, 'store'])
            ->name('external-applicants.store');
        Route::get('/external-applicants/{externalApplicant}/edit', [\App\Http\Controllers\ExternalApplicantController::class, 'edit'])
            ->name('external-applicants.edit');
        Route::put('/external-applicants/{externalApplicant}', [\App\Http\Controllers\ExternalApplicantController::class, 'update'])
            ->name('external-applicants.update');
        Route::delete('/external-applicants/{externalApplicant}', [\App\Http\Controllers\ExternalApplicantController::class, 'destroy'])
            ->name('external-applicants.destroy');
        
        // Der § 62-Kalkulator gehört zum Bewerber, nicht zur Zusatzprüfung:
        // der zweite Prüfungsteil betrifft jeden externen Prüfling, auch den
        // ohne eine einzige Zusatzprüfung.
        Route::get('/external-applicants/{externalApplicant}/mindestpunkte', [\App\Http\Controllers\ExternalApplicantController::class, 'mindestpunkte'])
            ->name('external-applicants.mindestpunkte');

        // Entscheidung 51 (01.09.2026): Abbruch bewusst feststellen/zurücknehmen.
        Route::post('/external-applicants/{externalApplicant}/abbruch', [\App\Http\Controllers\ExternalApplicantController::class, 'abbruchFeststellen'])
            ->name('external-applicants.abbruch.feststellen');
        Route::delete('/external-applicants/{externalApplicant}/abbruch', [\App\Http\Controllers\ExternalApplicantController::class, 'abbruchRueckgaengig'])
            ->name('external-applicants.abbruch.rueckgaengig');

        // Fächerwahl
        Route::get('/external-applicants/{externalApplicant}/subjects', [\App\Http\Controllers\ExternalApplicantSubjectsController::class, 'edit'])
            ->name('external-applicants.subjects.edit');
        Route::put('/external-applicants/{externalApplicant}/subjects', [\App\Http\Controllers\ExternalApplicantSubjectsController::class, 'update'])
            ->name('external-applicants.subjects.update');
        // Prüfung während der Eingabe. Die Maske schickt die aktuelle Wahl her,
        // statt die Regeln ein zweites Mal in JavaScript zu führen.
        Route::post('/external-applicants/{externalApplicant}/subjects/pruefen', [\App\Http\Controllers\ExternalApplicantSubjectsController::class, 'pruefen'])
            ->name('external-applicants.subjects.pruefen');
        
        // StudentExams erstellen
        Route::post('/external-applicants/{externalApplicant}/create-student-exams', [\App\Http\Controllers\ExternalApplicantSubjectsController::class, 'createStudentExams'])
            ->name('external-applicants.create-student-exams');
        
        // PDF-Export
        Route::get('/external-applicants/{externalApplicant}/export-pdf', [\App\Http\Controllers\ExternalApplicantController::class, 'exportPdf'])
            ->name('external-applicants.export-pdf');
    });
    
    // Hinweis (Modul 2.1, 26.08.2026): Die frühere Show-Route wurde entfernt.
    // Sie lud die View external-applicants.show, die es nie gab (500er), und war
    // nirgends verlinkt. Detailansicht ist derzeit die edit-Seite. Wenn eine
    // echte Detailseite gebaut wird, gehört sie wieder NACH alle spezifischen
    // Routes, sonst schluckt sie /external-applicants/create.
    
    // ========================================
    // Schriftliche Prüfungen (Tenant-spezifisch)
    // ========================================
    
    Route::prefix('written-exams')->name('written-exams.')->group(function () {

        // Prüfungstermine (für alle sichtbar)
        Route::get('/dates', [WrittenExamDateController::class, 'index'])->name('dates.index');

        // Tages-Zuordnung (Planer+) – MUSS vor /{writtenExamDate} stehen!
        Route::middleware(\App\Http\Middleware\CheckRole::class . ':planner,admin')->group(function () {
            Route::get('/dates/day/{day}/assign', [WrittenExamDateController::class, 'dayAssign'])
                ->name('dates.day-assign')
                ->where('day', '\d{4}-\d{2}-\d{2}');
            Route::post('/dates/day/{day}/store', [WrittenExamDateController::class, 'storeDay'])
                ->name('dates.day-store')
                ->where('day', '\d{4}-\d{2}-\d{2}');
        });

        // Show – NACH spezifischen Routen
        Route::get('/dates/{writtenExamDate}', [WrittenExamDateController::class, 'show'])->name('dates.show');

        // Keine dates.create/store/edit/update/destroy mehr: Die fuenf Routen
        // zeigten auf Methoden, die es im WrittenExamDateController nicht gibt
        // (er hat nur index, dayAssign, storeDay, show) — jeder Aufruf waere in
        // eine BadMethodCallException gelaufen. Verlinkt war keine davon.
        // Jahrestermine legt der Super-Admin ueber written-exam-slots.year-dates
        // an, die Tageszuordnung macht dates.day-assign.

        // Prüfungsmaterialien (Planer+)
        Route::middleware(CheckRole::class . ':planner,admin')->prefix('materials')->name('materials.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ExamMaterialController::class, 'index'])
                ->name('index');
            Route::get('/create', [\App\Http\Controllers\ExamMaterialController::class, 'create'])
                ->name('create');
            Route::post('/', [\App\Http\Controllers\ExamMaterialController::class, 'store'])
                ->name('store');
            Route::get('/{examMaterial}', [\App\Http\Controllers\ExamMaterialController::class, 'show'])
                ->name('show');
            Route::get('/{examMaterial}/edit', [\App\Http\Controllers\ExamMaterialController::class, 'edit'])
                ->name('edit');
            Route::put('/{examMaterial}', [\App\Http\Controllers\ExamMaterialController::class, 'update'])
                ->name('update');
            Route::delete('/{examMaterial}', [\App\Http\Controllers\ExamMaterialController::class, 'destroy'])
                ->name('destroy');
        });
        
        // Raumzuordnungen & Sitzpläne (Planer+)
        Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
            Route::get('/dates/{writtenExamYearDate}/assignments', [\App\Http\Controllers\WrittenExamAssignmentController::class, 'index'])
                ->name('assignments.index');
            // Kein create/store/edit/update/edit-all/update-all mehr
            // (30.08.2026): Die drei Masken dahinter lasen ein
            // $writtenExamDate, das der Controller nie uebergab, und
            // antworteten deshalb seit jeher mit 500. Verlinkt war keine.
            // Angelegt und geaendert werden Kurs-Raum-Zuordnungen ueber die
            // Tages-Zuordnung (dates.day-assign / dates.day-store), die
            // Hauptansicht ist der raumweite Sitzplan.
            
            // Raumweiter Sitzplan (alle Kurse eines Raums)
            Route::get('/dates/{writtenExamYearDate}/rooms/{room}/seating-plan', [\App\Http\Controllers\WrittenExamAssignmentController::class, 'showRoomPlan'])
                ->name('room-seating-plan');
            Route::post('/dates/{writtenExamYearDate}/rooms/{room}/auto-assign', [\App\Http\Controllers\WrittenExamAssignmentController::class, 'autoAssignRoom'])
                ->name('room-auto-assign');
            Route::post('/dates/{writtenExamYearDate}/rooms/{room}/clear-assignments', [\App\Http\Controllers\WrittenExamAssignmentController::class, 'clearRoomAssignments'])
                ->name('room-clear-assignments');
            Route::delete('/dates/{writtenExamYearDate}/rooms/{room}/assignments', [\App\Http\Controllers\WrittenExamAssignmentController::class, 'destroyRoomAssignments'])
                ->name('room-destroy-assignments');
            
            Route::get('/dates/{writtenExamYearDate}/assignments/{writtenExamAssignment}', [\App\Http\Controllers\WrittenExamAssignmentController::class, 'show'])
                ->name('assignments.show');
            Route::delete('/dates/{writtenExamYearDate}/assignments/{writtenExamAssignment}', [\App\Http\Controllers\WrittenExamAssignmentController::class, 'destroy'])
                ->name('assignments.destroy');

            // Sitzplatz-Zuweisungen (AJAX)
            //
            // Ohne assignments.auto-assign und assignments.clear-assignments:
            // Die beiden zeigten auf Methoden autoAssign() und
            // clearAssignments(), die es im Controller nie gab — jeder Aufruf
            // waere in eine BadMethodCallException gelaufen. Verwendet hat sie
            // nichts. Die lebenden Gegenstuecke arbeiten raumweit
            // (room-auto-assign, room-clear-assignments).
            Route::post('/dates/{writtenExamYearDate}/assignments/{writtenExamAssignment}/assign-seat', [\App\Http\Controllers\WrittenExamAssignmentController::class, 'assignSeat'])
                ->name('assignments.assign-seat');
            Route::post('/dates/{writtenExamYearDate}/assignments/{writtenExamAssignment}/remove-assignment', [\App\Http\Controllers\WrittenExamAssignmentController::class, 'removeAssignment'])
                ->name('assignments.remove-assignment');
        });
        
        // Aufsichtsverteilung (Planer+)
        Route::middleware(CheckRole::class . ':planner,admin')->group(function () {
            // Aufsichtsraster (MUSS zuerst stehen)
            Route::get('/supervisions/grids', [\App\Http\Controllers\WrittenExamSupervisionGridController::class, 'index'])
                ->name('supervisions.grids.index');
            Route::post('/supervisions/grids', [\App\Http\Controllers\WrittenExamSupervisionGridController::class, 'store'])
                ->name('supervisions.grids.store');
            Route::put('/supervisions/grids/{writtenExamSupervisionGrid}', [\App\Http\Controllers\WrittenExamSupervisionGridController::class, 'update'])
                ->name('supervisions.grids.update');
            Route::delete('/supervisions/grids/{writtenExamSupervisionGrid}', [\App\Http\Controllers\WrittenExamSupervisionGridController::class, 'destroy'])
                ->name('supervisions.grids.destroy');
            Route::post('/supervisions/grids/assign-room', [\App\Http\Controllers\WrittenExamSupervisionGridController::class, 'assignRoom'])
                ->name('supervisions.grids.assign-room');
            Route::post('/supervisions/grids/{writtenExamSupervisionGrid}/copy', [\App\Http\Controllers\WrittenExamSupervisionGridController::class, 'copy'])
                ->name('supervisions.grids.copy');
            Route::patch('/supervisions/grids/assign-time-slot/{writtenExamSupervisionTimeSlot}', [\App\Http\Controllers\WrittenExamSupervisionGridController::class, 'assignTimeSlot'])
                ->name('supervisions.grids.assign-time-slot');

            // Zeitslots für schriftliche Prüfungs-Aufsichten (MUSS vor /supervisions/{date}... stehen)
            Route::get('/supervisions/time-slots', [WrittenExamSupervisionTimeSlotController::class, 'index'])
                ->name('supervisions.time-slots.index');
            Route::post('/supervisions/time-slots', [WrittenExamSupervisionTimeSlotController::class, 'store'])
                ->name('supervisions.time-slots.store');
            Route::put('/supervisions/time-slots/{writtenExamSupervisionTimeSlot}', [WrittenExamSupervisionTimeSlotController::class, 'update'])
                ->name('supervisions.time-slots.update');
            Route::delete('/supervisions/time-slots/{writtenExamSupervisionTimeSlot}', [WrittenExamSupervisionTimeSlotController::class, 'destroy'])
                ->name('supervisions.time-slots.destroy');

            // Übersicht aller Prüfungstage für Aufsichtsplanung
            Route::get('/supervisions', [\App\Http\Controllers\SupervisionDistributionController::class, 'index'])
                ->name('supervisions.index');

            Route::get('/dates/{writtenExamYearDate}/supervisions', [\App\Http\Controllers\SupervisionDistributionController::class, 'show'])
                ->name('supervisions.show');
            Route::get('/dates/{writtenExamYearDate}/supervisions/plan', [\App\Http\Controllers\SupervisionDistributionController::class, 'plan'])
                ->name('supervisions.plan');
            Route::post('/dates/{writtenExamYearDate}/supervisions/assign-teacher', [\App\Http\Controllers\SupervisionDistributionController::class, 'assignTeacher'])
                ->name('supervisions.assign-teacher');
            Route::delete('/dates/{writtenExamYearDate}/supervisions/{supervision}/unassign', [\App\Http\Controllers\SupervisionDistributionController::class, 'unassignTeacher'])
                ->name('supervisions.unassign-teacher');
            Route::post('/dates/{writtenExamYearDate}/assignments/{assignment}/set-required-supervisors', [\App\Http\Controllers\SupervisionDistributionController::class, 'setRequiredSupervisors'])
                ->name('supervisions.set-required');
            Route::post('/dates/{writtenExamYearDate}/supervisions/auto-distribute', [\App\Http\Controllers\SupervisionDistributionController::class, 'autoDistribute'])
                ->name('supervisions.auto-distribute');
            Route::delete('/dates/{writtenExamYearDate}/supervisions/clear', [\App\Http\Controllers\SupervisionDistributionController::class, 'clear'])
                ->name('supervisions.clear');

            // Terminspezifische Zusatz-Aufsichtstypen
            Route::post('/dates/{writtenExamYearDate}/supervisions/central-types', [\App\Http\Controllers\SupervisionDistributionController::class, 'storeCentralType'])
                ->name('supervisions.central-types.store');
            Route::delete('/dates/{writtenExamYearDate}/supervisions/central-types/{centralType}', [\App\Http\Controllers\SupervisionDistributionController::class, 'destroyCentralType'])
                ->name('supervisions.central-types.destroy');
        });
        
        // Formulare (Mantelbögen etc.) – Planer+
        Route::middleware(CheckRole::class . ':planner,admin')->prefix('forms')->name('forms.')->group(function () {
            Route::get('/', [\App\Http\Controllers\WrittenExamFormController::class, 'index'])->name('index');
            Route::post('/mantelbogen', [\App\Http\Controllers\WrittenExamFormController::class, 'mantelbogen'])->name('mantelbogen');
            Route::get('/fachausschuesse/pdf', [\App\Http\Controllers\WrittenExamFormController::class, 'fachausschuessePdf'])->name('fachausschuesse.pdf');
            Route::get('/fachausschuesse/excel', [\App\Http\Controllers\WrittenExamFormController::class, 'fachausschuesseExcel'])->name('fachausschuesse.excel');
        });

        // Lehrereinsatz-Statistik
        Route::get('/teacher-deployment', [\App\Http\Controllers\TeacherDeploymentController::class, 'index'])
            ->name('teacher-deployment.index');
        Route::get('/teacher-deployment/export-pdf', [\App\Http\Controllers\TeacherDeploymentController::class, 'exportPdf'])
            ->name('teacher-deployment.export-pdf');
        Route::get('/teacher-deployment/export-excel', [\App\Http\Controllers\TeacherDeploymentController::class, 'exportExcel'])
            ->name('teacher-deployment.export-excel');

        // Exports (Planer+)
        Route::middleware(CheckRole::class . ':planner,admin')->prefix('exports')->name('exports.')->group(function () {
            // Einzelkurs-Exports
            Route::get('/dates/{writtenExamYearDate}/assignments/{writtenExamAssignment}/seating-plan', [\App\Http\Controllers\ExportController::class, 'seatingPlan'])
                ->name('seating-plan');
            Route::get('/dates/{writtenExamYearDate}/assignments/{writtenExamAssignment}/seat-cards', [\App\Http\Controllers\ExportController::class, 'seatCards'])
                ->name('seat-cards');
            Route::get('/dates/{writtenExamYearDate}/assignments/{writtenExamAssignment}/student-list', [\App\Http\Controllers\ExportController::class, 'studentList'])
                ->name('student-list');
            
            // Raumweite Exports (alle Kurse eines Raums)
            Route::get('/dates/{writtenExamYearDate}/rooms/{room}/seating-plan', [\App\Http\Controllers\ExportController::class, 'roomSeatingPlan'])
                ->name('room.seating-plan');
            Route::get('/dates/{writtenExamYearDate}/rooms/{room}/seat-cards', [\App\Http\Controllers\ExportController::class, 'roomSeatCards'])
                ->name('room.seat-cards');
            Route::get('/dates/{writtenExamYearDate}/rooms/{room}/student-list', [\App\Http\Controllers\ExportController::class, 'roomStudentList'])
                ->name('room.student-list');
            
            // Terminweite Exports (Aufsichten etc.)
            Route::get('/dates/{writtenExamYearDate}/supervision-lists', [\App\Http\Controllers\ExportController::class, 'supervisionLists'])
                ->name('supervision-lists');
            Route::get('/dates/{writtenExamYearDate}/room-overview', [\App\Http\Controllers\ExportController::class, 'roomOverview'])
                ->name('room-overview');
            // Termine-Übersicht Exports
            Route::get('/dates-overview/pdf', [\App\Http\Controllers\ExportController::class, 'datesOverviewPdf'])
                ->name('dates-overview-pdf');
            Route::get('/dates-overview/excel', [\App\Http\Controllers\ExportController::class, 'datesOverviewExcel'])
                ->name('dates-overview-excel');
            Route::get('/dates-overview/course-checklists', [\App\Http\Controllers\ExportController::class, 'courseChecklistsPdf'])
                ->name('dates-overview-course-checklists');
            Route::get('/all-rooms-seating-plans', [\App\Http\Controllers\ExportController::class, 'allRoomsSeatingPlansPdf'])
                ->name('all-rooms-seating-plans-pdf');

            // Tagesweite kombinierte Exports (alle Räume eines Tages in einem PDF)
            Route::get('/day/{day}/seating-plans', [\App\Http\Controllers\ExportController::class, 'daySeatingPlansPdf'])
                ->name('day-seating-plans-pdf')
                ->where('day', '\d{4}-\d{2}-\d{2}');
            Route::get('/day/{day}/seat-cards', [\App\Http\Controllers\ExportController::class, 'daySeatCardsPdf'])
                ->name('day-seat-cards-pdf')
                ->where('day', '\d{4}-\d{2}-\d{2}');
            Route::get('/day/{day}/student-lists', [\App\Http\Controllers\ExportController::class, 'dayStudentListsPdf'])
                ->name('day-student-lists-pdf')
                ->where('day', '\d{4}-\d{2}-\d{2}');

            // Gesamtaufsichtsplan (alle Tage)
            Route::get('/supervisions/overview/pdf', [\App\Http\Controllers\ExportController::class, 'supervisionsOverviewPdf'])
                ->name('supervisions-overview-pdf');
            Route::get('/supervisions/overview/excel', [\App\Http\Controllers\ExportController::class, 'supervisionsOverviewExcel'])
                ->name('supervisions-overview-excel');
            // Lehrkräfte-Aufsichtsliste
            Route::get('/supervisions/teacher-list/pdf', [\App\Http\Controllers\ExportController::class, 'teacherSupervisionListPdf'])
                ->name('supervisions-teacher-list-pdf');
            // Unterschriftsbögen: pro Tag + Raum eine Seite
            Route::get('/supervisions/signature-sheets/pdf', [\App\Http\Controllers\ExportController::class, 'supervisionSignatureSheetsPdf'])
                ->name('supervisions-signature-sheets-pdf');

            // --- NEU: Aufsichtsplanung (Kreuztabelle) exportieren ---
            // Wir verwenden denselben View/Export und binden das DateModel. 
            // Wichtig: Date-Routen wie oben aufgebaut.
            Route::get('/dates/{writtenExamYearDate}/supervisions/plan/pdf', [\App\Http\Controllers\ExportController::class, 'supervisionPlanPdf'])
                ->name('supervisions-plan-pdf');
            Route::get('/dates/{writtenExamYearDate}/supervisions/plan/excel', [\App\Http\Controllers\ExportController::class, 'supervisionPlanExcel'])
                ->name('supervisions-plan-excel');
        });
    });
    
    // Calendar Routes (Schulspezifisch)
    Route::resource('calendar', \App\Http\Controllers\CalendarEventController::class)
        ->names('calendar');
    Route::post('/calendar/regenerate-token', [\App\Http\Controllers\CalendarFeedController::class, 'regenerateToken'])
        ->name('calendar.regenerate-token');
    
    // Timetable Routes (Stundenplan & Klausurenplanung)
    Route::middleware(CheckRole::class . ':planner,admin')->prefix('timetable')->name('timetable.')->group(function () {
        Route::redirect('/', '/timetable/exam-schedule')->name('index');
        Route::get('/view', [\App\Http\Controllers\TimetableController::class, 'view'])->name('view');
        Route::get('/upload', [\App\Http\Controllers\TimetableController::class, 'upload'])->name('upload');
        Route::get('/preview', [\App\Http\Controllers\TimetableController::class, 'showPreview'])->name('preview.show');
        Route::post('/preview', [\App\Http\Controllers\TimetableController::class, 'preview'])->name('preview');
        Route::post('/update-preview-session', [\App\Http\Controllers\TimetableController::class, 'updatePreviewSession'])->name('update-preview-session');
        Route::post('/confirm-import', [\App\Http\Controllers\TimetableController::class, 'confirmImport'])->name('confirm-import');
        Route::post('/cancel-import', [\App\Http\Controllers\TimetableController::class, 'cancelImport'])->name('cancel-import');
        // Hier standen bis zum 28.08.2026 zwei Routen, die keine mehr sind:
        // POST /timetable/import zeigte auf TimetableController::import() —
        // eine Methode, die es im Controller nicht (mehr) gibt; der
        // Routen-Cache prüft das nicht, jeder Aufruf endete im 500er.
        // POST /timetable/import-combined hatte keine einzige aufrufende
        // View; der geführte Weg über /timetable/upload nimmt Stundenplan
        // und ASV-Datei ohnehin gemeinsam entgegen.
        Route::get('/exam-schedule', [\App\Http\Controllers\TimetableController::class, 'examSchedule'])->name('exam-schedule');
        Route::get('/teacher-availability', [\App\Http\Controllers\TimetableController::class, 'teacherAvailability'])->name('teacher-availability');
        Route::get('/class/{className}', [\App\Http\Controllers\TimetableController::class, 'classSchedule'])->name('class-schedule');
        Route::get('/teacher/{teacherCode}/schedule', [\App\Http\Controllers\TimetableController::class, 'getTeacherSchedule'])->name('teacher-schedule');
        
        // School Events Management
        Route::get('/events', [\App\Http\Controllers\TimetableController::class, 'manageEvents'])->name('events.index');
        Route::post('/events', [\App\Http\Controllers\TimetableController::class, 'storeEvent'])->name('events.store');
        Route::put('/events/{event}', [\App\Http\Controllers\TimetableController::class, 'updateEvent'])->name('events.update');
        Route::delete('/events/{event}', [\App\Http\Controllers\TimetableController::class, 'deleteEvent'])->name('events.delete');
        
        Route::get('/exam-schedule/export-pdf', [\App\Http\Controllers\TimetableController::class, 'exportExamSchedulePdf'])->name('exam-schedule.export-pdf');

        // Course Track Assignment (Schienen-Zuweisung)
        Route::post('/exam-schedule/assign-track', [\App\Http\Controllers\TimetableController::class, 'assignTrack'])->name('exam-schedule.assign-track');
        Route::delete('/exam-schedule/{entry}', [\App\Http\Controllers\TimetableController::class, 'destroyAssignment'])->name('exam-schedule.destroy-assignment');
        Route::post('/exam-schedule/{entry}/move', [\App\Http\Controllers\TimetableController::class, 'moveAssignment'])->name('exam-schedule.move-assignment');
        Route::post('/exam-schedule/{entry}/toggle-highlight', [\App\Http\Controllers\TimetableController::class, 'toggleHighlight'])->name('exam-schedule.toggle-highlight');
        Route::post('/exam-schedule/{entry}/notes', [\App\Http\Controllers\TimetableController::class, 'updateNotes'])->name('exam-schedule.update-notes');

        // Timetable Tracks (Schienen)
        Route::get('/tracks', [\App\Http\Controllers\CourseTrackController::class, 'index'])->name('tracks.index');
        Route::post('/tracks/import-asv', [\App\Http\Controllers\CourseTrackController::class, 'importFromAsv'])->name('tracks.import-asv');
        Route::get('/tracks/detect', [\App\Http\Controllers\CourseTrackController::class, 'detect'])->name('tracks.detect');
        Route::get('/tracks/create', [\App\Http\Controllers\CourseTrackController::class, 'create'])->name('tracks.create');
        Route::post('/tracks', [\App\Http\Controllers\CourseTrackController::class, 'store'])->name('tracks.store');
        Route::get('/tracks/{track}/edit', [\App\Http\Controllers\CourseTrackController::class, 'edit'])->name('tracks.edit');
        Route::put('/tracks/{track}', [\App\Http\Controllers\CourseTrackController::class, 'update'])->name('tracks.update');
        Route::post('/tracks/move-course', [\App\Http\Controllers\CourseTrackController::class, 'moveCourse'])->name('tracks.move-course');
        Route::delete('/tracks/{track}', [\App\Http\Controllers\CourseTrackController::class, 'destroy'])->name('tracks.destroy');
    });

    // Leistungsfach-Zuteilung Routes
    Route::middleware(CheckRole::class . ':planner,admin')->prefix('lk-wahlen')->name('lk-wahlen.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LkWahlController::class, 'index'])->name('index');
        Route::get('/subject-details', [\App\Http\Controllers\LkWahlController::class, 'getSubjectDetails'])->name('subject-details');
        Route::get('/create', [\App\Http\Controllers\LkWahlController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\LkWahlController::class, 'store'])->name('store');
        Route::get('/import', [\App\Http\Controllers\LkWahlController::class, 'import'])->name('import');
        Route::post('/import', [\App\Http\Controllers\LkWahlController::class, 'storeImport'])->name('store-import');
        Route::get('/export/excel', [\App\Http\Controllers\LkWahlController::class, 'export'])->name('export');
        Route::delete('/destroy-all', [\App\Http\Controllers\LkWahlController::class, 'destroyAll'])->name('destroy-all');
        Route::get('/{id}', [\App\Http\Controllers\LkWahlController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\LkWahlController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\LkWahlController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\LkWahlController::class, 'destroy'])->name('destroy');
    });

    Route::middleware(CheckRole::class . ':planner,admin')->prefix('lk-kurse')->name('lk-kurse.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LkKursController::class, 'index'])->name('index');
        Route::get('/planen', [\App\Http\Controllers\LkZuteilungController::class, 'planen'])->name('planen');
        Route::post('/berechne', [\App\Http\Controllers\LkZuteilungController::class, 'berechne'])->name('berechne');
        Route::post('/apply', [\App\Http\Controllers\LkZuteilungController::class, 'apply'])->name('apply');
        Route::post('/reset', [\App\Http\Controllers\LkKursController::class, 'reset'])->name('reset');
        Route::get('/{id}', [\App\Http\Controllers\LkKursController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\LkKursController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\LkKursController::class, 'update'])->name('update');
        Route::post('/{id}/update-zuteilung', [\App\Http\Controllers\LkKursController::class, 'updateZuteilung'])->name('update-zuteilung');
        Route::post('/{id}/add-student', [\App\Http\Controllers\LkKursController::class, 'addStudent'])->name('add-student');
        Route::post('/{id}/remove-student', [\App\Http\Controllers\LkKursController::class, 'removeStudent'])->name('remove-student');
        Route::delete('/{id}', [\App\Http\Controllers\LkKursController::class, 'destroy'])->name('destroy');
        Route::get('/export/pdf', [\App\Http\Controllers\LkKursController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export/excel', [\App\Http\Controllers\LkKursController::class, 'exportExcel'])->name('export-excel');
    });

    // W-Seminar-Wahlen Routes
    Route::middleware(CheckRole::class . ':planner,admin')->prefix('wseminar-wahlen')->name('wseminar-wahlen.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WseminarWahlController::class, 'index'])->name('index');
        Route::get('/import', [\App\Http\Controllers\WseminarWahlController::class, 'import'])->name('import');
        Route::post('/import', [\App\Http\Controllers\WseminarWahlController::class, 'storeImport'])->name('store-import');
        Route::delete('/destroy-all', [\App\Http\Controllers\WseminarWahlController::class, 'destroyAll'])->name('destroy-all');
        Route::get('/{wseminarWahl}', [\App\Http\Controllers\WseminarWahlController::class, 'edit'])->name('edit');
        Route::put('/{wseminarWahl}', [\App\Http\Controllers\WseminarWahlController::class, 'update'])->name('update');
    });

    // W-Seminar-Kurse Routes
    Route::middleware(CheckRole::class . ':planner,admin')->prefix('wseminar-kurse')->name('wseminar-kurse.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WseminarKursController::class, 'index'])->name('index');
        Route::get('/planen', [\App\Http\Controllers\WseminarZuteilungController::class, 'planen'])->name('planen');
        Route::get('/planen/export-pdf', [\App\Http\Controllers\WseminarZuteilungController::class, 'exportPdf'])->name('planen.export-pdf');
        Route::get('/planen/export-excel', [\App\Http\Controllers\WseminarZuteilungController::class, 'exportExcel'])->name('planen.export-excel');
        Route::get('/export/pdf', [\App\Http\Controllers\WseminarKursController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export/excel', [\App\Http\Controllers\WseminarKursController::class, 'exportExcel'])->name('export-excel');
        Route::post('/berechne', [\App\Http\Controllers\WseminarZuteilungController::class, 'berechne'])->name('berechne');
        Route::post('/apply', [\App\Http\Controllers\WseminarZuteilungController::class, 'apply'])->name('apply');
        Route::post('/reset', [\App\Http\Controllers\WseminarKursController::class, 'reset'])->name('reset');
        Route::get('/{id}', [\App\Http\Controllers\WseminarKursController::class, 'show'])->name('show');
    });

    // Zusatzprüfungen
    Route::middleware(CheckRole::class . ':planner,admin')
        ->prefix('zusatzpruefungen')
        ->name('zusatzpruefungen.')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\ZusatzpruefungController::class, 'index'])->name('index');
            // Prüfling = Schüler ("s<id>") oder externer Bewerber ("e<id>").
            // Die frühere Route student.written-exams ist mit der
            // Septemberrunde entfallen; die beiden anderen heißen jetzt
            // pruefling.* und werden vom Mindestpunkte-Modal gerufen.
            Route::get('/pruefling/{pruefling}/all-exams', [\App\Http\Controllers\ZusatzpruefungController::class, 'getAllExams'])->name('pruefling.all-exams');
            Route::get('/pruefling/{pruefling}/mindestpunkte-modal-data', [\App\Http\Controllers\ZusatzpruefungController::class, 'getMindestpunkteModalData'])->name('pruefling.mindestpunkte-modal-data');
            // Externe Bewerber rechnen nach § 62 — und zwar vollständig auf dem
            // Server: die Positionen 1–8 der Anlage 14 stehen im Datenmodell,
            // nicht im Browser. Der Kalkulator schickt hier nur den
            // Prüflingsschlüssel und bekommt das fertige Ergebnis zurück.
            Route::get('/pruefling/{pruefling}/mindestpunkte-62', [\App\Http\Controllers\ZusatzpruefungController::class, 'computeMindestpunkte62'])->name('pruefling.mindestpunkte-62');
            Route::patch('/pruefling/{pruefling}/exam-score', [\App\Http\Controllers\ZusatzpruefungController::class, 'storeExamScore'])->name('pruefling.exam-score.store');
            Route::post('/', [\App\Http\Controllers\ZusatzpruefungController::class, 'store'])->name('store');
            Route::delete('/{zusatzpruefung}', [\App\Http\Controllers\ZusatzpruefungController::class, 'destroy'])->name('destroy');
            Route::put('/{zusatzpruefung}/pruefer', [\App\Http\Controllers\ZusatzpruefungController::class, 'updatePruefer'])->name('pruefer.update');
            Route::post('/{zusatzpruefung}/place', [\App\Http\Controllers\ZusatzpruefungController::class, 'place'])->name('place');
            Route::delete('/{zusatzpruefung}/place', [\App\Http\Controllers\ZusatzpruefungController::class, 'unplace'])->name('unplace');
            Route::patch('/{zusatzpruefung}/absent', [\App\Http\Controllers\ZusatzpruefungController::class, 'toggleAbsent'])->name('absent.toggle');
            Route::patch('/{zusatzpruefung}/points', [\App\Http\Controllers\ZusatzpruefungController::class, 'updatePoints'])->name('points.update');
            Route::patch('/{zusatzpruefung}/lock', [\App\Http\Controllers\ZusatzpruefungController::class, 'toggleLock'])->name('lock.toggle');
            // Die Gesamtpunktzahl von Hand gibt es nur für Schüler — für
            // externe Bewerber ist sie aus den acht Prüfungen berechenbar.
            Route::patch('/student/{student}/deficit', [\App\Http\Controllers\ZusatzpruefungController::class, 'updateDeficit'])->name('student.deficit.update');
            Route::delete('/pruefling/{pruefling}/all', [\App\Http\Controllers\ZusatzpruefungController::class, 'destroyAllForPruefling'])->name('pruefling.destroy-all');

            Route::post('/days', [\App\Http\Controllers\ZusatzpruefDayController::class, 'store'])->name('days.store');
            Route::put('/days/{zusatzpruefDay}', [\App\Http\Controllers\ZusatzpruefDayController::class, 'update'])->name('days.update');
            Route::delete('/days/{zusatzpruefDay}', [\App\Http\Controllers\ZusatzpruefDayController::class, 'destroy'])->name('days.destroy');
            Route::post('/days/{zusatzpruefDay}/rooms', [\App\Http\Controllers\ZusatzpruefDayController::class, 'setRooms'])->name('days.rooms.set');
            Route::post('/days/{zusatzpruefDay}/timeslots', [\App\Http\Controllers\ZusatzpruefDayController::class, 'storeTimeslot'])->name('days.timeslots.store');
            Route::delete('/days/timeslots/{timeslot}', [\App\Http\Controllers\ZusatzpruefDayController::class, 'destroyTimeslot'])->name('days.timeslots.destroy');

            Route::post('/aufsichten', [\App\Http\Controllers\ZusatzpruefAufsichtController::class, 'store'])->name('aufsichten.store');
            Route::delete('/aufsichten/{zusatzpruefAufsicht}', [\App\Http\Controllers\ZusatzpruefAufsichtController::class, 'destroy'])->name('aufsichten.destroy');

            // Exporte
            Route::get('/export/plan-pdf', [\App\Http\Controllers\ZusatzpruefungController::class, 'exportPlanPdf'])->name('export.plan-pdf');
            Route::get('/export/student-list-pdf', [\App\Http\Controllers\ZusatzpruefungController::class, 'exportStudentListPdf'])->name('export.student-list-pdf');
            Route::get('/export/teacher-list-pdf', [\App\Http\Controllers\ZusatzpruefungController::class, 'exportTeacherListPdf'])->name('export.teacher-list-pdf');
            Route::get('/export/mindestpunkte-pdf', [\App\Http\Controllers\ZusatzpruefungController::class, 'exportMindestpunktePdf'])->name('export.mindestpunkte-pdf');
            Route::get('/export/vorbereitungsprotokoll-pdf', [\App\Http\Controllers\ZusatzpruefungController::class, 'exportVorbereitungsprotokollPdf'])->name('export.vorbereitungsprotokoll-pdf');
            Route::get('/export/niederschrift-pdf', [\App\Http\Controllers\ZusatzpruefungController::class, 'exportNiederschriftPdf'])->name('export.niederschrift-pdf');
            // Anmeldezettel und Fahrplan haben keine eigenen Routen mehr:
            // das Komplett-PDF enthält beide, ihre Blades sind längst weg.
            Route::get('/export/komplett-pdf', [\App\Http\Controllers\ZusatzpruefungController::class, 'exportKomplettPdf'])->name('export.komplett-pdf');
            Route::get('/teacher-overview', [\App\Http\Controllers\ZusatzpruefungController::class, 'teacherOverview'])->name('teacher-overview');
            Route::post('/mindestpunkte-compute', [\App\Http\Controllers\ZusatzpruefungController::class, 'computeMindestpunkte'])->name('mindestpunkte.compute');
            Route::patch('/{zusatzpruefung}/sort-order', [\App\Http\Controllers\ZusatzpruefungController::class, 'updateSortOrder'])->name('sort-order.update');
            Route::patch('/{zusatzpruefung}/anmeldung', [\App\Http\Controllers\ZusatzpruefungController::class, 'updateAnmeldung'])->name('anmeldung.update');
        });

    // ----------------------------------------------------------------
    // Abiturfeier — Ehrungen & Preise
    // ----------------------------------------------------------------
    Route::prefix('abiturfeier')->name('abiturfeier.')->group(function () {
        Route::get('/',                     [\App\Http\Controllers\AwardController::class, 'index'])->name('index');

        // Ehrungen
        Route::post('/awards',              [\App\Http\Controllers\AwardController::class, 'store'])->name('awards.store');
        Route::put('/awards/{award}',       [\App\Http\Controllers\AwardController::class, 'update'])->name('awards.update');
        Route::delete('/awards/{award}',    [\App\Http\Controllers\AwardController::class, 'destroy'])->name('awards.destroy');

        // Preisarten (Modal)
        Route::get('/award-types',          [\App\Http\Controllers\AwardController::class, 'awardTypes'])->name('award-types.index');
        Route::post('/award-types',         [\App\Http\Controllers\AwardController::class, 'storeAwardType'])->name('award-types.store');
        Route::put('/award-types/{awardType}',    [\App\Http\Controllers\AwardController::class, 'updateAwardType'])->name('award-types.update');
        Route::delete('/award-types/{awardType}', [\App\Http\Controllers\AwardController::class, 'destroyAwardType'])->name('award-types.destroy');

        // Beschreibungs-Autocomplete
        Route::get('/award-descriptions',   [\App\Http\Controllers\AwardController::class, 'awardDescriptions'])->name('award-descriptions.index');

        // PDF-Exporte
        Route::get('/pdf/gesamt',           [\App\Http\Controllers\AwardController::class, 'pdfGesamt'])->name('pdf.gesamt');
        Route::get('/pdf/schueler',         [\App\Http\Controllers\AwardController::class, 'pdfSchueler'])->name('pdf.schueler');
        Route::get('/pdf/preisart/{awardType}', [\App\Http\Controllers\AwardController::class, 'pdfPreisart'])->name('pdf.preisart');
    });
});
