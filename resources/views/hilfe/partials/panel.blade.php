{{--
    Ausschnitt für das Hilfe-Panel am „?" in der Kopfzeile.

    Wird per fetch nachgeladen und in das Panel gehängt. Kein Layout, kein
    <html> — nur der Inhalt. Fehlt zur Route ein Kapitel, steht hier ein
    Hinweis mit dem Weg zur Übersicht: Ein Knopf, der gelegentlich nichts tut,
    sieht kaputt aus.
--}}
@if ($kapitel === null)
    <p class="text-sm text-gray-600">
        Zu dieser Seite ist noch kein Kapitel hinterlegt.
    </p>
    <p class="mt-3">
        <a href="{{ route('hilfe.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">
            Zur Hilfe-Übersicht →
        </a>
    </p>
@else
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $kapitel->gruppe }}</p>
    <h3 class="text-lg font-semibold text-gray-900 mt-1">{{ $kapitel->titel }}</h3>

    <div class="hilfe-inhalt mt-4">
        {!! $html !!}
    </div>

    <div class="mt-6 pt-4 border-t border-gray-100 flex flex-wrap gap-4">
        <a href="{{ route('hilfe.show', $kapitel->slug) }}" class="text-sm text-indigo-600 hover:text-indigo-800">
            Als eigene Seite öffnen →
        </a>
        <a href="{{ route('hilfe.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">
            Alle Kapitel →
        </a>
    </div>
@endif
