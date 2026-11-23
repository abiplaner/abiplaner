<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Terminkalender') }}
            </h2>
            <div class="flex gap-2">
                @unlessarchive
                @can('create', App\Models\CalendarEvent::class)
                    <a href="{{ route('calendar.create') }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition-colors duration-150">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Neuer Termin
                    </a>
                @endcan
                @endarchive
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif
            
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif
            
            <!-- iCal Feed Box -->
            <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Kalender-Abo (Google Calendar, Outlook, Apple Calendar)
                        </h3>
                        <p class="text-sm text-blue-800 dark:text-blue-200 mb-3">
                            Abonnieren Sie diesen Kalender in Ihrer bevorzugten Kalender-App. Alle Termine werden automatisch synchronisiert.
                        </p>
                        
                        @if(auth()->user()->school)
                            @php
                                // getCalendarToken() erzeugt beim ersten Aufruf automatisch
                                // ein Token (und speichert es), statt den Nutzer ins Leere
                                // laufen zu lassen, falls für die Schule noch keins existiert.
                                auth()->user()->school->getCalendarToken();
                                $feedUrls = auth()->user()->school->getCalendarFeedUrls();
                            @endphp
                            
                            <!-- Tab Navigation -->
                            <div x-data="{ activeTab: 'all' }" class="mb-4">
                                <div class="flex gap-2 mb-3 border-b border-blue-300 dark:border-blue-700">
                                    <button @click="activeTab = 'all'" 
                                            :class="activeTab === 'all' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-blue-700 dark:text-blue-300'"
                                            class="px-4 py-2 border-b-2 font-medium text-sm transition-colors">
                                        Alle Termine
                                    </button>
                                    <button @click="activeTab = '11'" 
                                            :class="activeTab === '11' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-blue-700 dark:text-blue-300'"
                                            class="px-4 py-2 border-b-2 font-medium text-sm transition-colors">
                                        11. Jahrgangsstufe
                                    </button>
                                    <button @click="activeTab = '12'" 
                                            :class="activeTab === '12' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-blue-700 dark:text-blue-300'"
                                            class="px-4 py-2 border-b-2 font-medium text-sm transition-colors">
                                        Q12
                                    </button>
                                    <button @click="activeTab = '13'" 
                                            :class="activeTab === '13' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-blue-700 dark:text-blue-300'"
                                            class="px-4 py-2 border-b-2 font-medium text-sm transition-colors">
                                        Q13
                                    </button>
                                </div>
                                
                                <!-- Tab Content -->
                                <div>
                                    <!-- Alle Termine -->
                                    <div x-show="activeTab === 'all'" x-cloak>
                                        <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">
                                            Kalender mit allen Terminen für Ihre Schule (alle Jahrgangsstufen)
                                        </p>
                                        <div class="flex items-center gap-2">
                                            <input type="text" 
                                                   id="calendar-feed-url-all"
                                                   value="{{ $feedUrls['all'] }}" 
                                                   readonly
                                                   class="flex-1 px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-blue-300 dark:border-blue-700 rounded-md text-gray-900 dark:text-gray-100 font-mono">
                                            <button data-copy-calendar="all" 
                                                    id="copy-btn-all"
                                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- 11. Jahrgangsstufe -->
                                    <div x-show="activeTab === '11'" x-cloak>
                                        <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">
                                            Kalender nur mit Terminen für die 11. Jahrgangsstufe
                                        </p>
                                        <div class="flex items-center gap-2">
                                            <input type="text" 
                                                   id="calendar-feed-url-11"
                                                   value="{{ $feedUrls[11] }}" 
                                                   readonly
                                                   class="flex-1 px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-blue-300 dark:border-blue-700 rounded-md text-gray-900 dark:text-gray-100 font-mono">
                                            <button data-copy-calendar="11" 
                                                    id="copy-btn-11"
                                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Q12 -->
                                    <div x-show="activeTab === '12'" x-cloak>
                                        <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">
                                            Kalender nur mit Terminen für Q12
                                        </p>
                                        <div class="flex items-center gap-2">
                                            <input type="text" 
                                                   id="calendar-feed-url-12"
                                                   value="{{ $feedUrls[12] }}" 
                                                   readonly
                                                   class="flex-1 px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-blue-300 dark:border-blue-700 rounded-md text-gray-900 dark:text-gray-100 font-mono">
                                            <button data-copy-calendar="12" 
                                                    id="copy-btn-12"
                                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Q13 -->
                                    <div x-show="activeTab === '13'" x-cloak>
                                        <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">
                                            Kalender nur mit Terminen für Q13 (inkl. Abiturprüfungen)
                                        </p>
                                        <div class="flex items-center gap-2">
                                            <input type="text" 
                                                   id="calendar-feed-url-13"
                                                   value="{{ $feedUrls[13] }}" 
                                                   readonly
                                                   class="flex-1 px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-blue-300 dark:border-blue-700 rounded-md text-gray-900 dark:text-gray-100 font-mono">
                                            <button data-copy-calendar="13" 
                                                    id="copy-btn-13"
                                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <p class="mt-3 text-xs text-blue-700 dark:text-blue-300">
                                    <strong>Anleitung:</strong> Kopieren Sie den Link und fügen Sie ihn in Ihrer Kalender-App als "Kalender-Abo" oder "URL-Kalender" hinzu.
                                </p>
                            </div>
                        @else
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                Kalender-Abo nicht verfügbar: Ihrem Konto ist keine Schule zugeordnet.
                            </p>
                        @endif
                    </div>
                    
                    @unlessarchive
                    @if(auth()->user()->school)
                        <form method="POST" action="{{ route('calendar.regenerate-token') }}" class="ml-4">
                            @csrf
                            <button type="submit" 
                                    data-confirm="Möchten Sie wirklich einen neuen Kalender-Link generieren? Alle alten Links werden ungültig."
                                    class="px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm rounded-md transition-colors"
                                    title="Neuen Link generieren">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </form>
                    @endif
                    @endarchive
                </div>
            </div>
            
            <script @cspNonce>
                // Frueher onclick am Knopf (Modul 7.2); die Funktion selbst
                // bleibt, sie wird nur nicht mehr aus dem HTML heraus gerufen.
                document.querySelectorAll('[data-copy-calendar]').forEach((knopf) => {
                    knopf.addEventListener('click', () => copyCalendarUrl(knopf.dataset.copyCalendar));
                });

                function copyCalendarUrl(grade) {
                    const input = document.getElementById('calendar-feed-url-' + grade);
                    input.select();
                    document.execCommand('copy');
                    
                    // Show feedback
                    const btn = document.getElementById('copy-btn-' + grade);
                    const originalHTML = btn.innerHTML;
                    btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                    }, 2000);
                }
            </script>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <!-- Schuljahr-Umschalter -->
                    <div class="mb-6 flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium mr-1">Schuljahr</span>
                        @foreach($schoolYearOptions as $option)
                            <a href="{{ route('calendar.index', array_merge(request()->except(['page', 'school_year']), ['school_year' => $option])) }}"
                               @if($option === $schoolYear) aria-current="page" @endif
                               class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors
                                      {{ $option === $schoolYear
                                          ? 'bg-blue-600 text-white'
                                          : 'bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200' }}">
                                {{ $option }}
                            </a>
                        @endforeach
                    </div>

                    <!-- Filter -->
                    <form method="GET" action="{{ route('calendar.index') }}" class="mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Event-Typ Filter -->
                            <div>
                                <label for="event_type" class="block text-sm font-medium mb-1">Typ</label>
                                <select name="event_type" id="event_type" 
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="">Alle Typen</option>
                                    <option value="deadline" {{ request('event_type') == 'deadline' ? 'selected' : '' }}>Fristen</option>
                                    <option value="exam_written" {{ request('event_type') == 'exam_written' ? 'selected' : '' }}>Schriftliche Prüfungen</option>
                                    <option value="exam_oral" {{ request('event_type') == 'exam_oral' ? 'selected' : '' }}>Mündliche Prüfungen</option>
                                    <option value="exam_practical" {{ request('event_type') == 'exam_practical' ? 'selected' : '' }}>Praktische Prüfungen</option>
                                    <option value="holiday" {{ request('event_type') == 'holiday' ? 'selected' : '' }}>Ferien</option>
                                    <option value="public_holiday" {{ request('event_type') == 'public_holiday' ? 'selected' : '' }}>Feiertage</option>
                                    <option value="grading" {{ request('event_type') == 'grading' ? 'selected' : '' }}>Notenabgabe</option>
                                    <option value="administration" {{ request('event_type') == 'administration' ? 'selected' : '' }}>Verwaltung</option>
                                </select>
                            </div>
                            
                            <!-- Jahrgangsstufe Filter -->
                            <div>
                                <label for="grade_level" class="block text-sm font-medium mb-1">Jahrgangsstufe</label>
                                <select name="grade_level" id="grade_level" 
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="">Alle Stufen</option>
                                    <option value="11" {{ request('grade_level') == '11' ? 'selected' : '' }}>11. Jahrgangsstufe</option>
                                    <option value="12" {{ request('grade_level') == '12' ? 'selected' : '' }}>Q12</option>
                                    <option value="13" {{ request('grade_level') == '13' ? 'selected' : '' }}>Q13</option>
                                </select>
                            </div>
                            
                            <!-- Monat Filter -->
                            <div>
                                <label for="month" class="block text-sm font-medium mb-1">Monat</label>
                                <select name="month" id="month" 
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="">Alle Monate</option>
                                    @php
                                        $months = [
                                            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
                                            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
                                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
                                        ];
                                    @endphp
                                    @foreach($months as $num => $name)
                                        <option value="{{ $num }}" {{ request('month') == $num ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Das Schuljahr bleibt beim Filtern erhalten (Umschalter oben). --}}
                        <input type="hidden" name="school_year" value="{{ $schoolYear }}">
                        
                        <div class="mt-4 flex gap-2">
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                                Filtern
                            </button>
                            <a href="{{ route('calendar.index', ['school_year' => $schoolYear]) }}" 
                               class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg">
                                Zurücksetzen
                            </a>
                        </div>
                    </form>

                    @if($events->isEmpty())
                        <div class="text-center py-8 text-gray-500">
                            <p class="text-lg">Keine Termine im Schuljahr {{ $schoolYear }} gefunden.</p>
                            <p class="mt-2">Erstellen Sie Ihren ersten Termin, passen Sie die Filter an oder wechseln Sie das Schuljahr.</p>
                        </div>
                    @else
                        <!-- Termine-Tabelle -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Datum
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Titel
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Typ
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Jahrgangsstufe
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Ort
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Aktionen
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($events as $event)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-3 h-3 rounded-full mr-2" style="background-color: {{ $event->color }}"></div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                            {{ $event->date_range }}
                                                        </div>
                                                        @if($event->start_time)
                                                            <div class="text-xs text-gray-500">
                                                                {{ \Illuminate\Support\Str::substr($event->start_time, 0, 5) }}
                                                                @if($event->end_time)
                                                                    - {{ \Illuminate\Support\Str::substr($event->end_time, 0, 5) }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $event->title }}
                                                </div>
                                                @if($event->description)
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        {{ Str::limit($event->description, 100) }}
                                                    </div>
                                                @endif
                                                @if($event->is_mandatory)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 mt-1">
                                                        Pflichttermin
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    {{ $event->event_type === 'holiday' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' : '' }}
                                                    {{ $event->event_type === 'exam_written' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : '' }}
                                                    {{ $event->event_type === 'exam_oral' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : '' }}
                                                    {{ $event->event_type === 'deadline' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300' : '' }}
                                                    {{ !in_array($event->event_type, ['holiday', 'exam_written', 'exam_oral', 'deadline']) ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}">
                                                    {{ $event->event_type_name }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $event->grade_levels_name }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $event->location ?? '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex justify-end gap-2">
                                                    @if($event->is_global ?? false)
                                                        <span class="text-xs text-gray-400 dark:text-gray-500 italic">Globaler Termin</span>
                                                    @else
                                                        @unlessarchive
                                                        <a href="{{ route('calendar.edit', $event) }}" 
                                                           class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                            Bearbeiten
                                                        </a>
                                                        <form method="POST" action="{{ route('calendar.destroy', $event) }}" 
                                                              data-confirm="Termin wirklich löschen?"
                                                              class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" 
                                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                                Löschen
                                                            </button>
                                                        </form>
                                                        @endarchive
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $events->links() }}
                        </div>
                    @endif
                    
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
