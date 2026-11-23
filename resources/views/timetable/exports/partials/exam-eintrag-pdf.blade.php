{{-- Ein Klausur-/Prüfungseintrag im Klausurplan-PDF. Erwartet $exam. --}}
@php
    $isMuendlich = in_array($exam->exam_type, ['muendlich', 'kolloquium']);
    $isHighlighted = $exam->is_highlighted ?? false;

    $examTypeLabel = match($exam->exam_type) {
        'kolloquium' => 'Prakt./Mdl. Prüfung',
        'muendlich' => 'Mdl. Schulaufgabe',
        'kurzarbeit' => 'KA',
        default => ''
    };

    $divStyle = "font-size: 10px; margin-bottom: 1px; padding: 1px 2px; border-radius: 2px;";
    if ($isHighlighted) {
        $divStyle .= " background-color: #fefcbf; border-left: 2px solid #ecc94b;"; // Yellow highlight
    }

    if ($isMuendlich) {
        $divStyle .= " color: #2563eb;";
    } elseif ($exam->exam_type === 'kurzarbeit') {
        $divStyle .= " color: #047857;";
    } else {
        $divStyle .= " color: #9b2c2c;";
    }
@endphp
<div style="{{ $divStyle }}">
    <strong>
        @if($examTypeLabel !== '')
            {{ $examTypeLabel }}
        @endif
        @if(is_array($exam->course_codes) && count($exam->course_codes) > 0)
            {{ implode(', ', $exam->course_codes) }}
        @else
            {{ $exam->subject ?? 'Klausur' }}
        @endif
    </strong> 
    @if($exam->time_slot)
        ({{ $exam->time_slot }})
    @endif
    @if($exam->start_time)
        <br><span style="font-size: 9px;">{{ \Carbon\Carbon::parse($exam->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($exam->end_time)->format('H:i') }}</span>
    @endif
    @if($exam->notes)
        @php
            // Filter out the conflict message for oral exams
            $displayNotes = $exam->notes;
            if ($isMuendlich) {
                $displayNotes = str_replace('⚠️ Konflikt: Kein Unterricht an diesem Wochentag!', '', $displayNotes);
            }
        @endphp
        @if(trim($displayNotes))
            <br><span style="font-size: 9px; font-style: italic; color: #4a5568;">{{ trim($displayNotes) }}</span>
        @endif
    @endif
</div>
