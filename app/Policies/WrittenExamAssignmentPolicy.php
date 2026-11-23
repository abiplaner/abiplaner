<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WrittenExamAssignment;

class WrittenExamAssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WrittenExamAssignment $assignment): bool
    {
        // Multi-Tenant: Datenisolation durch separate Tenant-Datenbanken.
        return true;
    }

    /**
     * Determine whether the user can create models.
     * Planer und Super-Admin können Zuordnungen erstellen.
     */
    public function create(User $user): bool
    {
        return $user->isPlanner();
    }

    /**
     * Determine whether the user can update the model.
     * Wird auch für Sitzplatzzuweisungen verwendet.
     */
    public function update(User $user, WrittenExamAssignment $assignment): bool
    {
        // Multi-Tenant: Datenisolation erfolgt durch separate Tenant-Datenbanken.
        // Es reicht zu prüfen, ob der User Planer-Rechte hat.
        return $user->isPlanner();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WrittenExamAssignment $assignment): bool
    {
        return $user->isPlanner();
    }
}
