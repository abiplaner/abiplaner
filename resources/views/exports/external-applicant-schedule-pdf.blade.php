<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Prüfungsplan - {{ $applicant->full_name }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }
        
        h1 {
            font-size: 18pt;
            margin-bottom: 5px;
            color: #1a1a1a;
        }
        
        h2 {
            font-size: 14pt;
            margin-top: 25px;
            margin-bottom: 12px;
            color: #1e40af;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 3px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #1e40af;
            padding-bottom: 15px;
        }
        
        .subtitle {
            font-size: 11pt;
            color: #666;
            margin-top: 5px;
        }
        
        .applicant-info {
            background-color: #eff6ff;
            padding: 12px;
            margin-bottom: 25px;
            border-left: 4px solid #1e40af;
        }
        
        .info-row {
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: bold;
            color: #4b5563;
            display: inline-block;
            width: 120px;
        }
        
        .info-value {
            color: #1a1a1a;
        }
        
        .external-badge {
            background-color: #1e40af;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            margin-left: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 9pt;
        }
        
        th {
            background-color: #dbeafe;
            color: #1f2937;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #93c5fd;
        }
        
        td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
        }
        
        tr:nth-child(even) {
            background-color: #eff6ff;
        }
        
        .subject-number {
            width: 30px;
            text-align: center;
            font-weight: bold;
            color: #1e40af;
        }
        
        .subject-name {
            font-weight: bold;
        }
        
        .level-advanced {
            color: #dc2626;
            font-weight: bold;
        }
        
        .level-basic {
            color: #2563eb;
        }
        
        .leistungsfach-badge {
            background-color: #dc2626;
            color: white;
            padding: 2px 6px;
            border-radius: 2px;
            font-size: 7pt;
            margin-left: 5px;
        }
        
        .date-cell {
            white-space: nowrap;
        }
        
        .time-cell {
            white-space: nowrap;
        }
        
        .room-cell {
            text-align: center;
        }
        
        .not-scheduled {
            color: #dc2626;
            font-style: italic;
        }
        
        .scheduled {
            color: #059669;
        }
        
        .notes-section {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        
        .notes-title {
            font-weight: bold;
            color: #92400e;
            margin-bottom: 8px;
            font-size: 11pt;
        }
        
        .note-item {
            margin-bottom: 5px;
            color: #78350f;
            font-size: 9pt;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 5px;
        }
        
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Prüfungsplan für externe Bewerber</h1>
        <div class="subtitle">Abiturprüfung {{ $applicant->exam_year }}{{ $school ? ' - ' . $school->name : '' }}</div>
        <div class="subtitle" style="font-weight: bold; color: #1e40af;">§61 GSO - Externe Abiturprüfung</div>
    </div>

    <div class="applicant-info">
        <div class="info-row">
            <span class="info-label">Bewerber/in:</span>
            <span class="info-value">{{ $applicant->full_name }}</span>
            <span class="external-badge">EXTERN</span>
        </div>
        <div class="info-row">
            <span class="info-label">Geburtsdatum:</span>
            <span class="info-value">{{ $applicant->birth_date->format('d.m.Y') }}</span>
        </div>
        @if($applicant->email)
        <div class="info-row">
            <span class="info-label">E-Mail:</span>
            <span class="info-value">{{ $applicant->email }}</span>
        </div>
        @endif
        @if($applicant->phone)
        <div class="info-row">
            <span class="info-label">Telefon:</span>
            <span class="info-value">{{ $applicant->phone }}</span>
        </div>
        @endif
    </div>

    {{-- ERSTER PRÜFUNGSTEIL: SCHRIFTLICHE PRÜFUNGEN --}}
    <h2>Erster Prüfungsteil: Schriftliche Prüfungen (4 Fächer)</h2>
    <table>
        <thead>
            <tr>
                <th class="subject-number">Nr.</th>
                <th>Fach</th>
                <th>Niveau</th>
                <th class="date-cell">Datum</th>
                <th class="time-cell">Uhrzeit</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($writtenExams as $exam)
            <tr>
                <td class="subject-number">{{ $exam->subject_number }}</td>
                @php $fach = $faecher[$exam->position()] ?? null; @endphp
                <td class="subject-name">
                    {{ $fach['name'] ?? '—' }}
                    {{-- Das Leistungsfach, nicht das erhöhte Niveau: nach
                         § 61 Abs. 1 S. 5 sind Deutsch und Mathematik immer eA,
                         ohne deshalb Leistungsfach zu sein.

                         Das Abzeichen hing zuerst an der Spiegelspalte
                         `is_leistungsfach` (bfc9166) und war damit richtig. Als
                         die Spalte am 26.08.2026 fiel (bffbc95), wurde es auf
                         `ea` umgehängt — seither trug jeder Ausdruck drei
                         Leistungsfächer statt einem. Dieselbe Verwechslung wie
                         in der Blockbildung (Fehlerklasse 2 der
                         Septemberrunde), gefunden beim Durchklicken am
                         27.08.2026. Das Niveau steht ohnehin in der nächsten
                         Spalte. --}}
                    @if($fach['leistungsfach'] ?? false)
                        <span class="leistungsfach-badge">LF</span>
                    @endif
                </td>
                <td class="{{ ($fach['ea'] ?? false) ? 'level-advanced' : 'level-basic' }}">
                    {{ ($fach['ea'] ?? false) ? 'Erhöhtes Niveau (eA)' : 'Grundlegendes Niveau (gA)' }}
                </td>
                <td class="date-cell">
                    @if($exam->studentExam?->writtenExamDate)
                        {{ $exam->studentExam->writtenExamDate->date->format('d.m.Y') }}
                    @else
                        <span class="not-scheduled">Noch nicht geplant</span>
                    @endif
                </td>
                <td class="time-cell">
                    @if($exam->studentExam?->writtenExamDate)
                        {{ $exam->studentExam->writtenExamDate->getStartTime()->format('H:i') }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if($exam->studentExam?->writtenExamDate)
                        <span class="scheduled">✓ Geplant</span>
                    @else
                        <span class="not-scheduled">Ausstehend</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ZWEITER PRÜFUNGSTEIL: MÜNDLICHE PRÜFUNGEN --}}
    <h2>Zweiter Prüfungsteil: Mündliche Prüfungen (4 Fächer)</h2>
    <table>
        <thead>
            <tr>
                <th class="subject-number">Nr.</th>
                <th>Fach</th>
                <th class="date-cell">Datum</th>
                <th class="time-cell">Uhrzeit</th>
                <th class="room-cell">Raum</th>
                <th>Prüfer</th>
                <th>Beisitzer</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($oralExams as $exam)
            <tr>
                <td class="subject-number">{{ $exam->subject_number }}</td>
                @php $fach = $faecher[$exam->position()] ?? null; @endphp
                <td class="subject-name">
                    {{ $fach['name'] ?? '—' }}
                    {{-- § 61 Abs. 3 S. 7: **die zweite Fremdsprache** wird auf dem
                         Niveau einer spät beginnenden geprüft. Das hing bis zum
                         27.08.2026 am Fach-Kennzeichen `can_be_late_starting` —
                         das sagt nur, dass ein Fach spät beginnend angeboten
                         werden *kann*, und stand deshalb auch an einer
                         fortgeführten Sprache. Seit Entscheidung 32 benennt der
                         Prüfling die zweite Fremdsprache; danach richtet sich
                         der Hinweis. --}}
                    @if($fach['zweite_fremdsprache'] ?? false)
                        <span style="font-size: 8pt; color: #6b7280;">(2. Fremdsprache, Niveau spät beg.)</span>
                    @endif
                </td>
                {{-- § 64 Abs. 2: in diesem Fach findet keine Prüfung statt, das
                     Ergebnis kommt aus 13/II der Ersatzschule. Ohne diese Weiche
                     stünde hier sechsmal „—" und „Ausstehend" — als wäre ein
                     Termin vergessen worden. --}}
                @if($exam->istEingebracht())
                <td colspan="6" style="color: #374151;">
                    Ergebnis aus <strong>13/II</strong> der Ersatzschule
                    @if($applicant->ersatzschule)
                        ({{ $applicant->ersatzschule->name }})
                    @endif
                    @if($exam->eingebrachte_punktzahl !== null)
                        — <strong>{{ $exam->eingebrachte_punktzahl }} Punkte</strong> in einfacher Wertung
                    @else
                        — <span class="not-scheduled">Punktzahl fehlt</span>
                    @endif
                    <span style="font-size: 8pt; color: #6b7280;">· § 64 Abs. 2, Anlage 15</span>
                </td>
                @else
                <td class="date-cell">
                    @if($exam->studentExam?->assignment?->timeSlot)
                        {{ $exam->studentExam->assignment->timeSlot->date->locale('de')->translatedFormat('D, d.m.Y') }}
                    @else
                        <span class="not-scheduled">Noch nicht geplant</span>
                    @endif
                </td>
                <td class="time-cell">
                    @if($exam->studentExam?->assignment?->timeSlot)
                        {{ $exam->studentExam->assignment->timeSlot->exam_start_time }}
                    @else
                        —
                    @endif
                </td>
                <td class="room-cell">
                    @if($exam->studentExam?->assignment?->examRoom)
                        {{ $exam->studentExam->assignment->examRoom->room_number }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if($exam->studentExam?->examiner)
                        {{ $exam->studentExam->examiner->shortcode }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if($exam->studentExam?->assessor)
                        {{ $exam->studentExam->assessor->shortcode }}
                    @else
                        <span style="color: #f59e0b;">Noch offen</span>
                    @endif
                </td>
                <td>
                    @if($exam->studentExam?->assignment)
                        <span class="scheduled">✓ Geplant</span>
                    @else
                        <span class="not-scheduled">Ausstehend</span>
                    @endif
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- WICHTIGE HINWEISE --}}
    <div class="notes-section">
        <div class="notes-title">WICHTIGE HINWEISE</div>
        <div class="note-item">• Erscheinen Sie mindestens 30 Minuten vor Prüfungsbeginn am Prüfungsort</div>
        <div class="note-item">• Bringen Sie einen gültigen Lichtbildausweis mit</div>
        <div class="note-item">• Mobiltelefone und elektronische Geräte sind während der Prüfung nicht erlaubt</div>
        <div class="note-item">• Mündliche Prüfungen: 30 Minuten Prüfungszeit + 30 Minuten Vorbereitungszeit</div>
        <div class="note-item">• Schriftliche Prüfungen: Beachten Sie die offiziellen Ministeriumsvorgaben zur Prüfungsdauer</div>
        <div class="note-item">• Bei Fragen oder Änderungen wenden Sie sich bitte rechtzeitig an die Schulverwaltung</div>
    </div>

    <div class="footer">
        Erstellt am {{ now()->format('d.m.Y H:i') }} Uhr | Seite <span class="page-number"></span>
    </div>
</body>
</html>
