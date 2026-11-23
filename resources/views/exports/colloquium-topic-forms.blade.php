<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kolloquium – Inhaltliche Kopplungen</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #222;
            margin: 14mm 12mm;
        }
        .course-page {
            page-break-after: always;
        }
        .course-page:last-child {
            page-break-after: auto;
        }
        .sheet-title {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .sheet-sub {
            font-size: 8.5pt;
            color: #555;
            margin-bottom: 12px;
        }
        .course-header {
            background: #374151;
            color: #fff;
            padding: 6px 10px;
            font-size: 11pt;
            font-weight: bold;
        }
        .course-meta {
            background: #f3f4f6;
            padding: 5px 10px;
            font-size: 8.5pt;
            color: #374151;
            border: 1px solid #d1d5db;
            border-top: none;
            margin-bottom: 12px;
        }
        .course-meta span { margin-right: 20px; }
        .section-title {
            font-size: 9.5pt;
            font-weight: bold;
            margin: 12px 0 5px 0;
        }
        table { width: 100%; border-collapse: collapse; }
        /* Bei langen Kursen (bis 15 Prüflinge) kann die Liste auf eine zweite
           Seite laufen. Dann muss der Tabellenkopf mitwandern, sonst steht die
           Spalte "Gruppe" dort ohne Beschriftung. */
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th {
            background: #e5e7eb;
            color: #374151;
            padding: 4px 8px;
            text-align: left;
            font-size: 8pt;
            font-weight: bold;
            border: 1px solid #d1d5db;
        }
        td {
            padding: 5px 8px;
            font-size: 8.5pt;
            border: 1px solid #d1d5db;
            vertical-align: top;
        }
        .topic-slot {
            width: 8%;
            color: #6b7280;
            text-align: center;
        }
        .topic-empty {
            color: #9ca3af;
            font-style: italic;
        }
        .group-cell {
            height: 18px;
        }
        .hint {
            font-size: 8pt;
            color: #555;
            margin-top: 10px;
            padding: 6px 8px;
            background: #f9fafb;
            border-left: 3px solid #9ca3af;
        }
        .sign-row {
            margin-top: 22px;
            font-size: 8.5pt;
            color: #555;
        }
        .sign-line {
            border-top: 1px solid #9ca3af;
            width: 60mm;
            margin-top: 22px;
            padding-top: 3px;
        }
    </style>
</head>
<body>

@forelse ($courses as $course)
    @php
        $exams = $course->studentExams
            ->sortBy(fn ($exam) => [$exam->student?->lastname, $exam->student?->firstname]);

        $examiners = $course->studentExams
            ->map(fn ($exam) => $exam->examiner?->lastname)
            ->filter()
            ->unique()
            ->implode(', ');

        $first  = $course->colloquiumTopics->where('semester', '12/1')->sortBy('slot');
        $second = $course->colloquiumTopics->where('semester', '12/2')->sortBy('slot');
    @endphp

    <div class="course-page">
        <div class="sheet-title">Kolloquium – Inhaltliche Kopplungen</div>
        <div class="sheet-sub">
            {{ $school?->name ?? '' }} · Abitur {{ $examPeriod->year }}
        </div>

        <div class="course-header">
            Kurs {{ $course->course_code }} – {{ $course->subject?->name ?? '' }}
        </div>
        <div class="course-meta">
            <span><strong>Erstprüfer:</strong> {{ $examiners !== '' ? $examiners : ($course->teacher?->lastname ?? '–') }}</span>
            <span><strong>Prüflinge:</strong> {{ $exams->count() }}</span>
        </div>

        <div class="section-title">Themenbereiche</div>
        <table>
            <thead>
                <tr>
                    <th style="width:8%;">Nr.</th>
                    <th style="width:46%;">Halbjahr 12/1</th>
                    <th style="width:46%;">Halbjahr 12/2</th>
                </tr>
            </thead>
            <tbody>
                @for ($slot = 1; $slot <= 3; $slot++)
                    @php
                        $topicFirst  = $first->firstWhere('slot', $slot);
                        $topicSecond = $second->firstWhere('slot', $slot);
                    @endphp
                    <tr>
                        <td class="topic-slot" style="width:8%;">{{ $slot }}</td>
                        <td style="width:46%;">
                            @if ($topicFirst)
                                {{ $topicFirst->title }}
                            @else
                                <span class="topic-empty">&nbsp;</span>
                            @endif
                        </td>
                        <td style="width:46%;">
                            @if ($topicSecond)
                                {{ $topicSecond->title }}
                            @else
                                <span class="topic-empty">&nbsp;</span>
                            @endif
                        </td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <div class="section-title">Prüflinge und Kopplung</div>
        <table>
            <thead>
                <tr>
                    <th style="width:6%;">Nr.</th>
                    <th style="width:44%;">Name</th>
                    <th style="width:14%;">Gruppe</th>
                    <th style="width:36%;">Bemerkung</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($exams as $index => $exam)
                    <tr>
                        <td class="topic-slot" style="width:6%;">{{ $index + 1 }}</td>
                        <td style="width:44%;">
                            {{ $exam->student?->lastname }}, {{ $exam->student?->firstname }}
                        </td>
                        <td class="group-cell" style="width:14%;">&nbsp;</td>
                        <td style="width:36%;">&nbsp;</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="hint">
            <strong>Hinweis zur Kopplung:</strong>
            Prüflinge, die inhaltlich gemeinsam geprüft werden sollen, erhalten in der Spalte
            „Gruppe“ denselben Buchstaben (A, B, C …). Prüflinge ohne Eintrag werden einzeln
            geprüft.
        </div>

        <div class="sign-row">
            <div class="sign-line">Datum, Unterschrift Erstprüfer/in</div>
        </div>
    </div>
@empty
    <div class="sheet-title">Kolloquium – Inhaltliche Kopplungen</div>
    <p style="font-size:9pt;">
        Für den Jahrgang {{ $examPeriod->year }} konnten keine Formulare erzeugt werden.
        Gedruckt werden nur Kurse, für die sowohl Kolloquiumsprüfungen als auch
        Themenbereiche erfasst sind – bitte zuerst den Themenkatalog einlesen.
    </p>
@endforelse

</body>
</html>
