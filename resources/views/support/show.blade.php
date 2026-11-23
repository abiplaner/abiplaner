<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $fall->betreff }}
            </h2>
            <a href="{{ route('support.index') }}" class="text-sm text-indigo-600 hover:underline">
                ← Alle Meldungen
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">


            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-3">
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                    <span>Gemeldet am {{ $fall->created_at?->format('d.m.Y H:i') }}</span>
                    <span>von {{ $fall->melder_name ?? '—' }}</span>
                    <span>Status: <strong>{{ $fall->statusText() }}</strong></span>
                    @if ($fall->hilfe_slug)
                        <a href="{{ route('hilfe.show', $fall->hilfe_slug) }}" class="text-indigo-600 hover:underline">
                            Hilfekapitel zur Maske
                        </a>
                    @endif
                </div>
                <p class="text-sm text-gray-800 whitespace-pre-line">{{ $fall->beschreibung }}</p>
            </div>

            @include('support.partials.anhaenge', ['fall' => $fall, 'routenname' => 'support.anhang'])

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Verlauf</h3>

                @if ($fall->nachrichten->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">Noch keine Antwort.</p>
                @else
                    <ul class="mt-3 space-y-3">
                        @foreach ($fall->nachrichten as $nachricht)
                            <li class="rounded-md p-3 {{ $nachricht->vonDerSchule() ? 'bg-gray-50' : 'bg-indigo-50' }}">
                                <p class="text-xs text-gray-500">
                                    {{ $nachricht->vonDerSchule() ? ($nachricht->autor_name ?? 'Schule') : 'Support' }}
                                    · {{ $nachricht->created_at?->format('d.m.Y H:i') }}
                                </p>
                                <p class="mt-1 text-sm text-gray-800 whitespace-pre-line">{{ $nachricht->text }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('support.nachricht', $fall) }}" class="mt-5 space-y-2">
                    @csrf
                    <label for="text" class="block text-sm font-medium text-gray-700">Nachfragen oder ergänzen</label>
                    <textarea id="text" name="text" rows="4" required maxlength="5000"
                              class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @error('text')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Absenden
                    </button>
                </form>
            </div>

            {{-- Derselbe Ausschnitt wie in der Vorschau des Meldeformulars. --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Mitgeschickter Kontext</h3>
                <div class="mt-3">
                    @include('support.partials.kontext', ['kontext' => $fall->kontext ?? []])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
