<?php

namespace App\Http\Controllers;

use App\Models\ExamBoardMeeting;
use App\Models\ExamBoardMeetingAttendee;
use App\Models\School;
use App\Models\Teacher;
use App\Services\CurrentExamPeriodService;
use App\Services\ExamBoardMinutesService;
use App\Services\ExamBoardMinutesWriter;
use Illuminate\Http\Request;

/**
 * Sitzungen des Abiturprüfungsausschusses und ihre Niederschriften (Modul 4.1).
 */
class ExamBoardMeetingController extends Controller
{
    public function index()
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        $meetings = ExamBoardMeeting::where('exam_period_id', $examPeriod->id)
            ->with(['chair', 'secretary', 'attendees.teacher'])
            ->orderBy('number')
            ->get();

        $teachers = Teacher::orderBy('lastname')->orderBy('firstname')->get();

        // Ergebnisübersicht als Vorschau: zeigt schon vor dem Export, was im
        // Protokoll stehen wird — und macht sichtbar, wenn Noten noch fehlen.
        $results = app(ExamBoardMinutesService::class)->results($examPeriod);

        // Vorschlag für die nächste Sitzung: Nummer hochzählen, Teilnehmerkreis
        // und Ort der letzten Sitzung übernehmen.
        $last = $meetings->last();

        return view('exam-board.index', compact('meetings', 'teachers', 'results', 'examPeriod', 'last'));
    }

    public function store(Request $request)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();
        $data = $this->validated($request);

        $meeting = ExamBoardMeeting::create(array_merge($data['meeting'], [
            'exam_period_id' => $examPeriod->id,
        ]));

        $this->syncAttendees($meeting, $data['attendee_ids'], $data['guests']);

        return redirect()->route('exam-board.index')
            ->with('success', 'Sitzung ' . $meeting->number . ' angelegt.');
    }

    public function update(Request $request, ExamBoardMeeting $examBoardMeeting)
    {
        $data = $this->validated($request, $examBoardMeeting);

        $examBoardMeeting->update($data['meeting']);
        $this->syncAttendees($examBoardMeeting, $data['attendee_ids'], $data['guests']);

        return redirect()->route('exam-board.index')
            ->with('success', 'Sitzung ' . $examBoardMeeting->number . ' gespeichert.');
    }

    public function destroy(ExamBoardMeeting $examBoardMeeting)
    {
        $number = $examBoardMeeting->number;
        $examBoardMeeting->delete();

        return redirect()->route('exam-board.index')
            ->with('success', 'Sitzung ' . $number . ' gelöscht.');
    }

    /**
     * Niederschrift als Word-Dokument.
     */
    public function exportDocx(ExamBoardMeeting $examBoardMeeting)
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();
        $examBoardMeeting->load(['chair', 'secretary', 'attendees.teacher']);

        $user     = auth()->user();
        $schoolId = $user->school_id ?? School::first()?->school_number;
        $school   = $schoolId !== null ? School::find($schoolId) : null;

        $path = app(ExamBoardMinutesWriter::class)->write($examBoardMeeting, $examPeriod, $school);

        $filename = sprintf(
            'APA_%d_Protokoll_%s.docx',
            $examBoardMeeting->number,
            (string) ($examPeriod->year ?? date('Y'))
        );

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    /**
     * @return array{meeting: array<string, mixed>, attendee_ids: list<int>, guests: list<string>}
     */
    private function validated(Request $request, ?ExamBoardMeeting $existing = null): array
    {
        $examPeriod = app(CurrentExamPeriodService::class)->currentOrFail();

        $rules = [
            'number'               => 'required|integer|min:1|max:99',
            'date'                 => 'required|date',
            'start_time'           => 'nullable|date_format:H:i',
            'end_time'             => 'nullable|date_format:H:i',
            'location'             => 'nullable|string|max:100',
            'chair_teacher_id'     => 'nullable|integer|exists:teachers,id',
            'secretary_teacher_id' => 'nullable|integer|exists:teachers,id',
            'agenda'               => 'nullable|array',
            'agenda.*'             => 'nullable|string|max:500',
            'attachments'          => 'nullable|array',
            'attachments.*'        => 'nullable|string|max:500',
            'notes'                => 'nullable|string',
            'attendee_ids'         => 'nullable|array',
            'attendee_ids.*'       => 'integer|exists:teachers,id',
            'guests'               => 'nullable|array',
            'guests.*'             => 'nullable|string|max:255',
        ];

        $validated = $request->validate($rules);

        // Sitzungsnummern sind je Jahrgang eindeutig — die Prüfung hier statt
        // per unique-Regel, weil die Tabelle in der Tenant-Verbindung liegt.
        $clash = ExamBoardMeeting::where('exam_period_id', $examPeriod->id)
            ->where('number', $validated['number'])
            ->when($existing !== null, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();

        if ($clash) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'number' => 'Für diesen Abiturjahrgang gibt es bereits eine Sitzung mit dieser Nummer.',
            ]);
        }

        $clean = fn (?array $list): array => array_values(array_filter(
            array_map(fn ($v) => trim((string) $v), $list ?? []),
            fn (string $v): bool => $v !== ''
        ));

        return [
            'meeting' => [
                'number'               => (int) $validated['number'],
                'date'                 => $validated['date'],
                'start_time'           => $validated['start_time'] ?? null,
                'end_time'             => $validated['end_time'] ?? null,
                'location'             => $validated['location'] ?? null,
                'chair_teacher_id'     => $validated['chair_teacher_id'] ?? null,
                'secretary_teacher_id' => $validated['secretary_teacher_id'] ?? null,
                'agenda'               => $clean($validated['agenda'] ?? []),
                'attachments'          => $clean($validated['attachments'] ?? []),
                'notes'                => $validated['notes'] ?? null,
            ],
            'attendee_ids' => array_map('intval', $validated['attendee_ids'] ?? []),
            'guests'       => $clean($validated['guests'] ?? []),
        ];
    }

    /**
     * @param  list<int>     $teacherIds
     * @param  list<string>  $guests
     */
    private function syncAttendees(ExamBoardMeeting $meeting, array $teacherIds, array $guests): void
    {
        ExamBoardMeetingAttendee::where('exam_board_meeting_id', $meeting->id)->delete();

        $order = 0;

        foreach ($teacherIds as $id) {
            ExamBoardMeetingAttendee::create([
                'exam_board_meeting_id' => $meeting->id,
                'teacher_id'            => $id,
                'sort_order'            => $order++,
            ]);
        }

        foreach ($guests as $name) {
            ExamBoardMeetingAttendee::create([
                'exam_board_meeting_id' => $meeting->id,
                'name_override'         => $name,
                'sort_order'            => $order++,
            ]);
        }
    }
}
