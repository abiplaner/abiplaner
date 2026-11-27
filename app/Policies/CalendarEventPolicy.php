<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;

class CalendarEventPolicy
{
    /**
     * Determine if the user can view any calendar events.
     */
    public function viewAny(User $user): bool
    {
        // Alle authentifizierten User können Events sehen
        return true;
    }

    /**
     * Determine if the user can view the calendar event.
     *
     * Jeder Termin dieser Tabelle gehört der Schule, in deren Datenbank er
     * liegt — mehr gibt es nicht zu prüfen. Siehe update().
     */
    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        return true;
    }

    /**
     * Determine if the user can create calendar events.
     */
    public function create(User $user): bool
    {
        // Planer und Super-Admins können Events erstellen
        return in_array($user->role, ['planner', 'admin']);
    }

    /**
     * Determine if the user can update the calendar event.
     *
     * **Schuleigene Termine darf der Planer ändern, globale nur der
     * Super-Admin** (Entscheidung Wilfrieds, 30.08.2026). Genau das ist der
     * Bestand — nur nicht hier: Globale Termine liegen seit der Trennung in
     * der zentralen Tabelle `global_calendar_events` und werden über
     * Verwaltung → Globaler Kalender gepflegt, eine Strecke, die ohnehin
     * hinter `super_admin` hängt. In `calendar_events` steht ausschließlich
     * Schuleigenes.
     *
     * Die frühere Fassung unterschied beides an `school_id`: null hiess
     * global. Die Spalte gibt es in `calendar_events` nicht (und hätte in
     * einer Datenbank je Schule auch keinen Sinn), der Wert war also immer
     * null — und die Bedingung `school_id !== null` damit **immer falsch**.
     * Planer konnten ihre eigenen Termine weder bearbeiten noch löschen und
     * bekamen einen 403, ohne dass irgendwo ein Fehler sichtbar wurde.
     */
    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        return in_array($user->role, ['planner', 'admin'], true);
    }

    /**
     * Determine if the user can delete the calendar event.
     */
    public function delete(User $user, CalendarEvent $calendarEvent): bool
    {
        // Gleiche Logik wie update
        return $this->update($user, $calendarEvent);
    }

    /**
     * Globale Termine anlegen — nur der Super-Admin.
     *
     * Ein zweiter Weg, den niemand geht: Die Methode wird nirgends aufgerufen.
     * Globale Termine entstehen über `GlobalCalendarEventController` unter
     * Verwaltung → Globaler Kalender, und diese Strecke hängt bereits hinter
     * `CheckRole:super_admin`. Sie bleibt trotzdem stehen, weil sie die Regel
     * benennt und der Bereich sonst nur über die Route abgesichert waere.
     */
    public function createGlobal(User $user): bool
    {
        return $user->role === 'admin';
    }
}
