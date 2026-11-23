<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@abiplaner.de',
            'password' => Hash::make('password'), // Bitte nach dem ersten Login ändern!
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->command->info('✓ Superadmin erstellt (admin@abiplaner.de / password)');
        $this->command->warn('  WICHTIG: Passwort nach dem ersten Login ändern!');
    }
}
