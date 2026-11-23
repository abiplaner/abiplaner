<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Versionen: {{ $kapitel->titel }}
            </h2>
            <a href="{{ route('admin.hilfe.edit', $kapitel) }}"
               class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                ← Zum Kapitel
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">


            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600">
                    Vor jeder Änderung wird die bisherige Fassung hier abgelegt — auch vor dem
                    Zurückholen. Der aktuelle Stand steht nicht in dieser Liste; er ist das, was Sie
                    im Kapitel sehen.
                </p>
            </div>

            @forelse($versionen as $version)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-3 border-b border-gray-100 flex flex-wrap justify-between items-center gap-3">
                        <div>
                            <span class="text-sm font-medium text-gray-800">
                                {{ optional($version->created_at)->format('d.m.Y H:i') ?? 'ohne Zeitstempel' }}
                            </span>
                            <span class="text-sm text-gray-500">
                                · {{ $version->benutzername ?? 'unbekannt' }}
                                @if($version->anlass) · {{ $version->anlass }} @endif
                            </span>
                            @if($version->titel !== $kapitel->titel)
                                <div class="text-xs text-amber-700 mt-0.5">
                                    Titel damals: {{ $version->titel }}
                                </div>
                            @endif
                        </div>
                        <form method="POST"
                              action="{{ route('admin.hilfe.zurueckholen', [$kapitel, $version]) }}"
                              data-confirm="Diese Fassung zurückholen? Der aktuelle Stand wird vorher als Version gesichert.">
                            @csrf
                            <button type="submit"
                                    class="text-sm px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">
                                Zurückholen
                            </button>
                        </form>
                    </div>
                    <div class="p-6">
                        <details>
                            <summary class="text-sm text-indigo-600 cursor-pointer hover:text-indigo-800">
                                Text dieser Fassung anzeigen
                            </summary>
                            <pre class="mt-3 text-xs bg-gray-50 border border-gray-200 rounded p-4 overflow-x-auto whitespace-pre-wrap">{{ $version->markdown }}</pre>
                        </details>
                    </div>
                </div>
            @empty
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-600">
                        Noch keine Versionen — das Kapitel wurde seit dem Einspielen nicht geändert.
                    </p>
                </div>
            @endforelse

        </div>
    </div>
</x-app-layout>
