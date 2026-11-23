<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $fall->betreff }}
            </h2>
            <a href="{{ route('admin.support.index') }}" class="text-sm text-indigo-600 hover:underline">
                ← Alle Fälle
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-4 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-3">
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                    <span>{{ $fall->schule_name ?? $fall->school_id ?? 'ohne Schule' }}</span>
                    <span>{{ $fall->melder_name ?? '—' }} ({{ $fall->melder_email ?? '—' }})</span>
                    <span>{{ $fall->created_at?->format('d.m.Y H:i') }}</span>
                    @if ($fall->hilfe_slug)
                        <a href="{{ route('hilfe.show', $fall->hilfe_slug) }}" class="text-indigo-600 hover:underline">
                            Kapitel: {{ $fall->hilfe_slug }}
                        </a>
                    @endif
                </div>

                @if ($fall->url)
                    <p class="text-xs text-gray-500 break-all">{{ $fall->url }}</p>
                @endif

                <p class="text-sm text-gray-800 whitespace-pre-line">{{ $fall->beschreibung }}</p>

                @if ($fall->fehler_id)
                    <p class="text-xs text-gray-500">
                        Im Server-Log zu finden unter <code>{{ $fall->fehler_id }}</code>.
                    </p>
                @endif
            </div>

            @include('support.partials.anhaenge', ['fall' => $fall, 'routenname' => 'admin.support.anhang'])

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-wrap items-end gap-4">
                <form method="POST" action="{{ route('admin.support.status', $fall) }}" class="flex items-end gap-2">
                    @csrf
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status"
                                class="mt-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (\App\Models\SupportFall::STATUS_TEXT as $wert => $beschriftung)
                                <option value="{{ $wert }}" @selected($fall->status === $wert)>{{ $beschriftung }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        Setzen
                    </button>
                </form>

                @if ($fall->anhaenge->isNotEmpty())
                    <p class="text-sm text-amber-700">
                        „Erledigt" löscht die {{ $fall->anhaenge->count() }} Bildschirmfotos
                        dieses Falls.
                    </p>
                @endif

                <p class="text-sm text-gray-500">
                    Benachrichtigung:
                    {{ $fall->benachrichtigt_am
                        ? 'zugestellt am ' . $fall->benachrichtigt_am->format('d.m.Y H:i')
                        : 'steht aus (support:nachbenachrichtigen)' }}
                </p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Verlauf</h3>

                @if ($fall->nachrichten->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">Noch nichts.</p>
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

                <form method="POST" action="{{ route('admin.support.antwort', $fall) }}" class="mt-5 space-y-2">
                    @csrf
                    <label for="text" class="block text-sm font-medium text-gray-700">Antworten</label>
                    <textarea id="text" name="text" rows="5" required maxlength="5000"
                              class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @error('text')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Antwort absenden
                    </button>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Kontext zum Zeitpunkt der Meldung</h3>
                <div class="mt-3">
                    @include('support.partials.kontext', ['kontext' => $fall->kontext ?? []])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
