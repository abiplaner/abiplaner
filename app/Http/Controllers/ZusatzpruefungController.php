<?php

namespace App\Http\Controllers;

use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use App\Models\Room;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Teacher;
use App\Models\ZusatzpruefAssignment;
use App\Models\ZusatzpruefDay;
use App\Models\ZusatzpruefPruefer;
use App\Models\Zusatzpruefung;
use App\Models\ZusatzpruefStudentDeficit;
use App\Models\ZusatzpruefExamScore;
use App\Services\SportNotenRegel;
use App\Services\CurrentExamPeriodService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZusatzpruefungController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // index
    // ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $examPeriod = app(CurrentExamPeriodService::class)->current();

        if (!$examPeriod) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Prüfungszeitraum vorhanden.');
        }

        // Verwaiste "placed"-Einträge ohne Assignment reparieren
        Zusatzpruefung::where('status', 'placed')
            ->where('exam_period_id', $examPeriod->id)
            ->whereDoesntHave('assignment')
            ->update(['status' => 'pending']);

        // Placed-Prüfungen, deren Startzeit nicht mehr als Timeslot konfiguriert ist → zurück in Sidebar
        $timeslotOrphans = Zusatzpruefung::where('status', 'placed')
            ->where('exam_period_id', $examPeriod->id)
            ->whereHas('assignment', function ($q) {
                $q->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('zusatzpruef_day_timeslots')
                        ->whereColumn('zusatzpruef_day_timeslots.zusatzpruef_day_id', 'zusatzpruef_assignments.zusatzpruef_day_id')
                        ->whereColumn('zusatzpruef_day_timeslots.start_time', 'zusatzpruef_assignments.start_time');
                });
            })
            ->with('assignment')
            ->get();

        foreach ($timeslotOrphans as $orphan) {
            $orphan->assignment()->delete();
            $orphan->update(['status' => 'pending']);
        }

        // Ungeplante Prüfungen (Sidebar) – alphabetisch nach Prüfling, dann
        // sort_order. Kein Join auf students mehr: ein externer Bewerber hat
        // keinen Schülerdatensatz und fiele aus einem INNER JOIN heraus.
        // Sortiert wird deshalb in PHP über den Namen, den die Prüfung für
        // beide Fälle liefert.
        // Wegen Abbruch deaktivierte Zusatzpruefungen (absent=true durch
        // ExterneAbbruchService gesetzt) gehoeren nicht mehr auf das Brett —
        // weder in die Seitenleiste noch aufs Tagesraster. Ihr Termin ist
        // frei, sie selbst erledigt.
        $pending = Zusatzpruefung::where('status', 'pending')
            ->where('exam_period_id', $examPeriod->id)
            ->where('absent', false)
            ->with([
                'student',
                'studentExam.course.subject',
                'studentExam.examiner',
                'studentExam.secondCorrector',
                'studentExam.externalExamSubject.subject',
                'studentExam.externalExamSubject.externalApplicant',
                // Die Praxisnote hängt an der Prüfung, nicht an der
                // Zusatzprüfung — ohne sie rechnet getPointsDelta() im Sport
                // nicht, und ohne Eager Loading eine Abfrage je Zeile.
                'examScore',
                'pruefer.teacher',
            ])
            ->get()
            ->sortBy(fn (Zusatzpruefung $p): array => [
                $p->prueflingsName(),
                $p->sort_order ?? 0,
            ])
            ->values();

        // Platzierte Prüfungen, gruppiert nach Tag
        $placed = Zusatzpruefung::where('status', 'placed')
            ->where('exam_period_id', $examPeriod->id)
            ->where('absent', false)
            ->with([
                'student',
                'studentExam.course.subject',
                'studentExam.externalExamSubject.subject',
                'studentExam.externalExamSubject.externalApplicant',
                'assignment.day',
                'assignment.dayRoom.examRoom',
                'assignment.dayRoom.prepRoom',
                'examScore',
                'pruefer.teacher',
            ])
            ->get()
            ->groupBy('assignment.zusatzpruef_day_id');

        // Praxisnoten für Sport-ZP-Fälle (selten) nachladen: written_points liegt
        // zwar direkt in zusatzpruefungen, die praktische Note aber weiterhin in
        // zusatzpruef_exam_scores (siehe Docblock ZusatzpruefExamScore). Wird für
        // die korrekte Beitragsberechnung in der zpExams-Übersicht benötigt.
        $allPruefungenForScores = $pending->merge($placed->flatten()->values());
        $sportZpStudentExamIds  = $allPruefungenForScores
            ->filter(fn (Zusatzpruefung $p) => $p->studentExam->istSport())
            ->pluck('student_exam_id');
        $sportZpPracticalGrades = $sportZpStudentExamIds->isEmpty()
            ? collect()
            : ZusatzpruefExamScore::whereIn('student_exam_id', $sportZpStudentExamIds)
                ->get()
                ->keyBy('student_exam_id');

        // Konfigurierte Tage mit Raum-Paaren
        $days = ZusatzpruefDay::where('exam_period_id', $examPeriod->id)
            ->with(['dayRooms.examRoom', 'dayRooms.prepRoom', 'timeslots.aufsichten.teacher'])
            ->orderBy('date')
            ->get();

        // Schüler für Auswahlfeld – nur Q13
        $students = Student::where('exam_period_id', $examPeriod->id)
            ->where('grade', '13')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        // Das Auswahlfeld führt Schüler und externe Bewerber gemeinsam. Beide
        // tragen den Prüflingsschlüssel als Wert ("s<id>" bzw. "e<id>"); die
        // Oberfläche kennt nur noch ihn, der Server löst ihn über
        // StudentExam::desPrueflings() wieder auf. Eine numerische ID taugt
        // hier nicht mehr — die beiden Nummernkreise überschneiden sich.
        $prueflinge = $students
            ->map(fn (Student $s): array => [
                'id'     => 's' . $s->id,
                'name'   => $s->full_name,
                'extern' => false,
            ])
            ->concat(
                ExternalApplicant::where('exam_period_id', $examPeriod->id)
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get()
                    ->map(fn (ExternalApplicant $b): array => [
                        'id'     => 'e' . $b->id,
                        'name'   => $b->full_name,
                        'extern' => true,
                    ])
            )
            ->values();

        // Lehrer für Prüfer-Dropdowns (Aufsichten-Modal braucht Eloquent-Collection)
        $teachers = Teacher::active()->orderBy('shortcode')->get();

        // Lehrer mit Fakultas-IDs für gefilterte Prüfer-Auswahl im Drawer
        // (analog CourseController – mit Äquivalenz-Mapping)
        $subjectEquivalences = [
            'WR'  => ['WR', 'Ww'],
            'Smw' => ['Smw', 'Sm', 'Sw'],
            'Fs'  => ['Fs', 'F'],
        ];
        $allSubjectsByCode = \App\Models\Subject::pluck('id', 'code')->toArray();
        /** @var array<string, list<int>> $fakultasToExtraIds */
        $fakultasToExtraIds = [];
        foreach ($subjectEquivalences as $courseCode => $fakultasCodes) {
            $courseSubjectId = $allSubjectsByCode[$courseCode] ?? null;
            if (!$courseSubjectId) {
                continue;
            }
            foreach ($fakultasCodes as $fCode) {
                $fakultasToExtraIds[$fCode][] = (int) $courseSubjectId;
            }
        }
        $teachersData = Teacher::active()->with('subjects')->orderBy('shortcode')->get()
            ->map(function (\App\Models\Teacher $t) use ($fakultasToExtraIds): array {
                $subjectIds = $t->subjects->pluck('id')->map(fn($id) => (int) $id)->toArray();
                foreach ($t->subjects as $subj) {
                    $extras     = $fakultasToExtraIds[$subj->code] ?? [];
                    $subjectIds = array_values(array_unique(array_merge($subjectIds, $extras)));
                }
                return [
                    'id'          => $t->id,
                    'shortcode'   => $t->shortcode,
                    'lastname'    => $t->lastname,
                    'subject_ids' => $subjectIds,
                ];
            })->values();

        // Alle aktiven Räume für Tage-Modal
        $rooms = Room::active()->orderBy('room_number')->get();

        // Schüler-Defizite für diesen Prüfungszeitraum, nach Prüflingsschlüssel
        // abgelegt. Externe Bewerber kommen darin nicht vor: für sie ist die
        // Gesamtqualifikation aus den acht Prüfungen berechenbar
        // (ExterneGesamtqualifikationService), es gibt nichts von Hand zu
        // führen (Entscheidung vom 26.08.2026).
        $studentIds = $students->pluck('id');
        $deficits = ZusatzpruefStudentDeficit::where('exam_period_id', $examPeriod->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy(fn (ZusatzpruefStudentDeficit $d): string => 's' . $d->student_id);

        // ── Aufsichten-Coverage ─────────────────────────────────────────
        // Pro platzierter Prüfung: gibt es für (Tag, Timeslot, Vorb.-Raum)
        // mindestens eine Aufsicht? Zählt fehlende Kombinationen.
        $dayTimeslotMap = []; // day_id → Collection<start_time, ZusatzpruefDayTimeslot>
        $dayRoomsMap    = []; // day_id → Collection<ZusatzpruefDayRoom>
        foreach ($days as $_d) {
            $dayTimeslotMap[$_d->id] = $_d->timeslots->keyBy('start_time');
            $dayRoomsMap[$_d->id]    = $_d->dayRooms;
        }

        $needed = []; // "day:ts:prep" → [day_id, ts_id, prep_room_id]
        foreach ($placed->flatten() as $_p) {
            /** @var \App\Models\Zusatzpruefung $_p */
            $asgn = $_p->assignment;
            if (!$asgn) {
                continue;
            }
            $dayId   = $asgn->zusatzpruef_day_id;
            $ts      = ($dayTimeslotMap[$dayId] ?? collect())->get($asgn->start_time);
            $prepId  = $asgn->dayRoom->prep_room_id;
            if (!$ts || !$prepId) {
                continue;
            }
            $needed[$dayId . ':' . $ts->id . ':' . $prepId] = [
                'day_id'       => $dayId,
                'ts_id'        => $ts->id,
                'prep_room_id' => $prepId,
            ];
        }

        $uncoveredCount = 0;
        $uncoveredKeys  = []; // "day_id:ts_id" → Schlüssel für Blade-Abfrage
        $slotsWithExams = []; // "day_id:ts_id:prep_room_id" → true (alle Slots mit Prüfung)

        foreach ($needed as $key => $combo) {
            $slotsWithExams[$key] = true;

            $roomIds = ($dayRoomsMap[$combo['day_id']] ?? collect())
                ->where('prep_room_id', $combo['prep_room_id'])
                ->pluck('id')
                ->toArray();

            $ts = ($dayTimeslotMap[$combo['day_id']] ?? collect())->firstWhere('id', $combo['ts_id']);

            $covered = $ts !== null
                && $ts->aufsichten->whereIn('zusatzpruef_day_room_id', $roomIds)->isNotEmpty();

            if (!$covered) {
                $uncoveredCount++;
                $uncoveredKeys[$combo['day_id'] . ':' . $combo['ts_id']] = true;
            }
        }

        // Überflüssige Aufsichten zählen:
        // Aufsicht eingetragen, aber kein Prüfling in diesem Slot
        $orphanedCount = 0;
        foreach ($days as $_day) {
            $dayRoomMap = $_day->dayRooms->keyBy('id');
            foreach ($_day->timeslots as $_ts) {
                foreach ($_ts->aufsichten as $_auf) {
                    $dr     = $dayRoomMap->get($_auf->zusatzpruef_day_room_id);
                    $prepId = $dr?->prep_room_id;
                    if (!$prepId) {
                        continue;
                    }
                    if (!isset($slotsWithExams[$_day->id . ':' . $_ts->id . ':' . $prepId])) {
                        $orphanedCount++;
                    }
                }
            }
        }

        // ── Daten für Mindestpunkte-Kalkulator ─────────────────────────
        // Alle Abitur-Prüfungsfächer + eingetragene Noten der Prüflinge mit
        // Zusatzprüfungen (für das Modalfenster im Kalkulator). Abgelegt nach
        // Prüflingsschlüssel, weil student_id für externe Bewerber null ist.
        $alleZp = $pending->merge($placed->flatten());

        $zpStudentIds = $alleZp->pluck('student_id')->filter()->unique()->values();
        $zpBewerberIds = $alleZp
            ->map(fn (Zusatzpruefung $p) => $p->studentExam->externalExamSubject?->external_applicant_id)
            ->filter()
            ->unique()
            ->values();

        $allStudentExamsMap = ($zpStudentIds->isEmpty() && $zpBewerberIds->isEmpty())
            ? collect()
            : StudentExam::where(function ($q) use ($zpStudentIds, $zpBewerberIds) {
                    $q->whereIn('student_id', $zpStudentIds)
                      ->orWhereHas(
                          'externalExamSubject',
                          fn ($e) => $e->whereIn('external_applicant_id', $zpBewerberIds)
                      );
                })
                ->with(['course.subject', 'externalExamSubject.subject', 'externalExamSubject.externalApplicant'])
                ->get()
                ->groupBy(fn (StudentExam $e): string => $e->prueflingsSchluessel());

        $allExamScoresMap = $allStudentExamsMap->isEmpty()
            ? collect()
            : ZusatzpruefExamScore::where('exam_period_id', $examPeriod->id)
                ->whereIn('student_exam_id', $allStudentExamsMap->flatten()->pluck('id'))
                ->get()
                ->keyBy('student_exam_id');

        // ── Conflict map für client-seitige Drag-Vorab-Prüfung ────────
        // Für jede Prüfung: Prüflingsschlüssel, teacher_ids, Dauern
        // Für platzierte Prüfungen: day_id, start_time, end_time
        $conflictMapPruefungen = [];
        $conflictMapPlaced     = [];

        foreach ($pending as $_cp) {
            /** @var \App\Models\Zusatzpruefung $_cp */
            $conflictMapPruefungen[$_cp->id] = [
                'pruefling'   => $_cp->prueflingsSchluessel(),
                'teacher_ids' => $_cp->pruefer->pluck('teacher_id')->values()->toArray(),
                'prep_min'    => $_cp->kompensierteDauer(20),
                'exam_min'    => 20,
                'sort_order'  => $_cp->sort_order,
            ];
        }

        foreach ($placed->flatten() as $_cp) {
            /** @var \App\Models\Zusatzpruefung $_cp */
            $conflictMapPruefungen[$_cp->id] = [
                'pruefling'   => $_cp->prueflingsSchluessel(),
                'teacher_ids' => $_cp->pruefer->pluck('teacher_id')->values()->toArray(),
                'prep_min'    => $_cp->kompensierteDauer(20),
                'exam_min'    => 20,
                'sort_order'  => $_cp->sort_order,
            ];
            if ($_cp->assignment !== null && ! $_cp->absent) {
                $_a = $_cp->assignment;
                $_startC = Carbon::createFromTimeString((string) $_a->start_time);
                $_endC   = $_startC->copy()->addMinutes($_a->prep_duration_minutes + $_a->exam_duration_minutes);
                $conflictMapPlaced[] = [
                    'pruefung_id' => $_cp->id,
                    'pruefling'   => $_cp->prueflingsSchluessel(),
                    'day_id'      => $_a->zusatzpruef_day_id,
                    'day_date'    => ($days->firstWhere('id', $_a->zusatzpruef_day_id)?->date)?->toDateString() ?? '',
                    'start_time'  => $_startC->format('H:i'),
                    'end_time'    => $_endC->format('H:i'),
                    'teacher_ids' => $_cp->pruefer->pluck('teacher_id')->values()->toArray(),
                    'sort_order'  => $_cp->sort_order,
                ];
            }
        }

        $conflictMapData = [
            'pruefungen' => $conflictMapPruefungen,
            'placed'     => $conflictMapPlaced,
        ];

        return view('zusatzpruefungen.index', compact(
            'examPeriod', 'pending', 'placed', 'days',
            'students', 'prueflinge', 'teachers', 'teachersData', 'rooms', 'deficits',
            'uncoveredCount', 'uncoveredKeys', 'slotsWithExams', 'orphanedCount',
            'allStudentExamsMap', 'allExamScoresMap', 'conflictMapData',
            'sportZpPracticalGrades',
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // getAllExams (AJAX) — alle Abiturprüfungsfächer eines Prüflings + Status
    //
    // Angesprochen über den Prüflingsschlüssel ("s<id>" bzw. "e<id>") statt
    // über eine students-ID: externe Bewerber haben keinen Schülerdatensatz.
    // ─────────────────────────────────────────────────────────────────

    public function getAllExams(string $pruefling)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        $exams = StudentExam::desPrueflings($pruefling)
            ->with([
                'course.subject', 'examiner', 'secondCorrector', 'zusatzpruefung',
                'externalExamSubject.subject', 'externalExamSubject.externalApplicant',
            ])
            ->get();

        $savedScores = ZusatzpruefExamScore::whereIn('student_exam_id', $exams->pluck('id'))
            ->where('exam_period_id', $examPeriod->id)
            ->get()
            ->keyBy('student_exam_id');

        return response()->json(
            $exams->sortBy(fn(StudentExam $e) => ($e->exam_type === 'written' ? '0' : '1') . ($e->getSubjectCode() ?? ''))
                  ->values()
                  ->map(fn(StudentExam $e) => [
                'id'               => $e->id,
                'subject'          => $e->getSubjectName() ?? '–',
                'subject_code'     => $e->getSubjectCode() ?? '',
                'course_code'      => $e->getSubjectLabel(),
                'level'            => $e->getLevel() ?? 'grundlegend',
                'exam_type'        => $e->exam_type,
                'examiner'         => $e->examiner->shortcode ?? '–',
                'second_corrector' => $e->secondCorrector?->shortcode,
                'is_leistungsfach' => $e->istLeistungsfach(),
                'is_sport'         => $e->istSport(),
                'can_zp'           => $e->exam_type === 'written',
                'has_zp'           => $e->zusatzpruefung !== null,
                'pruefung_id'      => $e->zusatzpruefung instanceof Zusatzpruefung ? $e->zusatzpruefung->id : null,
                'sort_order'       => $e->zusatzpruefung instanceof Zusatzpruefung ? $e->zusatzpruefung->sort_order : 0,
                'improvement_only' => $e->zusatzpruefung instanceof Zusatzpruefung ? $e->zusatzpruefung->improvement_only : false,
                'improvement_points_needed' => $e->zusatzpruefung instanceof Zusatzpruefung
                    ? $e->zusatzpruefung->improvement_points_needed
                    : null,
                'saved_score'      => $e->zusatzpruefung instanceof Zusatzpruefung
                    ? $e->zusatzpruefung->written_points
                    : ($savedScores[$e->id]->score ?? null),
                'target_points'    => $e->zusatzpruefung instanceof Zusatzpruefung
                    ? $e->zusatzpruefung->target_points
                    : null,
                'practical_grade'  => $savedScores[$e->id]->practical_grade ?? null,
            ])
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // getMindestpunkteModalData — frische Modal-Daten nach jeder Änderung
    //
    // Gleiche Struktur wie $modalData in index.blade.php, als JSON statt
    // serverseitig eingebettet: das Mindestpunkte-Modal lädt sich damit nach
    // jeder Punkte-Änderung komplett neu, statt jeden reaktiven Pfad einzeln
    // synchron zu halten. Die Fächer-Abbildung kommt aus dem
    // AbiturExamDataService — dieselbe, die die Niederschrift benutzt.
    // ─────────────────────────────────────────────────────────────────

    public function getMindestpunkteModalData(string $pruefling)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        $pruefungen = Zusatzpruefung::whereHas(
                'studentExam',
                fn ($q) => $q->desPrueflings($pruefling)
            )
            ->where('exam_period_id', $examPeriod->id)
            ->with([
                'studentExam.course.subject',
                'studentExam.externalExamSubject.subject',
                'studentExam.externalExamSubject.externalApplicant',
            ])
            ->get();

        // Die Gesamtpunktzahl von Hand gibt es nur für Schüler; für externe
        // Bewerber ist sie aus den acht Prüfungen berechenbar (§ 62 GSO).
        $curTotal = null;
        if (str_starts_with($pruefling, 's')) {
            $curTotal = ZusatzpruefStudentDeficit::where('student_id', (int) substr($pruefling, 1))
                ->where('exam_period_id', $examPeriod->id)
                ->first()
                ?->current_total_points;
        }

        $ersteZp = $pruefungen->first();

        return response()->json([
            'studentName'     => $ersteZp instanceof Zusatzpruefung ? $ersteZp->prueflingsName() : null,
            'studentId'       => $pruefling,
            // Nach welcher Norm gerechnet wird. Externe Bewerber bestehen nach
            // § 62, reguläre Schüler nach § 61 — zwei Rechenwege, nicht ein
            // Rechenweg mit Sonderfall.
            'paragraph'       => str_starts_with($pruefling, 'e') ? '62' : '61',
            'curTotal'        => $curTotal,
            'improvementOnly' => $pruefungen->isNotEmpty()
                && $pruefungen->every(fn (Zusatzpruefung $p) => $p->improvement_only),
            'exams'           => app(\App\Services\AbiturExamDataService::class)
                ->examsForPruefling($pruefling, (int) $examPeriod->id),
            'pruefungen'      => $pruefungen->map(fn (Zusatzpruefung $p) => [
                'pruefungId'        => $p->id,
                'subject'           => ($p->studentExam->getSubjectName() ?? '–')
                                       . ' (' . $p->studentExam->getSubjectLabel() . ')',
                'written'           => $p->written_points,
                'targetInitial'     => $p->target_points,
                'isMathematik'      => $p->studentExam->istMathematik(),
                'excluded_semester' => $p->excluded_semester,
                'focus_semester'    => $p->focus_semester,
            ])->values()->toArray(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // computeMindestpunkte62 — der Kalkulator für externe Bewerber
    //
    // Gegenstück zu computeMindestpunkte(), das für reguläre Schüler nach
    // § 61 rechnet. Der Unterschied ist nicht nur die Norm, sondern auch der
    // Weg: § 61 bekommt die Fächer vom Browser geschickt, § 62 holt sie sich
    // selbst. Die Positionen 1–8 der Anlage 14 stehen im Datenmodell
    // (exam_part und subject_number), und daran hängen Faktoren, Niveauregeln
    // und beide Bestehenstatbestände — das gehört nicht in den Browser.
    // ─────────────────────────────────────────────────────────────────

    public function computeMindestpunkte62(string $pruefling)
    {
        if (! str_starts_with($pruefling, 'e')) {
            return response()->json([
                'error' => 'Nach § 62 rechnen nur externe Bewerber; für Schüler gilt § 61.',
            ], 422);
        }

        $rechnung = $this->externeRechnung($pruefling);

        if ($rechnung === null) {
            return response()->json([
                'paragraph' => '62',
                'faecherwahl_vollstaendig' => false,
                'ergebnis' => null,
            ]);
        }

        return response()->json(['paragraph' => '62', 'faecherwahl_vollstaendig' => true] + $rechnung);
    }

    /**
     * Die § 62-Rechnung eines externen Bewerbers — für das Modal und für beide
     * PDF-Exporte dieselbe Quelle.
     *
     * Gibt null zurück, solange die Fächerwahl die acht Positionen nicht genau
     * einmal belegt (§ 61 Abs. 1 S. 1). Der Rechner würde darauf werfen; eine
     * unvollständige Wahl ist aber ein zulässiger Zwischenstand, kein Fehler.
     *
     * Neben dem Ergebnis stehen die Rohfächer: der Rechner gibt nur zurück, was
     * er für die Norm braucht (Position, Niveau, Faktor, Wertung) und kennt
     * student_exam_id nicht. Die Eingabefelder im Modal hängen aber daran.
     * Statt den fertigen § 62-Rechner um ein Feld zu erweitern, das ihn nichts
     * angeht, reicht die Abbildung ihre eigene Quelle mit durch; verbunden wird
     * über die Position.
     *
     * @return array{faecher_quelle: list<array<string, mixed>>, ergebnis: array<string, mixed>}|null
     */
    private function externeRechnung(string $pruefling, ?int $examPeriodId = null): ?array
    {
        if (! str_starts_with($pruefling, 'e')) {
            return null;
        }

        $periode = $examPeriodId ?? (int) app(CurrentExamPeriodService::class)->currentOrFail()->id;

        $bewerber = \App\Models\ExternalApplicant::find((int) substr($pruefling, 1));

        if (! $bewerber instanceof \App\Models\ExternalApplicant) {
            return null;
        }

        $faecher = app(\App\Services\ExterneAbiturDataService::class)->faecherFuer($bewerber, $periode);

        if ($faecher === null) {
            return null;
        }

        return [
            'faecher_quelle' => $faecher,
            'ergebnis' => app(\App\Services\ExterneMindestpunkteService::class)->berechne($faecher),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // storeExamScore — Einzelnote eines Prüfungsfachs speichern (AJAX)
    // Wird vom Mindestpunkte-Kalkulator-Modal verwendet.
    //
    // Der Schlüssel ist student_exam_id allein: student_id ist bei jedem
    // externen Bewerber null und unterscheidet sie deshalb nicht. Laravel
    // macht aus `where('student_id', null)` ein `whereNull` — das trifft alle
    // Externen zugleich (siehe die längere Begründung in store()).
    // ─────────────────────────────────────────────────────────────────

    public function storeExamScore(Request $request, string $pruefling)
    {
        $validated = $request->validate([
            'student_exam_id' => 'required|exists:student_exams,id',
            'score'           => 'nullable|integer|min:0|max:15',
            'practical_grade' => 'nullable|integer|min:0|max:15',
        ]);

        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        // Die Prüfung muss dem angesprochenen Prüfling gehören.
        $studentExam = StudentExam::desPrueflings($pruefling)
            ->whereKey($validated['student_exam_id'])
            ->first();

        if (!$studentExam instanceof StudentExam) {
            return response()->json(['error' => 'Diese Prüfung gehört nicht zu diesem Prüfling.'], 422);
        }

        // Entscheidung 40: im Sport keine Theorienote ohne Praxisnote. Dieser
        // Weg trägt auch die Noten der Kolloquien — dort sitzt der größere Teil
        // des Problems (in den Echtdaten sechs Fälle gegen einen bei den
        // Zusatzprüfungen).
        $verstoss = app(SportNotenRegel::class)->pruefeNote(
            $studentExam,
            isset($validated['score']) ? (int) $validated['score'] : null,
            array_key_exists('practical_grade', $validated),
            $validated['practical_grade'] ?? null,
        );

        if ($verstoss !== null) {
            return response()->json(['error' => $verstoss], 422);
        }

        $updateData = [
            'exam_period_id' => $examPeriod->id,
            'student_id'     => $studentExam->student_id,
            'score'          => $validated['score'] ?? null,
        ];
        if (array_key_exists('practical_grade', $validated)) {
            $updateData['practical_grade'] = $validated['practical_grade'];
        }

        ZusatzpruefExamScore::updateOrCreate(
            ['student_exam_id' => $studentExam->id],
            $updateData
        );

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────
    // store — Zusatzprüfung(en) anlegen + Noten + Gesamt speichern
    // ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Der Prüfling wird über seinen Schlüssel angesprochen ("s<id>"
            // bzw. "e<id>"): eine students-ID kann ein externer Bewerber nicht
            // liefern, und die beiden Nummernkreise überschneiden sich.
            'pruefling'               => ['required', 'string', 'regex:/^[se]\d+$/'],
            'student_exam_ids'        => 'nullable|array',
            'student_exam_ids.*'      => 'integer|exists:student_exams,id',
            'scores'                  => 'nullable|array',
            'scores.*'                => 'nullable|integer|min:0|max:15',
            'practical_grades'        => 'nullable|array',
            'practical_grades.*'      => 'nullable|integer|min:0|max:15',
            'improvement_only'        => 'nullable|boolean',
            'improvement_points_needed' => 'nullable|integer|min:0|max:255',
        ]);

        $pruefling       = (string) $validated['pruefling'];
        $studentId       = str_starts_with($pruefling, 's') ? (int) substr($pruefling, 1) : null;
        $examIds         = array_map('intval', $validated['student_exam_ids'] ?? []);
        $practicalGrades = $validated['practical_grades'] ?? [];
        $scores          = $validated['scores'] ?? [];      // keys = student_exam_id (string)
        $improvementOnly = (bool) ($validated['improvement_only'] ?? false);
        $improvementPointsNeeded = $validated['improvement_points_needed'] ?? null;
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        // Entscheidung 40: erst prüfen, dann speichern. Dieser Aufruf trägt
        // Noten *und* legt Zusatzprüfungen an; beides wird geprüft, bevor die
        // Transaktion beginnt — sonst stünde die halbe Eingabe schon in der
        // Datenbank, wenn der zweite Teil abgewiesen wird.
        $verstoesse = $this->pruefeSportnoten($pruefling, $examIds, $scores, $practicalGrades);

        if ($verstoesse !== []) {
            return response()->json(['error' => implode(' ', $verstoesse)], 422);
        }

        DB::transaction(function () use ($pruefling, $studentId, $examIds, $scores, $practicalGrades, $improvementOnly, $improvementPointsNeeded, $examPeriod) {

            // 1. Noten für alle Fächer persistieren.
            //    Schlüssel ist student_exam_id allein. student_id taugt nicht
            //    dafür: Sie ist bei jedem externen Bewerber null, und Laravel
            //    macht aus `where('student_id', null)` ein `whereNull` — die
            //    Bedingung trifft damit *alle* Externen auf einmal statt
            //    genau einen. Gemessen an Echtdaten am 31.08.2026: 7 von 99
            //    Zeilen tragen student_id null, `where('student_id', null)`
            //    erzeugt `student_id is null` und liefert alle sieben.
            //    Der UNIQUE der Tabelle geht denselben Weg —
            //    `zpexsc_student_exam_unique` steht seit der Septemberrunde
            //    (Migration 2026_08_26_190000) auf student_exam_id allein
            //    statt auf (student_id, student_exam_id).
            //
            //    (Bis zum 31.08.2026 stand hier, ein solches `where` „findet
            //    nie etwas". Das war falsch herum: es findet zu viel.)
            foreach ($scores as $seId => $score) {
                $seId = (int) $seId;
                $pg   = isset($practicalGrades[(string) $seId])
                    ? (int) $practicalGrades[(string) $seId]
                    : null;

                ZusatzpruefExamScore::updateOrCreate(
                    ['student_exam_id' => $seId],
                    [
                        'student_id'      => $studentId,
                        'exam_period_id'  => $examPeriod->id,
                        'score'           => $score,
                        'practical_grade' => $pg,
                    ]
                );
                // written_points auf existierender Zusatzprüfung nachführen
                // (auch nachträgliche Korrekturen — z. B. im Verbesserungsmodus)
                if ($score !== null) {
                    Zusatzpruefung::where('student_exam_id', $seId)
                        ->update(['written_points' => $score]);
                }
            }

            // 1b. practical_grades für Fächer ohne Score-Eintrag separat speichern
            //     (z. B. wenn Sport-Prüfungsleistung noch nicht eingetragen, aber praktische Note schon)
            foreach ($practicalGrades as $seId => $pg) {
                $seId = (int) $seId;
                if (isset($scores[(string) $seId])) {
                    continue; // bereits oben verarbeitet
                }
                if ($pg === null || $pg === '') continue;
                ZusatzpruefExamScore::updateOrCreate(
                    ['student_exam_id' => $seId],
                    [
                        'student_id'      => $studentId,
                        'exam_period_id'  => $examPeriod->id,
                        'practical_grade' => (int) $pg,
                    ]
                );
            }

            // 2. Gesamtpunktzahl aus allen eingetragenen Noten berechnen.
            //    Sport: effective = (score + practical_grade) * 2; sonst: score * 4
            $nonNull = collect($scores)->filter(fn($s) => $s !== null);
            if ($nonNull->count() >= 5) {
                $total = $nonNull->reduce(function (int $carry, $score, $seId) use ($practicalGrades) {
                    $pg = isset($practicalGrades[(string) $seId])
                        ? (int) $practicalGrades[(string) $seId]
                        : null;
                    // Sport-Fach: praktische Note vorhanden → (score + pg) * 2
                    if ($pg !== null) {
                        return $carry + ((int) $score + $pg) * 2;
                    }
                    return $carry + (int) $score * 4;
                }, 0);
                // Nur für Schüler: die Tabelle existiert, weil die Anwendung
                // für sie keine Halbjahresleistungen führt und die
                // Gesamtpunktzahl von Hand kommt. Für einen externen Bewerber
                // ist sie aus den acht Prüfungen berechenbar — es gibt nichts
                // zu speichern (Entscheidung vom 26.08.2026).
                if ($studentId !== null) {
                    ZusatzpruefStudentDeficit::updateOrCreate(
                        ['student_id' => $studentId, 'exam_period_id' => $examPeriod->id],
                        ['current_total_points' => $total]
                    );
                }
            }

            // 3. Neue Zusatzprüfungen anlegen
            foreach ($examIds as $seId) {
                $studentExam = StudentExam::find($seId);
                if (! $studentExam instanceof StudentExam
                    || $studentExam->prueflingsSchluessel() !== $pruefling
                    || $studentExam->exam_type !== 'written') {
                    continue;
                }
                // Duplikat-Schutz: bestehende ZP nur aktualisieren, nicht neu anlegen
                $existing = Zusatzpruefung::where('student_exam_id', $seId)->first();
                if ($existing instanceof Zusatzpruefung) {
                    $existing->update([
                        'improvement_only'          => $improvementOnly,
                        'improvement_points_needed' => $improvementOnly ? $improvementPointsNeeded : null,
                    ]);
                    continue;
                }

                $writtenScore = isset($scores[(string) $seId]) ? (int) $scores[(string) $seId] : null;

                $pruefung = Zusatzpruefung::create([
                    'exam_period_id'             => $examPeriod->id,
                    'student_id'                 => $studentId,
                    'student_exam_id'            => $seId,
                    'status'                     => 'pending',
                    'written_points'             => $writtenScore,
                    'improvement_only'           => $improvementOnly,
                    'improvement_points_needed'  => $improvementOnly ? $improvementPointsNeeded : null,
                ]);

                $sort = 0;
                if ($studentExam->examiner_id) {
                    $pruefung->pruefer()->create([
                        'teacher_id' => $studentExam->examiner_id,
                        'role'       => 'erstpruefer',
                        'sort_order' => $sort++,
                    ]);
                }
                if ($studentExam->second_corrector_id) {
                    $pruefung->pruefer()->create([
                        'teacher_id' => $studentExam->second_corrector_id,
                        'role'       => 'zweitkorrektor',
                        'sort_order' => $sort,
                    ]);
                }
            }

            // 4. sort_order für alle ZPs dieses Schülers neu berechnen und setzen.
            //    Sortierung wie in MindestpunkteService::compute() (Auto-Modus):
            //    niedrigste written_points zuerst, sonst nichts.
            //
            //    Hier stand bis zum 31.08.2026 ein zweiter Schlüssel — „bei
            //    Gleichstand die schlechtere Aussicht zuerst", gemessen an
            //    minOralFor(w, 1). Der konnte einen Gleichstand nie brechen:
            //    minOralFor(w, 1) hing allein von w ab, und innerhalb einer
            //    Gleichstandsgruppe ist w per Definition dasselbe. Er ist
            //    entfallen (Entscheidung Wilfried); damit sortieren Controller
            //    und Dienst wieder nachweislich gleich.
            $allZps = Zusatzpruefung::whereHas(
                    'studentExam',
                    fn ($q) => $q->desPrueflings($pruefling)
                )
                ->where('exam_period_id', $examPeriod->id)
                ->get();

            if ($allZps->count() > 1) {
                $sorted = $allZps
                    ->sortBy(fn (Zusatzpruefung $zp): int => $zp->written_points ?? 999)
                    ->values();

                foreach ($sorted as $pos => $zp) {
                    $zp->update(['sort_order' => $pos + 1]);
                }
            } elseif ($allZps->count() === 1) {
                $allZps->first()->update(['sort_order' => 1]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Zusatzprüfung(en) angelegt.']);
    }

    /**
     * Sammelt die Sport-Verstoesse eines Speichervorgangs (Entscheidung 40).
     *
     * Zwei Faelle, beide ueber dieselbe Regel:
     *
     * - eine Theorienote zu einem Sportfach ohne Praxisnote — betrifft
     *   Kolloquium und schriftliche Pruefung gleichermassen,
     * - eine neu anzulegende Zusatzpruefung auf einem Sportfach ohne
     *   Praxisnote.
     *
     * Die Pruefungen werden in einem Zug geladen: `store()` bekommt bis zu
     * acht Faecher auf einmal, und eine Abfrage je Fach waere hier dasselbe
     * Muster, das anderswo schon als N+1 aufgefallen ist.
     *
     * @param  list<int>            $examIds  neu anzulegende Zusatzpruefungen
     * @param  array<string, mixed> $scores   student_exam_id => Theorienote
     * @param  array<string, mixed> $practicalGrades student_exam_id => Praxisnote
     * @return list<string>
     */
    private function pruefeSportnoten(
        string $pruefling,
        array $examIds,
        array $scores,
        array $practicalGrades
    ): array {
        $betroffene = array_values(array_unique(array_merge(
            array_map('intval', array_keys($scores)),
            $examIds
        )));

        if ($betroffene === []) {
            return [];
        }

        $pruefungen = StudentExam::desPrueflings($pruefling)
            ->whereIn('id', $betroffene)
            ->with('course.subject', 'externalExamSubject.subject')
            ->get()
            ->keyBy('id');

        $regel = app(SportNotenRegel::class);
        $verstoesse = [];

        foreach ($betroffene as $seId) {
            $pruefung = $pruefungen->get($seId);

            if (! $pruefung instanceof StudentExam) {
                continue;
            }

            $praxisImRequest = array_key_exists((string) $seId, $practicalGrades);
            $praxisNeu = $praxisImRequest && $practicalGrades[(string) $seId] !== null
                && $practicalGrades[(string) $seId] !== ''
                ? (int) $practicalGrades[(string) $seId]
                : null;

            $score = isset($scores[(string) $seId]) ? (int) $scores[(string) $seId] : null;

            $befund = in_array($seId, $examIds, true)
                ? $regel->pruefeZusatzpruefung($pruefung, $praxisImRequest, $praxisNeu)
                : $regel->pruefeNote($pruefung, $score, $praxisImRequest, $praxisNeu);

            if ($befund !== null) {
                $verstoesse[] = $befund;
            }
        }

        return $verstoesse;
    }

    // ─────────────────────────────────────────────────────────────────
    // toggleAbsent — "nicht angetreten" setzen / aufheben
    // ─────────────────────────────────────────────────────────────────

    public function toggleAbsent(Zusatzpruefung $zusatzpruefung)
    {
        $zusatzpruefung->update(['absent' => ! $zusatzpruefung->absent]);

        return response()->json([
            'success' => true,
            'absent'  => $zusatzpruefung->absent,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // destroy
    // ─────────────────────────────────────────────────────────────────

    public function destroy(Zusatzpruefung $zusatzpruefung)
    {
        $zusatzpruefung->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Zusatzprüfung entfernt.');
    }

    // ─────────────────────────────────────────────────────────────────
    // place (Drag & Drop → Slot anlegen / verschieben)
    // ─────────────────────────────────────────────────────────────────

    public function place(Request $request, Zusatzpruefung $zusatzpruefung)
    {
        $validated = $request->validate([
            'zusatzpruef_day_id'      => 'required|exists:zusatzpruef_days,id',
            'zusatzpruef_day_room_id' => 'required|exists:zusatzpruef_day_rooms,id',
            'start_time'              => 'required|date_format:H:i',
        ]);

        // NTA verlängert nur die Vorbereitung. Er hängt am Prüfling, nicht am
        // Schülerdatensatz — ein externer Bewerber kann ihn genauso haben.
        $prepMin  = $zusatzpruefung->kompensierteDauer(20);
        $examMin  = 20;                                   // Prüfungszeit immer 20 Min.

        // Prüflings-Konflikt prüfen (max. eine Prüfung pro Tag)
        $studentConflict = $this->checkStudentConflict(
            $zusatzpruefung,
            (int) $validated['zusatzpruef_day_id']
        );

        if ($studentConflict) {
            return response()->json(['error' => $studentConflict], 422);
        }

        // Lehrer-Konflikt prüfen (nur Prüfungszeit, nicht Vorbereitung)
        $conflict = $this->checkExaminerConflict(
            $zusatzpruefung,
            (int) $validated['zusatzpruef_day_id'],
            $validated['start_time'],
            $prepMin,
            $examMin
        );

        if ($conflict) {
            return response()->json(['error' => $conflict], 422);
        }

        DB::transaction(function () use ($zusatzpruefung, $validated, $prepMin, $examMin) {
            $zusatzpruefung->assignment()->delete();

            ZusatzpruefAssignment::create([
                'zusatzpruefung_id'       => $zusatzpruefung->id,
                'zusatzpruef_day_id'      => $validated['zusatzpruef_day_id'],
                'zusatzpruef_day_room_id' => $validated['zusatzpruef_day_room_id'],
                'start_time'              => $validated['start_time'],
                'prep_duration_minutes'   => $prepMin,
                'exam_duration_minutes'   => $examMin,
            ]);

            $zusatzpruefung->update(['status' => 'placed']);
        });

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────
    // unplace (zurück in Sidebar)
    // ─────────────────────────────────────────────────────────────────

    public function unplace(Zusatzpruefung $zusatzpruefung)
    {
        DB::transaction(function () use ($zusatzpruefung) {
            $zusatzpruefung->assignment()->delete();
            $zusatzpruefung->update(['status' => 'pending']);
        });

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────
    // updatePruefer
    // ─────────────────────────────────────────────────────────────────

    public function updatePruefer(Request $request, Zusatzpruefung $zusatzpruefung)
    {
        $validated = $request->validate([
            'pruefer'              => 'required|array|min:1',
            'pruefer.*.teacher_id' => 'required|exists:teachers,id',
            'pruefer.*.role'       => 'required|in:erstpruefer,zweitkorrektor,gast',
        ]);

        DB::transaction(function () use ($zusatzpruefung, $validated) {
            $zusatzpruefung->pruefer()->delete();
            foreach ($validated['pruefer'] as $i => $p) {
                $zusatzpruefung->pruefer()->create([
                    'teacher_id' => $p['teacher_id'],
                    'role'       => $p['role'],
                    'sort_order' => $i,
                ]);
            }
        });

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────
    // toggleLock — Prüfung fixieren / Fixierung aufheben
    // ─────────────────────────────────────────────────────────────────

    public function toggleLock(Zusatzpruefung $zusatzpruefung)
    {
        $zusatzpruefung->update(['locked' => ! $zusatzpruefung->locked]);

        return response()->json([
            'success' => true,
            'locked'  => $zusatzpruefung->locked,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // updatePoints — min_points / target_points inline speichern
    // ─────────────────────────────────────────────────────────────────

    public function updatePoints(Request $request, Zusatzpruefung $zusatzpruefung)
    {
        $validated = $request->validate([
            'written_points'  => 'nullable|integer|min:0|max:15',
            'min_points'      => 'nullable|integer|min:0|max:15',
            'target_points'   => 'nullable|integer|min:0|max:15',
            'practical_grade' => 'nullable|integer|min:0|max:15',
        ]);

        $pruefung = $zusatzpruefung->studentExam;

        // Entscheidung 40: eine Sport-Zusatzprüfung ohne Praxisnote kann weder
        // ihren Beitrag noch ihre Verbesserung ausweisen. Das Feld wird hier
        // mitgeschickt, wenn die Maske es kennt — auch als null, und genau das
        // Löschen ist der Fall, der verhindert werden muss.
        if ($pruefung instanceof StudentExam) {
            $verstoss = app(SportNotenRegel::class)->pruefeZusatzpruefung(
                $pruefung,
                array_key_exists('practical_grade', $validated),
                $validated['practical_grade'] ?? null,
            );

            if ($verstoss !== null) {
                return response()->json(['error' => $verstoss], 422);
            }
        }

        $data = [];
        foreach (['written_points', 'min_points', 'target_points'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $validated[$field];
            }
        }
        if (!empty($data)) {
            $zusatzpruefung->update($data);
        }

        // Praxisnote liegt nicht auf der Zusatzpruefung selbst, sondern in der
        // separaten ZusatzpruefExamScore-Tabelle (Sport-Sonderfall, siehe
        // MindestpunkteService::calcSportZpContribution()). Nur bei Sport-ZP
        // relevant — wird aber unabhängig vom Fach einfach durchgereicht, wenn
        // das Frontend den Wert mitsendet (Validierung lässt nur 0–15 zu).
        if ($request->has('practical_grade')) {
            // Schlüssel ist student_exam_id allein — wie in store() und wie
            // der UNIQUE der Tabelle.
            //
            // Vorher stand student_id davor. Das war nicht der Fehler, für den
            // man es halten könnte: Laravel macht aus `where('col', null)` ein
            // `whereNull`, ein externer Bewerber mit null auf beiden Seiten
            // wurde also durchaus gefunden (nachgerechnet am 28.08.2026).
            // Falsch wird es erst, wenn die gespeicherte Notenzeile und die
            // Zusatzprüfung in student_id auseinanderfallen — dann sucht der
            // zusammengesetzte Schlüssel daneben, updateOrCreate legt eine
            // zweite Zeile an und scheitert am UNIQUE. Der einspaltige
            // Schlüssel kann das nicht.
            ZusatzpruefExamScore::updateOrCreate(
                ['student_exam_id' => $zusatzpruefung->student_exam_id],
                [
                    'student_id'      => $zusatzpruefung->student_id,
                    'exam_period_id'  => $zusatzpruefung->exam_period_id,
                    'practical_grade' => $validated['practical_grade'],
                ]
            );
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────
    // updateDeficit — aktuelle Gesamtpunktzahl eines Schülers speichern
    // ─────────────────────────────────────────────────────────────────

    public function updateDeficit(Request $request, Student $student)
    {
        $validated = $request->validate([
            'current_total_points' => 'nullable|integer|min:0|max:900',
        ]);

        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        ZusatzpruefStudentDeficit::updateOrCreate(
            [
                'student_id'     => $student->id,
                'exam_period_id' => $examPeriod->id,
            ],
            [
                'current_total_points' => $validated['current_total_points'] ?? null,
            ]
        );

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────
    // destroyAllForPruefling — alle ZPs + Noten eines Prüflings löschen
    // ─────────────────────────────────────────────────────────────────

    public function destroyAllForPruefling(string $pruefling)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        $pruefungsIds = StudentExam::desPrueflings($pruefling)->pluck('id');

        DB::transaction(function () use ($pruefling, $pruefungsIds, $examPeriod) {
            // ZPs löschen (Slots, Prüfer, Aufsichten via cascade)
            Zusatzpruefung::whereIn('student_exam_id', $pruefungsIds)
                ->where('exam_period_id', $examPeriod->id)
                ->delete();

            // Gespeicherte Noten löschen
            ZusatzpruefExamScore::whereIn('student_exam_id', $pruefungsIds)
                ->where('exam_period_id', $examPeriod->id)
                ->delete();

            // Gesamtpunktzahl löschen — die gibt es nur für Schüler
            // (Entscheidung vom 26.08.2026), für externe Bewerber ist sie aus
            // den acht Prüfungen berechenbar.
            if (str_starts_with($pruefling, 's')) {
                ZusatzpruefStudentDeficit::where('student_id', (int) substr($pruefling, 1))
                    ->where('exam_period_id', $examPeriod->id)
                    ->delete();
            }
        });

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────
    // exportPlanPdf — Übersichtsplan als PDF
    // ─────────────────────────────────────────────────────────────────

    public function exportPlanPdf(Request $request)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        $user     = auth()->user();
        $schoolId = $user->school_id ?? \App\Models\School::first()?->school_number;
        $school   = $schoolId ? \App\Models\School::find($schoolId) : null;

        // Optional: nur bestimmte Tage exportieren
        $selectedDayIds = $request->has('day_ids')
            ? array_map('intval', (array) $request->input('day_ids'))
            : null;

        $daysQuery = ZusatzpruefDay::where('exam_period_id', $examPeriod->id)
            ->with([
                'timeslots.aufsichten.teacher',
                'timeslots.aufsichten.dayRoom.prepRoom',
                'dayRooms.examRoom',
                'dayRooms.prepRoom',
            ])
            ->orderBy('date');

        if ($selectedDayIds !== null) {
            $daysQuery->whereIn('id', $selectedDayIds);
        }

        $days = $daysQuery->get();

        // Alle Assignments mit Schüler- und Prüferdaten laden (nur gewählte Tage)
        $allAssignments = ZusatzpruefAssignment::whereHas('day', fn($q) =>
                $q->where('exam_period_id', $examPeriod->id)
                  ->when($selectedDayIds !== null, fn($q2) => $q2->whereIn('id', $selectedDayIds)))
            ->with([
                'zusatzpruefung.student',
                'zusatzpruefung.studentExam.course.subject',
                'zusatzpruefung.studentExam.externalExamSubject.externalApplicant',
                'zusatzpruefung.studentExam.externalExamSubject.subject',
                'zusatzpruefung.pruefer.teacher',
            ])
            ->get()
            ->groupBy('zusatzpruef_day_id');

        $dayData = [];
        foreach ($days as $day) {
            $dayAssignments = $allAssignments->get($day->id, collect());

            // Lookup: [dayRoomId][start_time 'HH:MM'] = ZusatzpruefAssignment
            $lookup = [];
            foreach ($dayAssignments as $asgn) {
                $lookup[$asgn->zusatzpruef_day_room_id][substr((string) $asgn->start_time, 0, 5)] = $asgn;
            }

            // NTA-Annotationen: extra Vorbereitungsminuten (Standard = 20)
            $ntaExtra = [];
            foreach ($dayAssignments as $asgn) {
                $ntaExtra[$asgn->id] = max(0, $asgn->prep_duration_minutes - 20);
            }
            $ntaDistinct = array_values(array_unique(array_filter(array_values($ntaExtra))));
            sort($ntaDistinct);
            $starSymbols    = ['*', '**', '***'];
            $ntaValueToStar = [];
            foreach ($ntaDistinct as $i => $val) {
                $ntaValueToStar[$val] = $starSymbols[$i] ?? str_repeat('*', $i + 1);
            }

            $dayData[] = [
                'day'            => $day,
                'timeslots'      => $day->timeslots->sortBy('start_time')->values(),
                'dayRooms'       => $day->dayRooms->sortBy('examRoom.room_number')->values(),
                'lookup'         => $lookup,
                'ntaExtra'       => $ntaExtra,
                'ntaValueToStar' => $ntaValueToStar,
            ];
        }

        $schlusstext = \App\Models\SchoolSetting::get('zusatzpruefungen.schlusstext');

        $pdf = Pdf::loadView('exports.zusatzpruef-plan-pdf', compact(
            'examPeriod', 'school', 'dayData', 'schlusstext'
        ))->setPaper('a4', 'landscape');

        $filename = 'Zusatzpruefungen_' . $examPeriod->year . '_' . now()->format('Ymd_Hi') . '.pdf';
        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────
    // exportStudentListPdf — Schülerliste alphabetisch
    // ─────────────────────────────────────────────────────────────────

    public function exportStudentListPdf()
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        $user     = auth()->user();
        $schoolId = $user->school_id ?? \App\Models\School::first()?->school_number;
        $school   = $schoolId ? \App\Models\School::find($schoolId) : null;

        // Alle Zusatzprüfungen, alphabetisch nach Nachname → Vorname → Fach (DIN 5007-2)
        $pruefungen = Zusatzpruefung::where('exam_period_id', $examPeriod->id)
            ->with([
                'student',
                'studentExam.course.subject',
                'studentExam.externalExamSubject.externalApplicant',
                'studentExam.externalExamSubject.subject',
                'assignment.day',
                'assignment.dayRoom.examRoom',
                'assignment.dayRoom.prepRoom',
                'pruefer.teacher',
            ])
            ->get();

        $collator = new \Collator('de_DE@collation=phonebook');
        $sortKey  = static function (Zusatzpruefung $zp): string {
            $pruefung = $zp->studentExam;

            return ($zp->prueflingsName())
                . ' '
                . ($pruefung instanceof StudentExam ? ($pruefung->getSubjectName() ?? '') : '');
        };
        $pruefungen = $pruefungen->sort(
            fn (Zusatzpruefung $a, Zusatzpruefung $b): int => $collator->compare($sortKey($a), $sortKey($b))
        )->values();

        $pdf = Pdf::loadView('exports.zusatzpruef-studentlist-pdf', compact(
            'examPeriod', 'school', 'pruefungen'
        ))->setPaper('a4', 'portrait');

        $filename = 'Zusatzpruefungen_Schuelerliste_' . $examPeriod->year . '_' . now()->format('Ymd_Hi') . '.pdf';
        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────
    // teacherOverview — JSON für In-Page-Modal
    // ─────────────────────────────────────────────────────────────────

    public function teacherOverview(): \Illuminate\Http\JsonResponse
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        [$teacherRows, $prepMaxMap] = $this->buildTeacherRows($examPeriod);

        $result = [];
        foreach ($teacherRows as $row) {
            /** @var \App\Models\Teacher $teacher */
            $teacher = $row['teacher'];
            $entries = [];
            foreach ($row['entries'] as $e) {
                $entry = [
                    'type'      => $e['type'],
                    'date'      => $e['date']->locale('de')->isoFormat('dd, D.M.'),
                    'date_full' => $e['date']->locale('de')->isoFormat('dddd, D. MMMM YYYY'),
                    'date_sort' => $e['date']->format('Y-m-d'),
                    'sort_time' => $e['sort_time'],
                    'day_label' => $e['day_label'] ?? null,
                ];
                if ($e['type'] === 'pruefung') {
                    $entry += [
                        'role'              => $e['role_label'],
                        'prep_time'         => $e['prep_time'],
                        'exam_time'         => $e['exam_time'],
                        'exam_room'         => $e['exam_room'],
                        'student_name'      => $e['student_name'],
                        'subject'           => $e['subject'],
                        'co_examiners'      => $e['co_examiners'],
                        'absent'            => $e['absent'],
                        'nta_extra'         => $e['nta_extra'],
                        'excluded_semester' => $e['excluded_semester'] ?? null,
                        'focus_semester'    => $e['focus_semester'] ?? null,
                        'is_math'           => $e['is_math'] ?? false,
                    ];
                } else {
                    $entry += [
                        'time_range' => $e['time_range'],
                        'prep_room'  => $e['prep_room'],
                    ];
                }
                $entries[] = $entry;
            }

            $pruefCount = $row['entries']->where('type', 'pruefung')->count();
            $aufsCount  = $row['entries']->where('type', 'aufsicht')->count();

            $result[] = [
                'id'          => $teacher->id,
                'shortcode'   => $teacher->shortcode,
                'name'        => trim(($teacher->lastname ?? '') . ', ' . ($teacher->firstname ?? ''), ', '),
                'pruef_count' => $pruefCount,
                'aufs_count'  => $aufsCount,
                'entries'     => $entries,
            ];
        }

        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────────
    // exportTeacherListPdf — Lehrerübersicht: Prüfungen & Aufsichten
    // ─────────────────────────────────────────────────────────────────

    public function exportTeacherListPdf(Request $request)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        $user     = auth()->user();
        $schoolId = $user->school_id ?? \App\Models\School::first()?->school_number;
        $school   = $schoolId ? \App\Models\School::find($schoolId) : null;

        $selectedDayIds = $request->has('day_ids')
            ? array_map('intval', (array) $request->input('day_ids'))
            : null;

        [$teacherRows] = $this->buildTeacherRows($examPeriod, $selectedDayIds);

        $pdf = Pdf::loadView('exports.zusatzpruef-teacher-list-pdf', compact(
            'examPeriod', 'school', 'teacherRows'
        ))->setPaper('a4', 'landscape');

        $filename = 'Zusatzpruefungen_Lehreruebersicht_' . $examPeriod->year . '_' . now()->format('Ymd_Hi') . '.pdf';
        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────
    // buildTeacherRows — gemeinsame Datenaufbereitung (für PDF + JSON)
    // Gibt [teacherRows, prepMaxMap] zurück.
    // ─────────────────────────────────────────────────────────────────

    /**
     * @param \App\Models\ExamPeriod $examPeriod
     * @param list<int>|null         $selectedDayIds  Nur Einträge dieser Tage einbeziehen (null = alle)
     * @return array{0: list<array{teacher: \App\Models\Teacher, entries: \Illuminate\Support\Collection<int, array<string, mixed>>}>, 1: array<string, int>}
     */
    private function buildTeacherRows(\App\Models\ExamPeriod $examPeriod, ?array $selectedDayIds = null): array
    {
        // Alle platzierten Assignments laden (ggf. auf gewählte Tage eingeschränkt)
        $assignments = \App\Models\ZusatzpruefAssignment::whereHas('day', fn($q) =>
                $q->where('exam_period_id', $examPeriod->id)
                  ->when($selectedDayIds !== null, fn($q2) => $q2->whereIn('id', $selectedDayIds)))
            ->with([
                'day',
                'dayRoom.examRoom',
                'dayRoom.prepRoom',
                'zusatzpruefung.student',
                'zusatzpruefung.studentExam.course.subject',
                'zusatzpruefung.studentExam.externalExamSubject.externalApplicant',
                'zusatzpruefung.studentExam.externalExamSubject.subject',
                'zusatzpruefung.pruefer.teacher',
            ])
            ->get();

        // Alle Aufsichten laden (ggf. auf gewählte Tage eingeschränkt)
        $aufsichten = \App\Models\ZusatzpruefAufsicht::whereHas('day', fn($q) =>
                $q->where('exam_period_id', $examPeriod->id)
                  ->when($selectedDayIds !== null, fn($q2) => $q2->whereIn('id', $selectedDayIds)))
            ->with(['day', 'dayRoom.prepRoom', 'timeslot', 'teacher'])
            ->get();

        // Lookup: "day_id:prep_room_id:start_time(HH:MM)" → max prep_duration_minutes
        /** @var array<string, int> $prepMaxMap */
        $prepMaxMap = [];
        foreach ($assignments as $asgn) {
            $prepRoomId = $asgn->dayRoom->prep_room_id;
            if (!$prepRoomId) {
                continue;
            }
            $key = $asgn->zusatzpruef_day_id . ':' . $prepRoomId . ':' . substr((string) $asgn->start_time, 0, 5);
            $existing = $prepMaxMap[$key] ?? 0;
            if ($asgn->prep_duration_minutes > $existing) {
                $prepMaxMap[$key] = $asgn->prep_duration_minutes;
            }
        }

        /** @var array<int, list<array<string, mixed>>> $teacherEntries */
        $teacherEntries = [];

        // Prüfer-Einträge
        foreach ($assignments as $asgn) {
            $zp = $asgn->zusatzpruefung;
            if (!$zp) {
                continue;
            }

            $prepStart  = substr((string) $asgn->start_time, 0, 5);
            [$ph, $pm]  = explode(':', $prepStart);
            $prepEndMin = (int)$ph * 60 + (int)$pm + $asgn->prep_duration_minutes;
            $examEndMin = $prepEndMin + $asgn->exam_duration_minutes;
            $prepEnd    = sprintf('%02d:%02d', intdiv($prepEndMin, 60), $prepEndMin % 60);
            $examStart  = $prepEnd;
            $examEnd    = sprintf('%02d:%02d', intdiv($examEndMin, 60), $examEndMin % 60);

            $examRoomNr  = $asgn->dayRoom->examRoom->room_number;
            $dispName    = $zp->prueflingsKurzname();
            $subjectName = $zp->studentExam->getSubjectName() ?? '–';
            // Externe haben keinen Kurs — dann steht nur das Fach da.
            $kurs        = $zp->studentExam->course;
            $courseCode  = ($zp->istExtern() || ! $kurs instanceof \App\Models\Course)
                ? ''
                : (string) $kurs->course_code;
            $subject     = $courseCode ? $subjectName . ' (' . $courseCode . ')' : $subjectName;
            $ntaExtra    = max(0, $asgn->prep_duration_minutes - 20);

            foreach ($zp->pruefer as $pruefer) {
                if (!$pruefer->teacher_id) {
                    continue;
                }
                $coExaminers = $zp->pruefer
                    ->filter(fn($p) => $p->teacher_id !== $pruefer->teacher_id)
                    ->map(fn($p) => $p->teacher->shortcode ?? '?')
                    ->filter()
                    ->join(', ');

                $teacherEntries[$pruefer->teacher_id][] = [
                    'type'              => 'pruefung',
                    'date'              => $asgn->day->date,
                    'day_label'         => $asgn->day->label,
                    'sort_time'         => $prepStart,
                    'prep_time'         => $prepStart . '–' . $prepEnd,
                    'exam_time'         => $examStart . '–' . $examEnd,
                    'exam_room'         => $examRoomNr,
                    'student_name'      => $dispName,
                    'subject'           => $subject,
                    'role_label'        => match($pruefer->role) {
                        'erstpruefer'    => 'Erstprüfer',
                        'zweitkorrektor' => 'Zweitkorrektor',
                        'gast'           => 'Gast',
                        default          => '–',
                    },
                    'co_examiners'      => $coExaminers,
                    'absent'            => (bool) $zp->absent,
                    'nta_extra'         => $ntaExtra,
                    'excluded_semester' => $zp->excluded_semester,
                    'focus_semester'    => $zp->focus_semester,
                    'is_math'           => $zp->studentExam->istMathematik(),
                ];
            }
        }

        // Aufsichts-Einträge
        foreach ($aufsichten as $auf) {
            $tid = $auf->teacher_id;
            $ts  = $auf->timeslot;
            if (!$ts) {
                continue;
            }
            $tsStart     = substr((string) $ts->start_time, 0, 5);
            [$th, $tm]   = explode(':', $tsStart);
            $baseMin     = (int)$th * 60 + (int)$tm;
            $aufStartMin = $baseMin - 10;
            $prepRoomId  = $auf->dayRoom->prep_room_id;
            $key         = $auf->zusatzpruef_day_id . ':' . ($prepRoomId ?? '') . ':' . $tsStart;
            $maxPrepMin  = $prepMaxMap[$key] ?? 20;
            $aufEndMin   = $baseMin + $maxPrepMin;
            $aufStart    = sprintf('%02d:%02d', intdiv(max(0, $aufStartMin), 60), (($aufStartMin % 60) + 60) % 60);
            $aufEnd      = sprintf('%02d:%02d', intdiv($aufEndMin, 60), $aufEndMin % 60);
            $prepRoomNr  = $auf->dayRoom->prepRoom->room_number ?? '?';

            $teacherEntries[$tid][] = [
                'type'       => 'aufsicht',
                'date'       => $auf->day->date,
                'day_label'  => $auf->day->label,
                'sort_time'  => $aufStart,
                'time_range' => $aufStart . '–' . $aufEnd,
                'prep_room'  => $prepRoomNr,
            ];
        }

        // Lehrer laden + sortieren (DIN 5007-2: Nachname, dann Vorname)
        $teacherIds  = array_keys($teacherEntries);
        $collator    = new \Collator('de_DE@collation=phonebook');
        $teachers    = Teacher::whereIn('id', $teacherIds)->get()
            ->sort(function (\App\Models\Teacher $a, \App\Models\Teacher $b) use ($collator): int {
                $keyA = ($a->lastname ?? '') . ', ' . ($a->firstname ?? '');
                $keyB = ($b->lastname ?? '') . ', ' . ($b->firstname ?? '');
                return $collator->compare($keyA, $keyB);
            })
            ->keyBy('id');
        $teacherRows = [];
        foreach ($teachers as $teacher) {
            $entries = collect($teacherEntries[$teacher->id] ?? [])
                ->sortBy(fn($e) => $e['date']->format('Y-m-d') . '_' . $e['sort_time'] . '_' . ($e['type'] === 'pruefung' ? '0' : '1'))
                ->values();
            $teacherRows[] = ['teacher' => $teacher, 'entries' => $entries];
        }

        return [$teacherRows, $prepMaxMap];
    }

    // ─────────────────────────────────────────────────────────────────
    // computeMindestpunkte — AJAX-Endpunkt für das Modal
    // ─────────────────────────────────────────────────────────────────

    /**
     * Nimmt die aktuellen Prüfungsdaten (Noten, ZP-Punkte) als JSON entgegen
     * und gibt die berechnete Kombinationstabelle zurück.
     *
     * Der Client schickt das komplette `exams`-Array (wie es das Modal intern
     * verwaltet) – inkl. aktuell eingetragener Noten. Die gesamte Berechnung
     * läuft in MindestpunkteService (einmalige, kanonische Implementierung).
     */
    public function computeMindestpunkte(Request $request)
    {
        $validated = $request->validate([
            'exams'                => 'required|array|min:1|max:10',
            'exams.*.id'           => 'required|integer',
            'exams.*.isZusatzpruefung' => 'required|boolean',
            'exams.*.isDeutsch'    => 'required|boolean',
            'exams.*.isMathematik' => 'required|boolean',
            'exams.*.isLeistungsfach'  => 'required|boolean',
            'exams.*.isForeignLanguage' => 'required|boolean',
            'exams.*.isScience'    => 'required|boolean',
            'exams.*.isSport'      => 'nullable|boolean',
            'exams.*.level'        => 'nullable|string',
            'exams.*.aufgabenfeld' => 'nullable|string',
            'exams.*.subject'      => 'required|string',
            'exams.*.writtenPoints'      => 'nullable|integer|min:0|max:15',
            'exams.*.targetPoints'       => 'nullable|integer|min:0|max:15',
            'exams.*.score'              => 'nullable|integer|min:0|max:15',
            'exams.*.sportScore'         => 'nullable|integer|min:0|max:15',
            'exams.*.sportPracticalGrade' => 'nullable|integer|min:0|max:15',
            'exams.*.sort_order'   => 'nullable|integer|min:0|max:99',
            'exams.*.pruefungId'   => 'nullable|integer',
            'exams.*.subjectCode'  => 'nullable|string|max:20',
        ]);

        $service = new \App\Services\MindestpunkteService();
        return response()->json($service->compute($validated['exams']));
    }

    // ─────────────────────────────────────────────────────────────────
    // updateSortOrder — Reihenfolge der ZP-Fächer im Mindestpunkte-Modal
    // ─────────────────────────────────────────────────────────────────

    public function updateSortOrder(Request $request, Zusatzpruefung $zusatzpruefung)
    {
        $validated = $request->validate([
            'sort_order' => 'required|integer|min:0|max:99',
        ]);

        $zusatzpruefung->update(['sort_order' => $validated['sort_order']]);

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────
    // updateAnmeldung — Anmeldedaten (Streichung, Schwerpunkt) speichern
    // ─────────────────────────────────────────────────────────────────

    public function updateAnmeldung(Request $request, Zusatzpruefung $zusatzpruefung)
    {
        $validated = $request->validate([
            'excluded_semester' => 'nullable|in:12_1,12_2,stochastik,geometrie',
            'focus_semester'    => 'nullable|in:12_1,12_2,13_1,13_2',
        ]);

        // excluded und focus dürfen nicht identisch sein
        if (
            !empty($validated['excluded_semester']) &&
            !empty($validated['focus_semester']) &&
            $validated['excluded_semester'] === $validated['focus_semester']
        ) {
            return response()->json(['error' => 'Das gestrichene Semester und das Schwerpunktsemester dürfen nicht identisch sein.'], 422);
        }

        $zusatzpruefung->update([
            'excluded_semester' => $validated['excluded_semester'] ?? null,
            'focus_semester'    => $validated['focus_semester'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────
    // exportVorbereitungsprotokollPdf
    // Pro Tag × Vorbereitungsraum × Timeslot eine Protokollseite
    // ─────────────────────────────────────────────────────────────────

    public function exportVorbereitungsprotokollPdf(\Illuminate\Http\Request $request)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();
        $user       = auth()->user();
        $schoolId   = $user->school_id ?? \App\Models\School::first()?->school_number;
        $school     = $schoolId ? \App\Models\School::find($schoolId) : null;

        // Optional: nur bestimmte Tage exportieren
        $selectedDayIds = $request->has('day_ids')
            ? array_map('intval', (array) $request->input('day_ids'))
            : null;

        // Alle Tage des Prüfungszeitraums mit Räumen, Timeslots und Aufsichten
        $daysQuery = \App\Models\ZusatzpruefDay::where('exam_period_id', $examPeriod->id)
            ->with([
                'dayRooms.prepRoom',
                'dayRooms.examRoom',
                'timeslots.aufsichten.teacher',
            ])
            ->orderBy('date');

        if ($selectedDayIds !== null) {
            $daysQuery->whereIn('id', $selectedDayIds);
        }

        $days = $daysQuery->get();

        // Alle platzierten Assignments mit Schülerdaten (ohne nicht angetretene Schüler)
        $assignments = \App\Models\ZusatzpruefAssignment::whereHas(
                'day', fn ($q) => $q->where('exam_period_id', $examPeriod->id)
                    ->when($selectedDayIds !== null, fn ($q2) => $q2->whereIn('id', $selectedDayIds))
            )
            ->whereHas('zusatzpruefung', fn ($q) => $q->where('absent', false))
            ->with([
                'zusatzpruefung.student',
                'zusatzpruefung.studentExam.course.subject',
                'zusatzpruefung.studentExam.externalExamSubject.externalApplicant',
                'zusatzpruefung.studentExam.externalExamSubject.subject',
                'dayRoom',
            ])
            ->get();

        $collator = new \Collator('de_DE@collation=phonebook');
        $pages    = [];

        foreach ($days as $day) {
            // Vorbereitungsräume dieses Tages (eindeutig, ohne null-Duplikate)
            $prepRoomIds = $day->dayRooms
                ->pluck('prep_room_id')
                ->filter()
                ->unique()
                ->values();

            if ($prepRoomIds->isEmpty()) {
                // Kein Vorbereitungsraum → Assignments nur dem Prüfungsraum zuordnen;
                // eine Sammelseite ohne Raumangabe
                $prepRoomIds = collect([null]);
            }

            // Assignments dieses Tages nach start_time gruppieren
            $dayAssignments = $assignments->where('zusatzpruef_day_id', $day->id);

            if ($dayAssignments->isEmpty()) {
                continue;
            }

            // Konfigurierte Timeslots sortiert
            $timeslots = $day->timeslots->sortBy('start_time')->values();

            // Alle Timeslot-Starttimes, die tatsächlich belegt sind (aus Assignments)
            $usedTimes = $dayAssignments->pluck('start_time')
                ->map(fn ($t) => substr((string) $t, 0, 5))
                ->unique()
                ->sort()
                ->values();

            $slotIndex = 1;

            foreach ($usedTimes as $startTime) {
                // Assignments dieses Timeslots
                $slotAssignments = $dayAssignments->filter(
                    fn ($a) => substr((string) $a->start_time, 0, 5) === $startTime
                );

                // Endzeit der Vorbereitung = start_time + prep_duration_minutes (max über alle Assignments)
                $maxPrep  = $slotAssignments->max('prep_duration_minutes');
                $prepEnd  = \Carbon\Carbon::createFromTimeString($startTime)
                    ->addMinutes((int) $maxPrep)
                    ->format('H:i');

                // Passende Aufsicht: Timeslot-Datensatz für diesen start_time
                $matchedTimeslot = $timeslots->first(
                    fn ($ts) => substr((string) $ts->start_time, 0, 5) === $startTime
                );

                foreach ($prepRoomIds as $prepRoomId) {
                    // Vorbereitungsraum-Objekt
                    $prepRoom = null;
                    if ($prepRoomId !== null) {
                        $prepRoom = \App\Models\Room::find($prepRoomId);
                    }

                    // Assignments, die diesem Vorbereitungsraum gehören
                    if ($prepRoomId !== null) {
                        $roomDayRoomIds = $day->dayRooms
                            ->where('prep_room_id', $prepRoomId)
                            ->pluck('id');
                        $pageAssignments = $slotAssignments->filter(
                            fn ($a) => $roomDayRoomIds->contains($a->zusatzpruef_day_room_id)
                        )->values();
                    } else {
                        $pageAssignments = $slotAssignments->values();
                    }

                    if ($pageAssignments->isEmpty()) {
                        continue;
                    }

                    // Alphabetisch sortieren (DIN 5007-2)
                    $sorted = $pageAssignments->sort(function ($a, $b) use ($collator) {
                        $zpA = $a->zusatzpruefung;
                        $zpB = $b->zusatzpruefung;

                        return $collator->compare(
                            $zpA instanceof Zusatzpruefung ? ($zpA->prueflingsName()) : '',
                            $zpB instanceof Zusatzpruefung ? ($zpB->prueflingsName()) : ''
                        );
                    })->values();

                    // Aufsicht für diesen Timeslot + Vorbereitungsraum ermitteln
                    $supervisor = null;
                    if ($matchedTimeslot !== null) {
                        // Zuerst: Aufsicht für genau diesen Timeslot + Raum
                        $aufsicht = $matchedTimeslot->aufsichten
                            ->where('zusatzpruef_day_room_id',
                                $day->dayRooms->where('prep_room_id', $prepRoomId)->first()?->id
                            )
                            ->first();
                        // Fallback: irgendeine Aufsicht dieses Timeslots
                        if ($aufsicht === null) {
                            $aufsicht = $matchedTimeslot->aufsichten->first();
                        }
                        $supervisor = $aufsicht?->teacher;
                    }

                    $pages[] = [
                        'date'        => $day->date,
                        'prepRoom'    => $prepRoom,
                        'prepStart'   => $startTime,
                        'prepEnd'     => $prepEnd,
                        'entries'     => $sorted,
                        'supervisor'  => $supervisor,
                        'slotNumber'  => $slotIndex,
                    ];
                }

                $slotIndex++;
            }
        }

        $pdf = Pdf::loadView('exports.zusatzpruef-vorbereitungsprotokoll-pdf', [
            'examPeriod' => $examPeriod,
            'pages'      => $pages,
            'school'     => $school,
        ])->setPaper('a4', 'portrait');

        $filename = 'ZP_Vorbereitungsprotokoll_' . $examPeriod->year . '_' . now()->format('Ymd_Hi') . '.pdf';

        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────
    // exportNiederschriftPdf
    // Je Prüfling zwei A4-Seiten: Deckblatt (Daten, Ausschuss, Ergebnis)
    // und Verlaufsseite (Aufgabenstellung, Fragen/Antworten).
    //
    // Filter (optional, kombinierbar):
    //   ?day_ids[]=1&day_ids[]=2   — nur diese Prüfungstage
    //   ?ids=4,7,9                 — nur diese Zusatzprüfungen
    // ─────────────────────────────────────────────────────────────────

    public function exportNiederschriftPdf(Request $request)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();
        $user       = auth()->user();
        $schoolId   = $user->school_id ?? \App\Models\School::first()?->school_number;
        $school     = $schoolId ? \App\Models\School::find($schoolId) : null;

        $selectedDayIds = $request->has('day_ids')
            ? array_map('intval', (array) $request->input('day_ids'))
            : null;

        $selectedIds = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        $entries = app(\App\Services\ZusatzpruefNiederschriftService::class)
            ->entries($examPeriod, $selectedDayIds, $selectedIds, $school);

        if ($entries === []) {
            return back()->with('error', 'Keine platzierten Zusatzprüfungen für den Export gefunden.');
        }

        $year = $examPeriod->year ?? date('Y');

        $pdf = Pdf::loadView('exports.zusatzpruef-niederschrift-pdf', [
            'entries'    => $entries,
            'examPeriod' => $examPeriod,
            'school'     => $school,
        ])->setPaper('a4', 'portrait');

        $filename = 'ZP_Niederschriften_' . $year . '_' . now()->format('Ymd_Hi') . '.pdf';

        return $pdf->download($filename);
    }

    public function exportMindestpunktePdf()
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        $user     = auth()->user();
        $schoolId = $user->school_id ?? \App\Models\School::first()?->school_number;
        $school   = $schoolId ? \App\Models\School::find($schoolId) : null;

        // Alle Prüflinge mit ZP
        $allZpPruefungen = Zusatzpruefung::where('exam_period_id', $examPeriod->id)
            ->with([
                'student',
                'studentExam.course.subject',
                'studentExam.externalExamSubject.subject',
                'studentExam.externalExamSubject.externalApplicant',
                'pruefer.teacher',
            ])
            ->get();

        if ($allZpPruefungen->isEmpty()) {
            return back()->with('error', 'Keine Zusatzprüfungen vorhanden.');
        }

        // Externe Bewerber bestehen nach § 62 GSO — ihre Gesamtqualifikation
        // entsteht allein aus den Prüfungen, ohne Halbjahresleistungen. Bis zum
        // Bau des ExterneMindestpunkteService wurden sie hier übersprungen und
        // nur auf einer Schlussseite benannt; jetzt bekommen sie ihre eigene
        // Seite mit der Rechnung, die zu ihnen gehört.
        //
        // Übrig bleibt die Schlussseite für die, bei denen die Fächerwahl die
        // acht Prüfungsfächer noch nicht vollständig belegt — dort ist nach
        // § 61 Abs. 1 S. 1 nichts zu rechnen.
        $externeRows = [];
        $uebersprungeneExterne = [];

        foreach ($allZpPruefungen->filter(fn (Zusatzpruefung $p) => $p->istExtern())
            ->groupBy(fn (Zusatzpruefung $p) => $p->studentExam->prueflingsSchluessel()) as $schluessel => $ihre) {
            $name = $ihre->first() instanceof Zusatzpruefung
                ? ($ihre->first()->prueflingsName())
                : '?';

            $rechnung = $this->externeRechnung((string) $schluessel, (int) $examPeriod->id);

            if ($rechnung === null) {
                $uebersprungeneExterne[] = $name;

                continue;
            }

            $externeRows[] = [
                'prueflingName' => $name,
                'pruefungen' => $ihre,
                'externe62' => $rechnung,
            ];
        }

        sort($uebersprungeneExterne);
        usort($externeRows, fn (array $a, array $b): int => strcmp($a['prueflingName'], $b['prueflingName']));

        $studentIds = $allZpPruefungen
            ->reject(fn (Zusatzpruefung $p) => $p->istExtern())
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values();

        // Die Fächer und die gespeicherten Noten holt AbiturExamDataService je
        // Prüfling. Das sind ein paar Abfragen mehr als das frühere Vorladen in
        // einem Rutsch — dafür gibt es die Abbildung nur noch einmal, und ein
        // PDF-Export läuft selten genug.
        $abiturDaten = app(\App\Services\AbiturExamDataService::class);

        // Gesamtpunktzahlen
        $deficits = ZusatzpruefStudentDeficit::where('exam_period_id', $examPeriod->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $service = new \App\Services\MindestpunkteService();

        // Pro Schüler: Daten aufbauen + berechnen
        $pruefungenByStudent = $allZpPruefungen
            ->reject(fn (Zusatzpruefung $p) => $p->istExtern())
            ->groupBy('student_id');
        $studentDataRows     = [];

        $sortiert = $pruefungenByStudent->sortBy(
            fn ($p) => $p->first() instanceof Zusatzpruefung ? ($p->first()->prueflingsName()) : ''
        );

        foreach ($sortiert as $studentId => $pruefungen) {
            $student = $pruefungen->first()->student;

            if (!$student instanceof Student) {
                continue;
            }

            // Die Fächer-Abbildung steht einmal, in AbiturExamDataService.
            // Bis zum 27.08.2026 hatte diese Methode ihre eigene Kopie, und die
            // war in drei Punkten anders: das Leistungsfach kam aus dem
            // Kurskürzel statt aus StudentExam::istLeistungsfach(), die
            // Praxisnote im Sport wurde nur für Fächer OHNE Zusatzprüfung
            // gesetzt, und 'improvement_only' fehlte ganz. Zwei Abbildungen
            // derselben Sache laufen auseinander — hier ist es passiert.
            $exams = $abiturDaten->examsFor($student, (int) $examPeriod->id);

            $computed = $service->compute($exams);

            // Verschlechterungs-Filter: Kombinationen ausblenden, bei denen
            // combined < written (analog zum hideWorsening-Toggle im Modal).
            // Die zpExams aus computed sind bereits korrekt nach sort_order sortiert.
            $zpOpenExams = $computed['zpExams'] ?? [];
            if (!empty($zpOpenExams) && $computed['allFixedScoresEntered']) {
                foreach (['combinations', 'passedCombinations', 'passedCombinationsN2', 'top5Combinations'] as $key) {
                    if ($computed[$key] !== null) {
                        $computed[$key] = $service->filterWorsening($computed[$key], $zpOpenExams);
                    }
                }
            }
            $totalPassedCount = count($computed['passedCombinations'] ?? []);

            $studentDataRows[] = [
                'student'          => $student,
                'exams'            => $exams,
                'computed'         => $computed,
                'pruefungen'       => $pruefungen,
                'curTotal'         => isset($deficits[$studentId]) ? $deficits[$studentId]->current_total_points : null,
                'service'          => $service,
                'totalPassedCount' => $totalPassedCount,
            ];
        }

        $pdf = Pdf::loadView('exports.zusatzpruef-mindestpunkte-pdf', compact(
            'examPeriod', 'school', 'studentDataRows', 'service', 'uebersprungeneExterne',
            'externeRows'
        ))->setPaper('a4', 'portrait');

        $filename = 'Zusatzpruefungen_Mindestpunkte_' . $examPeriod->year . '_' . now()->format('Ymd_Hi') . '.pdf';
        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────
    // Konflikt-Erkennung
    // ─────────────────────────────────────────────────────────────────

    private function checkStudentConflict(
        Zusatzpruefung $pruefung,
        int $dayId
    ): ?string {
        // Über den Prüflingsschlüssel, nicht über student_id: der ist bei
        // jedem externen Bewerber null. Ein `where('student_id', null)` wird
        // von Laravel zu `whereNull` — es würde alle Externen zu einer Person
        // verschmelzen und für jeden von ihnen den Tag eines anderen als
        // Konflikt melden.
        $alreadyOnDay = ZusatzpruefAssignment::where('zusatzpruef_day_id', $dayId)
            ->where('zusatzpruefung_id', '!=', $pruefung->id)
            ->whereHas('zusatzpruefung', fn ($q) =>
                $q->where('absent', false)
                  ->whereHas(
                      'studentExam',
                      fn ($se) => $se->desPrueflings($pruefung->prueflingsSchluessel())
                  ))
            ->exists();

        if ($alreadyOnDay) {
            return 'Dieser Prüfling hat an diesem Tag bereits eine Zusatzprüfung.';
        }

        return null;
    }

    private function checkExaminerConflict(
        Zusatzpruefung $pruefung,
        int $dayId,
        string $startTime,
        int $prepMinutes,
        int $examMinutes
    ): ?string {
        // Prüfer sind nur während der Prüfungszeit anwesend, nicht während der Vorbereitung
        $newExamStart = Carbon::createFromTimeString($startTime)->addMinutes($prepMinutes);
        $newExamEnd   = $newExamStart->copy()->addMinutes($examMinutes);

        $teacherIds = $pruefung->pruefer()->pluck('teacher_id');
        if ($teacherIds->isEmpty()) {
            return null;
        }

        $slotsOnDay = ZusatzpruefAssignment::where('zusatzpruef_day_id', $dayId)
            ->where('zusatzpruefung_id', '!=', $pruefung->id)
            ->whereHas('zusatzpruefung', fn($q) => $q->where('absent', false))
            ->whereHas('zusatzpruefung.pruefer', fn($q) =>
                $q->whereIn('teacher_id', $teacherIds))
            ->with(['zusatzpruefung.pruefer.teacher'])
            ->get();

        foreach ($slotsOnDay as $slot) {
            $slotExamStart = Carbon::createFromTimeString($slot->start_time)
                ->addMinutes($slot->prep_duration_minutes);
            $slotExamEnd   = $slotExamStart->copy()->addMinutes($slot->exam_duration_minutes);

            if ($newExamStart->lt($slotExamEnd) && $newExamEnd->gt($slotExamStart)) {
                $conflictPruefer = $slot->zusatzpruefung->pruefer
                    ->whereIn('teacher_id', $teacherIds->toArray())
                    ->first();
                $conflictTeacher = $conflictPruefer !== null ? $conflictPruefer->teacher : null;
                $name = $conflictTeacher !== null ? $conflictTeacher->shortcode : '?';
                return "Lehrkraft {$name} hat zu dieser Prüfungszeit bereits eine andere Zusatzprüfung.";
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // exportKomplettPdf — Fahrplan + Mindestpunkte + Anmeldezettel in einem PDF
    // ─────────────────────────────────────────────────────────────────

    public function exportKomplettPdf(\Illuminate\Http\Request $request)
    {
        // Prüfling = Schüler ("s<id>") oder externer Bewerber ("e<id>").
        $pruefling = (string) $request->string('pruefling');
        if (preg_match('/^[se]\d+$/', $pruefling) !== 1) {
            abort(404);
        }

        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();
        $settings   = \App\Models\SchoolSetting::allKeyed();
        $user       = auth()->user();
        $schoolId   = $user->school_id ?? \App\Models\School::first()?->school_number;
        $school     = $schoolId ? \App\Models\School::find($schoolId) : null;

        // Prüfungen mit allem (für Fahrplan + Anmeldezettel)
        $pruefungen = Zusatzpruefung::whereHas(
                'studentExam',
                fn ($q) => $q->desPrueflings($pruefling)
            )
            ->where('exam_period_id', $examPeriod->id)
            ->with([
                'studentExam.course.subject',
                'studentExam.externalExamSubject.subject',
                'studentExam.externalExamSubject.externalApplicant',
                'studentExam.examiner',
                'pruefer.teacher',
                'assignment.day',
            ])
            ->orderBy('sort_order')
            ->get();

        $ersteZp       = $pruefungen->first();
        $prueflingName = $ersteZp instanceof Zusatzpruefung ? ($ersteZp->prueflingsName()) : '?';
        $istExtern     = $ersteZp instanceof Zusatzpruefung && $ersteZp->istExtern();
        $ntaProzent    = $ersteZp instanceof Zusatzpruefung ? $ersteZp->nachteilsausgleichProzent() : 0;

        // Mindestpunkte-Berechnung (gleiche Logik wie exportMindestpunktePdf, nur 1 Prüfling)
        $service = new \App\Services\MindestpunkteService();

        $curTotal = null;
        if (!$istExtern) {
            $curTotal = ZusatzpruefStudentDeficit::where('exam_period_id', $examPeriod->id)
                ->where('student_id', (int) substr($pruefling, 1))
                ->first()
                ?->current_total_points;
        }

        // Dieselbe Abbildung wie überall sonst (AbiturExamDataService).
        // Die Kopie, die hier stand, trug bis zum 27.08.2026 die alte, falsche
        // Herleitung des Leistungsfachs für externe Bewerber:
        // getLevel() === 'erhoeht'. Nach § 61 Abs. 1 S. 5 sind Deutsch und
        // Mathematik immer eA, ohne Leistungsfach zu sein — dieses PDF zählte
        // einem Externen deshalb drei Leistungsfächer statt einem. Genau der
        // Befund aus Fehlerklasse 2 der Septemberrunde, an einer dritten
        // Stelle stehengeblieben.
        $exams = app(\App\Services\AbiturExamDataService::class)
            ->examsForPruefling($pruefling, (int) $examPeriod->id);

        $computed    = $service->compute($exams);
        $zpOpenExams = $computed['zpExams'] ?? [];
        if (!empty($zpOpenExams) && $computed['allFixedScoresEntered']) {
            foreach (['combinations', 'passedCombinations', 'passedCombinationsN2', 'top5Combinations'] as $key) {
                if ($computed[$key] !== null) {
                    $computed[$key] = $service->filterWorsening($computed[$key], $zpOpenExams);
                }
            }
        }
        // Gesamtzahl der bestandenen (nicht-verschlechternden) Kombinationen,
        // damit das PDF anzeigen kann, ob die 5 angezeigten Zeilen alle
        // möglichen Kombinationen sind oder nur eine Auswahl.
        $totalPassedCount = count($computed['passedCombinations'] ?? []);

        // Verbesserungsmodus: gilt aktuell einheitlich für den Schüler,
        // sobald mindestens eine seiner Zusatzprüfungen so markiert ist.
        $improvementOnly = $pruefungen->contains(fn (Zusatzpruefung $p) => $p->improvement_only);

        // Externe Bewerber bekommen ab jetzt ihre § 62-Rechnung statt eines
        // Hinweises. Bleibt sie null, ist die Fächerwahl noch nicht vollständig
        // — dann steht weiterhin der Hinweis dort.
        $externe62 = $istExtern ? $this->externeRechnung($pruefling, (int) $examPeriod->id) : null;

        $studentDataRows = [[
            'prueflingName'    => $prueflingName,
            'istExtern'        => $istExtern,
            'ntaProzent'       => $ntaProzent,
            'exams'            => $exams,
            'computed'         => $computed,
            'curTotal'         => $curTotal,
            'service'          => $service,
            'totalPassedCount' => $totalPassedCount,
            'improvementOnly'  => $improvementOnly,
            'externe62'        => $externe62,
        ]];

        $pdf = Pdf::loadView('exports.zusatzpruef-komplett-pdf', compact(
            'prueflingName', 'istExtern', 'ntaProzent', 'pruefungen', 'examPeriod',
            'settings', 'school', 'studentDataRows', 'service', 'improvementOnly',
            'externe62'
        ))->setPaper('a4', 'portrait');

        $dateiname = preg_replace('/[^A-Za-z0-9_-]/', '_', explode(',', $prueflingName)[0]);

        return $pdf->stream("komplett_{$dateiname}.pdf");
    }
}
