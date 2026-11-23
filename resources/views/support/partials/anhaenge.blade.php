{{--
    Bildschirmfotos einer Fallakte (Modul 6.4, Entscheidung 48).

    Ein Ausschnitt für beide Ansichten — die der Schule und die des Supports.
    Der Unterschied ist allein die Route, deshalb $routenname statt zweier
    Fassungen.

    $fall:       SupportFall
    $routenname: 'support.anhang' oder 'admin.support.anhang'
--}}
@if ($fall->anhaenge->isNotEmpty())
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <h3 class="text-base font-semibold text-gray-900">
            Bildschirmfotos
            <span class="font-normal text-gray-500">({{ $fall->anhaenge->count() }})</span>
        </h3>

        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($fall->anhaenge as $anhang)
                @php($url = route($routenname, ['fall' => $fall, 'anhang' => $anhang]))
                <figure class="border border-gray-200 rounded-md overflow-hidden">
                    <a href="{{ $url }}" target="_blank" rel="noopener">
                        <img src="{{ $url }}" alt="{{ $anhang->dateiname }}"
                             class="w-full h-40 object-cover bg-gray-50">
                    </a>
                    <figcaption class="px-3 py-2 text-xs text-gray-600 break-all">
                        {{ $anhang->dateiname }}
                        <span class="text-gray-400">· {{ $anhang->groesseLesbar() }}</span>
                    </figcaption>
                </figure>
            @endforeach
        </div>

        <p class="mt-3 text-xs text-gray-500">
            Die Bilder liegen außerhalb des Web-Verzeichnisses und werden nur hier
            ausgeliefert. Mit dem Abschluss des Falls werden sie gelöscht.
        </p>
    </div>
@endif
