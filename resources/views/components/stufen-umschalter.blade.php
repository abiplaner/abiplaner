{{--
    Umschalter Q13/Q12 für Seiten mit ?stufe= (Schülerliste, Fächerübersicht).

    route:  Name der Route, auf die die Knöpfe verlinken
    stufe:  gewählte Stufe als String ('13' oder '12')
    anzahl: Schülerzahl je Stufe, ['13' => …, '12' => …]
--}}
@props(['route', 'stufe', 'anzahl'])

<div class="mb-4 flex items-center gap-3">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Jahrgangsstufe:</span>
    <div class="flex rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
        {{-- PHP macht aus den Schlüsseln '13'/'12' Ganzzahlen; $stufe ist ein
             String. Ohne (string) war kein Knopf je als gewählt markiert. --}}
        @foreach(['13' => 'Q13 (Abiturjahrgang)', '12' => 'Q12'] as $s => $beschriftung)
            @php $gewaehlt = $stufe === (string) $s; @endphp
            <a href="{{ route($route, ['stufe' => $s]) }}"
               @class([
                   'px-4 py-2 text-sm font-medium flex items-center gap-2 transition-colors',
                   'border-l border-gray-300 dark:border-gray-600' => !$loop->first,
                   'bg-indigo-600 text-white font-semibold shadow-inner' => $gewaehlt,
                   'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600' => !$gewaehlt,
               ])
               @if($gewaehlt) aria-current="page" @endif>
                @if($gewaehlt)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                @endif
                {{ $beschriftung }}
                <span @class([
                    'px-1.5 py-0.5 rounded text-xs',
                    'bg-white/25 text-white' => $gewaehlt,
                    'bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300' => !$gewaehlt,
                ])>{{ $anzahl[$s] ?? 0 }}</span>
            </a>
        @endforeach
    </div>
</div>
