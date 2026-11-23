{{-- Ein Klausur-/Prüfungseintrag im Klausurplan. Erwartet $exam und $currentDay. --}}
@php
    $hasConflict = $exam->notes && str_contains($exam->notes, 'Konflikt');
    $isHighlighted = $exam->is_highlighted ?? false;
    $isMultiDay = $exam->isMultiDay();
    $isFirstDay = $isMultiDay && $exam->exam_date->isSameDay($currentDay['date']);
    $isMuendlich = in_array($exam->exam_type, ['muendlich', 'kolloquium']);
    $isKurzarbeit = $exam->exam_type === 'kurzarbeit';
    $examTypeLabel = match($exam->exam_type) {
        'kolloquium' => 'Prakt./Mdl.',
        'muendlich' => 'Mdl.',
        'kurzarbeit' => 'KA',
        default => ''
    };
    $textClass = $hasConflict ? 'text-red-700' : ($isMuendlich ? 'text-blue-900' : ($isKurzarbeit ? 'text-emerald-900' : 'text-gray-900'));

    $examBgClass = '';
    if ($hasConflict) {
        $examBgClass = 'bg-red-50 border-l-4 border-red-500 pl-2';
    } elseif ($isHighlighted) {
        $examBgClass = 'bg-yellow-50 border-l-4 border-yellow-400 pl-2 shadow-sm';
    } elseif ($isMuendlich) {
        $examBgClass = 'bg-blue-50 border-l-4 border-blue-500 pl-2';
    } elseif ($isKurzarbeit) {
        $examBgClass = 'bg-emerald-50 border-l-4 border-emerald-500 pl-2';
    }
@endphp
<div 
    class="mb-1.5 last:mb-0 {{ $examBgClass }} group relative cursor-move hover:shadow-sm p-1 rounded transition-colors {{ $isMultiDay ? 'border-2 border-dashed border-blue-300' : '' }}"
    draggable="true"
    x-on:dragstart="dragStartEntry($event, {{ $exam }})"
    x-on:dragend="dragEnd($event)"
>
    {{-- Multi-day indicator --}}
    @if($isMultiDay && !$isFirstDay)
        <div class="text-xs text-blue-600 font-medium mb-0.5">
            → Fortsetzung
        </div>
    @endif

    @if(is_array($exam->course_codes) && count($exam->course_codes) > 0)
        <div class="font-medium {{ $textClass }}"{!! $isKurzarbeit ? ' title="Kurzarbeit"' : '' !!}>
            @if($hasConflict) <span class="text-red-500 font-bold">!</span> @endif
            @if($examTypeLabel !== '') {{ $examTypeLabel }} @endif
            {{ implode(', ', $exam->course_codes) }} 
            @if($exam->time_slot)
                ({{ $exam->time_slot }})
            @endif
        </div>
    @else
        <div class="font-medium {{ $textClass }}"{!! $isKurzarbeit ? ' title="Kurzarbeit"' : '' !!}>
            @if($hasConflict) <span class="text-red-500 font-bold">!</span> @endif
            @if($examTypeLabel !== '') {{ $examTypeLabel }} @endif
            {{ $exam->subject ?? 'Klausur' }} 
            @if($exam->time_slot)
                ({{ $exam->time_slot }})
            @endif
        </div>
    @endif

    {{-- Show date range for multi-day exams on first day --}}
    @if($isMultiDay && $isFirstDay)
        <div class="text-xs text-blue-700 font-medium mt-0.5">
            {{ $exam->date_range }} ({{ $exam->getDurationDays() }} Tage)
        </div>
    @endif

    @if($exam->notes)
        <div class="text-xs {{ $hasConflict ? 'text-red-600 font-medium' : 'text-gray-600 italic' }} mt-0.5">
            {{ $exam->notes }}
        </div>
    @endif

    {{-- Actions --}}
    <div class="absolute top-1 right-1 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">
        <button type="button" 
            x-on:click.stop="openCommentModal({{ $exam->id }}, '{{ addslashes($exam->notes ?? '') }}')" 
            class="bg-white bg-opacity-75 rounded p-0.5 text-gray-500 hover:text-blue-600 transition-colors" 
            title="Kommentar">
            <svg class="w-4 h-4 {{ $exam->notes ? 'text-blue-600 fill-current' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
        </button>
        <input type="checkbox" 
            class="cursor-pointer w-4 h-4 text-yellow-600 bg-white bg-opacity-75 border-gray-300 rounded focus:ring-yellow-500" 
            x-on:click.stop="toggleHighlight({{ $exam->id }}, $event.target.checked)" 
            {{ $isHighlighted ? 'checked' : '' }} 
            title="Als Änderung markieren" />
        <button type="button" 
            class="text-gray-400 hover:text-red-600 transition-colors bg-white bg-opacity-75 rounded-full p-0.5" 
            x-on:click.stop="deleteAssignment({{ $exam->id }})" 
            title="Löschen">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
        </button>
    </div>
</div>
