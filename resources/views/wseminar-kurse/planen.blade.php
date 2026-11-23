<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                W-Seminar Zuteilung planen
            </h2>
            <a href="{{ route('wseminar-wahlen.index') }}" 
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            {{-- Info-Box --}}
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Automatische Zuteilung</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>Wählen Sie eine Strategie und klicken Sie auf "Berechnen" für eine Vorschau.</p>
                            <p class="mt-1">Die Zuteilung wird erst nach "Durchführen" in der Datenbank gespeichert.</p>
                            <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2">
                                <div>
                                    <strong>Schüler im Jahrgang:</strong> {{ $analyse['gesamt_schueler_jahrgang'] ?? 0 }}
                                </div>
                                <div>
                                    <strong>Schüler mit Wahlen:</strong> {{ $analyse['schueler_mit_wahlen'] ?? 0 }}
                                </div>
                                <div>
                                    <strong>Max. Teilnehmer/Seminar:</strong> 15
                                </div>
                            </div>
                            @if(isset($analyse['gesamt_schueler_jahrgang']) && $analyse['gesamt_schueler_jahrgang'] > 0)
                                @php
                                    $prozent = round(($analyse['schueler_mit_wahlen'] / $analyse['gesamt_schueler_jahrgang']) * 100, 1);
                                @endphp
                                <p class="mt-2">
                                    <strong>Wahlbeteiligung:</strong> {{ $prozent }}%
                                    @if($prozent < 100)
                                        <span class="text-red-600 font-semibold">({{ $analyse['gesamt_schueler_jahrgang'] - $analyse['schueler_mit_wahlen'] }} Schüler fehlen noch)</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Seminar-Statistik --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">W-Seminar-Übersicht</h3>
                        <div class="flex space-x-2">
                            <a href="{{ route('wseminar-kurse.planen.export-pdf', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
                               class="px-3 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                PDF
                            </a>
                            <a href="{{ route('wseminar-kurse.planen.export-excel', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
                               class="px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Excel
                            </a>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nr.
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        W-Seminar
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Erstwahlen
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Zweitwahlen
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Drittwahlen
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Gesamt
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($analyse['seminar_statistik'] as $index => $seminar)
                                    <tr class="{{ $seminar['gesamt'] < 10 ? 'bg-red-50' : '' }}">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $index + 1 }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900">{{ $seminar['subject_name'] }}</div>
                                            <div class="text-sm text-gray-500">{{ $seminar['titel'] }}</div>
                                            @if($seminar['gesamt'] < 10)
                                                <div class="text-xs text-red-600 font-semibold mt-1">
                                                    ⚠ Zu wenig Nachfrage (< 10)
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap text-sm text-gray-900">
                                            {{ $seminar['erstwahlen'] }}
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap text-sm text-gray-900">
                                            {{ $seminar['zweitwahlen'] }}
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap text-sm text-gray-900">
                                            {{ $seminar['drittwahlen'] }}
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap text-sm font-bold {{ $seminar['gesamt'] < 10 ? 'text-red-600' : 'text-gray-900' }}">
                                            {{ $seminar['gesamt'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Konfiguration --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6">Konfiguration</h3>
                    
                    <form id="zuteilungsForm" class="space-y-6">
                        @csrf
                        <input type="hidden" name="schuljahr" value="{{ $schuljahr }}">
                        <input type="hidden" name="jahrgang" value="{{ $jahrgang }}">
                        
                        {{-- Strategie --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Strategie
                            </label>
                            <div class="space-y-3">
                                <label class="flex items-start cursor-pointer">
                                    <input type="radio" 
                                           name="strategie" 
                                           value="optimiert"
                                           checked
                                           class="mt-1 mr-3"
                                           data-toggle-options>
                                    <div>
                                        <div class="font-medium">Optimierte Verteilung (Standard)</div>
                                        <div class="text-sm text-gray-600">Strategische Verteilung mit 3 Phasen (Survival-Mode, Prioritäts-Füllung, Verschiebung)</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-start cursor-pointer">
                                    <input type="radio" 
                                           name="strategie" 
                                           value="viele_erstwahlen"
                                           class="mt-1 mr-3"
                                           data-toggle-options>
                                    <div>
                                        <div class="font-medium">Viele Erstwahlen</div>
                                        <div class="text-sm text-gray-600">Maximiert die Anzahl der Schüler mit Erstwahl (einfacher Algorithmus)</div>
                                    </div>
                                </label>
                            </div>
                            
                            {{-- Optionen für Optimierte Verteilung --}}
                            <div id="optimiertOptions" class="mt-4 pl-7 border-l-2 border-blue-200">
                                 <label class="block text-sm font-medium text-gray-700 mb-1">
                                     Geplante Anzahl Kurse
                                 </label>
                                 <div class="flex items-center">
                                     <input type="number" 
                                            name="target_course_count" 
                                            value="{{ $minCourses }}" 
                                            min="{{ $minCourses }}"
                                            class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-24 sm:text-sm border-gray-300 rounded-md">
                                     <span class="ml-3 text-sm text-gray-500">
                                         (Mindestens {{ $minCourses }} empfohlen)
                                     </span>
                                 </div>
                                 <p class="mt-1 text-xs text-gray-500">
                                     Wählt die beliebtesten Kurse aus und füllt zuerst die kleineren Kurse auf.
                                 </p>
                            </div>
                        </div>
                        
                        {{-- Buttons --}}
                        <div class="flex items-center space-x-3 pt-4">
                            <button type="button" 
                                    data-berechne-zuteilung 
                                    class="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">
                                Zuteilung berechnen
                            </button>
                            <button type="button" 
                                    data-apply-zuteilung 
                                    id="applyBtn" 
                                    disabled
                                    class="px-6 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                Zuteilung durchführen
                            </button>
                            <a href="{{ route('wseminar-wahlen.index') }}" 
                               class="px-6 py-3 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 font-medium">
                                Abbrechen
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Ergebnis-Bereich --}}
            <div id="ergebnisBereich" class="hidden">
                {{-- Statistik --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Vorschau: Zuteilungs-Ergebnis</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-sm text-gray-600">Erstwahl</div>
                                <div class="flex items-baseline gap-2">
                                    <div class="text-2xl font-bold text-green-700" id="stat-erstwahl">0</div>
                                    <div class="text-sm text-green-600 font-medium" id="stat-erstwahl-pct">(0%)</div>
                                </div>
                            </div>
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <div class="text-sm text-gray-600">Zweitwahl</div>
                                <div class="flex items-baseline gap-2">
                                    <div class="text-2xl font-bold text-yellow-700" id="stat-zweitwahl">0</div>
                                    <div class="text-sm text-yellow-600 font-medium" id="stat-zweitwahl-pct">(0%)</div>
                                </div>
                            </div>
                            <div class="bg-orange-50 p-4 rounded-lg">
                                <div class="text-sm text-gray-600">Drittwahl</div>
                                <div class="flex items-baseline gap-2">
                                    <div class="text-2xl font-bold text-orange-700" id="stat-drittwahl">0</div>
                                    <div class="text-sm text-orange-600 font-medium" id="stat-drittwahl-pct">(0%)</div>
                                </div>
                            </div>
                            <div class="bg-red-50 p-4 rounded-lg">
                                <div class="text-sm text-gray-600">Nicht zugeteilt</div>
                                <div class="flex items-baseline gap-2">
                                    <div class="text-2xl font-bold text-red-700" id="stat-keine">0</div>
                                    <div class="text-sm text-red-600 font-medium" id="stat-keine-pct">(0%)</div>
                                </div>
                            </div>
                            <div class="bg-gray-100 p-4 rounded-lg">
                                <div class="text-sm text-gray-600">Gar nicht gewählt</div>
                                <div class="flex items-baseline gap-2">
                                    <div class="text-2xl font-bold text-gray-700" id="stat-nicht-gewaehlt">0</div>
                                    <div class="text-sm text-gray-600 font-medium" id="stat-nicht-gewaehlt-pct">(0%)</div>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Seminar-Übersicht --}}
                        <h4 class="font-semibold mb-3">Belegung nach Seminar</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">W-Seminar</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Erstwahlen</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Zweitwahlen</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Drittwahlen</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teilnehmer</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Freie Plätze</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="seminareListe">
                                    {{-- Wird per JavaScript gefüllt --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script @cspNonce>
    let zuteilungErgebnis = null;
    
    // Frueher Inline-Handler (Modul 7.2). Ueber document gebunden, damit die
    // Reihenfolge zu den Funktionsdefinitionen keine Rolle spielt.
    document.addEventListener('change', (ereignis) => {
        if (ereignis.target.closest('[data-toggle-options]')) {
            toggleOptions();
        }
    });

    document.addEventListener('click', (ereignis) => {
        if (ereignis.target.closest('[data-berechne-zuteilung]')) {
            berechneZuteilung();
        }

        if (ereignis.target.closest('[data-apply-zuteilung]')) {
            applyZuteilung();
        }
    });

    function toggleOptions() {
        const strategy = document.querySelector('input[name="strategie"]:checked').value;
        const opts = document.getElementById('optimiertOptions');
        if (strategy === 'optimiert') {
            opts.classList.remove('hidden');
        } else {
            opts.classList.add('hidden');
        }
    }

    function berechneZuteilung() {
        const form = document.getElementById('zuteilungsForm');
        const formData = new FormData(form);
        
        // Show loading state
        const berechnenBtn = event.target;
        berechnenBtn.disabled = true;
        berechnenBtn.textContent = 'Berechne...';
        
        fetch('{{ route('wseminar-kurse.berechne') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            berechnenBtn.disabled = false;
            berechnenBtn.textContent = 'Zuteilung berechnen';
            
            if (data.success) {
                zuteilungErgebnis = data;
                
                // Berechne "Gar nicht gewählt"
                // 1. Schüler ohne Datensatz
                const noRecord = Math.max(0, data.gesamt_schueler - data.anzahl_schueler);
                // 2. Schüler mit Datensatz aber leerer Wahl
                const emptyRecord = data.statistik.leer || 0;
                
                const notChosen = noRecord + emptyRecord;
                
                const total = data.gesamt_schueler > 0 ? data.gesamt_schueler : 1;
                
                // Helper für Prozente
                const calcPct = (val) => Math.round((val / total) * 100);
                
                // Update Statistik
                document.getElementById('stat-erstwahl').textContent = data.statistik.erstwahl;
                document.getElementById('stat-erstwahl-pct').textContent = `(${calcPct(data.statistik.erstwahl)}%)`;
                
                document.getElementById('stat-zweitwahl').textContent = data.statistik.zweitwahl;
                document.getElementById('stat-zweitwahl-pct').textContent = `(${calcPct(data.statistik.zweitwahl)}%)`;
                
                document.getElementById('stat-drittwahl').textContent = data.statistik.drittwahl;
                document.getElementById('stat-drittwahl-pct').textContent = `(${calcPct(data.statistik.drittwahl)}%)`;
                
                document.getElementById('stat-keine').textContent = data.statistik.keine;
                document.getElementById('stat-keine-pct').textContent = `(${calcPct(data.statistik.keine)}%)`;
                
                document.getElementById('stat-nicht-gewaehlt').textContent = notChosen;
                document.getElementById('stat-nicht-gewaehlt-pct').textContent = `(${calcPct(notChosen)}%)`;
                
                // Update Seminar-Liste
                const seminareListe = document.getElementById('seminareListe');
                seminareListe.innerHTML = '';
                
                data.seminare.forEach((seminar, index) => {
                    const row = document.createElement('tr');
                    
                    let belegungClass = seminar.zugeteilte_schueler >= seminar.max_teilnehmer 
                        ? 'bg-red-100 text-red-800' 
                        : 'bg-green-100 text-green-800';
                        
                    let rowClass = "";
                    let activeLabel = "";
                    
                    // Style inactive courses
                    if (!seminar.is_active && seminar.zugeteilte_schueler === 0) {
                        rowClass = "bg-gray-100 opacity-60";
                        belegungClass = 'bg-gray-200 text-gray-500';
                        activeLabel = '<span class="ml-2 text-xs text-red-500 font-bold">(Kommt nicht zustande)</span>';
                    }
                    
                    row.className = rowClass;
                    
                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                            ${index + 1}
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium">${seminar.subject_name}${activeLabel}</div>
                            <div class="text-sm text-gray-500">${seminar.titel}</div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            ${seminar.erstwahlen}
                        </td>
                        <td class="px-6 py-4 text-center">
                            ${seminar.zweitwahlen}
                        </td>
                        <td class="px-6 py-4 text-center">
                            ${seminar.drittwahlen}
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-sm font-semibold rounded ${belegungClass}">
                                ${seminar.zugeteilte_schueler} / ${seminar.max_teilnehmer}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            ${seminar.freie_plaetze}
                        </td>
                    `;
                    seminareListe.appendChild(row);
                });
                
                // Show result area
                document.getElementById('ergebnisBereich').classList.remove('hidden');
                document.getElementById('applyBtn').disabled = false;
                
                // Scroll to result
                document.getElementById('ergebnisBereich').scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                alert('Fehler bei der Berechnung: ' + (data.message || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            berechnenBtn.disabled = false;
            berechnenBtn.textContent = 'Zuteilung berechnen';
            console.error('Error:', error);
            alert('Fehler bei der Berechnung. Bitte versuchen Sie es erneut.');
        });
    }

    function applyZuteilung() {
        if (!zuteilungErgebnis) {
            alert('Bitte berechnen Sie zuerst eine Zuteilung.');
            return;
        }
        
        if (!confirm('Möchten Sie diese Zuteilung wirklich durchführen? Dies überschreibt alle bisherigen Zuteilungen.')) {
            return;
        }
        
        const form = document.getElementById('zuteilungsForm');
        const formData = new FormData(form);
        
        // Show loading state
        const applyBtn = document.getElementById('applyBtn');
        applyBtn.disabled = true;
        applyBtn.textContent = 'Speichere...';
        
        fetch('{{ route('wseminar-kurse.apply') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.href = '{{ route('wseminar-kurse.index', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}';
            } else {
                applyBtn.disabled = false;
                applyBtn.textContent = 'Zuteilung durchführen';
                alert('Fehler beim Speichern: ' + (data.message || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Zuteilung durchführen';
            console.error('Error:', error);
            alert('Fehler beim Speichern. Bitte versuchen Sie es erneut.');
        });
    }
    </script>
</x-app-layout>
