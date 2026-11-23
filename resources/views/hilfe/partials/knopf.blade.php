{{--
    Das „?" in der Kopfzeile samt Panel (Modul 6.3).

    Der Knopf lädt beim ersten Öffnen den Ausschnitt zur AKTUELLEN Route nach
    und behält ihn danach — auf derselben Seite ändert sich das Kapitel nicht.
    Der Routenname wird serverseitig eingesetzt; das Panel weiß von sich aus
    nicht, wo es steht.
--}}
@php
    $hilfePanelUrl = route('hilfe.panel', ['route' => request()->route()?->getName() ?? '']);
@endphp

<div x-data="{
        offen: false,
        geladen: false,
        inhalt: '',
        oeffnen() {
            this.offen = true;
            if (this.geladen) return;
            fetch(@js($hilfePanelUrl), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(antwort => antwort.ok ? antwort.text() : Promise.reject(antwort.status))
                .then(text => { this.inhalt = text; this.geladen = true; })
                .catch(() => {
                    this.inhalt = @js('<p class="text-sm text-red-700">Die Hilfe konnte nicht geladen werden.</p>');
                    this.geladen = true;
                });
        }
     }"
     @keydown.escape.window="offen = false">

    <button type="button" @click="oeffnen()" title="Hilfe zu dieser Seite"
            class="inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:text-indigo-600 hover:border-indigo-400 focus:outline-none transition ease-in-out duration-150">
        <span class="text-base font-semibold leading-none">?</span>
        <span class="sr-only">Hilfe zu dieser Seite</span>
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
            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500">Hilfe</span>
            <button type="button" @click="offen = false"
                    class="text-gray-400 hover:text-gray-700 text-2xl leading-none px-2">
                &times;
                <span class="sr-only">Schließen</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-5">
            <p x-show="!geladen" class="text-sm text-gray-500">Wird geladen …</p>
            <div x-show="geladen" x-html="inhalt"></div>
        </div>
    </div>
</div>
