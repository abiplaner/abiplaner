<?php

namespace App\Mail;

use App\Models\SupportFall;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Die Eskalationsmail zu einer neuen Support-Meldung (Modul 6.4, Stufe 1).
 *
 * **Bewusst knapp.** Schule, Betreff, ein Auszug, ein Verweis — mehr nicht.
 * Der erhobene Kontext gehoert NICHT in die Mail: Er enthaelt moeglicherweise
 * Namen, und eine Mail liegt danach in jedem Postfach, durch das sie gelaufen
 * ist. Im Dashboard liegt er hinter der Anmeldung.
 */
class SupportFallGemeldet extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SupportFall $fall)
    {
    }

    public function envelope(): Envelope
    {
        $schule = $this->fall->schule_name ?? $this->fall->school_id ?? 'ohne Schule';

        return new Envelope(
            subject: '[AbiPlaner] ' . $schule . ': ' . Str::limit($this->fall->betreff, 80),
            replyTo: $this->antwortAdresse(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.support-fall',
            with: [
                'fall' => $this->fall,
                'auszug' => Str::limit(
                    $this->fall->beschreibung,
                    (int) config('support.mail_auszug', 400)
                ),
                'verweis' => url('/admin/support/' . $this->fall->id),
                // Nur die Zahl. Die Bilder selbst gehen nicht mit — sie zeigen
                // fast immer Namen, und eine Mail liegt danach in jedem
                // Postfach, durch das sie gelaufen ist.
                'anhaenge' => $this->fall->anhaenge()->count(),
            ],
        );
    }

    /**
     * Antworten gehen an den Melder, nicht ins Leere.
     *
     * Der eigentliche Weg ist der Verlauf in der Anwendung; wer trotzdem auf
     * „Antworten" drueckt, soll aber nicht bei `noreply@` landen.
     *
     * @return list<Address>
     */
    private function antwortAdresse(): array
    {
        $email = $this->fall->melder_email;

        if (! is_string($email) || $email === '') {
            return [];
        }

        return [new Address($email, $this->fall->melder_name ?? $email)];
    }
}
