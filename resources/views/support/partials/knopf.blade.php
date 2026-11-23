{{--
    Das Melde-Panel in der Kopfzeile (Modul 6.4, Stufe 1).

    Dasselbe Muster wie das „?" daneben — mit einem Unterschied: Das Formular
    steht HIER und wird nicht nachgeladen. Alpine baut in `x-html`
    eingesetztes Markup nicht auf; ein nachgeladenes Formular haette also
    keine Reaktion beim Tippen. Nachgeladen wird deshalb nur, was reine
    Anzeige ist: der Kontext und die Kapitelvorschlaege.

    Der Kontext wird erst beim Oeffnen geholt — er kostet ein gutes Dutzend
    Abfragen (Einrichtungsstand), und die will niemand auf jeder Seite.
--}}
@php
    $herkunftRoute = request()->route()?->getName() ?? '';
    $kontextUrl = route('support.kontext', ['herkunft_route' => $herkunftRoute]);
@endphp

<div x-data="{
        offen: false,
        gesendet: false,
        kontextHtml: '',
        kontextGeladen: false,
        kontextOffen: false,
        vorschlagHtml: '',
        dateien: [],
        oeffnen() {
            this.offen = true;
            if (this.kontextGeladen) return;
            fetch(@js($kontextUrl), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(antwort => antwort.ok ? antwort.text() : Promise.reject(antwort.status))
                .then(text => { this.kontextHtml = text; this.kontextGeladen = true; })
                .catch(() => {
                    this.kontextHtml = @js('<p class="text-sm text-red-700">Der Kontext konnte nicht erhoben werden. Die Meldung lässt sich trotzdem absenden.</p>');
                    this.kontextGeladen = true;
                });
        },
        vorschlagen(begriff) {
            if (begriff.trim().length < 4) { this.vorschlagHtml = ''; return; }
            fetch(@js(route('support.vorschlag')) + '?q=' + encodeURIComponent(begriff),
                  { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(antwort => antwort.ok ? antwort.text() : Promise.reject(antwort.status))
                .then(text => { this.vorschlagHtml = text; })
                .catch(() => { this.vorschlagHtml = ''; });
        }
     }"
     @keydown.escape.window="offen = false">

    <button type="button" @click="oeffnen()" title="Fehler oder Frage melden"
            class="inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:text-amber-600 hover:border-amber-400 focus:outline-none transition ease-in-out duration-150">
        <span class="text-base font-semibold leading-none">!</span>
        <span class="sr-only">Fehler oder Frage melden</span>
    </button>

    {{-- Abdunklung --}}
    <div x-show="offen" x-cloak @click="offen = false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-gray-900/40"></div>

    {{-- Panel --}}
    <div x-show="offen" x-cloak
         x-transition:enter="transform transition ease-out duration-200"
         x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in duration-150"
         x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 z-50 w-full max-w-xl bg-white shadow-2xl flex flex-col">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500">Fehler oder Frage melden</span>
            <button type="button" @click="offen = false"
                    class="text-gray-400 hover:text-gray-700 text-2xl leading-none px-2">
                &times;
                <span class="sr-only">Schließen</span>
            </button>
        </div>

        <form method="POST" action="{{ route('support.store') }}" enctype="multipart/form-data"
              class="flex-1 overflow-y-auto px-6 py-5 space-y-4"
              @submit="gesendet = true">
            @csrf
            <input type="hidden" name="herkunft_route" value="{{ $herkunftRoute }}">
            <input type="hidden" name="herkunft_url" value="{{ url()->full() }}">

            <div>
                <label for="support-betreff" class="block text-sm font-medium text-gray-700">Worum geht es?</label>
                <input id="support-betreff" name="betreff" type="text" required maxlength="200"
                       placeholder="Kurz in einem Satz"
                       @input.debounce.500ms="vorschlagen($event.target.value)"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="support-beschreibung" class="block text-sm font-medium text-gray-700">Was ist passiert?</label>
                <textarea id="support-beschreibung" name="beschreibung" required rows="6" maxlength="5000"
                          placeholder="Was haben Sie gemacht, was haben Sie erwartet, was ist stattdessen passiert?"
                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            </div>

            {{-- Stufe 1.5: passende Kapitel, waehrend getippt wird --}}
            <div x-show="vorschlagHtml !== ''" x-cloak x-html="vorschlagHtml"></div>

            {{--
                Bildschirmfotos (Entscheidung 48). Der Hinweis ist kein
                Kleingedrucktes: Ein Bildschirmfoto aus dieser Anwendung zeigt
                fast immer Namen. Deshalb steht daneben, wann die Bilder wieder
                verschwinden.
            --}}
            <div>
                <label for="support-anhaenge" class="block text-sm font-medium text-gray-700">
                    Bildschirmfotos (freiwillig)
                </label>
                <input id="support-anhaenge" name="anhaenge[]" type="file" multiple
                       accept="image/png,image/jpeg,image/webp"
                       @change="dateien = Array.from($event.target.files).map(d => d.name)"
                       class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                <p class="mt-1 text-xs text-gray-500">
                    Höchstens {{ \App\Services\SupportAnhangService::MAX_ANZAHL }} Bilder
                    (PNG, JPEG oder WebP), je bis
                    {{ (int) (\App\Services\SupportAnhangService::MAX_KB / 1024) }} MB.
                </p>
                <p class="mt-1 text-xs text-amber-700">
                    Ein Bildschirmfoto zeigt meist Namen. Es wird nur im Fall angezeigt,
                    nie per E-Mail versendet, und beim Abschluss des Falls gelöscht.
                </p>
                <ul x-show="dateien.length > 0" x-cloak class="mt-1 text-xs text-gray-600">
                    <template x-for="name in dateien" :key="name">
                        <li x-text="name"></li>
                    </template>
                </ul>
            </div>

            @error('anhaenge.*')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            {{--
                „Das wird mitgeschickt" — vollstaendig und im Klartext.
                Keine Hoeflichkeit: Zwei der vier Bloecke koennen Namen
                enthalten, und wer nicht sieht, was er mitschickt, schickt
                entweder nichts oder zu viel.
            --}}
            <div class="border border-gray-200 rounded-md">
                <button type="button" @click="kontextOffen = !kontextOffen"
                        class="w-full flex items-center justify-between px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                    <span>Das wird mitgeschickt</span>
                    <span x-text="kontextOffen ? '▾' : '▸'" class="text-gray-400"></span>
                </button>
                <div x-show="kontextOffen" x-cloak class="px-3 pb-3 border-t border-gray-100">
                    <p x-show="!kontextGeladen" class="text-sm text-gray-500 pt-3">Wird erhoben …</p>
                    <div x-show="kontextGeladen" x-html="kontextHtml" class="pt-3"></div>
                    {{--
                        Entscheidung 53: Wer sieht, was er schickt, soll auch
                        sehen, wie lange es bleibt. Die Zahlen kommen aus der
                        Konfiguration, damit hier keine zweite Wahrheit steht.
                    --}}
                    <p class="pt-3 mt-3 border-t border-gray-100 text-xs text-gray-500">
                        Wie lange das bleibt: Bildschirmfotos bis zum Abschluss des Falls,
                        die beiden Blöcke mit möglichen Namen
                        {{ (int) config('support.aufbewahrung.kontext_tage') }} Tage danach,
                        die Fallakte
                        {{ (int) config('support.aufbewahrung.fallakte_monate') }} Monate danach.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" x-bind:disabled="gesendet"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">
                    Meldung absenden
                </button>
                <a href="{{ route('support.index') }}" class="text-sm text-indigo-600 hover:underline">
                    Meine Meldungen
                </a>
            </div>
        </form>
    </div>
</div>
