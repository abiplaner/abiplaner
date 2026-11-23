<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Wahl bearbeiten: {{ $wseminarWahl->schueler->lastname }}, {{ $wseminarWahl->schueler->firstname }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('wseminar-wahlen.update', $wseminarWahl) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Schülerinformationen</h3>
                            <p class="mt-1 text-sm text-gray-600">Klasse: {{ $wseminarWahl->schueler->class_name ?? 'Unbekannt' }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Erstwahl -->
                            <div>
                                <label for="erstwahl_wseminar_id" class="block text-sm font-medium text-gray-700">Erstwahl</label>
                                <select id="erstwahl_wseminar_id" name="erstwahl_wseminar_id" required
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Bitte wählen...</option>
                                    @foreach($kurse as $kurs)
                                        <option value="{{ $kurs->id }}" {{ $wseminarWahl->erstwahl_wseminar_id == $kurs->id ? 'selected' : '' }}>
                                            {{ $kurs->subject?->name ?? 'Unbekannt' }} - {{ Str::limit($kurs->titel, 40) }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('erstwahl_wseminar_id')" class="mt-2" />
                            </div>

                            <!-- Zweitwahl -->
                            <div>
                                <label for="zweitwahl_wseminar_id" class="block text-sm font-medium text-gray-700">Zweitwahl</label>
                                <select id="zweitwahl_wseminar_id" name="zweitwahl_wseminar_id"
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Keine Auswahl</option>
                                    @foreach($kurse as $kurs)
                                        <option value="{{ $kurs->id }}" {{ $wseminarWahl->zweitwahl_wseminar_id == $kurs->id ? 'selected' : '' }}>
                                            {{ $kurs->subject?->name ?? 'Unbekannt' }} - {{ Str::limit($kurs->titel, 40) }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('zweitwahl_wseminar_id')" class="mt-2" />
                            </div>

                            <!-- Drittwahl -->
                            <div>
                                <label for="drittwahl_wseminar_id" class="block text-sm font-medium text-gray-700">Drittwahl</label>
                                <select id="drittwahl_wseminar_id" name="drittwahl_wseminar_id"
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Keine Auswahl</option>
                                    @foreach($kurse as $kurs)
                                        <option value="{{ $kurs->id }}" {{ $wseminarWahl->drittwahl_wseminar_id == $kurs->id ? 'selected' : '' }}>
                                            {{ $kurs->subject?->name ?? 'Unbekannt' }} - {{ Str::limit($kurs->titel, 40) }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('drittwahl_wseminar_id')" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-4">
                            <a href="{{ route('wseminar-wahlen.index') }}" class="text-gray-600 hover:text-gray-900">Abbrechen</a>
                            <x-primary-button>
                               Speichern
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
