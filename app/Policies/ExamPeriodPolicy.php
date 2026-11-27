<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ExamPeriod;

class ExamPeriodPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Jeder eingeloggte User kann ExamPeriods sehen
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ExamPeriod $examPeriod): bool
    {
        return true; // ExamPeriods sind global, jeder kann sie ansehen
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Nur Superadmins können globale ExamPeriods erstellen
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExamPeriod $examPeriod): bool
    {
        // Nur Superadmins können globale ExamPeriods bearbeiten (Stammdaten)
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can manage planning (reset, snapshot, etc.) 
     * within the exam period.
     */
    public function managePlanning(User $user, ExamPeriod $examPeriod): bool
    {
        return $user->isAdmin() || $user->isPlanner();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExamPeriod $examPeriod): bool
    {
        // Nur Superadmins können globale ExamPeriods löschen
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can lock the model.
     * 
     * Note: Das Status-Feld wurde aus ExamPeriods entfernt, da es schulspezifisch war.
     * Diese Methode wird möglicherweise nicht mehr verwendet.
     */
    public function lock(User $user, ExamPeriod $examPeriod): bool
    {
        return $user->isAdmin();
    }
}
