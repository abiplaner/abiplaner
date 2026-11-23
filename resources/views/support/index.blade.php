<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Meldungen an den Support
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-4 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600">
                    Hier stehen alle Meldungen Ihrer Schule — auch die Ihrer Kolleginnen und
                    Kollegen. Eine neue Meldung schreiben Sie über das
                    <strong>„!"</strong> oben rechts; von dort gehen die technischen Angaben
                    automatisch mit.
                </p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($faelle->isEmpty())
                    <p class="p-6 text-sm text-gray-500">Noch keine Meldung.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-6 py-3 text-left">Betreff</th>
                                <th class="px-6 py-3 text-left">Gemeldet</th>
                                <th class="px-6 py-3 text-left">Von</th>
                                <th class="px-6 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($faelle as $fall)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-3">
                                        <a href="{{ route('support.show', $fall) }}"
                                           class="font-medium text-indigo-600 hover:underline">
                                            {{ $fall->betreff }}
                                        </a>
                                        @if (($fall->ungelesen_count ?? 0) > 0)
                                            <span class="ms-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-800">
                                                neue Antwort
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-gray-600">{{ $fall->created_at?->format('d.m.Y H:i') }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $fall->melder_name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $fall->statusText() }}</td>
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
