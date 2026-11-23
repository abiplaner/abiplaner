{{-- Sidebar-Karte für eine ungeplante Zusatzprüfung --}}
@php
    $student    = $pruefung->student;
    $exam       = $pruefung->studentExam;
    $subject    = $exam?->getSubjectName() ?? '–';
    $code       = $exam?->getSubjectLabel() ?? '';
    // Nachteilsausgleich hängt am Prüfling — Schüler wie externer Bewerber.
    $nta        = $pruefung->hatNachteilsausgleich();
    $ntaPct     = $pruefung->nachteilsausgleichProzent();
    $prueferStr = $pruefung->pruefer->map(fn($p) => $p->teacher?->shortcode ?? '–')->implode(', ');

    $sortOrder   = $pruefung->sort_order ?? 0;
    $orderColors = [
        1 => ['bg' => '#dbeafe', 'text' => '#1d4ed8', 'border' => '#93c5fd'],
        2 => ['bg' => '#dcfce7', 'text' => '#15803d', 'border' => '#86efac'],
        3 => ['bg' => '#f3e8ff', 'text' => '#7e22ce', 'border' => '#d8b4fe'],
    ];
    $orderColor = ($sortOrder >= 1 && $sortOrder <= 3) ? $orderColors[$sortOrder] : null;

    $pruefungData = [
        'id'        => $pruefung->id,
        'subject'   => $subject,
        'subjectId' => $exam?->fachAusKatalog()?->id,
        'student'   => $pruefung->prueflingsName(),
        'nta'       => $nta,
        'ntaPct'    => $ntaPct,
        'slot'      => null,
        'pruefer'   => $pruefung->pruefer->map(fn($p) => [
            'teacher_id'   => $p->teacher_id,
            'role'         => $p->role,
            'teacher_name' => $p->teacher ? $p->teacher->lastname . ', ' . mb_substr($p->teacher->firstname, 0, 1) . '. (' . $p->teacher->shortcode . ')' : '–',
        ])->values()->toArray(),
    ];
@endphp

<div class="mb-2 bg-white rounded shadow-sm zp-card {{ $nta ? 'nta-card' : '' }}"
     draggable="true"
     @dragstart="onDragStart($event, {{ $pruefung->id }})"
     style="border: 1px solid {{ $orderColor ? $orderColor['border'] : '#e5e7eb' }}; {{ $orderColor ? 'border-left: 3px solid ' . $orderColor['text'] . ';' : '' }}">

    <div class="p-2">
        <div class="flex items-start justify-between gap-1">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-0.5">
                    @if($orderColor)
                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full text-xs font-bold leading-none shrink-0"
                              style="background: {{ $orderColor['bg'] }}; color: {{ $orderColor['text'] }};">{{ $sortOrder }}</span>
                    @endif
                    <span class="text-sm font-semibold text-gray-800 truncate">{{ $pruefung->prueflingsName() }}</span>
                    @if($pruefung->istExtern())
                        <span class="text-xs bg-purple-100 text-purple-700 px-1 py-0.5 rounded leading-none shrink-0"
                              title="Externer Bewerber nach § 61 GSO">ext</span>
                    @endif
                </div>
                <div class="text-xs text-gray-600">{{ $subject }} <span class="text-gray-400">{{ $code }}</span></div>
                @if($nta)
                    <span class="inline-block text-xs px-1.5 py-0.5 bg-yellow-100 text-yellow-700 rounded mt-0.5">
                        NTA +{{ $ntaPct }} %
                    </span>
                @endif
                @if($pruefung->improvement_only)
                    <span class="inline-block text-xs px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded mt-0.5">
                        🎯 Verbesserung
                    </span>
                @endif
                @if($prueferStr)
                    <div class="text-xs text-gray-400 mt-0.5">{{ $prueferStr }}</div>
                @endif
            </div>
            @unlessarchive
            <div class="flex flex-col items-end gap-1 shrink-0">
                <button
                    @click.stop="openDrawer({{ json_encode($pruefungData) }})"
                    class="text-xs text-blue-500 hover:text-blue-700 leading-none" title="Prüfer bearbeiten">✏</button>
                <form method="POST" action="{{ route('zusatzpruefungen.destroy', $pruefung) }}"
                    data-confirm="Zusatzprüfung wirklich löschen?">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-red-400 hover:text-red-600 leading-none" title="Entfernen">×</button>
                </form>
            </div>
            @endarchive
        </div>
    </div>
</div>
