<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                🎓 Abiturfeier — Ehrungen & Preise
                @if($examPeriod)
                    <span class="text-base font-normal text-gray-500 ml-2">{{ $examPeriod->year }}</span>
                @endif
            </h2>
            <div class="flex items-center space-x-2">
                {{-- PDF-Dropdown --}}
                <div class="relative" x-data="{ pdfOpen: false }">
                    <button @click="pdfOpen = !pdfOpen"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center space-x-1">
                        <span>📄 PDF</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="pdfOpen" @click.away="pdfOpen = false"
                         class="absolute right-0 mt-1 w-60 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg z-50">
                        <a href="{{ route('abiturfeier.pdf.gesamt') }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                            📋 Alle Preise (nach Preisart)
                        </a>
                        <a href="{{ route('abiturfeier.pdf.schueler') }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 border-t border-gray-100 dark:border-gray-700">
                            👤 Schülerliste (alphabetisch)
                        </a>
                        @if($awardTypes->isNotEmpty())
                            <div class="border-t border-gray-200 dark:border-gray-600 mt-1 pt-1">
                                <p class="px-4 py-1 text-xs text-gray-400 uppercase tracking-wide">Je Preisart</p>
                                @foreach($awardTypes as $at)
                                    <a href="{{ route('abiturfeier.pdf.preisart', $at) }}" target="_blank"
                                       class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        🏆 {{ $at->name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Preisarten-Modal öffnen --}}
                <button x-data @click="$dispatch('open-award-types-modal')"
                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    ⚙ Preisarten
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="awardsApp()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Flash-Meldungen --}}
            @if($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Bedienungshinweis --}}
            <div x-data="{ helpOpen: false }" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <button @click="helpOpen = !helpOpen"
                        class="w-full flex items-center justify-between px-5 py-3 text-left">
                    <div class="flex items-center space-x-2 text-blue-700 dark:text-blue-300 font-medium text-sm">
                        <span>ℹ</span>
                        <span>Wie wird diese Seite ausgefüllt?</span>
                    </div>
                    <svg class="w-4 h-4 text-blue-500 transition-transform" :class="helpOpen ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="helpOpen" class="px-5 pb-5 space-y-4 text-sm text-blue-900 dark:text-blue-200">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-1">

                        {{-- Schritt 1 --}}
                        <div class="bg-white dark:bg-blue-900/30 rounded-lg p-4 border border-blue-100 dark:border-blue-800">
                            <div class="font-semibold text-blue-800 dark:text-blue-200 mb-2 flex items-center space-x-1">
                                <span class="bg-blue-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center shrink-0">1</span>
                                <span>Preisarten anlegen (⚙ Preisarten)</span>
                            </div>
                            <p class="text-xs text-blue-700 dark:text-blue-300 leading-relaxed">
                                Trage jede Preisart einmalig ein: <strong>Name</strong> (z. B. „Bio-Zukunftspreis"),
                                optionale <strong>Kategorie</strong> (z. B. „Fachpreis"), verantwortliche
                                <strong>Lehrkraft</strong> und <strong>Sortierung</strong>.
                                <br><br>
                                <span class="text-amber-700 dark:text-amber-300 font-medium">Hinweis bei fehlendem Preisträger:</span>
                                Falls in diesem Jahr kein Schüler den Preis erhält, trägt dieser Text im PDF
                                automatisch ein — z. B. „In diesem Jahr erfüllt keine*r die Bedingungen."
                                Bleibt er leer, erscheint die Preisart gar nicht im PDF.
                            </p>
                        </div>

                        {{-- Schritt 2 --}}
                        <div class="bg-white dark:bg-blue-900/30 rounded-lg p-4 border border-blue-100 dark:border-blue-800">
                            <div class="font-semibold text-blue-800 dark:text-blue-200 mb-2 flex items-center space-x-1">
                                <span class="bg-blue-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center shrink-0">2</span>
                                <span>Schüler eintragen (+ Schüler hinzufügen)</span>
                            </div>
                            <p class="text-xs text-blue-700 dark:text-blue-300 leading-relaxed">
                                Klicke bei der jeweiligen Preisart auf <strong>+ Schüler hinzufügen</strong>
                                und wähle den Schüler aus. Mehrere Schüler pro Preisart sind möglich —
                                einfach mehrfach hinzufügen.
                                <br><br>
                                Felder beim Hinzufügen:
                            </p>
                            <ul class="text-xs text-blue-700 dark:text-blue-300 mt-1 space-y-1 list-none">
                                <li>📋 <strong>Platzierung</strong> — für „1. Preisträger", „2. Preisträger" etc.
                                    (z. B. GDCH: Schuhmacher als 1. Preisträger)</li>
                                <li>☑ <strong>Kandidat / Vorschlag</strong> — wenn der Schüler
                                    vorgeschlagen wird, aber die Entscheidung noch aussteht
                                    (z. B. Studienstiftung-Vorschlag)</li>
                                <li>📝 <strong>Notiz</strong> — Freitext für Fach, Thema, Punkte etc.
                                    (z. B. „Ph/MNT, 817 Punkte" bei Seminararbeitspreisen)</li>
                            </ul>
                        </div>

                        {{-- Schritt 3 --}}
                        <div class="bg-white dark:bg-blue-900/30 rounded-lg p-4 border border-blue-100 dark:border-blue-800">
                            <div class="font-semibold text-blue-800 dark:text-blue-200 mb-2 flex items-center space-x-1">
                                <span class="bg-blue-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center shrink-0">3</span>
                                <span>PDF erstellen (📄 PDF)</span>
                            </div>
                            <p class="text-xs text-blue-700 dark:text-blue-300 leading-relaxed">
                                Drei Export-Varianten stehen zur Verfügung:
                            </p>
                            <ul class="text-xs text-blue-700 dark:text-blue-300 mt-1 space-y-1 list-none">
                                <li>📋 <strong>Alle Preise</strong> — Gesamtliste nach Preisart, mit
                                    Platzierung, Kandidaten-Vermerk und Notizen</li>
                                <li>👤 <strong>Schülerliste</strong> — alle Schüler alphabetisch mit
                                    ihren Ehrungen (Schüler ohne Preis werden nicht angezeigt)</li>
                                <li>🏆 <strong>Je Preisart</strong> — einzelnes Blatt pro Preisart,
                                    z. B. für die Laudatio</li>
                            </ul>
                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-2 leading-relaxed">
                                Tipp: Die Sortierung der Preisarten im PDF entspricht der Sortierungsnummer
                                aus ⚙ Preisarten — kleinere Zahlen erscheinen zuerst.
                            </p>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Ansichts-Umschalter als Tabs --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-3">
                <div class="flex space-x-1">
                    <button @click="view = 'by-type'"
                            :class="view === 'by-type'
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                            class="flex items-center space-x-2 px-4 py-2 rounded-md text-sm font-medium transition-colors">
                        <span>🏆</span>
                        <span>Nach Preisart</span>
                    </button>
                    <button @click="view = 'by-student'"
                            :class="view === 'by-student'
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                            class="flex items-center space-x-2 px-4 py-2 rounded-md text-sm font-medium transition-colors">
                        <span>👤</span>
                        <span>Nach Schüler</span>
                    </button>
                </div>
            </div>

            {{-- Ansicht: Nach Preisart --}}
            <div x-show="view === 'by-type'">
                @include('abiturfeier.partials.by-type-view')
            </div>

            {{-- Ansicht: Nach Schüler --}}
            <div x-show="view === 'by-student'">
                @include('abiturfeier.partials.by-student-view')
            </div>

        </div>
    </div>

    {{-- Modals --}}
    @include('abiturfeier.partials.award-types-modal')
    @include('abiturfeier.partials.edit-modal')

<script @cspNonce>
function awardsApp() {
    return {
        view: 'by-type',
    };
}
</script>
</x-app-layout>
