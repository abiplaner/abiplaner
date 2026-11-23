{{--
    Kapitelvorschläge zum getippten Betreff (Modul 6.4, Stufe 1.5).

    Die billigste Stufe des Moduls: Die Volltextsuche gibt es seit 6.3, hier
    fängt sie die Frage ab, bevor daraus ein Fall wird. Bewusst höchstens
    drei — eine lange Liste liest niemand, der gerade einen Fehler melden will.

    Trifft nichts zu, bleibt der Ausschnitt leer statt „keine Treffer" zu
    sagen: Ein Hinweis auf das Ausbleiben eines Vorschlags hilft niemandem.
--}}
@if ($treffer !== [])
    <div class="rounded-md bg-indigo-50 border border-indigo-100 p-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">
            Vielleicht steht es schon in der Hilfe
        </p>
        <ul class="mt-2 space-y-2">
            @foreach ($treffer as $eintrag)
                <li>
                    <a href="{{ route('hilfe.show', $eintrag['kapitel']->slug) }}" target="_blank"
                       class="text-sm font-medium text-indigo-700 hover:underline">
                        {{ $eintrag['kapitel']->titel }}
                    </a>
                    <p class="text-xs text-gray-600">{{ $eintrag['fundstelle'] }}</p>
                </li>
            @endforeach
        </ul>
    </div>
@endif
