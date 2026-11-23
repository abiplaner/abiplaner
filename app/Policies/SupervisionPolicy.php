<?php

namespace App\Policies;

use App\Models\Supervision;
use App\Models\User;

class SupervisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlanner();
    }

    public function view(User $user, Supervision $supervision): bool
    {
        return $user->isPlanner();
    }

    public function create(User $user): bool
    {
        return $user->isPlanner();
    }

    public function update(User $user, Supervision $supervision): bool
    {
        return $user->isPlanner();
    }

    public function delete(User $user, ?Supervision $supervision = null): bool
    {
        return $user->isPlanner();
    }
}
