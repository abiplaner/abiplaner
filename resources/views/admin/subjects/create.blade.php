<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Neues Fach anlegen
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.subjects.store') }}">
                        @csrf

                        <!-- Code -->
                        <div class="mb-4">
                            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">
                                Fachcode <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="code" 
                                   name="code" 
                                   value="{{ old('code') }}"
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('code') border-red-500 @enderror"
                                   placeholder="z.B. d, m, e, eth">
                            @error('code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-sm text-gray-500">Kleinbuchstaben, eindeutig (z.B. "d" für Deutsch, "m" für Mathematik)</p>
                        </div>

                        <!-- Name -->
                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                Fachname <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name') }}"
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                                   placeholder="z.B. Deutsch, Mathematik">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Farbe -->
                        <div class="mb-4">
                            <label for="color" class="block text-sm font-medium text-gray-700 mb-1">
                                Farbe <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center space-x-2">
                                <input type="color" 
                                       id="color" 
                                       name="color" 
                                       value="{{ old('color', '#3B82F6') }}"
                                       required
                                       class="h-10 w-20 border border-gray-300 rounded-md">
                                <input type="text" 
                                       id="color_text" 
                                       value="{{ old('color', '#3B82F6') }}"
                                       readonly
                                       class="px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-700 w-32">
                            </div>
                            @error('color')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-sm text-gray-500">Farbe für die Darstellung in der Oberfläche</p>
                        </div>

                        <!-- Aufgabenfeld -->
                        <div class="mb-4">
                            <label for="aufgabenfeld" class="block text-sm font-medium text-gray-700 mb-1">
                                Aufgabenfeld <span class="text-red-500">*</span>
                            </label>
                            <select id="aufgabenfeld" 
                                    name="aufgabenfeld" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('aufgabenfeld') border-red-500 @enderror">
                                <option value="">-- Bitte wählen --</option>
                                <option value="sprachlich_literarisch" {{ old('aufgabenfeld') == 'sprachlich_literarisch' ? 'selected' : '' }}>
                                    Sprachlich-literarisch-künstlerisch
                                </option>
                                <option value="gesellschaftswissenschaftlich" {{ old('aufgabenfeld') == 'gesellschaftswissenschaftlich' ? 'selected' : '' }}>
                                    Gesellschaftswissenschaftlich
                                </option>
                                <option value="mint" {{ old('aufgabenfeld') == 'mint' ? 'selected' : '' }}>
                                    Mathematisch-naturwissenschaftlich-technisch
                                </option>
                                <option value="sport" {{ old('aufgabenfeld') == 'sport' ? 'selected' : '' }}>
                                    Sport
                                </option>
                                <option value="sonstiges" {{ old('aufgabenfeld') == 'sonstiges' ? 'selected' : '' }}>
                                    Sonstiges
                                </option>
                            </select>
                            @error('aufgabenfeld')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Sortierung -->
                        <div class="mb-4">
                            <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">
                                Sortierreihenfolge
                            </label>
                            <input type="number" 
                                   id="sort_order" 
                                   name="sort_order" 
                                   value="{{ old('sort_order', 100) }}"
                                   min="0"
                                   step="10"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('sort_order') border-red-500 @enderror"
                                   placeholder="z.B. 100, 200, 300">
                            @error('sort_order')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-sm text-gray-500">Niedrigere Zahlen werden weiter oben angezeigt</p>
                        </div>

                        <!-- Eigenschaften -->
                        <div class="mb-4 space-y-3">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Eigenschaften
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       id="is_foreign_language" 
                                       name="is_foreign_language" 
                                       value="1"
                                       {{ old('is_foreign_language') ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Fremdsprache</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       id="is_science" 
                                       name="is_science" 
                                       value="1"
                                       {{ old('is_science') ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Naturwissenschaft</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       id="can_be_late_starting" 
                                       name="can_be_late_starting" 
                                       value="1"
                                       {{ old('can_be_late_starting') ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Kann spät beginnend sein</span>
                            </label>
                        </div>

                        <!-- Abitur möglich -->
                        <div class="mb-6">
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       id="can_abitur" 
                                       name="can_abitur" 
                                       value="1"
                                       {{ old('can_abitur', true) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">In diesem Fach kann Abitur gemacht werden</span>
                            </label>
                            <p class="mt-1 ml-6 text-sm text-gray-500">Z.B. Sport, Musik, Kunst können oft nicht als Abiturfach gewählt werden</p>
                        </div>

                        <!-- Buttons -->
                        <div class="flex items-center justify-end space-x-3">
                            <a href="{{ route('admin.subjects.index') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </a>
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Fach anlegen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script @cspNonce>
        // Sync color picker mit text input
        document.getElementById('color').addEventListener('input', function(e) {
            document.getElementById('color_text').value = e.target.value;
            document.getElementById('color').setAttribute('name', 'color');
        });
    </script>
</x-app-layout>
