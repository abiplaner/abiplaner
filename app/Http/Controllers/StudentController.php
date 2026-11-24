<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\Course;
use App\Models\StudentExam;
use App\Services\CurrentExamPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StudentsExport;

class StudentController extends Controller
{
    /**
     * Display an alphabetical listing of all students with their exam data.
     */
    public function index(Request $request)
    {
        $examPeriod = $this->getCurrentExamPeriod();

        if (!$examPeriod) {
            return $this->redirectNoExamPeriod();
        }

        $stufe = $this->gewaehlteStufe($request);

        $anzahlJeStufe = $this->anzahlJeStufe($examPeriod->id);

        // ===== Schüler mit Problemen sammeln =====
        $studentsWithProblems = [];
        
        // Schüler mit falscher Anzahl an mündlichen Prüfungen
        // Nur der aktive Abiturjahrgang (grade=13) hat zu diesem Zeitpunkt
        // Prüfungszuordnungen — Q12/Q11 werden bewusst nicht geprüft.
        // Bewusst über "grade" statt "class_name" gefiltert: class_name wird
        // beim CSV-Import nicht gesetzt (bei keinem der Jahrgänge), ein reiner
        // class_name-Filter würde daher alle NULL-class_name-Schüler treffen.
        $studentsWithWrongOralCount = DB::table('students')
            ->leftJoin('student_exams', function($join) {
                $join->on('students.id', '=', 'student_exams.student_id')
                     ->where('student_exams.exam_type', '=', 'oral');
            })
            ->where('students.exam_period_id', $examPeriod->id)
            ->where('students.grade', '13')
            ->groupBy('students.id', 'students.firstname', 'students.lastname', 'students.external_id')
            ->havingRaw('COUNT(student_exams.id) != 2')
            ->select(
                'students.id', 
                'students.external_id',
                'students.firstname', 
                'students.lastname', 
                DB::raw('COUNT(student_exams.id) as exam_count')
            )
            ->orderBy('students.lastname')
            ->orderBy('students.firstname')
            ->get();
        
        foreach ($studentsWithWrongOralCount as $student) {
            $studentsWithProblems[] = [
                'student_id' => $student->id,
                'student_name' => $student->lastname . ', ' . $student->firstname,
                'external_id' => $student->external_id,
                'type' => 'oral_count',
                'message' => $student->exam_count == 0 
                    ? 'Keine mündlichen Prüfungen zugeordnet'
                    : ($student->exam_count == 1 
                        ? 'Nur 1 mündliche Prüfung (sollte 2 sein)'
                        : $student->exam_count . ' mündliche Prüfungen (sollte 2 sein)')
            ];
        }
        
        // Schüler mit falscher Anzahl an schriftlichen Prüfungen (ebenfalls nur grade=13)
        $studentsWithWrongWrittenCount = DB::table('students')
            ->leftJoin('student_exams', function($join) {
                $join->on('students.id', '=', 'student_exams.student_id')
                     ->where('student_exams.exam_type', '=', 'written');
            })
            ->where('students.exam_period_id', $examPeriod->id)
            ->where('students.grade', '13')
            ->groupBy('students.id', 'students.firstname', 'students.lastname', 'students.external_id')
            ->havingRaw('COUNT(student_exams.id) != 3')
            ->select(
                'students.id', 
                'students.external_id',
                'students.firstname', 
                'students.lastname', 
                DB::raw('COUNT(student_exams.id) as exam_count')
            )
            ->orderBy('students.lastname')
            ->orderBy('students.firstname')
            ->get();
        
        foreach ($studentsWithWrongWrittenCount as $student) {
            $studentsWithProblems[] = [
                'student_id' => $student->id,
                'student_name' => $student->lastname . ', ' . $student->firstname,
                'external_id' => $student->external_id,
                'type' => 'written_count',
                'message' => $student->exam_count == 0 
                    ? 'Keine schriftlichen Prüfungen zugeordnet'
                    : ($student->exam_count < 3 
                        ? 'Nur ' . $student->exam_count . ' schriftliche Prüfung(en) (sollte 3 sein)'
                        : $student->exam_count . ' schriftliche Prüfungen (sollte 3 sein)')
            ];
        }
        
        // Die Prüfungszahlen gelten nur für den Abiturjahrgang. In der Q12
        // stehen Abiturfächer, aber noch keine vollständigen Prüfungen.
        if ($stufe !== '13') {
            $studentsWithProblems = [];
        }

        // Schüler der gewählten Jahrgangsstufe des aktuellen Prüfungszeitraums
        $students = $this->schuelerDerStufe($examPeriod->id, $stufe)
            ->with([
                'exams.course.subject', 
                'exams.examiner', 
                'exams.writtenExamDate.slot',
                'exams.assignment.timeSlot', 
                'exams.assignment.examRoom'
            ])
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get()
            ->map(fn($student) => $this->buildStudentExamData($student, true));

        // Alle eindeutigen Fächer für den Filter-Dropdown
        $filterSubjects = $students
            ->flatMap(fn($s) => collect($s['exams'])->pluck('subject'))
            ->unique()
            ->sort()
            ->values();

        return view('students.index', compact('examPeriod', 'students', 'studentsWithProblems', 'filterSubjects', 'stufe', 'anzahlJeStufe'));
    }

    /**
     * Jahrgangsstufe der Schülerliste aus ?stufe=; Vorgabe ist die Q13.
     */
    private function gewaehlteStufe(Request $request): string
    {
        return $request->query('stufe') === '12' ? '12' : '13';
    }

    /**
     * Schüler einer Jahrgangsstufe im Prüfungszeitraum (siehe Student::scopeDerStufe()).
     *
     * @return \Illuminate\Database\Eloquent\Builder<Student>
     */
    private function schuelerDerStufe(int $examPeriodId, string $stufe): \Illuminate\Database\Eloquent\Builder
    {
        return Student::where('exam_period_id', $examPeriodId)->derStufe($stufe);
    }

    /**
     * Schülerzahl je Stufe für die Knöpfe des Umschalters.
     *
     * @return array{13: int, 12: int}
     */
    private function anzahlJeStufe(int $examPeriodId): array
    {
        return [
            '13' => $this->schuelerDerStufe($examPeriodId, '13')->count(),
            '12' => $this->schuelerDerStufe($examPeriodId, '12')->count(),
        ];
    }

    /**
     * Display a dedicated subject overview page.
     */
    public function subjectOverview(Request $request)
    {
        $examPeriod = $this->getCurrentExamPeriod();

        if (!$examPeriod) {
            return $this->redirectNoExamPeriod();
        }

        $stufe         = $this->gewaehlteStufe($request);
        $anzahlJeStufe = $this->anzahlJeStufe($examPeriod->id);

        $subjectStats = $this->calculateSubjectStats($examPeriod, $stufe);
        $studentCount = $anzahlJeStufe[$stufe];

        // Bewusst nur Schüler (kein imPruefungszeitraum): die Zahlen darunter
        // werden gegen $studentCount gehalten, und der zählt eine
        // Jahrgangsstufe. Externe Bewerber haben keine Jahrgangsstufe und
        // gehören nicht in diese Gegenüberstellung.
        $allExams = StudentExam::whereHas('student', function ($q) use ($examPeriod, $stufe) {
            $q->where('exam_period_id', $examPeriod->id)->derStufe($stufe);
        })->get();

        $actualWritten   = $allExams->where('exam_type', 'written')->count();
        $actualOral      = $allExams->where('exam_type', 'oral')->count();
        $expectedWritten = $studentCount * 3;
        $expectedOral    = $studentCount * 2;

        $plausibility = [
            'student_count'    => $studentCount,
            'written_actual'   => $actualWritten,
            'written_expected' => $expectedWritten,
            'written_ok'       => $actualWritten === $expectedWritten,
            'written_diff'     => $actualWritten - $expectedWritten,
            'oral_actual'      => $actualOral,
            'oral_expected'    => $expectedOral,
            'oral_ok'          => $actualOral === $expectedOral,
            'oral_diff'        => $actualOral - $expectedOral,
        ];

        return view('students.subjects', compact('examPeriod', 'subjectStats', 'plausibility', 'stufe', 'anzahlJeStufe'));
    }

    /**
     * Show the form for creating a new student.
     */
    public function create()
    {
        $examPeriod = $this->getCurrentExamPeriod();

        if (!$examPeriod) {
            return $this->redirectNoExamPeriod();
        }

        // Neue Schüler bekommen keine Stufe und zählen damit zur Q13.
        $courses = Course::where('exam_period_id', $examPeriod->id)
            ->derStufe('13')
            ->with(['subject', 'teacher'])
            ->get()
            ->sortBy(fn($course) => $course->subject->name ?? '');

        return view('students.create', compact('examPeriod', 'courses'));
    }

    /**
     * Store a newly created student in storage.
     */
    public function store(Request $request)
    {
        $examPeriod = $this->getCurrentExamPeriod();

        if (!$examPeriod) {
            return $this->redirectNoExamPeriod();
        }

        $validated = $request->validate(
            $this->getStudentValidationRules(),
            $this->getStudentValidationMessages()
        );

        // Prüfen ob external_id bereits existiert
        $existingStudent = Student::where('exam_period_id', $examPeriod->id)
            ->where('external_id', $validated['external_id'])
            ->first();

        if ($existingStudent) {
            return back()
                ->withInput()
                ->withErrors(['external_id' => 'Ein Schüler mit dieser Nummer existiert bereits.']);
        }

        // Schüler erstellen
        $student = Student::create([
            'exam_period_id' => $examPeriod->id,
            'external_id' => $validated['external_id'],
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'disability_compensation_percent' => $validated['disability_compensation_percent'] ?? null,
            'disability_compensation_notes' => $validated['disability_compensation_notes'] ?? null,
        ]);

        // Die 5 Prüfungen anlegen (S1–S3 schriftlich, K1–K2 mündlich)
        foreach (['s1', 's2', 's3'] as $key) {
            $course = Course::findOrFail($validated["{$key}_course_id"]);
            StudentExam::create([
                'student_id'  => $student->id,
                'course_id'   => $course->id,
                'examiner_id' => $course->teacher_id,
                'exam_type'   => 'written',
            ]);
        }
        foreach (['k1', 'k2'] as $key) {
            $course = Course::findOrFail($validated["{$key}_course_id"]);
            StudentExam::create([
                'student_id'  => $student->id,
                'course_id'   => $course->id,
                'examiner_id' => $course->teacher_id,
                'exam_type'   => 'oral',
            ]);
        }

        return redirect()
            ->route('students.index')
            ->with('success', "Schüler {$student->full_name} wurde erfolgreich angelegt.");
    }

    /**
     * Show the form for editing the specified student.
     */
    public function edit(Student $student)
    {
        $examPeriod = $this->getCurrentExamPeriod();

        if (!$examPeriod || $student->exam_period_id !== $examPeriod->id) {
            return redirect()->route('students.index')
                ->with('error', 'Schüler nicht gefunden oder gehört nicht zum aktuellen Prüfungszeitraum.');
        }

        $student->load(['exams.course.subject', 'exams.course.teacher', 'blockedDays']);

        // Nur Kurse der eigenen Stufe — Q12 und Q13 stehen im selben Zeitraum.
        $courses = Course::where('exam_period_id', $examPeriod->id)
            ->derStufe($student->grade === '12' ? '12' : '13')
            ->with(['subject', 'teacher'])
            ->get()
            ->sortBy(fn($course) => $course->subject->name ?? '');

        return view('students.edit', compact('examPeriod', 'student', 'courses'));
    }

    /**
     * Update the specified student in storage.
     */
    public function update(Request $request, Student $student)
    {
        $examPeriod = $this->getCurrentExamPeriod();

        if (!$examPeriod || $student->exam_period_id !== $examPeriod->id) {
            return redirect()->route('students.index')
                ->with('error', 'Schüler nicht gefunden oder gehört nicht zum aktuellen Prüfungszeitraum.');
        }

        $validated = $request->validate(
            $this->getStudentValidationRules(),
            $this->getStudentValidationMessages()
        );

        // Prüfen ob external_id bereits von anderem Schüler verwendet wird
        $existingStudent = Student::where('exam_period_id', $examPeriod->id)
            ->where('external_id', $validated['external_id'])
            ->where('id', '!=', $student->id)
            ->first();

        if ($existingStudent) {
            return back()
                ->withInput()
                ->withErrors(['external_id' => 'Ein anderer Schüler verwendet bereits diese Nummer.']);
        }

        // Schüler aktualisieren
        $student->update([
            'external_id' => $validated['external_id'],
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'disability_compensation_percent' => $validated['disability_compensation_percent'] ?? null,
            'disability_compensation_notes' => $validated['disability_compensation_notes'] ?? null,
            'bestanden' => $request->boolean('bestanden'),
        ]);

        // Bestehende Prüfungen nach Typ laden
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentExam> $oralExams */
        $oralExams    = $student->exams()->where('exam_type', 'oral')->get();
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentExam> $writtenExams */
        $writtenExams = $student->exams()->where('exam_type', 'written')->get();

        // S1–S3 schriftliche Prüfungen aktualisieren
        $this->updateOrCreateWrittenExam($student, $writtenExams->get(0), Course::findOrFail($validated['s1_course_id']));
        $this->updateOrCreateWrittenExam($student, $writtenExams->get(1), Course::findOrFail($validated['s2_course_id']));
        $this->updateOrCreateWrittenExam($student, $writtenExams->get(2), Course::findOrFail($validated['s3_course_id']));

        // K1–K2 mündliche Prüfungen aktualisieren
        $this->updateOrCreateOralExam($student, $oralExams->get(0), Course::findOrFail($validated['k1_course_id']));
        $this->updateOrCreateOralExam($student, $oralExams->get(1), Course::findOrFail($validated['k2_course_id']));

        return redirect()
            ->route('students.index')
            ->with('success', "Schüler {$student->full_name} wurde erfolgreich aktualisiert.");
    }

    /**
     * Remove the specified student from storage.
     */
    public function destroy(Student $student)
    {
        $examPeriod = $this->getCurrentExamPeriod();

        if (!$examPeriod || $student->exam_period_id !== $examPeriod->id) {
            return redirect()->route('students.index')
                ->with('error', 'Schüler nicht gefunden oder gehört nicht zum aktuellen Prüfungszeitraum.');
        }

        $fullName = $student->full_name;

        // StudentExams werden durch CASCADE gelöscht
        $student->delete();

        return redirect()
            ->route('students.index')
            ->with('success', "Schüler {$fullName} wurde erfolgreich gelöscht.");
    }

    /**
     * Export students as PDF.
     */
    public function exportPdf(Request $request)
    {
        ini_set('memory_limit', '512M');

        $examPeriod = $this->getCurrentExamPeriod();

        if (!$examPeriod) {
            return $this->redirectNoExamPeriod('students.index');
        }

        $school = $this->getSchoolForUser();
        $stufe  = $this->gewaehlteStufe($request);

        $students = $this->schuelerDerStufe($examPeriod->id, $stufe)
            ->with([
                'exams.course.subject',
                'exams.examiner',
                'exams.assessor',
                'exams.writtenExamDate.slot',
                'exams.assignment.timeSlot',
                'exams.assignment.examRoom',
            ])
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get()
            ->map(fn($student) => $this->buildStudentExamData($student, false));

        $pdf = Pdf::loadView('students.pdf', compact('examPeriod', 'school', 'students', 'stufe'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('schuelerliste-q' . $stufe . '-' . $examPeriod->name . '.pdf');
    }

    /**
     * Export students as Excel.
     */
    public function exportExcel(Request $request)
    {
        $examPeriod = $this->getCurrentExamPeriod();

        if (!$examPeriod) {
            return $this->redirectNoExamPeriod('students.index');
        }

        $stufe = $this->gewaehlteStufe($request);

        return Excel::download(
            new StudentsExport($examPeriod->id, $this->schuelerDerStufe($examPeriod->id, $stufe)->pluck('id')->all()),
            'schuelerliste-q' . $stufe . '-' . $examPeriod->name . '.xlsx'
        );
    }
    
    /**
     * Export subject statistics as PDF.
     */
    public function exportSubjectsPdf(Request $request)
    {
        $examPeriod = $this->getCurrentExamPeriod();

        if (!$examPeriod) {
            return $this->redirectNoExamPeriod('students.index');
        }

        $school       = $this->getSchoolForUser();
        $stufe        = $this->gewaehlteStufe($request);
        $subjectStats = $this->calculateSubjectStats($examPeriod, $stufe);

        $pdf = Pdf::loadView('students.subjects-pdf', compact('examPeriod', 'school', 'subjectStats', 'stufe'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('faecheruebersicht-q' . $stufe . '-' . $examPeriod->name . '.pdf');
    }
    
    /**
     * Export subject statistics as Excel.
     */
    public function exportSubjectsExcel(Request $request)
    {
        $examPeriod = $this->getCurrentExamPeriod();

        if (!$examPeriod) {
            return $this->redirectNoExamPeriod('students.index');
        }

        $stufe = $this->gewaehlteStufe($request);

        return Excel::download(
            new \App\Exports\SubjectStatsExport($examPeriod->id, $stufe),
            'faecheruebersicht-q' . $stufe . '-' . $examPeriod->name . '.xlsx'
        );
    }

    // =========================================================================
    // Private Hilfsmethoden
    // =========================================================================

    /**
     * Liefert die aktuell gewählte Prüfungsperiode oder null.
     */
    private function getCurrentExamPeriod(): ?ExamPeriod
    {
        return app(CurrentExamPeriodService::class)->current();
    }

    /**
     * Erzeugt ein Redirect-Response wenn kein Prüfungszeitraum gefunden wurde.
     */
    private function redirectNoExamPeriod(string $route = 'dashboard'): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route($route)->with('error', 'Kein Prüfungszeitraum gefunden.');
    }

    /**
     * Liefert die Schule des aktuell eingeloggten Benutzers (zentral → Tenant-Fallback).
     */
    private function getSchoolForUser(): ?\App\Models\School
    {
        $user   = auth()->user();
        $school = $user->school;

        if (!$school) {
            $school = app(\App\Services\AktuelleSchule::class)->schule();
        }

        if (!$school && $user->school_id) {
            $school = \App\Models\School::find($user->school_id);
        }

        return $school;
    }

    /**
     * Validierungsregeln für Schülerdaten (store & update).
     */
    private function getStudentValidationRules(): array
    {
        return [
            'external_id'                    => 'required|string|max:50',
            'firstname'                      => 'required|string|max:100',
            'lastname'                       => 'required|string|max:100',
            'disability_compensation_percent' => 'nullable|integer|min:0|max:100',
            'disability_compensation_notes'  => 'nullable|string',
            'bestanden'                      => 'nullable|boolean',
            's1_course_id'                   => 'required|exists:courses,id',
            's2_course_id'                   => 'required|exists:courses,id|different:s1_course_id',
            's3_course_id'                   => 'required|exists:courses,id|different:s1_course_id,s2_course_id',
            'k1_course_id'                   => 'required|exists:courses,id|different:s1_course_id,s2_course_id,s3_course_id',
            'k2_course_id'                   => 'required|exists:courses,id|different:s1_course_id,s2_course_id,s3_course_id,k1_course_id',
        ];
    }

    /**
     * Validierungsfehlermeldungen für Schülerdaten (store & update).
     */
    private function getStudentValidationMessages(): array
    {
        return [
            'external_id.required'                    => 'Die Schüler-Nummer ist erforderlich.',
            'firstname.required'                      => 'Der Vorname ist erforderlich.',
            'lastname.required'                       => 'Der Nachname ist erforderlich.',
            'disability_compensation_percent.integer' => 'Der Nachteilsausgleich muss eine Zahl sein.',
            'disability_compensation_percent.min'     => 'Der Nachteilsausgleich muss mindestens 0% betragen.',
            'disability_compensation_percent.max'     => 'Der Nachteilsausgleich darf maximal 100% betragen.',
            's1_course_id.required'                  => 'Bitte wählen Sie einen S1-Kurs aus.',
            's2_course_id.required'                  => 'Bitte wählen Sie einen S2-Kurs aus.',
            's2_course_id.different'                 => 'S2 muss sich von S1 unterscheiden.',
            's3_course_id.required'                  => 'Bitte wählen Sie einen S3-Kurs aus.',
            's3_course_id.different'                 => 'S3 muss sich von S1 und S2 unterscheiden.',
            'k1_course_id.required'                  => 'Bitte wählen Sie einen K1-Kurs aus.',
            'k1_course_id.different'                 => 'K1 muss sich von den schriftlichen Fächern unterscheiden.',
            'k2_course_id.required'                  => 'Bitte wählen Sie einen K2-Kurs aus.',
            'k2_course_id.different'                 => 'Alle 5 Prüfungsfächer müssen unterschiedlich sein.',
        ];
    }

    /**
     * Baut das Prüfungs-Daten-Array für einen Schüler auf.
     *
     * @param  \App\Models\Student  $student        Schüler mit geladenen Relationen
     * @param  bool                 $includeEndTime Ob Endzeit und NTA-Basiszeit berechnet werden
     * @return array{id: int, external_id: string, full_name: string, firstname: string, lastname: string, disability_compensation_percent: int|null, exams: array}
     */
    private function buildStudentExamData(Student $student, bool $includeEndTime = false): array
    {
        $allExams = collect();

        foreach ($student->exams as $exam) {
            $examData = [
                'type'        => $exam->exam_type ?? 'oral',
                'subject'     => $exam->course->subject->name ?? 'Unbekannt',
                'course_code' => $exam->course->course_code ?? '',
                'examiner'    => $exam->examiner ? $exam->examiner->shortcode : 'n/a',
                'is_assigned' => $exam->isAssigned(),
            ];

            if ($exam->exam_type === 'written') {
                $yearDate = $exam->writtenExamDate; // WrittenExamYearDate
                if ($yearDate) {
                    $slot           = $yearDate->slot;
                    $seatAssignment = $exam->getWrittenExamSeatAssignment();
                    $startTime      = $slot->start_time
                        ? \Carbon\Carbon::parse($slot->start_time)
                        : null;

                    $examData['date']           = $yearDate->exam_date;
                    $examData['date_formatted'] = $yearDate->exam_date->format('d.m.Y');
                    $examData['time']           = $startTime ? $startTime->format('H:i') : '-';

                    if ($includeEndTime && $slot && $slot->end_time) {
                        $compensation          = $student->disability_compensation_percent ?? 0;
                        $endTimeBase           = \Carbon\Carbon::parse($slot->end_time);
                        $endTime               = $compensation
                            ? $slot->getEndTimeWithCompensation($compensation)
                            : $endTimeBase;

                        $examData['end_time']      = $endTime->format('H:i');
                        $examData['end_time_base'] = $compensation ? $endTimeBase->format('H:i') : null;
                    } else {
                        $examData['end_time']      = '-';
                        $examData['end_time_base'] = null;
                    }

                    if ($seatAssignment && $seatAssignment->assignment) {
                        $examData['room'] = $seatAssignment->assignment->room
                            ? $seatAssignment->assignment->room->room_number : '-';
                        // Nicht seat->seat_number: diese Spalte gibt es nicht,
                        // der Platz stand hier immer leer.
                        $examData['seat'] = $seatAssignment->assignment
                            ->platznummern()[$seatAssignment->seat_id] ?? null;
                    } else {
                        $examData['room'] = '-';
                        $examData['seat'] = null;
                    }

                    $examData['sort_date'] = $yearDate->exam_date->format('Y-m-d') . ' ' .
                        ($startTime ? $startTime->format('H:i') : '00:00');
                } else {
                    $examData += [
                        'date'           => null,
                        'date_formatted' => 'Nicht geplant',
                        'time'           => '-',
                        'end_time'       => '-',
                        'end_time_base'  => null,
                        'room'           => '-',
                        'seat'           => null,
                        'sort_date'      => '9999-99-99 99:99',
                    ];
                }
            } else {
                // Mündliche Prüfung (Kolloquium)
                $timeSlot               = $exam->assignment->timeSlot ?? null;
                $examData['date']           = $timeSlot?->date;
                $examData['date_formatted'] = $timeSlot ? $timeSlot->date->format('d.m.Y') : 'Nicht geplant';
                $examData['time']           = $timeSlot ? $timeSlot->exam_start_time : '-';
                $examData['room']           = ($exam->assignment && $exam->assignment->examRoom)
                    ? $exam->assignment->examRoom->room_number : '-';
                $examData['seat']           = null;
                $examData['sort_date']      = $timeSlot
                    ? $timeSlot->date->format('Y-m-d') . ' ' . $timeSlot->exam_start_time
                    : '9999-99-99 99:99';
            }

            $allExams->push($examData);
        }

        // Schriftliche vor mündlichen, dann chronologisch
        $allExams = $allExams->sortBy([
            fn($a, $b) => ($a['type'] === 'written' ? 0 : 1) - ($b['type'] === 'written' ? 0 : 1),
            fn($a, $b) => strcmp($a['sort_date'], $b['sort_date']),
        ])->values();

        return [
            'id'                              => $student->id,
            'external_id'                     => $student->external_id,
            'full_name'                       => $student->full_name,
            'firstname'                       => $student->firstname,
            'lastname'                        => $student->lastname,
            'disability_compensation_percent' => $student->disability_compensation_percent,
            'exams'                           => $allExams->toArray(),
        ];
    }

    /**
     * Berechnet die Fächerstatistik für einen Prüfungszeitraum.
     *
     * @return \Illuminate\Support\Collection
     */
    private function calculateSubjectStats(ExamPeriod $examPeriod, string $stufe = '13'): \Illuminate\Support\Collection
    {
        return app(\App\Services\SubjectStatsService::class)->calculate($examPeriod, $stufe);
    }

    /**
     * Aktualisiert eine schriftliche Prüfung oder erstellt sie neu.
     * Löscht bei Kurswechsel die verknüpfte Sitzplatzzuweisung.
     */
    private function updateOrCreateWrittenExam(Student $student, ?StudentExam $existing, Course $course): void
    {
        if ($existing) {
            if ($existing->course_id != $course->id && $existing->written_exam_year_date_id) {
                \App\Models\WrittenExamSeatAssignment::where('student_id', $student->id)
                    ->whereHas('assignment', function ($query) use ($existing) {
                        $query->where('written_exam_year_date_id', $existing->written_exam_year_date_id)
                              ->where('course_id', $existing->course_id);
                    })
                    ->delete();
            }
            $existing->update(['course_id' => $course->id, 'examiner_id' => $course->teacher_id]);
        } else {
            StudentExam::create([
                'student_id'  => $student->id,
                'course_id'   => $course->id,
                'examiner_id' => $course->teacher_id,
                'exam_type'   => 'written',
            ]);
        }
    }

    /**
     * Aktualisiert eine mündliche Prüfung oder erstellt sie neu.
     * Löscht bei Kurswechsel das bestehende ExamAssignment.
     */
    private function updateOrCreateOralExam(Student $student, ?StudentExam $existing, Course $course): void
    {
        if ($existing) {
            if ($existing->course_id != $course->id && $existing->assignment) {
                $existing->assignment->delete();
            }
            $existing->update(['course_id' => $course->id, 'examiner_id' => $course->teacher_id]);
        } else {
            StudentExam::create([
                'student_id'  => $student->id,
                'course_id'   => $course->id,
                'examiner_id' => $course->teacher_id,
                'exam_type'   => 'oral',
            ]);
        }
    }

    /**
     * Gesperrten Tag für einen Schüler anlegen (AJAX).
     */
    public function storeBlockedDay(Request $request, Student $student): \Illuminate\Http\JsonResponse
    {
        $examPeriod = $this->getCurrentExamPeriod();
        if (!$examPeriod || $student->exam_period_id !== $examPeriod->id) {
            return response()->json(['success' => false, 'message' => 'Schüler nicht gefunden.'], 404);
        }

        $validated = $request->validate([
            'date'   => 'required|date_format:Y-m-d',
            'reason' => 'nullable|string|max:200',
        ]);

        // Doppeleintrag verhindern
        $existing = \App\Models\StudentBlockedDay::where('student_id', $student->id)
            ->where('date', $validated['date'])
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Dieser Tag ist bereits gesperrt.'], 422);
        }

        $blocked = \App\Models\StudentBlockedDay::create([
            'student_id' => $student->id,
            'date'       => $validated['date'],
            'reason'     => $validated['reason'] ?? null,
        ]);

        return response()->json(['success' => true, 'blocked_day' => [
            'id'     => $blocked->id,
            'date'   => $blocked->date->format('Y-m-d'),
            'reason' => $blocked->reason,
        ]]);
    }

    /**
     * Gesperrten Tag löschen (AJAX).
     */
    public function destroyBlockedDay(Student $student, \App\Models\StudentBlockedDay $blockedDay): \Illuminate\Http\JsonResponse
    {
        $examPeriod = $this->getCurrentExamPeriod();
        if (!$examPeriod || $student->exam_period_id !== $examPeriod->id || $blockedDay->student_id !== $student->id) {
            return response()->json(['success' => false, 'message' => 'Nicht gefunden.'], 404);
        }

        $blockedDay->delete();
        return response()->json(['success' => true]);
    }
}
