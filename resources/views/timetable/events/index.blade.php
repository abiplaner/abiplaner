<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Schulinterne Termine verwalten
            </h2>
            <div class="flex space-x-4">
                <a href="{{ route('timetable.exam-schedule') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    ← Zurück zum Klausurenplan
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ showModal: false, editMode: false, currentEvent: {} }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Success Message --}}
            
            {{-- Filter & Add Button --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6">
                <div class="flex justify-between items-center">
                    <form method="GET" action="{{ route('timetable.events.index') }}" class="flex gap-4">
                        <div>
                            <label for="school_year" class="block text-sm font-medium text-gray-700 mb-2">Schuljahr</label>
                            <select name="school_year" id="school_year" class="rounded-md border-gray-300 shadow-sm" data-submit-on-change>
                                @foreach(\App\Support\SchoolYear::options($schoolYear) as $jahr)
                                    <option value="{{ $jahr }}" {{ $schoolYear === $jahr ? 'selected' : '' }}>{{ $jahr }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                    
                    @unlessarchive
                    <button @click="showModal = true; editMode = false; currentEvent = { school_year: '{{ $schoolYear }}', blocks_exams: true, affected_grades: ['Q12', 'Q13'] }" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                        + Neuer Termin
                    </button>
                    @endarchive
                </div>
            </div>

            {{-- Events Table --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Termine {{ $schoolYear }}</h3>
                    
                    @if($events->isEmpty())
                        <div class="text-center py-12 text-gray-500">
                            Keine schulinternen Termine gefunden.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Typ</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Datum</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jahrgangsstufen</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Blockiert Klausuren</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($events as $event)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $event->name }}</div>
                                                @if($event->description)
                                                    <div class="text-sm text-gray-500">{{ Str::limit($event->description, 50) }}</div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    {{ match($event->type) {
                                                        'veranstaltung' => 'bg-yellow-100 text-yellow-800',
                                                        'exkursion' => 'bg-green-100 text-green-800',
                                                        'pruefung' => 'bg-purple-100 text-purple-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    } }}">
                                                    {{ match($event->type) {
                                                        'veranstaltung' => 'Veranstaltung',
                                                        'exkursion' => 'Exkursion',
                                                        'pruefung' => 'Prüfung',
                                                        default => 'Sonstiges'
                                                    } }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if($event->end_date && !$event->start_date->isSameDay($event->end_date))
                                                    {{ $event->start_date->format('d.m.Y') }} - {{ $event->end_date->format('d.m.Y') }}
                                                @else
                                                    {{ $event->start_date->format('d.m.Y') }}
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if(empty($event->affected_grades))
                                                    <span class="text-gray-400 italic">Alle</span>
                                                @else
                                                    {{ implode(', ', $event->affected_grades) }}
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="{{ $event->blocks_exams ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                                    {{ $event->blocks_exams ? 'Ja' : 'Nein' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                @unlessarchive
                                                @php
                                                    $eventData = $event->toArray();
                                                    $eventData['start_date'] = $event->start_date->format('Y-m-d');
                                                    $eventData['end_date'] = $event->end_date ? $event->end_date->format('Y-m-d') : null;
                                                @endphp
                                                <button @click="editMode = true; showModal = true; currentEvent = {{ json_encode($eventData) }}" 
                                                        class="text-indigo-600 hover:text-indigo-900 mr-4">
                                                    Bearbeiten
                                                </button>
                                                <form action="{{ route('timetable.events.delete', $event) }}" method="POST" class="inline" 
                                                      data-confirm="Termin wirklich löschen?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        Löschen
                                                    </button>
                                                </form>
                                                @endarchive
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Info Box --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6 text-sm text-blue-800">
                <strong>💡 Hinweis:</strong> 
                <ul class="list-disc ml-5 mt-2">
                    <li><strong>Ferien und Feiertage</strong> werden zentral verwaltet und erscheinen automatisch im Klausurenplan</li>
                    <li>Hier werden nur <strong>schulspezifische Termine</strong> wie Exkursionen und Veranstaltungen verwaltet</li>
                    <li>Termine mit "Blockiert Klausuren = Ja" verhindern die Planung von Klausuren an diesen Tagen</li>
                    <li>Wählen Sie die betroffenen Jahrgangsstufen aus (oder lassen Sie leer für alle)</li>
                </ul>
            </div>

            {{-- Modal for Create/Edit --}}
            <div x-show="showModal" 
                 x-cloak
                 class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50"
                 @click.away="showModal = false">
                <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4" @click.stop>
                    <h3 class="text-lg font-semibold mb-4" x-text="editMode ? 'Termin bearbeiten' : 'Neuer Termin'"></h3>
                    
                    <form :action="editMode ? '/timetable/events/' + currentEvent.id : '{{ route('timetable.events.store') }}'" 
                          method="POST">
                        @csrf
                        <input type="hidden" :name="editMode ? '_method' : ''" :value="editMode ? 'PUT' : ''">
                        <input type="hidden" name="school_year" :value="currentEvent.school_year || '{{ $schoolYear }}'">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                <input type="text" name="name" x-model="currentEvent.name" 
                                       class="w-full rounded-md border-gray-300" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Typ *</label>
                                <select name="type" x-model="currentEvent.type" 
                                        class="w-full rounded-md border-gray-300" required>
                                    <option value="veranstaltung">Veranstaltung</option>
                                    <option value="exkursion">Exkursion</option>
                                    <option value="pruefung">Prüfung</option>
                                    <option value="sonstiges">Sonstiges</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Startdatum *</label>
                                <input type="date" name="start_date" x-model="currentEvent.start_date" 
                                       class="w-full rounded-md border-gray-300" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Enddatum (optional)</label>
                                <input type="date" name="end_date" x-model="currentEvent.end_date" 
                                       class="w-full rounded-md border-gray-300">
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                                <textarea name="description" x-model="currentEvent.description" 
                                          rows="3" class="w-full rounded-md border-gray-300"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Betroffene Jahrgangsstufen</label>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="affected_grades[]" value="Q12" 
                                               :checked="currentEvent.affected_grades && currentEvent.affected_grades.includes('Q12')"
                                               class="rounded border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Q12</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="affected_grades[]" value="Q13" 
                                               :checked="currentEvent.affected_grades && currentEvent.affected_grades.includes('Q13')"
                                               class="rounded border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Q13</span>
                                    </label>
                                    <p class="text-xs text-gray-500 mt-1">Leer lassen für alle Jahrgangsstufen</p>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Blockiert Klausuren?</label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="blocks_exams" value="1" 
                                           :checked="currentEvent.blocks_exams"
                                           class="rounded border-gray-300">
                                    <span class="ml-2 text-sm text-gray-700">Ja, Klausuren an diesem Tag verhindern</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-2 mt-6">
                            <button type="button" @click="showModal = false" 
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <span x-text="editMode ? 'Speichern' : 'Erstellen'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
