<?php

namespace App\Policies;

use App\Models\User;
use App\Models\School;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Die eigene Schule darf jeder sehen, alle anderen nur der Super-Admin.
     *
     * Verglichen wird gegen `school_number`, nicht gegen `id`: **Die Tabelle
     * `schools` hat gar keine Spalte `id`** — ihr Primärschlüssel ist die
     * vierstellige Schulnummer (`School::$primaryKey`). `$school->id` lieferte
     * damit immer `null`, und die Prüfung war für jeden Nicht-Super-Admin
     * **immer falsch**: Ein Planer kam an die Seite seiner eigenen Schule nicht
     * heran.
     *
     * Beide Seiten sind jetzt `varchar(4)` und tragen die führende Null — der
     * strenge Vergleich ist damit auch der richtige.
     */
    public function view(User $user, School $school): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $school->school_number;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, School $school): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, School $school): bool
    {
        return $user->isSuperAdmin();
    }
}
