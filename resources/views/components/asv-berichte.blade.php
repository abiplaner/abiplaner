{{--
    ASV-Berichtsvorlagen eines Imports zum Herunterladen.

    Welche Vorlagen es gibt, steht allein im ImportLeitfadenService unter
    `asv_berichte` — diese Komponente zeigt sie nur an. Hat ein Import keine,
    rendert sie nichts. Eine neue Vorlage braucht daher keine Änderung hier.

    variante="kasten": hervorgehobener Hinweis oben auf der Importseite
    variante="knopf":  nur die Download-Knöpfe, etwa im Import-Leitfaden
--}}
@props(['schluessel', 'variante' => 'kasten'])

@php
    $berichte = app(\App\Services\ImportLeitfadenService::class)->fuer($schluessel)['asv_berichte'] ?? [];
@endphp

@if ($berichte !== [])
    @if ($variante === 'knopf')
        @foreach ($berichte as $bericht)
            <a href="{{ route('setup.imports.asv-report', [$schluessel, $bericht['datei']]) }}"
               class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700">
                ↓ ASV-Bericht (.bsbj)
            </a>
        @endforeach
    @else
        <div class="mb-6 bg-amber-50 border-2 border-amber-400 rounded-lg p-4 shadow-sm">
            <div class="flex items-start">
                <div class="flex-shrink-0 text-amber-600 text-xl mr-3">📌</div>
                <div class="flex-1">
                    <h3 class="text-base font-bold text-amber-900">
                        Woher stammen die Daten? (ASV-Berichtsbibliothek)
                    </h3>
                    <p class="mt-1 text-sm text-amber-900">
                        Dieser Export stammt aus einem eigenen ASV-Bericht. Importieren Sie die Berichtsdatei
                        einmalig in die <strong>ASV-Berichtsbibliothek</strong> und führen Sie den Bericht dann dort aus.
                    </p>
                    <ul class="mt-3 space-y-2">
                        @foreach ($berichte as $bericht)
                            <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <span class="text-sm text-amber-900">
                                    <code class="font-mono font-semibold bg-amber-100 px-1.5 py-0.5 rounded">{{ $bericht['anzeigename'] }}</code>
                                    <span class="text-xs text-amber-800">— Bereich „{{ $bericht['bereich'] }}"</span>
                                </span>
                                <a href="{{ route('setup.imports.asv-report', [$schluessel, $bericht['datei']]) }}"
                                   class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-semibold rounded-md hover:bg-amber-700 shadow-sm whitespace-nowrap">
                                    ⬇️ Herunterladen
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif
@endif
