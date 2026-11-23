@php use App\Models\Teacher; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Lehrkräfte
            </h2>
            <div class="flex gap-2">
                @unlessarchive
                @can('viewAny', Teacher::class)
                    <button type="button" data-open-unavailability
                            class="inline-flex items-center px-4 py-2 bg-orange-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-600">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        Abwesenheiten
                    </button>
                @endcan
                @can('create', Teacher::class)
                    <a href="{{ route('teachers.create') }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Neue Lehrkraft
                    </a>
                @endcan
                @endarchive
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Die Erfolgsmeldung gibt bereits layouts/app.blade.php aus; hier stand sie ein zweites Mal (28.08.2026). --}}

            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Stundenplan Import Info --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <svg class="w-6 h-6 mr-3 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-blue-900 mb-2">Kombinierter Import: Stundenplan & Lehrerdaten</h3>
                        <p class="text-sm text-blue-800 mb-3">
                            Importieren Sie gleichzeitig den <strong>Untis-Stundenplan</strong> und die <strong>ASV-Lehrerdaten</strong>. 
                            Die Daten werden automatisch abgeglichen, um eine hohe Datenqualität sicherzustellen.
                        </p>
                        <ul class="text-sm text-blue-700 list-disc list-inside mb-3 space-y-1">
                            <li>Vervollständigung der Lehrernamen aus ASV</li>
                            <li>Abgleich von Kürzeln zwischen beiden Systemen</li>
                            <li>Automatische Erkennung von Unstimmigkeiten</li>
                        </ul>
                        <a href="{{ route('timetable.upload') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Kombinierter Import starten →
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    
                    <!-- Such- und Filterformular -->
                    <div class="mb-6">
                        <form method="GET" action="{{ route('teachers.index') }}" class="flex gap-4 items-end">
                            <div class="flex-1">
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Suche</label>
                                <input type="text" 
                                       name="search" 
                                       id="search" 
                                       value="{{ request('search') }}"
                                       placeholder="Kürzel, Name oder Titel..."
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="flex-1">
                                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Fach</label>
                                <select name="subject" 
                                        id="subject"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Alle Fächer</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject->id }}" {{ request('subject') == $subject->id ? 'selected' : '' }}>
                                            {{ $subject->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex-1">
                                <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Sortieren</label>
                                <select name="sort_by" 
                                        id="sort_by"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="shortcode" {{ request('sort_by', 'shortcode') == 'shortcode' ? 'selected' : '' }}>Kürzel</option>
                                    <option value="lastname" {{ request('sort_by') == 'lastname' ? 'selected' : '' }}>Nachname</option>
                                    <option value="hours" {{ request('sort_by') == 'hours' ? 'selected' : '' }}>UPZ</option>
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Filter anwenden
                                </button>
                                <a href="{{ route('teachers.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    Zurücksetzen
                                </a>
                            </div>
                        </form>
                    </div>

                    @if($teachers->count() > 0)
                        <!-- Bulk-Delete-Formular -->
                        <form id="bulk-delete-form" method="POST" action="{{ route('teachers.bulk-destroy') }}">
                            @csrf
                            
                            <!-- Bulk-Aktionen Toolbar -->
                            @unlessarchive
                            <div id="bulk-actions-toolbar" class="mb-4 p-3 bg-gray-100 rounded-md hidden">
                                <div class="flex justify-between items-center">
                                    <span id="selected-count" class="text-sm text-gray-700">0 ausgewählt</span>
                                    @can('create', \App\Models\Teacher::class)
                                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                            Ausgewählte löschen
                                        </button>
                                    @endcan
                                </div>
                            </div>
                            @endarchive

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            @unlessarchive
                                            @can('create', \App\Models\Teacher::class)
                                                <th class="px-6 py-3 text-left">
                                                    <input type="checkbox" 
                                                           id="select-all" 
                                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                           data-toggle-all-teachers>
                                                </th>
                                            @endcan
                                            @endarchive
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Kürzel
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Name
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Amtsbezeichnung
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Geschl.
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Typ
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                UPZ
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Fächer
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Aktionen
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($teachers as $teacher)
                                            <tr class="hover:bg-gray-50 {{ $teacher->is_active ? '' : 'opacity-50' }}">
                                                @unlessarchive
                                                @can('create', \App\Models\Teacher::class)
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <input type="checkbox" 
                                                               name="teacher_ids[]" 
                                                               value="{{ $teacher->id }}" 
                                                               class="teacher-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                               data-update-bulk-toolbar>
                                                    </td>
                                                @endcan
                                                @endarchive
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $teacher->shortcode }}
                                                    @unless($teacher->is_active)
                                                        <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-gray-200 text-gray-600">inaktiv</span>
                                                    @endunless
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $teacher->lastname }}, {{ $teacher->firstname }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $teacher->title ?? '-' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    @if($teacher->gender === 'm')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">m</span>
                                                    @elseif($teacher->gender === 'w')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-pink-100 text-pink-700">w</span>
                                                    @elseif($teacher->gender === 'd')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">d</span>
                                                    @else
                                                        <span class="text-gray-300">–</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $teacher->employment_type ?? '-' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $teacher->hours ? number_format($teacher->hours, 1, ',', '.') : '-' }}
                                                </td>
                                                <td class="px-6 py-4 text-sm">
                                                    @if($teacher->subjects->count() > 0)
                                                        <div class="flex flex-wrap gap-1">
                                                            @foreach($teacher->subjects as $subject)
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                    {{ $subject->code }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex items-center gap-2">
                                                        {{-- Stundenplan --}}
                                                        <a href="{{ route('teachers.timetable', $teacher) }}"
                                                           title="Stundenplan"
                                                           class="text-green-600 hover:text-green-900">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                        </a>
                                                        @unlessarchive
                                                        {{-- Bearbeiten --}}
                                                        @can('update', $teacher)
                                                            <a href="{{ route('teachers.edit', [$teacher, 'page' => request('page')]) }}"
                                                               title="Bearbeiten"
                                                               class="text-blue-600 hover:text-blue-900">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                            </a>
                                                        @endcan
                                                        {{-- Löschen --}}
                                                        @can('delete', $teacher)
                                                            <button type="button"
                                                                    data-delete-teacher="{{ $teacher->id }}"
                                                                    title="Löschen"
                                                                    class="text-red-600 hover:text-red-900">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </button>
                                                        @endcan
                                                        @endarchive
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </form>
                        
                        <!-- Individuelle Delete-Formulare (AUSSERHALB des Bulk-Forms) -->
                        @foreach($teachers as $teacher)
                            @can('delete', $teacher)
                                <form id="delete-form-{{ $teacher->id }}" 
                                      action="{{ route('teachers.destroy', $teacher) }}" 
                                      method="POST" 
                                      class="hidden">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @endcan
                        @endforeach
                        
                        <div class="mt-6">
                            {{ $teachers->links() }}
                        </div>
                    @else
                        <p class="text-gray-500">Keine Lehrkräfte gefunden.</p>
                    @endif
                    
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Modal: Abwesenheiten-Übersicht ===== --}}
    <div id="unavailability-modal"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden"
         role="dialog" aria-modal="true">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50" data-close-unavailability></div>

        {{-- Panel --}}
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    Abwesenheiten – Kolloquiumsplanung
                </h2>
                <button type="button" data-close-unavailability
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Toolbar --}}
            <div class="px-6 py-3 bg-gray-50 border-b border-gray-200 flex items-center gap-4 flex-wrap">
                <div class="flex-1 min-w-48">
                    <select id="modal-exam-period" data-load-unavailabilities
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-orange-500 focus:border-orange-500">
                        <option value="">– Prüfungszeitraum wählen –</option>
                    </select>
                </div>
                <a id="modal-pdf-link" href="#" target="_blank"
                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded-md hover:bg-red-700 transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    PDF exportieren
                </a>
            </div>

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto px-6 py-4" id="modal-content">
                <div id="modal-loading" class="flex items-center justify-center py-16 text-gray-400 gap-3">
                    <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 1116 0A8 8 0 014 12z"/>
                    </svg>
                    Wird geladen …
                </div>
                <div id="modal-empty" class="hidden py-16 text-center text-gray-400 italic text-sm">
                    Keine Abwesenheiten für diesen Prüfungszeitraum eingetragen.
                </div>
                <table id="modal-table" class="hidden min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kürzel</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lehrkraft</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Abwesenheitstage</th>
                        </tr>
                    </thead>
                    <tbody id="modal-tbody" class="bg-white divide-y divide-gray-200">
                    </tbody>
                </table>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-3 border-t border-gray-200 flex justify-between items-center text-xs text-gray-500">
                <span id="modal-summary"></span>
                <button type="button" data-close-unavailability
                        class="px-4 py-2 text-sm bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                    Schließen
                </button>
            </div>
        </div>
    </div>

    <script @cspNonce>
        // ===== Modal-Logik =====
        const overviewUrl  = '{{ route('teachers.unavailability.overview') }}';
        const pdfBaseUrl   = '{{ route('teachers.unavailability.overview.pdf') }}';

        // Frueher Inline-Handler (Modul 7.2). Die Funktionen bleiben, sie
        // werden nur nicht mehr aus dem HTML heraus gerufen. Bewusst direkt am
        // Element gebunden statt ueber document: deleteTeacher() stoppt die
        // Weitergabe des Klicks, und das wirkt nur an Ort und Stelle.
        document.querySelectorAll('[data-open-unavailability]').forEach(
            (el) => el.addEventListener('click', openUnavailabilityModal));
        document.querySelectorAll('[data-close-unavailability]').forEach(
            (el) => el.addEventListener('click', closeUnavailabilityModal));
        document.querySelectorAll('[data-toggle-all-teachers]').forEach(
            (el) => el.addEventListener('change', () => toggleAllCheckboxes(el)));
        document.querySelectorAll('[data-update-bulk-toolbar]').forEach(
            (el) => el.addEventListener('change', updateBulkToolbar));
        document.querySelectorAll('[data-load-unavailabilities]').forEach(
            (el) => el.addEventListener('change', () => loadUnavailabilities(el.value)));
        document.querySelectorAll('[data-delete-teacher]').forEach(
            (el) => el.addEventListener('click',
                (ereignis) => deleteTeacher(ereignis, Number(el.dataset.deleteTeacher))));

        function openUnavailabilityModal() {
            document.getElementById('unavailability-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            // Erste Ladung falls noch nicht geschehen
            if (document.getElementById('modal-exam-period').options.length <= 1) {
                loadUnavailabilities('');
            }
        }

        function closeUnavailabilityModal() {
            document.getElementById('unavailability-modal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        // ESC-Taste schließt Modal
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeUnavailabilityModal();
        });

        async function loadUnavailabilities(examPeriodId) {
            // UI zurücksetzen
            document.getElementById('modal-loading').classList.remove('hidden');
            document.getElementById('modal-table').classList.add('hidden');
            document.getElementById('modal-empty').classList.add('hidden');
            document.getElementById('modal-summary').textContent = '';

            const url = overviewUrl + (examPeriodId ? '?exam_period_id=' + encodeURIComponent(examPeriodId) : '');

            try {
                const res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();

                // Prüfungszeitraum-Dropdown befüllen (nur beim ersten Laden)
                const sel = document.getElementById('modal-exam-period');
                if (sel.options.length <= 1 && data.examPeriods) {
                    sel.innerHTML = '<option value="">– Prüfungszeitraum wählen –</option>';
                    data.examPeriods.forEach(ep => {
                        const opt = document.createElement('option');
                        opt.value = ep.id;
                        opt.textContent = ep.name + ' (' + ep.year + ')';
                        if (data.activeExamPeriod && ep.id === data.activeExamPeriod.id) opt.selected = true;
                        sel.appendChild(opt);
                    });
                } else if (data.activeExamPeriod) {
                    sel.value = data.activeExamPeriod.id;
                }

                // PDF-Link aktualisieren
                const activId = data.activeExamPeriod ? data.activeExamPeriod.id : '';
                document.getElementById('modal-pdf-link').href = pdfBaseUrl + (activId ? '?exam_period_id=' + activId : '');

                document.getElementById('modal-loading').classList.add('hidden');

                const tbody = document.getElementById('modal-tbody');
                tbody.innerHTML = '';

                const unavails = data.unavailabilities;
                const teachers = data.teachers;
                const teacherIds = Object.keys(unavails);

                if (teacherIds.length === 0) {
                    document.getElementById('modal-empty').classList.remove('hidden');
                    return;
                }

                // Sortiert nach Kürzel
                const sorted = teacherIds.sort((a, b) => {
                    const ta = teachers[a]?.shortcode ?? 'zzz';
                    const tb = teachers[b]?.shortcode ?? 'zzz';
                    return ta.localeCompare(tb);
                });

                let totalDays = 0;
                sorted.forEach(tid => {
                    const t = teachers[tid] ?? { shortcode: '?', name: '?' };
                    const entries = unavails[tid];
                    totalDays += entries.length;

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-gray-50';

                    // Chip-Liste der Daten
                    const chips = entries.map(e => {
                        const d = e.date;
                        const wd = e.weekday;
                        const r  = e.reason ? (' – ' + e.reason) : '';
                        return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 mr-1 mb-1" title="${wd}, ${d}${r}">${d}${e.reason ? ' <span class=\'text-orange-500\'>('+e.reason+')</span>' : ''}</span>`;
                    }).join('');

                    tr.innerHTML = `
                        <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-900">${t.shortcode}</td>
                        <td class="px-4 py-3 text-gray-700">${t.name}</td>
                        <td class="px-4 py-3"><div class="flex flex-wrap gap-1">${chips}</div></td>
                    `;
                    tbody.appendChild(tr);
                });

                document.getElementById('modal-table').classList.remove('hidden');
                document.getElementById('modal-summary').textContent =
                    totalDays + ' Abwesenheit(en) von ' + sorted.length + ' Lehrkraft(en)';

            } catch (err) {
                document.getElementById('modal-loading').classList.add('hidden');
                document.getElementById('modal-empty').classList.remove('hidden');
                document.getElementById('modal-empty').textContent = 'Fehler beim Laden der Daten.';
                console.error(err);
            }
        }
    </script>

    <script @cspNonce>
        function toggleAllCheckboxes(selectAllCheckbox) {
            const checkboxes = document.querySelectorAll('.teacher-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateBulkToolbar();
        }

        function updateBulkToolbar() {
            const checkboxes = document.querySelectorAll('.teacher-checkbox:checked');
            const count = checkboxes.length;
            const toolbar = document.getElementById('bulk-actions-toolbar');
            const countSpan = document.getElementById('selected-count');
            
            if (count > 0) {
                toolbar.classList.remove('hidden');
                countSpan.textContent = count + ' ausgewählt';
            } else {
                toolbar.classList.add('hidden');
            }

            // Update "Select All" checkbox state
            const allCheckboxes = document.querySelectorAll('.teacher-checkbox');
            const selectAllCheckbox = document.getElementById('select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = count === allCheckboxes.length && count > 0;
            }
        }

        function confirmBulkDelete() {
            const count = document.querySelectorAll('.teacher-checkbox:checked').length;
            if (count === 0) {
                alert('Bitte wählen Sie mindestens eine Lehrkraft aus.');
                return false;
            }
            return confirm(`Möchten Sie wirklich ${count} Lehrkraft(en) löschen? Diese Aktion kann nicht rückgängig gemacht werden.`);
        }

        // Frueher ein Inline-Handler am Formular (Modul 7.2). Die Frage haengt
        // an einer Bedingung (mindestens eine Auswahl) und passt deshalb nicht
        // auf data-confirm.
        document.getElementById('bulk-delete-form')?.addEventListener('submit', (ereignis) => {
            if (!confirmBulkDelete()) {
                ereignis.preventDefault();
            }
        });
        
        function deleteTeacher(event, teacherId) {
            event.stopPropagation();
            if (confirm('Wirklich löschen?')) {
                document.getElementById('delete-form-' + teacherId).submit();
            }
        }
    </script>
</x-app-layout>
