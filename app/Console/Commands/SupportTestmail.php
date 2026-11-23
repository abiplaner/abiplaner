<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Prueft den Mailweg der Support-Organisation (Modul 6.4, Stufe 1).
 *
 * **Wozu ein eigener Befehl:** Ein kaputter Mailweg faellt sonst erst auf,
 * wenn eine Meldung nicht ankommt — und dann weiss niemand, ob es keine
 * Meldung gab oder keine Mail. Der Befehl gehoert nach jedem Deploy, der
 * `MAIL_*` anfasst, einmal ausgefuehrt.
 *
 * Er sagt ausdruecklich auch, wenn gar nicht gesendet wird: `MAIL_MAILER=log`
 * schreibt still in `storage/logs` und sieht von aussen wie Erfolg aus.
 */
class SupportTestmail extends Command
{
    protected $signature = 'support:testmail {empfaenger? : Abweichende Zieladresse}';

    protected $description = 'Sendet eine Testmail an die Support-Adresse und meldet, was wirklich passiert ist';

    public function handle(): int
    {
        $ziel = (string) ($this->argument('empfaenger') ?: config('support.empfaenger', ''));
        $mailer = (string) config('mail.default');
        $absender = (string) config('mail.from.address');

        $this->line('Mailer:    ' . $mailer);
        $this->line('Absender:  ' . $absender);
        $this->line('Empfaenger: ' . ($ziel === '' ? '— nicht konfiguriert —' : $ziel));

        if ($ziel === '') {
            $this->error('SUPPORT_EMPFAENGER ist leer. Es wuerde keine Eskalationsmail versendet.');

            return self::FAILURE;
        }

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log — es geht nichts hinaus, die Mail landet in storage/logs.');
        }

        if (str_contains($absender, 'example.com')) {
            $this->warn('MAIL_FROM_ADDRESS steht noch auf der Vorgabe (example.com).');
        }

        try {
            Mail::raw(
                "Testmail des AbiPlaner (support:testmail).\n\n"
                . 'Wenn diese Nachricht ankommt, ist der Mailweg fuer die '
                . "Support-Eskalation eingerichtet.\n",
                static function ($nachricht) use ($ziel): void {
                    $nachricht->to($ziel)->subject('[AbiPlaner] Testmail');
                }
            );
        } catch (Throwable $e) {
            $this->error('Versand gescheitert: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info($mailer === 'log'
            ? 'Ohne Fehler in das Log geschrieben — ein echter Versand ist damit NICHT belegt.'
            : 'Ohne Fehler uebergeben. Jetzt im Postfach nachsehen.');

        return self::SUCCESS;
    }
}
