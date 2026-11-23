<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WrittenExamSlot;

/**
 * Nur Super-Admin darf Slots anlegen, bearbeiten oder löschen.
 * Alle eingeloggten User dürfen lesen.
 */
class WrittenExamSlotPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WrittenExamSlot $slot): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, WrittenExamSlot $slot): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, WrittenExamSlot $slot): bool
    {
        return $user->isAdmin();
    }
}
