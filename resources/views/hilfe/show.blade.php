<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                ❓ {{ $kapitel->titel }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('hilfe.handbuch') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    ↓ Handbuch
                </a>
                <a href="{{ route('hilfe.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    ← Übersicht
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid lg:grid-cols-4 gap-6">

            <div class="lg:col-span-3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 lg:p-8">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $kapitel->gruppe }}</p>
                <div class="hilfe-inhalt mt-3">
                    {!! $html !!}
                </div>

                @if ($kapitel->routenListe() !== [])
                    <div class="mt-8 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500">
                            Dieses Kapitel erscheint am „?" auf allen Seiten unterhalb von
                            <code class="text-gray-600">{{ implode(', ', $kapitel->routenListe()) }}</code>.
                        </p>
                    </div>
                @endif
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 lg:sticky lg:top-6">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Weitere Kapitel</h3>

                    @foreach ($gruppen as $gruppe => $kapitelliste)
                        <p class="mt-4 text-xs font-semibold text-gray-700">{{ $gruppe }}</p>
                        <ul class="mt-1 space-y-0.5">
                            @foreach ($kapitelliste as $eintrag)
                                <li>
                                    <a href="{{ route('hilfe.show', $eintrag->slug) }}"
                                       class="text-sm {{ $eintrag->slug === $kapitel->slug ? 'font-semibold text-gray-900' : 'text-indigo-600 hover:text-indigo-800' }}">
                                        {{ $eintrag->titel }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
