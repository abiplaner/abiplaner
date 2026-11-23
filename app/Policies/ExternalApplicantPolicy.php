<?php

namespace App\Policies;

use App\Models\ExternalApplicant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExternalApplicantPolicy
{
    /**
     * Determine whether the user can view any models.
     * Alle authentifizierten Benutzer können die Liste sehen
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Alle authentifizierten Benutzer können einzelne Bewerber sehen
     */
    public function view(User $user, ExternalApplicant $externalApplicant): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     * Nur Planner und Super Admin können Bewerber anlegen
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['planner', 'super_admin']);
    }

    /**
     * Determine whether the user can update the model.
     * Nur Planner und Super Admin können Bewerber bearbeiten
     */
    public function update(User $user, ExternalApplicant $externalApplicant): bool
    {
        return in_array($user->role, ['planner', 'super_admin']);
    }

    /**
     * Determine whether the user can delete the model.
     * Nur Planner und Super Admin können Bewerber löschen
     */
    public function delete(User $user, ExternalApplicant $externalApplicant): bool
    {
        return in_array($user->role, ['planner', 'super_admin']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExternalApplicant $externalApplicant): bool
    {
        return in_array($user->role, ['planner', 'super_admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExternalApplicant $externalApplicant): bool
    {
        return $user->role === 'super_admin';
    }
}
