<?php

namespace App\Policies;

use App\Models\GlobalCalendarEvent;
use App\Models\User;

class GlobalCalendarEventPolicy
{
    /**
     * Determine if the user can view any global calendar events.
     */
    public function viewAny(User $user): bool
    {
        // Nur Super-Admins können globale Kalender-Events verwalten
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can view the global calendar event.
     */
    public function view(User $user, GlobalCalendarEvent $globalCalendarEvent): bool
    {
        // Nur Super-Admins können globale Events sehen
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can create global calendar events.
     */
    public function create(User $user): bool
    {
        // Nur Super-Admins können globale Events erstellen
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can update the global calendar event.
     */
    public function update(User $user, GlobalCalendarEvent $globalCalendarEvent): bool
    {
        // Nur Super-Admins können globale Events bearbeiten
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can delete the global calendar event.
     */
    public function delete(User $user, GlobalCalendarEvent $globalCalendarEvent): bool
    {
        // Nur Super-Admins können globale Events löschen
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can restore the global calendar event.
     */
    public function restore(User $user, GlobalCalendarEvent $globalCalendarEvent): bool
    {
        // Nur Super-Admins können globale Events wiederherstellen
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can permanently delete the global calendar event.
     */
    public function forceDelete(User $user, GlobalCalendarEvent $globalCalendarEvent): bool
    {
        // Nur Super-Admins können globale Events permanent löschen
        return $user->isSuperAdmin();
    }
}
