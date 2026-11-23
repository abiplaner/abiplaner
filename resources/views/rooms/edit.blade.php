<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Raum bearbeiten') }}: {{ $room->room_number }}
            </h2>
        </div>
    </x-slot>

@php
    $templateData = $seatingTemplates->map(fn($t) => [
        'id'          => $t->id,
        'name'        => $t->name,
        'rows'        => $t->rows,
        'columns'     => $t->columns,
        'orientation' => $t->orientation ?? 'top',
        'available'   => $t->seats->where('seat_type', 'available')->count(),
        'reserve'     => $t->seats->where('seat_type', 'reserve')->count(),
        'edit_url'    => route('seating-templates.edit', $t->id),
    ])->keyBy('id');
    $currentStandardId = $room->seatingTemplateVariants->firstWhere('variant', 'standard')?->seating_template_id;
    $currentHvId = $room->seatingTemplateVariants->firstWhere('variant', 'hoerverstaendnis')?->seating_template_id;
@endphp

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8"
             x-data="{
                selectedTemplateId: '{{ old('seating_template_id', $currentStandardId) }}',
                templates: {{ json_encode($templateData) }},
                get selectedTemplate() {
                    return this.selectedTemplateId ? (this.templates[this.selectedTemplateId] ?? null) : null;
                },
                orientationLabel(o) {
                    const map = { top: 'Pult oben', bottom: 'Pult unten', left: 'Pult links', right: 'Pult rechts' };
                    return map[o] ?? o;
                }
             }">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('rooms.update', $room) }}">
                        @csrf
                        @method('PUT')

                        <!-- Raumnummer -->
                        <div class="mb-4">
                            <x-input-label for="room_number" :value="__('Raumnummer')" />
                            <x-text-input id="room_number" class="block mt-1 w-full" type="text" name="room_number"
                                :value="old('room_number', $room->room_number)" required autofocus />
                            <x-input-error :messages="$errors->get('room_number')" class="mt-2" />
                        </div>

                        <!-- Raumtyp -->
                        <div class="mb-4">
                            <x-input-label for="room_type" :value="__('Raumtyp')" />
                            <select id="room_type" name="room_type"
                                class="block mt-1 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="exam"        {{ old('room_type', $room->room_type) == 'exam'        ? 'selected' : '' }}>Prüfungsraum</option>
                                <option value="preparation" {{ old('room_type', $room->room_type) == 'preparation' ? 'selected' : '' }}>Vorbereitungsraum</option>
                                <option value="waiting"     {{ old('room_type', $room->room_type) == 'waiting'     ? 'selected' : '' }}>Warteraum</option>
                                <option value="lockdown"    {{ old('room_type', $room->room_type) == 'lockdown'    ? 'selected' : '' }}>Absperrraum</option>
                                <option value="corridor"    {{ old('room_type', $room->room_type) == 'corridor'    ? 'selected' : '' }}>Gangraum</option>
                            </select>
                            <x-input-error :messages="$errors->get('room_type')" class="mt-2" />
                        </div>

                        <!-- Sitzplan-Vorlage -->
                        <div class="mb-4">
                            <x-input-label for="seating_template_id" :value="__('Sitzplan-Vorlage (optional)')" />
                            <select id="seating_template_id" name="seating_template_id"
                                    x-model="selectedTemplateId"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">-- Keine Vorlage --</option>
                                @foreach($seatingTemplates as $template)
                                    <option value="{{ $template->id }}">
                                        {{ $template->name }}
                                        ({{ $template->rows }}×{{ $template->columns }},
                                        {{ $template->seats->where('seat_type', 'available')->count() }} Plätze)
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('seating_template_id')" class="mt-2" />

                            <!-- Vorlagen-Info-Karte -->
                            <div x-show="selectedTemplate"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="mt-3 p-4 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-1 text-sm">
                                        <p class="font-semibold text-blue-900 dark:text-blue-100" x-text="selectedTemplate?.name"></p>
                                        <div class="flex flex-wrap gap-4 text-blue-800 dark:text-blue-200">
                                            <span>
                                                📐 <span x-text="selectedTemplate?.rows"></span> × <span x-text="selectedTemplate?.columns"></span> Raster
                                            </span>
                                            <span>
                                                ✅ <span x-text="selectedTemplate?.available"></span> verfügbare Plätze
                                            </span>
                                            <template x-if="selectedTemplate?.reserve > 0">
                                                <span>
                                                    🔶 <span x-text="selectedTemplate?.reserve"></span> Reserve
                                                </span>
                                            </template>
                                            <span>
                                                🎓 <span x-text="orientationLabel(selectedTemplate?.orientation)"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <a :href="selectedTemplate?.edit_url"
                                       class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-300 bg-white dark:bg-gray-700 border border-blue-300 dark:border-blue-600 rounded-lg hover:bg-blue-50 dark:hover:bg-gray-600 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Vorlage bearbeiten
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Sitzplan-Vorlage: Hörverstehen -->
                        <div class="mb-4">
                            <x-input-label for="hv_seating_template_id" :value="__('Sitzplan-Vorlage Hörverstehen (optional)')" />
                            <select id="hv_seating_template_id" name="hv_seating_template_id"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">-- Wie Standard --</option>
                                @foreach($seatingTemplates as $template)
                                    <option value="{{ $template->id }}"
                                        {{ old('hv_seating_template_id', $currentHvId) == $template->id ? 'selected' : '' }}>
                                        {{ $template->name }}
                                        ({{ $template->rows }}×{{ $template->columns }},
                                        {{ $template->seats->where('seat_type', 'available')->count() }} Plätze)
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Nur nötig, wenn für den Hörverstehen-Teil (moderne Fremdsprachen) ein abweichender Sitzplan im selben Raum verwendet wird.
                            </p>
                            <x-input-error :messages="$errors->get('hv_seating_template_id')" class="mt-2" />
                        </div>

                        <!-- Notizen -->
                        <div class="mb-4">
                            <x-input-label for="notes" :value="__('Notizen (optional)')" />
                            <textarea id="notes" name="notes" rows="3"
                                class="block mt-1 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $room->notes) }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <!-- Aktiv -->
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" value="1"
                                       {{ old('is_active', $room->is_active) ? 'checked' : '' }}
                                       class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700">
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Raum ist aktiv') }}</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-between mt-6">
                            <a href="{{ route('rooms.index') }}"
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                                Abbrechen
                            </a>
                            <div class="flex gap-4">
                                @can('delete', $room)
                                    <button type="button"
                                            data-confirm="Raum wirklich löschen?" data-submit="deleteForm"
                                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                        Löschen
                                    </button>
                                @endcan
                                <x-primary-button>
                                    {{ __('Änderungen speichern') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>

                    <!-- Delete Form -->
                    @can('delete', $room)
                        <form id="deleteForm" method="POST" action="{{ route('rooms.destroy', $room) }}" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
