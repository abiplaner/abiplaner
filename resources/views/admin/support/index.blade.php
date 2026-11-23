<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Support — Fälle aller Schulen
            </h2>
            <span class="text-sm text-gray-600">{{ $offen }} offen</span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">


            @if ($langeOffen > 0)
                {{--
                    Entscheidung 53: Die Aufbewahrungsfristen hängen an
                    `geschlossen_am`. Ein Fall, der offen bleibt, wird nie
                    gelöscht — dieser Hinweis verhindert, dass „offen lassen"
                    zum Aufbewahrungstrick wird.
                --}}
                <div class="bg-amber-50 border-l-4 border-amber-400 text-amber-900 rounded-md p-4 text-sm">
                    <strong>{{ $langeOffen }}</strong>
                    {{ $langeOffen === 1 ? 'Fall steht' : 'Fälle stehen' }}
                    seit mehr als {{ $langeOffenMonate }} Monaten offen.
                    Die Aufbewahrungsfristen zählen ab dem Abschluss — was offen bleibt,
                    wird nie gelöscht. Bitte abschließen, wenn erledigt.
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                <nav class="flex flex-wrap gap-2 text-sm">
                    @php
                        $filter = ['offen' => 'Offen', 'alle' => 'Alle'] + \App\Models\SupportFall::STATUS_TEXT;
                    @endphp
                    @foreach ($filter as $wert => $beschriftung)
                        <a href="{{ route('admin.support.index', ['status' => $wert]) }}"
                           class="px-3 py-1 rounded-full border {{ $status === $wert ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                            {{ $beschriftung }}
                        </a>
                    @endforeach
                </nav>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($faelle->isEmpty())
                    <p class="p-6 text-sm text-gray-500">Kein Fall in dieser Auswahl.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-6 py-3 text-left">Schule</th>
                                <th class="px-6 py-3 text-left">Betreff</th>
                                <th class="px-6 py-3 text-left">Maske</th>
                                <th class="px-6 py-3 text-left">Eingegangen</th>
                                <th class="px-6 py-3 text-left">Status</th>
                                <th class="px-6 py-3 text-left">Mail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($faelle as $fall)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-3 text-gray-700">
                                        {{ $fall->schule_name ?? $fall->school_id ?? '—' }}
                                    </td>
                                    <td class="px-6 py-3">
                                        <a href="{{ route('admin.support.show', $fall) }}"
                                           class="font-medium text-indigo-600 hover:underline">
                                            {{ $fall->betreff }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-3 text-gray-500">{{ $fall->route_name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $fall->created_at?->format('d.m.Y H:i') }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $fall->statusText() }}</td>
                                    <td class="px-6 py-3">
                                        @if ($fall->benachrichtigt_am)
                                            <span class="text-green-700">✓</span>
                                        @else
                                            <span class="text-amber-700" title="Versand steht aus">steht aus</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{ $faelle->links() }}
        </div>
    </div>
</x-app-layout>
