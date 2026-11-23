<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Schienen automatisch erkennen') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('timetable.tracks.store') }}">
                @csrf
                <input type="hidden" name="school_year" value="{{ $schoolYear }}">

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Vorschau für {{ $schoolYear }}</h3>
                                <p class="text-sm text-gray-500">Wählen Sie die Schienen aus, die Sie speichern möchten.</p>
                            </div>
                            <div class="flex space-x-3">
                                <a href="{{ route('timetable.tracks.index') }}" class="text-gray-600 hover:text-gray-900 px-4 py-2">
                                    Abbrechen
                                </a>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium">
                                    Auswahl speichern
                                </button>
                            </div>
                        </div>

                        @if($detected->isEmpty())
                            <div class="text-center py-12 text-gray-500">
                                Keine Schienen automatisch erkannt. Bitte prüfen Sie, ob der Stundenplan importiert wurde.
                            </div>
                        @else
                            @foreach($detected as $grade => $tracks)
                                <div class="mb-8">
                                    <h3 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">{{ $grade }}</h3>
                                    
                                    <div class="grid grid-cols-1 gap-4">
                                        @foreach($tracks as $track)
                                            <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                                <div class="flex items-start">
                                                    <div class="flex items-center h-5">
                                                        {{-- Prefix ID with grade to avoid collisions (e.g. Q12_track_1 vs Q13_track_1) --}}
                                                        <input type="checkbox" name="tracks[{{ $grade }}_{{ $track['id'] }}][selected]" value="1" checked class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                                    </div>
                                                    <div class="ml-3 text-sm flex-1">
                                                        <div class="flex justify-between">
                                                            <label class="font-medium text-gray-700">
                                                                {{-- Display all time slots as the main label --}}
                                                                {{ $track['all_time_slots'] }} ({{ $track['course_count'] }} Kurse)
                                                            </label>
                                                        </div>
                                                        
                                                        <!-- Hidden inputs for data -->
                                                        <input type="hidden" name="tracks[{{ $grade }}_{{ $track['id'] }}][grade]" value="{{ $grade }}">
                                                        <input type="hidden" name="tracks[{{ $grade }}_{{ $track['id'] }}][name]" value="{{ $track['all_time_slots'] }}">
                                                        <input type="hidden" name="tracks[{{ $grade }}_{{ $track['id'] }}][time_slots]" value="{{ json_encode($track['time_slots']) }}">
                                                        @foreach($track['courses'] as $courseCode)
                                                            <input type="hidden" name="tracks[{{ $grade }}_{{ $track['id'] }}][course_codes][]" value="{{ $courseCode }}">
                                                        @endforeach

                                                        <div class="mt-2 flex flex-wrap gap-1">
                                                            @foreach($track['courses'] as $courseCode)
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                                    {{ $courseCode }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
