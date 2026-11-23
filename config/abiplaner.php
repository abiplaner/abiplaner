<?php

return [

    /*
    | Startschlüssel der Desktop-App (App\Http\Middleware\DesktopZugang).
    | Die App setzt ihn bei jedem Start neu; leer = kein Riegel (Docker,
    | Entwicklung).
    */
    'desktop_token' => env('ABIPLANER_DESKTOP_TOKEN', ''),

];
