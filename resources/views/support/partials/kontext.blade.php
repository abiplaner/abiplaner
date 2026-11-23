{{--
    Der erhobene Kontext im Klartext (Modul 6.4).

    Dieselbe Darstellung dient zwei Zwecken: der Vorschau im Meldeformular
    („das wird mitgeschickt") und der Ansicht in der Fallakte. Deshalb ein
    Ausschnitt und keine zwei Fassungen — sonst zeigt die Vorschau etwas
    anderes, als in der Akte landet.

    $kontext: array{technik, einrichtung, aktivitaeten, fehler}
--}}
@php
    $technik = $kontext['technik'] ?? [];
    $einrichtung = $kontext['einrichtung'] ?? null;
    $aktivitaeten = $kontext['aktivitaeten'] ?? [];
    $fehler = $kontext['fehler'] ?? null;

    // Entscheidung 53: Nach Ablauf der Frist fallen `aktivitaeten` und
    // `fehler`; der Vermerk bleibt. Ohne ihn stünde hier „außer den
    // technischen Angaben wird nichts mitgeschickt" — was für einen gekürzten
    // Fall schlicht falsch wäre. **Ein leeres Feld muss sagen können, warum es
    // leer ist.**
    $gekuerztAm = $kontext['gekuerzt_am'] ?? null;

    // Durchweg ?? null: Eine Fallakte kann ohne Kontext dastehen — etwa ein
    // von Hand angelegter Fall oder einer, dessen Erhebung am kaputten
    // Mandanten gescheitert ist. Genau dann darf die Ansicht nicht umfallen.
    $schulnummer = $technik['schule'] ?? null;
    $rolle = $technik['rolle'] ?? null;

    $technikZeilen = [
        'Schule' => trim(($technik['schule_name'] ?? '') . ' ' . ($schulnummer ? '(' . $schulnummer . ')' : '')),
        'Maske' => $technik['route'] ?? null,
        'Adresse' => $technik['url'] ?? null,
        'Zeitpunkt' => $technik['zeitpunkt'] ?? null,
        'Angemeldet als' => trim(($technik['nutzer'] ?? '') . ' ' . ($rolle ? '(' . $rolle . ')' : '')),
        'Browser' => $technik['browser'] ?? null,
        'Stand' => $technik['app_version'] ?? null,
        'Laravel / PHP' => trim(($technik['laravel'] ?? '') . ' / ' . ($technik['php'] ?? '')),
        'Tenant-Migrationen' => $technik['tenant_migrationen'] ?? null,
    ];

    $statusText = ['done' => 'erledigt', 'partial' => 'teilweise', 'open' => 'offen'];
@endphp

<div class="space-y-4 text-sm">

    @if ($technik === [])
        <p class="text-gray-500">Zu diesem Fall wurde kein Kontext erhoben.</p>
    @else
    <div>
        <h4 class="font-semibold text-gray-800">Technisch</h4>
        <dl class="mt-1 divide-y divide-gray-100">
            @foreach ($technikZeilen as $bezeichnung => $wert)
                @if ($wert !== null && trim((string) $wert) !== '')
                    <div class="flex gap-3 py-1">
                        <dt class="w-40 shrink-0 text-gray-500">{{ $bezeichnung }}</dt>
                        <dd class="text-gray-800 break-all">{{ $wert }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>
        <p class="mt-1 text-xs text-gray-500">Enthält keine Schülerdaten.</p>
    </div>

    @if ($einrichtung !== null)
        <div>
            <h4 class="font-semibold text-gray-800">
                Einrichtungsstand
                <span class="font-normal text-gray-500">
                    ({{ $einrichtung['fortschritt']['erledigt'] ?? '?' }} von
                    {{ $einrichtung['fortschritt']['gesamt'] ?? '?' }} Pflichtbausteinen)
                </span>
            </h4>
            <ul class="mt-1 space-y-0.5">
                @foreach ($einrichtung['bausteine'] ?? [] as $baustein)
                    <li class="flex gap-2">
                        <span class="w-24 shrink-0 text-gray-500">
                            {{ $statusText[$baustein['status']] ?? $baustein['status'] }}
                        </span>
                        <span class="text-gray-800">
                            {{ $baustein['titel'] }}
                            <span class="text-gray-500">— {{ $baustein['detail'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-1 text-xs text-gray-500">Enthält keine Personendaten.</p>
        </div>
    @endif

    @if ($aktivitaeten !== [])
        <div>
            <h4 class="font-semibold text-gray-800">Ihre letzten Schritte</h4>
            <ul class="mt-1 space-y-0.5">
                @foreach ($aktivitaeten as $schritt)
                    <li class="text-gray-800">
                        <span class="text-gray-500">{{ $schritt['zeitpunkt'] }}</span>
                        — {{ $schritt['aktion'] }}
                        @if (! empty($schritt['objekt'])) <span class="text-gray-500">{{ $schritt['objekt'] }}</span> @endif
                        @if (! empty($schritt['beschreibung'])) <span class="text-gray-600">„{{ $schritt['beschreibung'] }}"</span> @endif
                    </li>
                @endforeach
            </ul>
            <p class="mt-1 text-xs text-amber-700">
                Kann Namen enthalten — lesen Sie die Liste, bevor Sie absenden.
            </p>
        </div>
    @endif

    @if ($fehler !== null)
        <div>
            <h4 class="font-semibold text-gray-800">Zuletzt aufgetretener Fehler</h4>
            <dl class="mt-1 space-y-0.5">
                <div class="flex gap-3"><dt class="w-40 shrink-0 text-gray-500">Kennung</dt>
                    <dd class="text-gray-800 break-all">{{ $fehler['id'] ?? '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-40 shrink-0 text-gray-500">Art</dt>
                    <dd class="text-gray-800 break-all">{{ $fehler['klasse'] ?? '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-40 shrink-0 text-gray-500">Meldung</dt>
                    <dd class="text-gray-800 break-words">{{ $fehler['meldung'] ?? '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-40 shrink-0 text-gray-500">Stelle</dt>
                    <dd class="text-gray-800 break-all">{{ ($fehler['datei'] ?? '?') . ':' . ($fehler['zeile'] ?? '?') }}</dd></div>
            </dl>
            @if (! empty($fehler['rahmen']))
                <ul class="mt-1 text-xs text-gray-600 space-y-0.5">
                    @foreach ($fehler['rahmen'] as $rahmen)
                        <li class="break-all">{{ $rahmen }}</li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-1 text-xs text-amber-700">
                Bei Datenbankfehlern werden SQL und Werte entfernt; sonst kann die Meldung
                Namen enthalten.
            </p>
        </div>
    @endif

    @if ($gekuerztAm !== null)
        <p class="text-xs text-gray-500 border-t border-gray-100 pt-2">
            Die Blöcke „Ihre letzten Schritte" und „Zuletzt aufgetretener Fehler" wurden
            am {{ \Carbon\Carbon::parse($gekuerztAm)->format('d.m.Y') }} nach Ablauf der
            Aufbewahrungsfrist gelöscht. Die technischen Angaben bleiben — sie tragen
            keinen Personenbezug.
        </p>
    @elseif ($einrichtung === null && $aktivitaeten === [] && $fehler === null)
        <p class="text-gray-500">Außer den technischen Angaben oben wird nichts mitgeschickt.</p>
    @endif
    @endif
</div>
