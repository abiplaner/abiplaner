<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Hilfe verwalten
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('hilfe.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Hilfe ansehen
                </a>
                <a href="{{ route('admin.hilfe.create') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Neues Kapitel
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600">
                    {{ $anzahl }} Kapitel. Sie gelten <strong>für alle Schulen</strong> — es gibt keine
                    schulspezifischen Fassungen. Änderungen sind sofort wirksam, ohne Deploy.
                </p>
                <p class="text-sm text-gray-600 mt-2">
                    Ein Kapitel erscheint am „?" auf allen Seiten, deren Routenname mit einem seiner
                    Präfixe beginnt. Bei mehreren passenden gewinnt das längste Präfix.
                </p>
            </div>

            @if($luecken !== [] || $toteVerweise !== [])
                <div class="bg-amber-50 border-l-4 border-amber-400 p-4">
                    <h3 class="font-semibold text-amber-800">Deckung unvollständig</h3>

                    @if($luecken !== [])
                        <p class="text-sm text-amber-800 mt-2">
                            <strong>{{ count($luecken) }} Seite(n) ohne Kapitel.</strong>
                            Dort zeigt das „?" einen leeren Kasten:
                        </p>
                        <p class="text-xs text-amber-700 mt-1 font-mono">{{ implode(', ', $luecken) }}</p>
                    @endif

                    @if($toteVerweise !== [])
                        <p class="text-sm text-amber-800 mt-3">
                            <strong>{{ count($toteVerweise) }} toter Routenverweis(e).</strong>
                            Diese Kapitel erscheinen nirgends:
                        </p>
                        <p class="text-xs text-amber-700 mt-1 font-mono">{{ implode(' · ', $toteVerweise) }}</p>
                    @endif
                </div>
            @endif

            @forelse($gruppen as $gruppe => $kapitelliste)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-sm font-semibold text-gray-700">{{ $gruppe }}</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-2 w-16">Nr.</th>
                                <th class="px-6 py-2">Titel</th>
                                <th class="px-6 py-2">Routen</th>
                                <th class="px-6 py-2 w-32">Stand</th>
                                <th class="px-6 py-2 w-24"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($kapitelliste as $kapitel)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-3 text-sm text-gray-500">{{ $kapitel->reihenfolge }}</td>
                                    <td class="px-6 py-3">
                                        <a href="{{ route('admin.hilfe.edit', $kapitel) }}"
                                           class="text-indigo-600 font-medium hover:text-indigo-800">
                                            {{ $kapitel->titel }}
                                        </a>
                                        <div class="text-xs text-gray-400 font-mono">{{ $kapitel->slug }}</div>
                                    </td>
                                    <td class="px-6 py-3 text-xs text-gray-500 font-mono">
                                        {{ $kapitel->routen ?: '—' }}
                                    </td>
                                    <td class="px-6 py-3 text-xs">
                                        @if($kapitel->istRedigiert())
                                            <span class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-800">redigiert</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-600">Grundfassung</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <a href="{{ route('admin.hilfe.edit', $kapitel) }}"
                                           class="text-sm text-gray-600 hover:text-gray-900">Bearbeiten</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-600">
                        Es ist kein Kapitel hinterlegt. Die Grundfassung spielt
                        <code>php artisan hilfe:einspielen</code> ein.
                    </p>
                </div>
            @endforelse

        </div>
    </div>
</x-app-layout>
