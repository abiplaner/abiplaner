<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:create 
                            {--name= : Name des Superadmins}
                            {--email= : E-Mail-Adresse des Superadmins}
                            {--password= : Passwort des Superadmins}
                            {--force : Überschreibt existierenden Superadmin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Erstellt einen neuen Superadmin-Benutzer';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('');
        $this->info('==============================================');
        $this->info('   Superadmin-Benutzer erstellen');
        $this->info('==============================================');
        $this->info('');

        // Prüfen, ob bereits ein Superadmin existiert
        $existingSuperAdmin = User::where('role', 'super_admin')->first();
        
        if ($existingSuperAdmin && !$this->option('force')) {
            $this->warn('⚠ Es existiert bereits ein Superadmin:');
            $this->line("   Name:  {$existingSuperAdmin->name}");
            $this->line("   Email: {$existingSuperAdmin->email}");
            $this->info('');
            
            if (!$this->confirm('Möchten Sie einen weiteren Superadmin erstellen?', false)) {
                $this->info('Abgebrochen.');
                return 0;
            }
        }

        // Daten sammeln
        $name = $this->option('name') ?? $this->ask('Name des Superadmins');
        $email = $this->option('email') ?? $this->ask('E-Mail-Adresse');
        $password = $this->option('password') ?? $this->secret('Passwort (min. 8 Zeichen)');

        // Validierung
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            $this->error('');
            $this->error('Validierungsfehler:');
            foreach ($validator->errors()->all() as $error) {
                $this->error("  • $error");
            }
            $this->error('');
            return 1;
        }

        // Superadmin erstellen
        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $this->info('');
            $this->info('✓ Superadmin erfolgreich erstellt!');
            $this->info('');
            $this->line('Login-Daten:');
            $this->line("  Name:     {$user->name}");
            $this->line("  Email:    {$user->email}");
            $this->line("  Passwort: ********");
            $this->info('');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('');
            $this->error('Fehler beim Erstellen des Superadmins:');
            $this->error("  {$e->getMessage()}");
            $this->error('');
            return 1;
        }
    }
}
