@php
    /** @var \App\Models\ExamMaterial|null $examMaterial */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subject> $faecher */
    /** @var \Illuminate\Support\Collection $zuordnungen */
    $examMaterial ??= null;

    // Was im Formular steht: nach einer abgewiesenen Eingabe das Eingetippte,
    // sonst der gespeicherte Stand.
    $alteFaecher = old('faecher');

    $istZugeordnet = static function (int $id) use ($alteFaecher, $zuordnungen): bool {
        if (is_array($alteFaecher)) {
            return filter_var($alteFaecher[$id]['zugeordnet'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return $zuordnungen->has($id);
    };

    $istPflicht = static function (int $id) use ($alteFaecher, $zuordnungen): bool {
        if (is_array($alteFaecher)) {
            return filter_var($alteFaecher[$id]['pflicht'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) ($zuordnungen->get($id)?->is_required ?? false);
    };

    $notiz = static function (int $id) use ($alteFaecher, $zuordnungen): string {
        if (is_array($alteFaecher)) {
            return (string) ($alteFaecher[$id]['notiz'] ?? '');
        }

        return (string) ($zuordnungen->get($id)?->notes ?? '');
    };
@endphp

<div class="mb-4">
    <x-input-label for="name" :value="__('Bezeichnung')" />
    <x-text-input id="name" name="name" type="text" class="block mt-1 w-full"
                  :value="old('name', $examMaterial?->name)" required autofocus />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mb-4">
    <x-input-label for="icon" :value="__('Symbol (optional)')" />
    <x-text-input id="icon" name="icon" type="text" class="block mt-1 w-24"
                  :value="old('icon', $examMaterial?->icon)" maxlength="50" />
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Ein Emoji, das in der Liste vor der Bezeichnung steht — etwa 📖, 📐 oder 🔢.
    </p>
    <x-input-error :messages="$errors->get('icon')" class="mt-2" />
</div>

<div class="mb-4">
    <x-input-label for="description" :value="__('Beschreibung (optional)')" />
    <textarea id="description" name="description" rows="3"
              class="block mt-1 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $examMaterial?->description) }}</textarea>
    <x-input-error :messages="$errors->get('description')" class="mt-2" />
</div>

<div x-data="{
        standard: {{ old('is_default', $examMaterial?->is_default) ? 'true' : 'false' }},
        alleVorschlagen() {
            this.$refs.liste.querySelectorAll('input[data-zugeordnet]').forEach((kaestchen) => {
                kaestchen.checked = true;
                kaestchen.dispatchEvent(new Event('change'));
            });
        }
     }">

    <div class="mb-6 border-t border-gray-200 dark:border-gray-700 pt-6">
        <label class="flex items-start">
            <input type="checkbox" name="is_default" value="1" x-model="standard"
                   @change="standard && alleVorschlagen()"
                   class="mt-1 rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700">
            <span class="ml-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Grundausstattung') }}</span>
                <span class="block text-sm text-gray-500 dark:text-gray-400">
                    Material, das in aller Regel gebraucht wird. Beim Ankreuzen werden unten
                    alle Fächer vorgeschlagen — die Auswahl lässt sich danach beliebig
                    korrigieren.
                </span>
            </span>
        </label>
        <x-input-error :messages="$errors->get('is_default')" class="mt-2" />
    </div>

    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('Zugeordnete Fächer') }}</h3>
        <p class="mt-1 mb-4 text-sm text-gray-500 dark:text-gray-400">
            Die Aufsichtsliste druckt je Fach eine Material-Checkliste und liest genau
            diese Zuordnungen. Ein Fach ohne Zuordnung erscheint dort ohne Materialien.
        </p>

        <div x-ref="liste" class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fach') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-32">{{ __('Pflicht') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Notiz') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                    @foreach($faecher as $fach)
                        <tr x-data="{ an: {{ $istZugeordnet($fach->id) ? 'true' : 'false' }} }"
                            :class="an ? '' : 'opacity-60'">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <label class="flex items-center">
                                    <input type="checkbox" data-zugeordnet
                                           name="faecher[{{ $fach->id }}][zugeordnet]" value="1"
                                           x-model="an"
                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700">
                                    <span class="ml-2 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $fach->name }}
                                        <span class="text-gray-400 dark:text-gray-500">({{ $fach->code }})</span>
                                    </span>
                                </label>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <label class="flex items-center">
                                    <input type="checkbox"
                                           name="faecher[{{ $fach->id }}][pflicht]" value="1"
                                           {{ $istPflicht($fach->id) ? 'checked' : '' }}
                                           :disabled="!an"
                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 disabled:opacity-50">
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ __('erforderlich') }}</span>
                                </label>
                            </td>
                            <td class="px-4 py-2">
                                <input type="text"
                                       name="faecher[{{ $fach->id }}][notiz]"
                                       value="{{ $notiz($fach->id) }}"
                                       maxlength="500"
                                       :disabled="!an"
                                       placeholder="{{ __('z. B. „nur unkommentierte Ausgabe“') }}"
                                       class="block w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm disabled:opacity-50">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-input-error :messages="$errors->get('faecher')" class="mt-2" />
    </div>
</div>
