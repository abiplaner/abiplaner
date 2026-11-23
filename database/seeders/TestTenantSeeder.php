<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;

class TestTenantSeeder extends Seeder
{
    /**
     * Erstellt einen Test-Tenant für Multi-Tenancy Tests
     */
    public function run(): void
    {
        // Test-Schule erstellen
        $school = School::create([
            'name' => 'Test-Gymnasium Musterstadt',
            'short_name' => 'TGM',
            'school_number' => '0001',
            'city' => 'Musterstadt',
            'postal_code' => '80331',
            'state' => 'Bayern',
            'email' => 'info@test-gymnasium.de',
            'phone' => '089-12345678',
            'is_active' => true,
        ]);

        $this->command->info("✅ Test-Schule erstellt: {$school->name} (ID: {$school->id})");

        // Domain erstellen (für subdomain-basiertes Routing)
        $school->domains()->create([
            'domain' => 'test.localhost',
        ]);

        $this->command->info("✅ Domain erstellt: test.localhost");
        $this->command->info("🎉 Test-Tenant erfolgreich erstellt!");
        $this->command->info("📊 Tenant-Datenbank: school_{$school->id}");
    }
}
