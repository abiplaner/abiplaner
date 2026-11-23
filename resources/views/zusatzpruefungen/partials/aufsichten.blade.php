{{-- Aufsichten-Bereich unterhalb der Matrix --}}
<div class="mt-4 bg-white border border-gray-200 rounded-lg shadow-sm" x-data="{ open: false }">
    <button @click="open = !open"
        class="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 rounded-lg">
        <span>Aufsichten Vorbereitungsräume</span>
        <span class="text-gray-400 text-xs" x-text="open ? '▲' : '▼'"></span>
    </button>

    <div x-show="open" x-cloak class="px-4 pb-4">
        @foreach($days as $day)
            @php
                $roomsWithPrep = $day->dayRooms->filter(fn($dr) => $dr->prep_room_id !== null);
            @endphp
            @if($roomsWithPrep->isEmpty()) @continue @endif

            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">
                    {{ $day->label ?? $day->date->locale('de')->translatedFormat('l, d.m.Y') }}
                </h3>

                @foreach($roomsWithPrep as $dayRoom)
                    <div class="mb-3 ml-4">
                        <div class="text-xs text-gray-500 mb-1">
                            Vorbereitungsraum: {{ $dayRoom->prepRoom?->room_number ?? '–' }}
                        </div>

                        {{-- Bestehende Aufsichten --}}
                        @foreach($dayRoom->aufsichten as $auf)
                            <div class="flex items-center gap-2 mb-1 text-sm">
                                <span class="text-gray-700">{{ $auf->teacher?->shortcode }} {{ $auf->teacher?->lastname }}</span>
                                @unlessarchive
                                <form method="POST" action="{{ route('zusatzpruefungen.aufsichten.destroy', $auf) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-600 text-xs">×</button>
                                </form>
                                @endarchive
                            </div>
                        @endforeach

                        {{-- Neue Aufsicht hinzufügen --}}
                        @unlessarchive
                        <form method="POST" action="{{ route('zusatzpruefungen.aufsichten.store') }}" class="flex gap-2 items-center mt-1">
                            @csrf
                            <input type="hidden" name="zusatzpruef_day_id" value="{{ $day->id }}">
                            <input type="hidden" name="zusatzpruef_day_room_id" value="{{ $dayRoom->id }}">
                            <select name="teacher_id" required class="text-xs border-gray-300 rounded py-1">
                                <option value="">– Lehrkraft –</option>
                                @foreach($teachers as $t)
                                    <option value="{{ $t->id }}">{{ $t->shortcode }} {{ $t->lastname }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded border border-gray-300">
                                Hinzufügen
                            </button>
                        </form>
                        @endarchive
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
