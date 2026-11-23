{{--
    Eskalationsmail zu einer Support-Meldung (Modul 6.4).

    Was hier NICHT steht, ist Absicht: der erhobene Kontext. Er kann Namen
    enthalten und gehoert hinter die Anmeldung, nicht in ein Postfach.
--}}
<x-mail::message>
# Neue Meldung

**Schule:** {{ $fall->schule_name ?? $fall->school_id ?? '—' }}
**Melder:** {{ $fall->melder_name ?? '—' }} ({{ $fall->melder_email ?? '—' }})
**Maske:** {{ $fall->route_name ?? '—' }}
**Eingegangen:** {{ $fall->created_at?->format('d.m.Y H:i') }}

## {{ $fall->betreff }}

{{ $auszug }}

@if($fall->fehler_id)
Kurz zuvor ist ein Fehler aufgetreten. Kennung im Server-Log: `{{ $fall->fehler_id }}`
@endif

@if($anhaenge > 0)
{{ $anhaenge }} Bildschirmfoto(s) hängen am Fall — sie werden **nicht** per Mail
versendet und sind nur im Fall zu sehen.
@endif

<x-mail::button :url="$verweis">
Fall öffnen
</x-mail::button>

Der vollständige Kontext steht im Fall selbst — hinter der Anmeldung, nicht in
dieser Nachricht.

<x-mail::subcopy>
Diese Nachricht wurde vom AbiPlaner erzeugt. Eine Antwort auf diese Mail geht
an den Melder; der Weg für die Bearbeitung ist der Verlauf im Fall.
</x-mail::subcopy>
</x-mail::message>
