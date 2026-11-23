<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Subject;

class SubjectPolicy
{
    /**
     * Nur Super-Admins können Fächer verwalten (schulübergreifend)
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Subject $subject): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->isSuperAdmin();
    }
}
