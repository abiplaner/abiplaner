<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Neues Template erstellen
            </h2>
            <a href="{{ route('slot-templates.index', $examPeriod) }}" 
               class="text-gray-600 hover:text-gray-800">
                ← Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            
            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Template-Grunddaten</h3>
                
                <form method="POST" action="{{ route('slot-templates.store', $examPeriod) }}">
                    @csrf
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" value="{{ old('name') }}" 
                                   placeholder="z.B. 'Montag Standard' oder 'Freitag verkürzt'"
                                   class="w-full border-gray-300 rounded-md shadow-sm" required>
                            <p class="text-xs text-gray-500 mt-1">Vergeben Sie einen aussagekräftigen Namen</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung (optional)</label>
                            <textarea name="description" rows="3" 
                                      placeholder="z.B. 'Standardtag mit 5 Prüfungen von 08:00-12:00 Uhr'"
                                      class="w-full border-gray-300 rounded-md shadow-sm">{{ old('description') }}</textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                            Template erstellen
                        </button>
                        <a href="{{ route('slot-templates.index', $examPeriod) }}" 
                           class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-lg">
                            Abbrechen
                        </a>
                    </div>
                </form>

                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded">
                    <p class="text-sm text-blue-800">
                        <strong>Hinweis:</strong> Nach dem Erstellen können Sie dem Template Zeitslots hinzufügen 
                        und es dann auf beliebige Tage anwenden.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
