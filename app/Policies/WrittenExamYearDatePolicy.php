<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WrittenExamYearDate;

/**
 * Nur Super-Admin darf Jahrestermine anlegen, bearbeiten oder löschen.
 */
class WrittenExamYearDatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WrittenExamYearDate $yearDate): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, WrittenExamYearDate $yearDate): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, WrittenExamYearDate $yearDate): bool
    {
        return $user->isAdmin();
    }
}
