{{-- Eine von der Einrichtung vorbelegte Sache, eine Zeile. --}}
@php
    $vorgabe = $item['status'] !== \App\Services\SetupChecklistService::STATUS_DONE;
@endphp

<li class="px-4 py-2.5 flex items-center gap-3 text-sm">
    <span class="shrink-0 w-4 text-center {{ $vorgabe ? 'text-amber-500' : 'text-green-600' }}"
          title="{{ $vorgabe ? 'noch Vorgabe bzw. unvollständig' : 'angepasst' }}">{{ $vorgabe ? '●' : '✓' }}</span>
    <div class="grow min-w-0 truncate" title="{{ $item['description'] }}">
        <span class="font-medium text-gray-900">{{ $item['title'] }}</span>
        <span class="text-gray-500">· {{ $item['detail'] }}</span>
    </div>
    @if ($item['route'] && \Illuminate\Support\Facades\Route::has($item['route']))
        <a href="{{ route($item['route']) }}" class="shrink-0 text-xs font-medium text-indigo-600 hover:text-indigo-800">
            {{ $vorgabe ? 'Anpassen' : 'Ansehen' }} →
        </a>
    @endif
</li>
