<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Räume
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('seating-templates.index') }}" 
                   class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                    📐 Sitzplan-Vorlagen
                </a>
                @unlessarchive
                @can('create', \App\Models\Room::class)
                    <button data-modal-open="createRoomModal"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        ➕ Neuer Raum
                    </button>
                @endcan
                @endarchive
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    {{ session('error') }}
                </div>
            @endif

            @if($rooms->count() > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Raumnummer
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Typ
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Sitzplan-Vorlage
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Notizen
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($rooms as $room)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ !$room->is_active ? 'opacity-50' : '' }}">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                Raum {{ $room->room_number }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-600 dark:text-gray-300">
                                                @switch($room->room_type)
                                                    @case('exam')
                                                        📝 Prüfung
                                                        @break
                                                    @case('preparation')
                                                        📚 Vorbereitung
                                                        @break
                                                    @case('waiting')
                                                        ⏳ Warten
                                                        @break
                                                    @case('lockdown')
                                                        🚫 Absperr
                                                        @break
                                                    @case('corridor')
                                                        🚪 Gang
                                                        @break
                                                @endswitch
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $standardVariant = $room->seatingTemplateVariants->firstWhere('variant', 'standard');
                                                $hvVariant = $room->seatingTemplateVariants->firstWhere('variant', 'hoerverstaendnis');
                                            @endphp
                                            @if($standardVariant)
                                                <div class="text-sm">
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $standardVariant->seatingTemplate->name }}
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $standardVariant->seatingTemplate->rows }}×{{ $standardVariant->seatingTemplate->columns }} 
                                                        ({{ $standardVariant->seatingTemplate->seats->where('seat_type', 'available')->count() }} Plätze)
                                                    </div>
                                                    @if($hvVariant)
                                                        <div class="text-xs text-teal-600 dark:text-teal-400 mt-0.5">
                                                            🎧 HV: {{ $hvVariant->seatingTemplate->name }}
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-sm text-gray-400 dark:text-gray-500">
                                                    Keine Vorlage zugewiesen
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($room->is_active)
                                                <span class="inline-flex px-2 py-1 text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    Aktiv
                                                </span>
                                            @else
                                                <span class="inline-flex px-2 py-1 text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                    Inaktiv
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 max-w-xs">
                                            <div class="text-sm text-gray-600 dark:text-gray-300 truncate" title="{{ $room->notes }}">
                                                {{ $room->notes ?? '-' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            @unlessarchive
                                            <div class="flex justify-end space-x-2">
                                                @can('update', $room)
                                                    <a href="{{ route('rooms.edit', $room) }}"
                                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                            title="Bearbeiten">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </a>
                                                @endcan
                                                @can('delete', $room)
                                                    <form action="{{ route('rooms.destroy', $room) }}" method="POST" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" 
                                                                data-confirm="Raum wirklich löschen?"
                                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                                title="Löschen">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endcan
                                            </div>
                                            @endarchive
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Noch keine Räume vorhanden</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Erstellen Sie Räume für Prüfungen, Vorbereitung, Warten und mehr.
                        </p>
                        @unlessarchive
                        @can('create', \App\Models\Room::class)
                            <button data-modal-open="createRoomModal"
                                    class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Ersten Raum erstellen
                            </button>
                        @endcan
                        @endarchive
                    </div>
                </div>
            @endif

        </div>
    </div>

    {{-- Modal für Raum erstellen --}}
    <div id="createRoomModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Neuer Raum</h3>
                <button data-modal-close="createRoomModal"
                        class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form action="{{ route('rooms.store') }}" method="POST">
                @csrf
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Raumnummer</label>
                        <input type="text" name="room_number" required
                               class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               placeholder="z.B. 403">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Raumtyp</label>
                        <select name="room_type" required
                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="exam">📝 Prüfungsraum</option>
                            <option value="preparation">📚 Vorbereitungsraum</option>
                            <option value="waiting">⏳ Warteraum</option>
                            <option value="lockdown">🚫 Absperrraum</option>
                            <option value="corridor">🚪 Gangraum</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sitzplan-Vorlage (Standard)</label>
                        <select name="seating_template_id"
                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Keine Vorlage —</option>
                            @foreach($seatingTemplates as $template)
                                <option value="{{ $template->id }}">
                                    {{ $template->name }}
                                    ({{ $template->rows }}×{{ $template->columns }},
                                    {{ $template->seats->where('seat_type', 'available')->count() }} Plätze)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sitzplan-Vorlage Hörverstehen (optional)</label>
                        <select name="hv_seating_template_id"
                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Wie Standard —</option>
                            @foreach($seatingTemplates as $template)
                                <option value="{{ $template->id }}">
                                    {{ $template->name }}
                                    ({{ $template->rows }}×{{ $template->columns }},
                                    {{ $template->seats->where('seat_type', 'available')->count() }} Plätze)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kapazität</label>
                        <input type="number" name="capacity" value="1" min="1"
                               class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notizen</label>
                        <textarea name="notes" rows="2"
                                  class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                  placeholder="z.B. Beamer verfügbar, barrierefrei, etc."></textarea>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" value="1" checked
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <label for="is_active" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Aktiv</label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" 
                            data-modal-close="createRoomModal"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Abbrechen
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Erstellen
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

<script @cspNonce>
</script>
