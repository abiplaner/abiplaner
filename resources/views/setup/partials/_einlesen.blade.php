{{-- Eine einzulesende Datei, kompakt: Stand, Anleitung, Vorlage, Import.
     Genutzt auf der Einrichtungsseite und auf der Startseite. --}}
@php
    use App\Services\SetupChecklistService;

    $erledigt = $item['status'] === SetupChecklistService::STATUS_DONE;
    $teilweise = $item['status'] === SetupChecklistService::STATUS_PARTIAL;
@endphp

<li class="px-4 py-3 flex flex-col md:flex-row md:items-center gap-2 md:gap-4">
    <div class="flex items-start gap-3 grow min-w-0">
        <span class="shrink-0 mt-0.5 inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold
            {{ $erledigt ? 'bg-green-100 text-green-700' : ($teilweise ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-700') }}"
              title="{{ $erledigt ? 'eingelesen' : ($teilweise ? 'unvollständig' : 'fehlt noch') }}">
            {{ $erledigt ? '✓' : ($teilweise ? '!' : $nr) }}
        </span>
        <div class="min-w-0">
            <div class="text-sm">
                <span class="font-semibold text-gray-900">{{ $item['title'] }}</span>
                <span class="text-gray-500">· {{ $item['detail'] }}</span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">{{ $item['description'] }}</p>
            @if ($item['hint'])
                <p class="text-xs text-amber-700 mt-0.5">{{ $item['hint'] }}</p>
            @endif
        </div>
    </div>

    <div class="shrink-0 flex flex-wrap items-center gap-2 pl-9 md:pl-0">
        @if ($item['guide'])
            <a href="{{ route('setup.imports') }}#{{ $item['guide'] }}"
               class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Anleitung</a>
            <a href="{{ route('setup.imports.template', $item['guide']) }}"
               class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Vorlage</a>
        @endif
        @if ($item['route'] && \Illuminate\Support\Facades\Route::has($item['route']))
            <a href="{{ route($item['route']) }}"
               class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-semibold
                   {{ $erledigt ? 'border border-gray-300 text-gray-700 hover:bg-gray-50' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
                {{ $erledigt ? 'Neu einlesen' : 'Einlesen' }}
            </a>
        @endif
    </div>
</li>
