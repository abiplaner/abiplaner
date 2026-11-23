<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🧭 Einrichtung
            </h2>
            <a href="{{ route('dashboard') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                ← Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- 1. Einlesen: das einzig Notwendige --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 pt-4 pb-3 border-b border-gray-100">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="font-semibold text-gray-900">
                            @if ($progress['offen'] === 0)
                                ✅ Alle Dateien eingelesen — es kann geplant werden.
                            @else
                                Drei Dateien einlesen — {{ $progress['erledigt'] }} von {{ $progress['gesamt'] }} erledigt
                            @endif
                        </h3>
                        <a href="{{ route('setup.imports') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                            Alle Anleitungen →
                        </a>
                    </div>
                    <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5" role="progressbar"
                         aria-valuenow="{{ $progress['prozent'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-1.5 rounded-full {{ $progress['offen'] === 0 ? 'bg-green-500' : 'bg-indigo-500' }}"
                             style="width: {{ $progress['prozent'] }}%"></div>
                    </div>
                </div>
                <ol class="divide-y divide-gray-100">
                    @foreach ($einlesen as $nr => $item)
                        @include('setup.partials._einlesen', ['item' => $item, 'nr' => $nr + 1])
                    @endforeach
                </ol>
            </div>

            {{-- 2. Anpassen: steht schon, ist aber Vorgabe --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 pt-4 pb-2">
                    <h3 class="font-semibold text-gray-900">Vorbelegt — bei Gelegenheit anpassen</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Das hat die Einrichtung schon angelegt, geplant werden kann damit sofort.
                        Mit ● markiert ist, was noch der Vorgabe entspricht oder unvollständig ist.
                    </p>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($anpassen as $item)
                        @include('setup.partials._anpassen', ['item' => $item])
                    @endforeach
                </ul>
            </div>

        </div>
    </div>
</x-app-layout>
