<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support-Organisation (Modul 6.4)
    |--------------------------------------------------------------------------
    |
    | Wohin die Eskalation geht. Leer bedeutet: kein Mailversand — die Faelle
    | stehen dann nur im Dashboard. Das ist ein gueltiger Betrieb und kein
    | Fehler; `support:testmail` sagt es deutlich.
    |
    | Bewusst ohne Vorgabe: Jede Installation setzt ihre eigene Adresse in der
    | .env, damit keine Fremdinstallation Fallmeldungen an Dritte schickt.
    |
    */

    'empfaenger' => env('SUPPORT_EMPFAENGER', ''),

    /*
    | Wie viele Zeichen der Beschreibung in die Benachrichtigungsmail wandern.
    | Bewusst knapp: Der Kontext gehoert NICHT in die Mail, sondern ins
    | Dashboard hinter die Anmeldung.
    */

    'mail_auszug' => 400,

    /*
    |--------------------------------------------------------------------------
    | Aufbewahrungsfristen (Entscheidung 53, 31.08.2026)
    |--------------------------------------------------------------------------
    |
    | Art. 5 Abs. 1 lit. e DSGVO verlangt eine Speicherbegrenzung. Die Zahlen
    | stehen hier und nicht im Code: Eine Schule oder ein
    | Datenschutzbeauftragter kann andere verlangen, und dann soll niemand
    | einen Befehl anfassen muessen.
    |
    | Alle Fristen zaehlen ab `geschlossen_am`. **Ein offener Fall wird nie
    | angefasst** — sonst waere „offen lassen" der bequeme Weg, die Frist zu
    | umgehen; dagegen steht `offen_hinweis_monate`.
    |
    | Die Staffelung folgt dem Risiko: Was am ehesten Schuelernamen enthaelt,
    | geht zuerst.
    |
    |  1. Anhaenge — beim Abschluss (Entscheidung 48, eigener Befehl).
    |  2. kontext.aktivitaeten und kontext.fehler — `kontext_tage`.
    |     technik und einrichtung bleiben; sie tragen keinen Personenbezug und
    |     beantworten weiter, welches Modul Support erzeugt.
    |  3. Die ganze Fallakte samt Nachrichten — `fallakte_monate`.
    |     Zwei Jahre, weil der Abiplaner im Jahresrhythmus laeuft: Ein Fall aus
    |     dem Abitur 2027 wird im Abitur 2028 wieder erzaehlt. Ein Jahr wuerde
    |     die Akte genau dann loeschen, wenn sie das erste Mal wieder gebraucht
    |     wird.
    |
    | Die Zahlen sind aus dem Zweck hergeleitet, nicht aus einer Norm.
    |
    */

    'aufbewahrung' => [

        // Tage nach dem Abschluss, bis die personenbezogenen Kontextbloecke
        // geleert werden.
        'kontext_tage' => (int) env('SUPPORT_AUFBEWAHRUNG_KONTEXT_TAGE', 90),

        // Monate nach dem Abschluss, bis die Fallakte selbst faellt.
        'fallakte_monate' => (int) env('SUPPORT_AUFBEWAHRUNG_FALLAKTE_MONATE', 24),

        // Ab wann ein *offener* Fall im Dashboard angemahnt wird. Keine
        // Loeschfrist — eine Erinnerung, damit „offen" nicht zum
        // Aufbewahrungstrick wird.
        'offen_hinweis_monate' => (int) env('SUPPORT_AUFBEWAHRUNG_OFFEN_HINWEIS_MONATE', 12),

    ],

];
