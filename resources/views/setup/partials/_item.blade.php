@php
    use App\Services\SetupChecklistService;

    $badge = match ($item['status']) {
        SetupChecklistService::STATUS_DONE => ['✓', 'bg-green-100 text-green-800 border-green-300'],
        SetupChecklistService::STATUS_PARTIAL => ['◐', 'bg-amber-100 text-amber-800 border-amber-300'],
        default => ['○', 'bg-gray-100 text-gray-600 border-gray-300'],
    };
    $label = match ($item['status']) {
        SetupChecklistService::STATUS_DONE => 'erledigt',
        SetupChecklistService::STATUS_PARTIAL => 'teilweise erledigt',
        default => 'offen',
    };
    $erledigt = $item['status'] === SetupChecklistService::STATUS_DONE;
@endphp

<li class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-start gap-4">
    <span class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full border text-lg {{ $badge[1] }}"
          title="{{ $label }}" aria-label="{{ $label }}">
        {{ $badge[0] }}
    </span>

    <div class="grow min-w-0">
        <div class="flex flex-wrap items-baseline gap-x-2">
            <h4 class="font-semibold text-gray-900">{{ $item['title'] }}</h4>
            @unless ($item['required'])
                <span class="text-xs text-gray-500">(optional)</span>
            @endunless
        </div>

        <p class="text-sm text-gray-600 mt-0.5">{{ $item['description'] }}</p>

        <p class="text-sm mt-1 {{ $erledigt ? 'text-gray-700' : 'text-gray-900 font-medium' }}">
            {{ $item['detail'] }}
        </p>

        @if ($item['hint'])
            <p class="text-sm text-gray-500 mt-1">↳ {{ $item['hint'] }}</p>
        @endif

        @if (($item['guide'] ?? null) && \Illuminate\Support\Facades\Route::has('setup.imports'))
            <p class="text-sm mt-1">
                <a class="text-indigo-600 hover:text-indigo-800 underline"
                   href="{{ route('setup.imports') }}#{{ $item['guide'] }}">
                    Anleitung und Vorlage für diese Datei
                </a>
            </p>
        @endif
    </div>

    @if ($item['route'] && \Illuminate\Support\Facades\Route::has($item['route']))
        <div class="shrink-0 sm:pt-1">
            <a href="{{ route($item['route']) }}"
               class="inline-flex items-center px-4 py-2 border rounded-md font-semibold text-xs uppercase tracking-widest
                      {{ $erledigt
                         ? 'border-gray-300 text-gray-700 hover:bg-gray-50'
                         : 'bg-indigo-600 border-transparent text-white hover:bg-indigo-700' }}">
                {{ $erledigt ? 'Ansehen' : 'Erledigen' }}
            </a>
        </div>
    @endif
</li>
