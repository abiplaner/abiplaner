<div class="space-y-6">

    {{-- Name --}}
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
            Name <span class="text-red-500">*</span>
        </label>
        <input type="text" id="name" name="name"
               value="{{ old('name', $slot?->name) }}"
               placeholder="z.B. Biologie gA oder Sammeltag gA – Gesellschaft"
               class="w-full px-3 py-2 border rounded-md text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500
                      {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    {{-- Anforderungsniveau --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
            Anforderungsniveau <span class="text-red-500">*</span>
        </label>
        <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="requirement_level" value="grundlegend"
                       {{ old('requirement_level', $slot?->requirement_level) === 'grundlegend' ? 'checked' : '' }}
                       class="text-blue-600">
                <span class="text-sm text-gray-700">Grundlegendes Anforderungsniveau (gA)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="requirement_level" value="erhoeht"
                       {{ old('requirement_level', $slot?->requirement_level) === 'erhoeht' ? 'checked' : '' }}
                       class="text-purple-600">
                <span class="text-sm text-gray-700">Erhöhtes Anforderungsniveau (eA)</span>
            </label>
        </div>
        <x-input-error :messages="$errors->get('requirement_level')" class="mt-1" />
    </div>

    {{-- Uhrzeiten --}}
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="start_time" class="block text-sm font-medium text-gray-700 mb-1">
                Prüfungsbeginn <span class="text-red-500">*</span>
            </label>
            <input type="time" id="start_time" name="start_time"
                   value="{{ old('start_time', $slot ? \Carbon\Carbon::parse($slot->start_time)->format('H:i') : '') }}"
                   class="w-full px-3 py-2 border rounded-md text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500
                          {{ $errors->has('start_time') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
            <x-input-error :messages="$errors->get('start_time')" class="mt-1" />
        </div>
        <div>
            <label for="end_time" class="block text-sm font-medium text-gray-700 mb-1">
                Reguläres Ende <span class="text-red-500">*</span>
            </label>
            <input type="time" id="end_time" name="end_time"
                   value="{{ old('end_time', $slot ? \Carbon\Carbon::parse($slot->end_time)->format('H:i') : '') }}"
                   class="w-full px-3 py-2 border rounded-md text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500
                          {{ $errors->has('end_time') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
            <x-input-error :messages="$errors->get('end_time')" class="mt-1" />
        </div>
    </div>

    {{-- Sort-Order --}}
    <div>
        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">
            Sortierung <span class="text-red-500">*</span>
        </label>
        <input type="number" id="sort_order" name="sort_order" min="0" max="9999"
               value="{{ old('sort_order', $slot?->sort_order ?? 0) }}"
               class="w-32 px-3 py-2 border rounded-md text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500
                      {{ $errors->has('sort_order') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
        <p class="text-xs text-gray-500 mt-1">
            Bestimmt die Anzeigereihenfolge. Konvention: 10/11 = Bio gA/eA, 80–84 = Sammeltag gA, 90–97 = Sammeltag eA
        </p>
        <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
    </div>

    {{-- Fächer --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
            Zugeordnete Fächer
        </label>
        <p class="text-xs text-gray-500 mb-3">
            Welche Fächer werden in diesem Slot geprüft? Mehrfachauswahl möglich.
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-64 overflow-y-auto border border-gray-200 rounded-md p-3 bg-gray-50">
            @foreach($subjects as $subject)
                <label class="flex items-center gap-2 cursor-pointer hover:bg-white rounded px-1 py-0.5">
                    <input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}"
                           {{ in_array($subject->id, old('subject_ids', $selectedSubjectIds ?? [])) ? 'checked' : '' }}
                           class="rounded text-blue-600">
                    <span class="flex items-center gap-1 text-sm text-gray-700">
                        <span class="inline-block w-3 h-3 rounded-sm flex-shrink-0"
                              style="background-color: {{ $subject->color }}"></span>
                        <span class="font-mono text-xs text-gray-500">{{ $subject->code }}</span>
                        {{ $subject->name }}
                    </span>
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$errors->get('subject_ids')" class="mt-1" />
        <x-input-error :messages="$errors->get('subject_ids.*')" class="mt-1" />
    </div>

</div>
