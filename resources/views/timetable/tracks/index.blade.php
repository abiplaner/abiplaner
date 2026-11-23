<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Schienen-Verwaltung') }}
            </h2>
            <div class="flex space-x-4">
                <a href="{{ route('timetable.exam-schedule') }}" class="text-gray-600 hover:text-gray-900">
                    ← Zurück zur Klausurenplanung
                </a>
                @unlessarchive
                <a href="{{ route('timetable.tracks.create', ['school_year' => $schoolYear]) }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    + Neue Schiene erstellen
                </a>
                <button data-modal-toggle="asv-import-form" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    ASV-Schienen importieren
                </button>
                @endarchive
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- ASV Import Form (hidden by default) -->
            <div id="asv-import-form" class="hidden bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">ASV-Schienen importieren</h3>
                    <form method="POST" action="{{ route('timetable.tracks.import-asv') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="school_year" value="{{ $schoolYear }}">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                            <div>
                                <label for="stdschie_file" class="block text-sm font-medium text-gray-700 mb-2">
                                    STDSCHIE.txt <span class="text-red-500">*</span>
                                </label>
                                <input type="file" id="stdschie_file" name="stdschie_file" accept=".txt,.csv" required
                                    class="block w-full text-sm text-gray-500
                                    file:mr-4 file:py-2 file:px-4
                                    file:rounded-md file:border-0
                                    file:text-sm file:font-semibold
                                    file:bg-blue-50 file:text-blue-700
                                    hover:file:bg-blue-100">
                                <p class="mt-1 text-xs text-gray-500">Schienendefinitionen</p>
                            </div>
                            
                            <div>
                                <label for="stdkursschie_file" class="block text-sm font-medium text-gray-700 mb-2">
                                    STDKURSSCHIE.txt <span class="text-red-500">*</span>
                                </label>
                                <input type="file" id="stdkursschie_file" name="stdkursschie_file" accept=".txt,.csv" required
                                    class="block w-full text-sm text-gray-500
                                    file:mr-4 file:py-2 file:px-4
                                    file:rounded-md file:border-0
                                    file:text-sm file:font-semibold
                                    file:bg-blue-50 file:text-blue-700
                                    hover:file:bg-blue-100">
                                <p class="mt-1 text-xs text-gray-500">Kurszuordnungen</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" data-modal-close="asv-import-form" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                Abbrechen
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium">
                                Importieren
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="GET" action="{{ route('timetable.tracks.index') }}" class="flex items-center space-x-4">
                        <div>
                            <label for="school_year" class="block text-sm font-medium text-gray-700">Schuljahr</label>
                            <select id="school_year" name="school_year" class="mt-1 block w-full py-2 pl-3 pr-10 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" data-submit-on-change>
                                @foreach($schoolYears as $jahr)
                                    <option value="{{ $jahr }}" {{ $schoolYear === $jahr ? 'selected' : '' }}>{{ $jahr }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    @foreach($errors->all() as $fehler)
                        <p>{{ $fehler }}</p>
                    @endforeach
                </div>
            @endif

            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <span class="font-bold">Tipp:</span> Sie können Kurse per Drag & Drop zwischen den Schienen verschieben, um die Zuordnung zu ändern.
                        </p>
                    </div>
                </div>
            </div>

            @if($tracks->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200 text-center text-gray-500">
                        <p class="mb-4">Noch keine Schienen für dieses Schuljahr vorhanden.</p>
                        <button data-modal-open="asv-import-form" class="text-blue-600 hover:text-blue-800 font-medium">
                            Jetzt aus ASV-Export importieren
                        </button>
                    </div>
                </div>
            @else
                @foreach($tracks as $grade => $gradeTracks)
                    <h3 class="text-lg font-medium text-gray-900 mb-4 mt-8">{{ $grade }}</h3>
                    <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Zeitfenster</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kurse</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($gradeTracks as $track)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $track->name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @php
                                                $timeSlotDisplay = '';
                                                if (is_array($track->time_slots)) {
                                                    $slots = $track->time_slots;
                                                    $first = reset($slots);
                                                    if (is_array($first) || is_object($first)) {
                                                        $parts = [];
                                                        foreach ($slots as $slot) {
                                                            if ((is_array($slot) || is_object($slot)) && isset($slot['day_short'], $slot['periods'])) {
                                                                $periods = is_array($slot['periods']) ? implode('/', $slot['periods']) : $slot['periods'];
                                                                $parts[] = $slot['day_short'] . ' ' . $periods . '.';
                                                            }
                                                        }
                                                        $timeSlotDisplay = implode(', ', $parts);
                                                    } else {
                                                        $timeSlotDisplay = implode(', ', $slots);
                                                    }
                                                }
                                            @endphp
                                            {{ $timeSlotDisplay ?: '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 min-h-[50px] transition-colors duration-200" 
                                            id="track-courses-{{ $track->id }}"
                                            data-track-id="{{ $track->id }}">
                                            <div class="flex flex-wrap gap-1 max-w-md min-h-[30px]">
                                                @foreach($track->course_codes as $code)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 cursor-move hover:bg-gray-200 hover:shadow"
                                                          draggable="true"
                                                          data-drag-code="{{ $code }}"
                                                          data-drag-track="{{ $track->id }}">
                                                        {{ $code }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            @unlessarchive
                                            <div class="flex justify-end space-x-3">
                                                <a href="{{ route('timetable.tracks.edit', $track) }}" class="text-indigo-600 hover:text-indigo-900" title="Bearbeiten">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </a>
                                                <form method="POST" action="{{ route('timetable.tracks.destroy', $track) }}" data-confirm="Schiene wirklich löschen?" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-500 hover:text-red-700" title="Löschen">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                            @endarchive
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>

<script @cspNonce>
    // Frueher ondragover/ondragleave/ondrop an der Zelle und ondragstart an
    // der Kursmarke (Modul 7.2). Die Zelle traegt ohnehin data-track-id, das
    // reicht als Anker; Drag-Ereignisse steigen wie Klicks auf.
    document.addEventListener('dragover', (ereignis) => {
        if (ereignis.target.closest('[data-track-id]')) {
            allowDrop(ereignis);
        }
    });

    document.addEventListener('dragleave', (ereignis) => {
        if (ereignis.target.closest('[data-track-id]')) {
            leaveDrop(ereignis);
        }
    });

    document.addEventListener('drop', (ereignis) => {
        const zelle = ereignis.target.closest('[data-track-id]');

        if (zelle) {
            drop(ereignis, Number(zelle.dataset.trackId));
        }
    });

    document.addEventListener('dragstart', (ereignis) => {
        const marke = ereignis.target.closest('[data-drag-code]');

        if (marke) {
            drag(ereignis, marke.dataset.dragCode, Number(marke.dataset.dragTrack));
        }
    });

    function allowDrop(ev) {
        ev.preventDefault();
        const targetTd = ev.target.closest('td');
        if (targetTd) {
            targetTd.classList.add('bg-blue-50');
        }
    }

    function leaveDrop(ev) {
        const targetTd = ev.target.closest('td');
        if (targetTd) {
            targetTd.classList.remove('bg-blue-50');
        }
    }

    function drag(ev, courseCode, sourceTrackId) {
        ev.dataTransfer.setData("text/plain", JSON.stringify({
            courseCode: courseCode,
            sourceTrackId: sourceTrackId
        }));
        ev.dataTransfer.effectAllowed = "move";
    }

    function drop(ev, targetTrackId) {
        ev.preventDefault();
        
        const targetTd = ev.target.closest('td');
        if (targetTd) {
            targetTd.classList.remove('bg-blue-50');
        }

        const data = JSON.parse(ev.dataTransfer.getData("text/plain"));
        const courseCode = data.courseCode;
        const sourceTrackId = data.sourceTrackId;

        // Don't do anything if dropped on same track
        if (sourceTrackId === targetTrackId) {
            return;
        }

        // Optimistic UI update could be done here, but let's just reload or move element
        // For now, let's call the API and reload on success to be safe and simple
        
        fetch('{{ route('timetable.tracks.move-course') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                source_track_id: sourceTrackId,
                target_track_id: targetTrackId,
                course_code: courseCode
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => { throw new Error(data.message || 'Error moving course'); });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Fehler beim Verschieben des Kurses: ' + error.message);
        });
    }
</script>
