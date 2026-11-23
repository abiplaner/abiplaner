<?php

namespace App\Policies;

use App\Models\ExamMaterial;
use App\Models\User;

class ExamMaterialPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'super_admin' || $user->role === 'planner';
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ExamMaterial $examMaterial): bool
    {
        return $user->role === 'super_admin' || $user->role === 'planner';
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'super_admin' || $user->role === 'planner';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExamMaterial $examMaterial): bool
    {
        return $user->role === 'super_admin' || $user->role === 'planner';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExamMaterial $examMaterial): bool
    {
        return $user->role === 'super_admin' || $user->role === 'planner';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExamMaterial $examMaterial): bool
    {
        return $this->delete($user, $examMaterial);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExamMaterial $examMaterial): bool
    {
        return $this->delete($user, $examMaterial);
    }
}
