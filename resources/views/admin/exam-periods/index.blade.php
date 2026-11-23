<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Prüfungszeiträume verwalten
            </h2>
            <a href="{{ route('admin.exam-periods.create') }}" 
               class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Neuer Prüfungszeitraum
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            


            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($examPeriods->count() > 0)
                        <div class="space-y-4">
                            @foreach($examPeriods as $period)
                                <div class="border rounded-lg p-4 hover:bg-gray-50">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h3 class="text-lg font-semibold text-gray-900">{{ $period->name }}</h3>
                                            <p class="text-sm text-gray-600 mt-1">
                                                Jahr: {{ $period->year }}
                                            </p>
                                            <div class="mt-2 text-sm text-gray-700">
                                                <div><strong>Woche 1:</strong> {{ $period->week_1_start->format('d.m.Y') }} - {{ $period->week_1_end->format('d.m.Y') }}</div>
                                                <div><strong>Woche 2:</strong> {{ $period->week_2_start->format('d.m.Y') }} - {{ $period->week_2_end->format('d.m.Y') }}</div>
                                            </div>
                                            {{-- Keine Dauer-Marken: exam_periods
                                                 hat weder exam_duration_minutes
                                                 noch prep_duration_minutes. Die
                                                 beiden Marken standen leer da
                                                 ("Prüfungsdauer:  Min."). --}}
                                        </div>
                                        <div class="flex space-x-2">
                                            <a href="{{ route('admin.exam-periods.edit', $period) }}" 
                                               class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                                Bearbeiten
                                            </a>
                                            <form action="{{ route('admin.exam-periods.destroy', $period) }}" 
                                                  method="POST" 
                                                  class="inline"
                                                  data-confirm="Prüfungszeitraum wirklich löschen? Dies kann nicht rückgängig gemacht werden!">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="px-3 py-1 bg-red-600 text-white text-sm rounded-md hover:bg-red-700">
                                                    Löschen
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            {{ $examPeriods->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Keine Prüfungszeiträume</h3>
                            <p class="mt-1 text-sm text-gray-500">Erstellen Sie einen neuen Prüfungszeitraum.</p>
                            <div class="mt-6">
                                <a href="{{ route('admin.exam-periods.create') }}" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Neuer Prüfungszeitraum
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
