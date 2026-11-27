<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Notausgang „Passwort vergessen“ für den Schulserver (Docker):
 *
 *   docker compose exec abiplaner php artisan abiplaner:passwort lehrer@schule.de
 *   docker compose exec abiplaner php artisan abiplaner:passwort lehrer@schule.de --admin
 *
 * Setzt das Passwort eines Kontos neu, auf Wunsch mit Rolle Schuladmin. Gibt
 * es das Konto nicht, legt der Befehl es an (mit --admin als Schuladmin) —
 * so kommt man auch nach versehentlich gelöschtem letztem Schuladmin wieder
 * hinein. In der Desktop-App gibt es dafür „Passwort vergessen?“ auf der
 * Anmeldeseite (PasswortVergessenController).
 */
class PasswortSetzen extends Command
{
    protected $signature = 'abiplaner:passwort
                            {email? : E-Mail-Adresse des Kontos}
                            {--admin : Konto zum Schuladmin machen}
                            {--name= : Name, falls das Konto neu angelegt wird}';

    protected $description = 'Passwort eines Kontos neu setzen (legt es bei Bedarf an)';

    public function handle(): int
    {
        if (! School::query()->exists()) {
            $this->error('Es ist noch keine Schule eingerichtet. Bitte zuerst die Einrichtung im Browser durchlaufen.');

            return self::FAILURE;
        }

        $email = (string) ($this->argument('email') ?? $this->ask('E-Mail-Adresse'));
        $nutzer = User::query()->where('email', $email)->first();

        if (! $nutzer instanceof User) {
            if (! $this->confirm("Ein Konto {$email} gibt es nicht. Neu anlegen?", true)) {
                return self::FAILURE;
            }
        }

        $passwort = (string) $this->secret('Neues Passwort');
        $wiederholung = (string) $this->secret('Passwort wiederholen');

        $pruefung = Validator::make(
            ['email' => $email, 'password' => $passwort, 'password_confirmation' => $wiederholung],
            ['email' => ['required', 'email'], 'password' => ['required', 'confirmed', Password::defaults()]],
        );
        if ($pruefung->fails()) {
            foreach ($pruefung->errors()->all() as $fehler) {
                $this->error($fehler);
            }

            return self::FAILURE;
        }

        $nutzer ??= new User([
            'name' => (string) ($this->option('name') ?: strstr($email, '@', true)),
            'email' => $email,
            'role' => 'planner',
        ]);

        $nutzer->forceFill([
            'password' => Hash::make($passwort),
            'school_id' => School::query()->value('school_number'),
            'is_active' => true,
            'email_verified_at' => $nutzer->email_verified_at ?? now(),
        ]);
        if ($this->option('admin')) {
            $nutzer->role = 'admin';
        }
        $nutzer->save();

        $this->info("Passwort für {$nutzer->email} ({$nutzer->rollenName()}) gesetzt.");

        return self::SUCCESS;
    }
}
