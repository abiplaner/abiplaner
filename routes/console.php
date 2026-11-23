<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tägliche Sicherung der Datenbank, 02:00 Uhr (Docker-Betrieb; die
// Desktop-App sichert beim Start, siehe abiplaner:vorbereiten).
// Behält die letzten 14 automatischen Sicherungen.
Schedule::command('abiplaner:sichern --art=auto --keep=14')
    ->dailyAt('02:00')
    ->runInBackground()
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Geplante Sicherung (abiplaner:sichern) fehlgeschlagen');
    });

// Liegengebliebene Eskalationsmails nachholen (Modul 6.4).
//
// Der Versand laeuft bei QUEUE_CONNECTION=sync im Request und ist deshalb
// abgefangen: Scheitert er, steht die Meldung trotzdem, `benachrichtigt_am`
// bleibt leer. Ohne diesen Lauf bliebe sie es fuer immer — die Vorkehrung im
// Dienst waere dann nur die halbe Miete.
//
// Alle 15 Minuten, weil eine Fehlermeldung nicht bis zum naechsten Morgen
// warten soll. Steht nichts aus, sagt der Befehl das und tut nichts.
Schedule::command('support:nachbenachrichtigen')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::warning('support:nachbenachrichtigen konnte nicht alles zustellen');
    });

// Bildschirmfotos erledigter Faelle und verwaiste Dateien (Modul 6.4,
// Entscheidung 48). Der Regelweg ist der Abschluss des Falls; das hier ist die
// Gegenprobe fuer alles, was daran vorbeikam.
Schedule::command('support:anhaenge-aufraeumen')
    ->dailyAt('03:10')
    ->withoutOverlapping();

// Aufbewahrungsfristen der Fallakten (Modul 6.4, Entscheidung 53): Kontext
// kuerzen nach 90 Tagen, Akte loeschen nach 24 Monaten — beides ab dem
// Abschluss des Falls, offene Faelle bleiben unangetastet. Die Zahlen stehen
// in config/support.php.
//
// Nach dem Anhaenge-Lauf, damit ein Fall, der beide betrifft, erst seine
// Bilder verliert und dann seine Akte. Findet der Befehl nichts, sagt er das
// und tut nichts.
Schedule::command('support:fallakten-aufraeumen')
    ->dailyAt('03:20')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('support:fallakten-aufraeumen ist gescheitert');
    });

// Anonymisierung externer Bewerber, deren Frist (anonymize_at, zwei Jahre
// nach dem Abitur) erreicht ist. Läuft über alle Mandanten, deshalb eigener
// Slot nach den zentralen Support-Aufräumläufen.
Schedule::command('data:anonymize')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('data:anonymize ist gescheitert');
    });
