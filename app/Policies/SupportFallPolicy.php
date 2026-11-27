<?php

namespace App\Policies;

use App\Models\SupportFall;
use App\Models\User;

/**
 * Wer eine Fallakte sehen darf (Modul 6.4, Stufe 1).
 *
 * **Die harte Grenze des Moduls.** Die Faelle liegen zentral (Entscheidung
 * 43) — die Mandantentrennung von 6.1 traegt hier also nicht, sie muss von
 * Hand gezogen werden. Deshalb steht sie in einer Policy und nicht als
 * `where`-Bedingung in einer Abfrage: Eine Bedingung in einer Liste schuetzt
 * die Liste, aber nicht den direkten Aufruf ueber eine fremde Fall-Id.
 */
class SupportFallPolicy
{
    /**
     * Der Super-Admin bearbeitet die Faelle aller Schulen — er ist der
     * Support. Alle anderen sehen ausschliesslich Faelle ihrer eigenen Schule.
     */
    public function view(User $user, SupportFall $fall): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->school_id !== null
            && $fall->school_id !== null
            && $user->school_id === $fall->school_id;
    }

    /** Im Verlauf antworten darf, wer den Fall sehen darf. */
    public function antworten(User $user, SupportFall $fall): bool
    {
        return $this->view($user, $fall);
    }

    /** Status und Bearbeitung sind allein Sache des Supports. */
    public function bearbeiten(User $user, SupportFall $fall): bool
    {
        return $user->isAdmin();
    }
}
