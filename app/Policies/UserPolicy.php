<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Super Admins können alles
     */
    public function before(User $user): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        
        return null;
    }
    
    /**
     * Determine if the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        // Planner können Benutzer ihrer Schule sehen
        return $user->role === 'planner';
    }

    /**
     * Determine if the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Planner können nur Benutzer ihrer eigenen Schule sehen
        return $user->role === 'planner' && $user->school_id === $model->school_id;
    }

    /**
     * Determine if the user can create models.
     */
    public function create(User $user): bool
    {
        // Nur Super Admins können Benutzer erstellen
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Nur Super Admins können Benutzer bearbeiten
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Nur Super Admins können Benutzer löschen
        // Niemand kann sich selbst löschen
        return $user->role === 'admin' && $user->id !== $model->id;
    }
}
