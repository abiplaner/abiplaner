<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Zuteilung planen: {{ $jahrgang < 10 ? $jahrgang : $jahrgang }}. Klassen
            </h2>
            <a href="{{ route('lk-wahlen.index', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="zuteilungPlaner()">
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
                            <p>Konfigurieren Sie die Parameter und klicken Sie auf "Berechnen" für eine Vorschau.</p>
                            <p class="mt-1">Die Zuteilung wird erst nach "Durchführen" in der Datenbank gespeichert.</p>
                            <p class="mt-1"><strong>Anzahl Schüler:</strong> {{ $wahlenCount }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Konfigurationsbereich --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6">Konfiguration</h3>
                    
                    <form @submit.prevent="berechnen" class="space-y-6">
                        
                        {{-- Hidden Fields --}}
                        <input type="hidden" name="schuljahr" x-model="config.schuljahr">
                        <input type="hidden" name="jahrgang" x-model="config.jahrgang">
                        
                        {{-- Anzahl Kurse --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Anzahl Kurse (ohne externe): <span x-text="config.max_kurse"></span>
                            </label>
                            <input type="range" 
                                   x-model.number="config.max_kurse"
                                   min="3" 
                                   max="15" 
                                   step="1"
                                   class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>3</span>
                                <span>15</span>
                            </div>
                        </div>
                        
                        {{-- Priorisierung --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Strategie
                            </label>
                            <div class="space-y-3">
                                <label class="flex items-start">
                                    <input type="radio" 
                                           x-model="config.strategie" 
                                           value="niemand_ohne_wahl"
                                           class="mt-1 mr-3">
                                    <div>
                                        <div class="font-medium">Niemand ohne Wahl (Standard)</div>
                                        <div class="text-sm text-gray-600">Jeder Schüler bekommt Erst- oder Zweitwahl</div>
                                    </div>
                                </label>
                                <label class="flex items-start">
                                    <input type="radio" 
                                           x-model="config.strategie" 
                                           value="viele_erstwahlen"
                                           class="mt-1 mr-3">
                                    <div>
                                        <div class="font-medium">Möglichst viele Erstwahlen</div>
                                        <div class="text-sm text-gray-600">Maximiert Erstwahl-Erfüllung (einige ohne beide Wahlen möglich)</div>
                                    </div>
                                </label>
                                <label class="flex items-start">
                                    <input type="radio" 
                                           x-model="config.strategie" 
                                           value="ausgeglichen"
                                           class="mt-1 mr-3">
                                    <div>
                                        <div class="font-medium">Ausgeglichene Kursgrößen</div>
                                        <div class="text-sm text-gray-600">Minimiert Unterschiede in Kursgrößen</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        {{-- Externe Kurse --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Externe Kurse (werden immer eingerichtet, zählen nicht zur Maximalzahl)
                            </label>
                            <div class="space-y-2 max-h-64 overflow-y-auto border border-gray-200 rounded-md p-3">
                                @foreach($alleFaecher as $fach)
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               value="{{ $fach['subject_id'] }}"
                                               x-model="config.externe_faecher"
                                               class="rounded border-gray-300 text-blue-600 mr-2">
                                        <span class="text-sm">
                                            {{ $fach['name'] }} 
                                            <span class="text-gray-500">({{ $fach['gesamt'] }} Wahlen)</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        
                        {{-- Buttons --}}
                        <div class="flex items-center justify-between pt-4 border-t">
                            <a href="{{ route('lk-wahlen.index', ['schuljahr' => $schuljahr, 'jahrgang' => $jahrgang]) }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <button type="submit"
                                    :disabled="loading"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!loading">Berechnen</span>
                                <span x-show="loading" class="flex items-center">
                                    <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Berechne...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            {{-- Vorschau-Bereich --}}
            <div x-show="vorschauVorhanden" 
                 x-transition
                 class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold">Vorschau</h3>
                        <form @submit.prevent="durchfuehren">
                            <button type="submit"
                                    :disabled="loading"
                                    class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed font-semibold">
                                <span x-show="!loading">✓ Durchführen</span>
                                <span x-show="loading">Speichere...</span>
                            </button>
                        </form>
                    </div>
                    
                    {{-- Fehler --}}
                    <div x-show="error" 
                         x-transition
                         class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                        <p class="text-sm text-red-700" x-text="error"></p>
                    </div>
                    
                    {{-- Warnungen --}}
                    <div x-show="warnungen.length > 0" 
                         x-transition
                         class="mb-4 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                        <h4 class="text-sm font-medium text-yellow-800 mb-2">Warnungen</h4>
                        <ul class="list-disc list-inside text-sm text-yellow-700">
                            <template x-for="warnung in warnungen" :key="warnung">
                                <li x-text="warnung"></li>
                            </template>
                        </ul>
                    </div>
                    
                    {{-- Statistik-Karten --}}
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 rounded-lg p-4">
                            <div class="text-sm text-gray-600">Geplante Kurse</div>
                            <div class="text-xl font-bold text-blue-600">
                                <span x-text="vorschau.kurse.filter(k => !k.ist_extern).length"></span>
                                <span class="text-sm text-gray-500" x-show="vorschau.kurse.filter(k => k.ist_extern).length > 0">
                                    + <span x-text="vorschau.kurse.filter(k => k.ist_extern).length"></span> (extern)
                                </span>
                            </div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4">
                            <div class="text-sm text-gray-600">Erstwahl erfüllt</div>
                            <div class="text-2xl font-bold text-green-600">
                                <span x-text="vorschau.statistik.erstwahl_erhalten"></span>
                                <span class="text-base" x-text="'(' + vorschau.statistik.erstwahl_prozent + '%)'"></span>
                            </div>
                        </div>
                        <div class="bg-yellow-50 rounded-lg p-4">
                            <div class="text-sm text-gray-600">Zweitwahl erfüllt</div>
                            <div class="text-2xl font-bold text-yellow-600">
                                <span x-text="vorschau.statistik.zweitwahl_erhalten"></span>
                                <span class="text-base" x-text="'(' + vorschau.statistik.zweitwahl_prozent + '%)'"></span>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-sm text-gray-600">Keine Wahl</div>
                            <div class="text-2xl font-bold text-gray-600">
                                <span x-text="vorschau.statistik.keine_wahl_erhalten"></span>
                                <span class="text-base" x-text="'(' + vorschau.statistik.keine_wahl_prozent + '%)'"></span>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Kurstabelle --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fach</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Kurs</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Extern</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Schüler</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="kurs in vorschau.kurse" :key="kurs.subject_id + '_' + kurs.kurs_nummer">
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900" x-text="kurs.name"></td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-900" x-text="kurs.kurs_nummer"></td>
                                        <td class="px-4 py-3 text-center">
                                            <span x-show="kurs.ist_extern" 
                                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                Extern
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900" x-text="kurs.zugeteilte_schueler"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    </div>

                    {{-- Nicht zugeteilte Schüler --}}
                    <div x-show="vorschau.nicht_zugeteilt && vorschau.nicht_zugeteilt.length > 0" class="mt-8">
                        <h4 class="text-md font-semibold text-red-700 mb-2">Nicht zugeteilte Schüler (<span x-text="vorschau.nicht_zugeteilt.length"></span>)</h4>
                        <div class="overflow-x-auto border border-red-200 rounded-lg">
                            <table class="min-w-full divide-y divide-red-200">
                                <thead class="bg-red-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-red-500 uppercase">Klasse</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-red-500 uppercase">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-red-500 uppercase">1. Wahl</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-red-500 uppercase">2. Wahl</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-red-100">
                                    <template x-for="schueler in vorschau.nicht_zugeteilt" :key="schueler.id">
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-900" x-text="schueler.klasse"></td>
                                            <td class="px-4 py-2 text-sm text-gray-900" x-text="schueler.nachname + ', ' + schueler.vorname"></td>
                                            <td class="px-4 py-2 text-sm text-gray-500" x-text="schueler.erstwahl"></td>
                                            <td class="px-4 py-2 text-sm text-gray-500" x-text="schueler.zweitwahl"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    {{-- Alpine.js Component --}}
    <script @cspNonce>
        function zuteilungPlaner() {
            return {
                config: {
                    schuljahr: '{{ $schuljahr }}',
                    jahrgang: {{ $jahrgang }},
                    max_kurse: {{ $defaultMaxKurse }},
                    strategie: 'niemand_ohne_wahl',
                    externe_faecher: @json($autoExternalSubjectIds ?? [])
                },
                loading: false,
                vorschauVorhanden: false,
                vorschau: {
                    kurse: [],
                    statistik: {},
                    anzahl_schueler: 0,
                    nicht_zugeteilt: []
                },
                warnungen: [],
                error: null,
                
                async berechnen() {
                    this.loading = true;
                    this.error = null;
                    this.warnungen = [];
                    
                    try {
                        const response = await fetch('{{ route("lk-kurse.berechne") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.config)
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.vorschau = {
                                kurse: data.kurse,
                                statistik: data.statistik,
                                anzahl_schueler: data.anzahl_schueler,
                                nicht_zugeteilt: data.nicht_zugeteilt
                            };
                            this.warnungen = data.warnungen || [];
                            this.vorschauVorhanden = true;
                            
                            // Scroll to preview
                            this.$nextTick(() => {
                                document.querySelector('[x-show="vorschauVorhanden"]').scrollIntoView({ 
                                    behavior: 'smooth', 
                                    block: 'start' 
                                });
                            });
                        } else {
                            this.error = data.error || 'Berechnung fehlgeschlagen';
                        }
                    } catch (err) {
                        this.error = 'Netzwerkfehler: ' + err.message;
                    } finally {
                        this.loading = false;
                    }
                },
                
                async durchfuehren() {
                    if (!confirm('Möchten Sie die Zuteilung wirklich durchführen? Bestehende Zuteilungen werden überschrieben.')) {
                        return;
                    }
                    
                    this.loading = true;
                    this.error = null;
                    
                    // Create form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route("lk-kurse.apply") }}';
                    
                    // Add CSRF token
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = '{{ csrf_token() }}';
                    form.appendChild(csrfInput);
                    
                    // Add config data
                    for (const [key, value] of Object.entries(this.config)) {
                        if (Array.isArray(value)) {
                            value.forEach(item => {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = key + '[]';
                                input.value = item;
                                form.appendChild(input);
                            });
                        } else {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = value;
                            form.appendChild(input);
                        }
                    }
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            };
        }
    </script>
</x-app-layout>
