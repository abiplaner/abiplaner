<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Fächer verwalten
            </h2>
            @can('create', \App\Models\Subject::class)
                <a href="{{ route('admin.subjects.create') }}" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Neues Fach
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <!-- Such- und Filterbereich -->
                    <div class="mb-6 space-y-4">
                        <form method="GET" action="{{ route('admin.subjects.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Suchfeld -->
                            <div class="md:col-span-2">
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Suche</label>
                                <input type="text" 
                                       id="search" 
                                       name="search" 
                                       value="{{ request('search') }}"
                                       placeholder="Code oder Name..." 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <!-- Sortierung -->
                            <div>
                                <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Sortieren</label>
                                <select id="sort_by" 
                                        name="sort_by" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="code" {{ request('sort_by') == 'code' ? 'selected' : '' }}>Code</option>
                                    <option value="name" {{ request('sort_by') == 'name' ? 'selected' : '' }}>Name</option>
                                </select>
                            </div>

                            <!-- Buttons -->
                            <div class="md:col-span-3 flex space-x-2">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Filter anwenden
                                </button>
                                <a href="{{ route('admin.subjects.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    Zurücksetzen
                                </a>
                            </div>
                        </form>
                    </div>

                    @if($subjects->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Code
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Farbe
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Aufgabenfeld
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Eigenschaften
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Aktionen
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($subjects as $subject)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $subject->code }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $subject->name }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <div class="flex items-center space-x-2">
                                                    <div class="w-6 h-6 rounded" style="background-color: {{ $subject->color }}"></div>
                                                    <span class="text-gray-600">{{ $subject->color }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    {{ $subject->getAufgabenfeldLabel() }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600">
                                                <div class="space-y-1">
                                                    @if($subject->is_foreign_language)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                            🌐 Fremdsprache
                                                        </span>
                                                    @endif
                                                    @if($subject->is_science)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                            🔬 Naturwissenschaft
                                                        </span>
                                                    @endif
                                                    @if($subject->can_be_late_starting)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                            ⏰ Spät beginnend möglich
                                                        </span>
                                                    @endif
                                                    @if($subject->can_abitur)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            🎓 Abitur möglich
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                @can('update', $subject)
                                                    <a href="{{ route('admin.subjects.edit', $subject) }}" 
                                                       class="text-blue-600 hover:text-blue-900">
                                                        Bearbeiten
                                                    </a>
                                                @endcan
                                                @can('delete', $subject)
                                                    <form action="{{ route('admin.subjects.destroy', $subject) }}" 
                                                          method="POST" 
                                                          class="inline"
                                                          data-confirm="Wirklich löschen? Hinweis: Das Fach kann nicht gelöscht werden, wenn es noch in Kursen verwendet wird.">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                                            Löschen
                                                        </button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6">
                            {{ $subjects->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <p class="text-gray-500">Keine Fächer gefunden.</p>
                            @can('create', \App\Models\Subject::class)
                                <a href="{{ route('admin.subjects.create') }}" 
                                   class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Erstes Fach anlegen
                                </a>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
