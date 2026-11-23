<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Schuleinstellungen
        </h2>
    </x-slot>

    <div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

    <form method="POST" action="{{ route('school-settings.update') }}" id="settings-form">
    @csrf

    {{-- ══════════════════════════════════════════════════════════════
         Schulstammdaten (Anschrift & Kontakt)
    ══════════════════════════════════════════════════════════════ --}}
    @if(isset($school) && $school)
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Schulstammdaten ({{ $school->name }})
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Anschrift und Kontaktdaten der Schule für Niederschriften und Exporte (werden beim Schuljahreswechsel automatisch übernommen).
            </p>
        </div>
        <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-6 gap-4">
            <div class="sm:col-span-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Straße und Hausnummer</label>
                <input type="text" name="school[address]"
                       value="{{ old('school.address', $school->address ?? ($settings['school.address'] ?? '')) }}"
                       placeholder="z. B. Albrechtstr. 4-6"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Postleitzahl</label>
                <input type="text" name="school[postal_code]"
                       value="{{ old('school.postal_code', $school->postal_code ?? ($settings['school.postal_code'] ?? '')) }}"
                       placeholder="z. B. 80636"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>
            <div class="sm:col-span-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ort</label>
                <input type="text" name="school[city]"
                       value="{{ old('school.city', $school->city ?? ($settings['school.city'] ?? '')) }}"
                       placeholder="z. B. Musterstadt"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>
            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">E-Mail</label>
                <input type="email" name="school[email]"
                       value="{{ old('school.email', $school->email ?? ($settings['school.email'] ?? '')) }}"
                       placeholder="z. B. verwaltung@schule.de"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>
            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telefon</label>
                <input type="text" name="school[phone]"
                       value="{{ old('school.phone', $school->phone ?? ($settings['school.phone'] ?? '')) }}"
                       placeholder="z. B. 089-123456"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════
         Kolloquiumsplan – Optionen
    ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Kolloquiumsplan – Optionen
            </h3>
        </div>
        <div class="px-6 py-5 space-y-4">
            {{-- Aufsichtstabelle anzeigen --}}
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Aufsichtstabelle im PDF anzeigen</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Legt fest, ob die Aufsichtstabelle (Vorbereitungsraum, Warteraum, Gangaufsicht) im Kolloquiumsplan-PDF ausgegeben wird.
                    </p>
                </div>
                <label for="toggle_show_aufsichten" style="position:relative;display:inline-flex;align-items:center;cursor:pointer;margin-left:1.5rem;flex-shrink:0;">
                    <input type="hidden"   name="settings[kolloquiumsplan.show_aufsichten]" value="0">
                    <input type="checkbox" name="settings[kolloquiumsplan.show_aufsichten]" value="1"
                           id="toggle_show_aufsichten"
                           class="toggle-switch-input"
                           {{ ($settings['kolloquiumsplan.show_aufsichten'] ?? '1') === '1' ? 'checked' : '' }}>
                    <span class="toggle-switch-track"></span>
                </label>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Kolloquiumsplan – Schlusstext
    ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Kolloquiumsplan – Schlusstext
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Dieser Text erscheint am Ende des generierten Kolloquiumsplans (PDF &amp; Excel).
                Typischerweise Hinweise zu Koppelgruppen, Warteraum-Regelungen u. ä.
            </p>
        </div>
        <div class="px-6 py-5">
            <div x-data="quillEditor('kolloquiumsplan.schlusstext', @js($settings['kolloquiumsplan.schlusstext'] ?? ''))">
                <div x-ref="editor"
                     class="bg-white rounded"
                     style="min-height:180px"></div>
                <input type="hidden" name="settings[kolloquiumsplan.schlusstext]" x-ref="input" value="{{ $settings['kolloquiumsplan.schlusstext'] ?? '' }}">
            </div>
            {{-- Platzhalter-Dokumentation --}}
            <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                <p class="font-semibold mb-1">📌 Verfügbare Platzhalter (werden beim PDF-Export automatisch ersetzt):</p>
                <ul class="space-y-1">
                    <li>
                        <code class="bg-amber-100 px-1 rounded font-mono">[[warteraum]]</code>
                        – Warteraumbezeichnung(en) aus der Raumkonfiguration, z.&thinsp;B. <em>MRE B202</em>
                    </li>
                    <li>
                        <code class="bg-amber-100 px-1 rounded font-mono">[[nta_liste]]</code>
                        – Liste der Schüler mit Nachteilsausgleich inkl. Zeitangabe, z.&thinsp;B. <em>Klaus (5 min); Fischer (6 min)</em>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Kolloquium – Aufsichten-Hinweistexte
    ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Kolloquium – Aufsichten-Hinweistexte
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Diese Texte erscheinen im Aufsichtsplan-Export (PDF) unterhalb der Tabelle, je einer
                für Gangaufsicht, Vorbereitungsraum und Warteraum – z.&thinsp;B. Ablaufhinweise für
                die eingeteilten Aufsichten.
            </p>
        </div>
        <div class="px-6 py-5 space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Gangaufsicht</label>
                <div x-data="quillEditor('supervision_note.gangaufsicht', @js($settings['supervision_note.gangaufsicht'] ?? ''))">
                    <div x-ref="editor" class="bg-white rounded" style="min-height:120px"></div>
                    <input type="hidden" name="settings[supervision_note.gangaufsicht]" x-ref="input" value="{{ $settings['supervision_note.gangaufsicht'] ?? '' }}">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vorbereitungsraum</label>
                <div x-data="quillEditor('supervision_note.vorbereitung', @js($settings['supervision_note.vorbereitung'] ?? ''))">
                    <div x-ref="editor" class="bg-white rounded" style="min-height:120px"></div>
                    <input type="hidden" name="settings[supervision_note.vorbereitung]" x-ref="input" value="{{ $settings['supervision_note.vorbereitung'] ?? '' }}">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Warteraum</label>
                <div x-data="quillEditor('supervision_note.warteraum', @js($settings['supervision_note.warteraum'] ?? ''))">
                    <div x-ref="editor" class="bg-white rounded" style="min-height:120px"></div>
                    <input type="hidden" name="settings[supervision_note.warteraum]" x-ref="input" value="{{ $settings['supervision_note.warteraum'] ?? '' }}">
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Zusatzprüfungen – Termine
    ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Zusatzprüfungen – Termine
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Diese Angaben erscheinen auf dem Schüler-Fahrplan und den Anmeldezetteln.
            </p>
        </div>
        <div class="px-6 py-5 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Anmeldeschluss – Datum
                    </label>
                    <input type="date" name="settings[zusatzpruefungen.anmeldeschluss_date]"
                           value="{{ $settings['zusatzpruefungen.anmeldeschluss_date'] ?? '' }}"
                           class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Anmeldeschluss – Uhrzeit
                    </label>
                    <input type="time" name="settings[zusatzpruefungen.anmeldeschluss_time]"
                           value="{{ $settings['zusatzpruefungen.anmeldeschluss_time'] ?? '08:00' }}"
                           class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Anmeldestelle (Raum/Ort)
                    </label>
                    <input type="text" name="settings[zusatzpruefungen.anmeldestelle]"
                           value="{{ $settings['zusatzpruefungen.anmeldestelle'] ?? '' }}"
                           placeholder="z.B. Oberstufensekretariat (MA 106)"
                           class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Prüfungszeitraum – Von (Datum)
                    </label>
                    <input type="date" name="settings[zusatzpruefungen.pruefung_von]"
                           value="{{ $settings['zusatzpruefungen.pruefung_von'] ?? '' }}"
                           class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Prüfungszeitraum – Bis (Datum)
                    </label>
                    <input type="date" name="settings[zusatzpruefungen.pruefung_bis]"
                           value="{{ $settings['zusatzpruefungen.pruefung_bis'] ?? '' }}"
                           class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Ergebnisbekanntgabe (Ort und Zeit)
                </label>
                <input type="text" name="settings[zusatzpruefungen.ergebnis_info]"
                       value="{{ $settings['zusatzpruefungen.ergebnis_info'] ?? '' }}"
                       placeholder="z.B. bei den Oberstufenkoordinatoren in Raum MA105, ca. 30 Min. nach Prüfungsende"
                       class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Zusatzprüfungen – Schlusstext
    ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Zusatzprüfungen – Schlusstext
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Dieser Text erscheint am Ende des generierten Zusatzprüfungen-Plans (PDF).
                Typischerweise Hinweise zu Abläufen, Warteraum-Regelungen u. ä.
            </p>
        </div>
        <div class="px-6 py-5">
            <div x-data="quillEditor('zusatzpruefungen.schlusstext', @js($settings['zusatzpruefungen.schlusstext'] ?? ''))">
                <div x-ref="editor"
                     class="bg-white rounded"
                     style="min-height:180px"></div>
                <input type="hidden" name="settings[zusatzpruefungen.schlusstext]" x-ref="input" value="{{ $settings['zusatzpruefungen.schlusstext'] ?? '' }}">
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Absenzen – Schwellenwerte (Roadmap 8.2)
    ══════════════════════════════════════════════════════════════ --}}
    <div id="absenzen" class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Absenzen – Schwellenwerte
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Ab wann ein Wert in der Absenzen-Übersicht rot markiert wird. Reine Kennzahlen,
                keine automatische Bewertung — die Entscheidung bleibt bei der Oberstufenkoordination.
            </p>
        </div>
        <div class="px-6 py-5 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Bisherige Schultage im laufenden Schuljahr
                    </label>
                    <p class="text-sm text-gray-800 dark:text-gray-200 mb-1">
                        Automatisch berechnet: <strong>{{ $autoSchooldays }}</strong> Tage
                        (seit Schuljahresbeginn am {{ $schoolYearStart->format('d.m.Y') }}, aus dem Kalender)
                    </p>
                    <input type="number" min="0" name="settings[absences.schultage_bisher_override]"
                           value="{{ $settings['absences.schultage_bisher_override'] ?? '' }}"
                           placeholder="Manuell überschreiben (optional)"
                           class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm">
                    <p class="mt-1 text-xs text-gray-500">
                        Nur ausfüllen, wenn der automatische Wert falsch ist (z. B. fehlende
                        Kalendereinträge). Leer lassen, um den automatischen Wert zu verwenden.
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Krankheitstage – Schwelle (%)
                    </label>
                    <input type="number" min="0" max="100" step="0.5" name="settings[absences.krank_percent_threshold]"
                           value="{{ $settings['absences.krank_percent_threshold'] ?? '10' }}"
                           class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm">
                </div>
                <div></div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Unentschuldigte Fehlstunden – Schwelle (Anzahl)
                    </label>
                    <input type="number" min="0" name="settings[absences.unentschuldigt_threshold]"
                           value="{{ $settings['absences.unentschuldigt_threshold'] ?? '3' }}"
                           class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Verspätungen – Schwelle (Anzahl)
                    </label>
                    <input type="number" min="0" name="settings[absences.verspaetet_threshold]"
                           value="{{ $settings['absences.verspaetet_threshold'] ?? '5' }}"
                           class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm">
                </div>
            </div>
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Maßnahmen-Typen (frei anpassbar, einer pro Zeile)
                </label>
                <textarea name="settings[absences.measure_types_custom]" rows="4"
                          class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm font-mono"
                          placeholder="Gespräch geführt&#10;Verweis&#10;Sonstige Maßnahme">{{ $settings['absences.measure_types_custom'] ?? implode("\n", \App\Models\AbsenceMeasure::DEFAULT_CUSTOM_TYPES) }}</textarea>
                <p class="mt-1 text-xs text-gray-500">
                    "Attestpflicht verhängt" / "Attestpflicht aufgehoben" sind fest vorgegeben (Basis der
                    Attestpflicht-Fristverfolgung) und hier nicht aufgeführt. Änderungen wirken sich nur
                    auf künftig neu erfasste Maßnahmen aus; bereits dokumentierte bleiben unverändert
                    sichtbar.
                </p>
            </div>
        </div>
    </div>

    {{-- Speichern --}}
    @unlessarchive
    <div class="flex justify-end">
        <button type="submit"
                class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Einstellungen speichern
        </button>
    </div>
    @else
    <p class="text-xs text-gray-400 text-right">In der Archivansicht können Einstellungen nicht gespeichert werden.</p>
    @endarchive

    </form>
    </div>
    </div>


    @push('scripts')
    {{-- Die Komponente quillEditor liegt in resources/js/quill-editor.js;
         Quill selbst wird von dort dynamisch nachgeladen (Modul 7.2). --}}
    <style>
    .ql-toolbar.ql-snow { border-radius: 0.375rem 0.375rem 0 0; background: #f9fafb; }
    .ql-container.ql-snow { border-radius: 0 0 0.375rem 0.375rem; font-size: 14px; }
    .ql-editor { min-height: 100px; }

    /* Toggle-Switch */
    .toggle-switch-input { position: absolute; opacity: 0; width: 0; height: 0; }
    .toggle-switch-track {
        display: inline-block; width: 44px; height: 24px;
        background: #d1d5db; border-radius: 9999px;
        position: relative; transition: background 0.2s;
    }
    .toggle-switch-track::after {
        content: ''; position: absolute;
        top: 2px; left: 2px;
        width: 20px; height: 20px;
        background: #fff; border-radius: 9999px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        transition: transform 0.2s;
    }
    .toggle-switch-input:checked + .toggle-switch-track { background: #2563eb; }
    .toggle-switch-input:checked + .toggle-switch-track::after { transform: translateX(20px); }
    </style>
    @endpush

</x-app-layout>
