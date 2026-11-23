{{-- Preisarten-Modal (Zahnrad) --}}
<div x-data="awardTypesModal()"
     @open-award-types-modal.window="show = true"
     x-show="show"
     class="fixed inset-0 z-50 flex items-center justify-center"
     style="display:none">

    <div class="absolute inset-0 bg-black bg-opacity-40" @click="show = false"></div>

    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-3xl mx-4"
         style="max-height: 90vh; overflow-y: auto;">

        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Preisarten verwalten</h3>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
        </div>

        <div class="p-6 space-y-2">

            {{-- Bestehende Preisarten --}}
            @forelse($awardTypes as $awardType)
                @php $at = $awardType; @endphp
                <div x-data="{ editing: false }" class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">

                    {{-- Zeile: Normalansicht --}}
                    <div x-show="!editing"
                         class="flex items-center justify-between px-4 py-3 bg-white dark:bg-gray-800">
                        <div class="flex items-center space-x-3 flex-1 min-w-0">
                            <span class="font-medium text-gray-800 dark:text-gray-100 truncate">
                                {{ $at->name }}
                            </span>
                            @if($at->category)
                                <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-full shrink-0">
                                    {{ $at->category }}
                                </span>
                            @endif
                            @php $lkAt = $at->getRelation('teacher'); @endphp
                            @if($lkAt)
                                <span class="text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded-full shrink-0">
                                    👤 {{ $lkAt->shortcode }}
                                </span>
                            @endif
                            @if($at->certificate_in_envelope)
                                <span class="text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 rounded-full shrink-0">
                                    ✉️ Urkunde im Kuvert
                                </span>
                            @endif
                            @if($at->has_physical_prize)
                                <span class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-2 py-0.5 rounded-full shrink-0">
                                    🎁 Sachpreis
                                </span>
                            @endif
                            @if(!$at->active)
                                <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-full shrink-0">
                                    inaktiv
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center space-x-2 shrink-0 ml-3">
                            @unlessarchive
                            <button @click="editing = true"
                                    class="px-3 py-1 text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded hover:bg-blue-100 dark:hover:bg-blue-900/50 font-medium">
                                ✎ Bearbeiten
                            </button>
                            <form method="POST"
                                  action="{{ route('abiturfeier.award-types.destroy', $at) }}"
                                  data-confirm="Preisart »{{ $at->name }}« löschen?">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="px-3 py-1 text-xs bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded hover:bg-red-100 dark:hover:bg-red-900/50 font-medium">
                                    🗑 Löschen
                                </button>
                            </form>
                            @endarchive
                        </div>
                    </div>

                    {{-- Zeile: Bearbeitungsmodus --}}
                    <div x-show="editing"
                         class="p-4 bg-blue-50 dark:bg-gray-700 border-t border-blue-100 dark:border-gray-600">
                        <form method="POST"
                              action="{{ route('abiturfeier.award-types.update', $at) }}"
                              class="space-y-3">
                            @csrf @method('PUT')

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Name *</label>
                                    <input type="text" name="name" value="{{ $at->name }}" required
                                           class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-600 rounded-md shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Kategorie</label>
                                    <input type="text" name="category" value="{{ $at->category }}"
                                           placeholder="z. B. Fachpreis"
                                           class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-600 rounded-md shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Verantw. Lehrkraft</label>
                                    <select name="teacher_id"
                                            class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-600 rounded-md shadow-sm">
                                        <option value="">–</option>
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->id }}"
                                                    {{ $at->teacher_id == $teacher->id ? 'selected' : '' }}>
                                                {{ $teacher->shortcode }} – {{ $teacher->lastname }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Sortierung</label>
                                    <input type="number" name="sort_order" value="{{ $at->sort_order }}" min="0"
                                           class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-600 rounded-md shadow-sm">
                                </div>
                            </div>

                            {{-- Kein-Preisträger-Hinweis --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                                    Hinweis bei fehlendem Preisträger
                                    <span class="font-normal text-gray-400">(optional — erscheint im PDF wenn kein Schüler eingetragen)</span>
                                </label>
                                <input type="text" name="no_winner_note" value="{{ $at->no_winner_note }}" maxlength="300"
                                       placeholder="z. B. In diesem Jahr erfüllt keine*r die Bedingungen."
                                       class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-600 rounded-md shadow-sm">
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                {{-- Aktiv-Toggle + neue Flags --}}
                                <div class="flex items-center space-x-4">
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="hidden" name="active" value="0">
                                        <input type="checkbox" name="active" value="1"
                                               {{ $at->active ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Aktiv</span>
                                    </label>
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="hidden" name="certificate_in_envelope" value="0">
                                        <input type="checkbox" name="certificate_in_envelope" value="1"
                                               {{ $at->certificate_in_envelope ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-emerald-600">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Urkunde im Kuvert</span>
                                    </label>
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="hidden" name="has_physical_prize" value="0">
                                        <input type="checkbox" name="has_physical_prize" value="1"
                                               {{ $at->has_physical_prize ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-amber-600">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Sachpreis vorhanden</span>
                                    </label>
                                </div>

                                <div class="flex space-x-2">
                                    <button type="button" @click="editing = false"
                                            class="px-3 py-1.5 text-sm bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded hover:bg-gray-300">
                                        Abbrechen
                                    </button>
                                    <button type="submit"
                                            class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                                        Speichern
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            @empty
                <p class="text-center text-gray-400 py-4">Noch keine Preisarten vorhanden.</p>
            @endforelse

            {{-- Neue Preisart --}}
            @unlessarchive
            <div class="border-t border-gray-200 dark:border-gray-600 pt-5 mt-4">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">+ Neue Preisart anlegen</p>
                <form method="POST" action="{{ route('abiturfeier.award-types.store') }}"
                      class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Name *</label>
                            <input type="text" name="name" required placeholder="z. B. Abiturpreis"
                                   class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Kategorie</label>
                            <input type="text" name="category" placeholder="z. B. Fachpreis"
                                   class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Verantw. Lehrkraft</label>
                            <select name="teacher_id"
                                    class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm">
                                <option value="">–</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}">{{ $teacher->shortcode }} – {{ $teacher->lastname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Sortierung</label>
                            <input type="number" name="sort_order" value="0" min="0"
                                   class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                            Hinweis bei fehlendem Preisträger
                            <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <input type="text" name="no_winner_note" maxlength="300"
                               placeholder="z. B. In diesem Jahr erfüllt keine*r die Bedingungen."
                               class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm">
                    </div>
                    <div class="flex items-center space-x-4">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="hidden" name="certificate_in_envelope" value="0">
                            <input type="checkbox" name="certificate_in_envelope" value="1"
                                   class="rounded border-gray-300 text-emerald-600">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Urkunde im Kuvert</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="hidden" name="has_physical_prize" value="0">
                            <input type="checkbox" name="has_physical_prize" value="1"
                                   class="rounded border-gray-300 text-amber-600">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Sachpreis vorhanden</span>
                        </label>
                    </div>
                    <div>
                        <button type="submit"
                                class="px-4 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700">
                            + Anlegen
                        </button>
                    </div>
                </form>
            </div>
            @endarchive

            <p class="text-xs text-gray-400 pt-2">
                Sortierung: Kleinere Zahlen erscheinen zuerst. Inaktive Preisarten können nicht neu vergeben werden.
            </p>
        </div>
    </div>
</div>

<script @cspNonce>
function awardTypesModal() {
    return { show: false };
}
</script>
