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
        return $user->isAdmin();
    }

    public function view(User $user, Subject $subject): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->isAdmin();
    }
}
