<?php

/**
 * Allgemeine Routen: Startseite, Ersteinrichtung, Anmeldung, Profil, Hilfe
 * und die Verwaltung des Super-Admins. Die Planungsseiten der Schule stehen
 * in routes/schule.php.
 */

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;


// Startseite: leitet auf Anmeldung bzw. Dashboard um.
Route::get('/', [LandingController::class, 'index'])->name('home');

// Einstieg der Desktop-App (setzt das Zugangs-Cookie, siehe DesktopZugang).
Route::get('/desktop/start', [LandingController::class, 'index'])->name('desktop.start');

// Einrichtungsassistent (erster Start). Der Controller antwortet mit 404,
// sobald die Schule eingerichtet ist.
Route::middleware('guest')->group(function () {
    Route::get('/ersteinrichtung', [\App\Http\Controllers\ErsteinrichtungController::class, 'show'])
        ->name('ersteinrichtung.show');
    Route::post('/ersteinrichtung', [\App\Http\Controllers\ErsteinrichtungController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('ersteinrichtung.store');
});

// Auth Routes (von Breeze)
require __DIR__.'/auth.php';

// 2FA-Challenge nach Passwort-Login (Zwischenzustand, daher KEIN auth-Middleware).
Route::middleware('guest')->group(function () {
    Route::get('/two-factor-challenge', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:6,1');
});

// Admin Dashboard (only for super_admins without tenancy)
Route::middleware(['auth', 'verified', 'track.last-page'])->group(function () {
    // Profile Routes (für alle eingeloggten User, auch Super-Admins)
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [\App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');

    // 2FA-Selbstverwaltung (Opt-in im Profil)
    Route::get('/two-factor', [\App\Http\Controllers\TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('/two-factor/confirm', [\App\Http\Controllers\TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('/two-factor', [\App\Http\Controllers\TwoFactorController::class, 'destroy'])->name('two-factor.disable');
    Route::delete('/two-factor/devices', [\App\Http\Controllers\TwoFactorController::class, 'clearDevices'])->name('two-factor.devices.clear');
    
    // Hilfesystem und Handbuch (Modul 6.3) — zentral, ohne Tenancy und ohne
    // Rollenprüfung: Die Kapitel gelten für alle Schulen, und Lehrkräfte,
    // Beisitzer und Super-Admins brauchen dieselben Erklärungen wie der
    // Koordinator. Die festen Pfade stehen vor {slug}, sonst schluckt der
    // Platzhalter sie.
    Route::get('/hilfe', [\App\Http\Controllers\HilfeController::class, 'index'])->name('hilfe.index');
    Route::get('/hilfe/panel', [\App\Http\Controllers\HilfeController::class, 'panel'])->name('hilfe.panel');
    Route::get('/hilfe/handbuch.pdf', [\App\Http\Controllers\HilfeController::class, 'handbuch'])->name('hilfe.handbuch');
    Route::get('/hilfe/{slug}', [\App\Http\Controllers\HilfeController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')
        ->name('hilfe.show');

    // Super-Admin Bereich (nur super_admin)
    Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin')->prefix('admin')->name('admin.')->group(function () {
        // Security-Checkliste M1: Rollenprüfung jetzt via Middleware statt nur im Controller.
        Route::get('/dashboard', [\App\Http\Controllers\AdminDashboardController::class, 'index'])
            ->name('dashboard');

        
        Route::resource('users', \App\Http\Controllers\UserController::class);

        // 2FA-Reset durch Admin (Nutzer hat TOTP-App verloren bzw. Geräte zurücksetzen)
        Route::post('users/{user}/two-factor/reset', [\App\Http\Controllers\UserController::class, 'resetTwoFactor'])
            ->name('users.two-factor.reset');
        Route::post('users/{user}/two-factor/devices/reset', [\App\Http\Controllers\UserController::class, 'resetTrustedDevices'])
            ->name('users.two-factor.devices.reset');
        Route::resource('subjects', \App\Http\Controllers\SubjectController::class);
        // Ohne show: Die Detailseite war eine Mandanten-Seite (Schueler,
        // geplante Pruefungen, Konflikte, Import, Exporte) auf einer zentralen
        // Route. Der Super-Admin ruft sie ohne Mandanten auf — das Laden der
        // Beziehungen endete zwangslaeufig in "Database connection [tenant] not
        // configured". Die Seite konnte in ihrer einzigen erreichbaren Form nie
        // funktionieren. Was zu einem Zeitraum einer Schule gehoert, steht im
        // Dashboard und in der Planung des Mandanten.
        Route::resource('exam-periods', \App\Http\Controllers\ExamPeriodController::class)
            ->except(['show']);
        
        // Jahrestermine: konkretes Datum je Slot und Prüfungsjahr
        // WICHTIG: Muss VOR dem Resource stehen, damit 'year-dates' nicht als
        //          {writtenExamSlot}-Parameter gematcht wird!
        Route::get('written-exam-slots/year-dates/{year}', [\App\Http\Controllers\WrittenExamSlotController::class, 'yearDatesIndex'])
            ->name('written-exam-slots.year-dates')
            ->where('year', '[0-9]{4}');
        Route::post('written-exam-slots/year-dates/{year}', [\App\Http\Controllers\WrittenExamSlotController::class, 'yearDatesStore'])
            ->name('written-exam-slots.year-dates.store')
            ->where('year', '[0-9]{4}');
        Route::delete('written-exam-slots/year-dates/{writtenExamYearDate}', [\App\Http\Controllers\WrittenExamSlotController::class, 'yearDateDestroy'])
            ->name('written-exam-slots.year-date.destroy');

        // Schriftliche Prüfungs-Slots (jahresunabhängig: Zeiten + Fachzuordnung)
        Route::resource('written-exam-slots', \App\Http\Controllers\WrittenExamSlotController::class)
            ->except(['show']);
        
        // Hilfesystem (Modul 6.3): Die Kapitel sind zentraler Bestand — ein
        // Satz Texte für alle Schulen. Die festen Pfade stehen vor der
        // Resource, sonst schluckt {hilfeKapitel} sie.
        Route::post('hilfe/vorschau', [\App\Http\Controllers\HilfeKapitelController::class, 'vorschau'])
            ->name('hilfe.vorschau');
        Route::get('hilfe/{hilfeKapitel}/versionen', [\App\Http\Controllers\HilfeKapitelController::class, 'versionen'])
            ->name('hilfe.versionen');
        Route::post('hilfe/{hilfeKapitel}/versionen/{version}/zurueckholen',
            [\App\Http\Controllers\HilfeKapitelController::class, 'zurueckholen'])
            ->name('hilfe.zurueckholen');
        Route::resource('hilfe', \App\Http\Controllers\HilfeKapitelController::class)
            ->parameters(['hilfe' => 'hilfeKapitel'])
            ->except(['show']);

        // Support-Fälle aller Schulen (Modul 6.4, Stufe 1). Zentral und ohne
        // Tenancy — genau dafür liegen die Fallakten in der zentralen
        // Datenbank (Entscheidung 43). Die Policy zieht die Grenze am
        // einzelnen Fall noch einmal.
        // Abgeschaltet seit 19.11.2026: Die Meldungen liefen an die zentrale
        // Web-Version; in der Desktop-Fassung gibt es keinen Empfänger.
        Route::middleware('abgeschaltet')->group(function () {
            Route::get('support', [\App\Http\Controllers\SupportVerwaltungController::class, 'index'])
                ->name('support.index');
            Route::get('support/{fall}', [\App\Http\Controllers\SupportVerwaltungController::class, 'show'])
                ->name('support.show');
            Route::get('support/{fall}/anhang/{anhang}', [\App\Http\Controllers\SupportVerwaltungController::class, 'anhang'])
                ->name('support.anhang');
            Route::post('support/{fall}/antwort', [\App\Http\Controllers\SupportVerwaltungController::class, 'antwort'])
                ->name('support.antwort');
            Route::post('support/{fall}/status', [\App\Http\Controllers\SupportVerwaltungController::class, 'status'])
                ->name('support.status');
        });

        // Globaler Kalender (systemweit, für alle Schulen)
        Route::resource('global-calendar', \App\Http\Controllers\GlobalCalendarEventController::class)
            ->names('global-calendar')
            ->parameters(['global-calendar' => 'globalCalendarEvent']);
    });
    
});

// Meldestelle der Content-Security-Policy (Modul 7.2, Stufe 2).
// Ohne Anmeldung, weil die Meldung im Browser entsteht und auch die
// Anmeldeseite selbst einen Verstoß auslösen kann. Gedrosselt, weil eine
// kaputte Seite sonst im Sekundentakt meldet; die Antwort ist leer (204).
Route::post('/csp-bericht', \App\Http\Controllers\CspBerichtController::class)
    ->middleware('throttle:30,1')
    ->name('csp.bericht');

// Public Calendar Feed (accessible without authentication)
// Security-Checkliste H6: Rate Limiting gegen Token-Brute-Force/DoS.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/calendar/feed/{token}', [\App\Http\Controllers\CalendarFeedController::class, 'feed'])
        ->name('calendar.feed');
    Route::get('/calendar/feed/{token}/{gradeLevel}', [\App\Http\Controllers\CalendarFeedController::class, 'feed'])
        ->where('gradeLevel', '[0-9]+')
        ->name('calendar.feed.grade');
});
