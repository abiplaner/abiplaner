<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Jahrgangsliste-Import: Vorschau ({{ $examPeriod->name }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="mb-6 bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-2">{{ $originalFilename }}</h3>
                <p class="text-sm text-gray-600">
                    Jahrgangsstufe {{ $grade }} ·
                    {{ count($preview['rows']) }} Namen gelesen ·
                    <span class="text-green-700">{{ $preview['new_count'] }} neu</span> ·
                    <span class="text-gray-500">{{ $preview['existing_count'] }} bereits vorhanden</span>
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden mb-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-500">
                            <th class="py-2 px-4">Name</th>
                            <th class="py-2 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($preview['rows'] as $row)
                            <tr>
                                <td class="py-1.5 px-4">{{ $row['lastname'] }}, {{ $row['firstname'] }}</td>
                                <td class="py-1.5 px-4 {{ $row['status'] === 'neu' ? 'text-green-700' : 'text-gray-400' }}">
                                    {{ $row['status'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <form action="{{ route('import.simple-process') }}" method="POST">
                @csrf
                <input type="hidden" name="temp_path" value="{{ $tempPath }}">
                <input type="hidden" name="grade" value="{{ $grade }}">
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('import.form') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Abbrechen
                    </a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        {{ $preview['new_count'] }} Schüler importieren
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
