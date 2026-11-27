<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rolle „Schuladmin“ (admin) statt „Super-Admin“ (super_admin).
 *
 * Seit es nur noch eine Schule je Installation gibt, ist der Super-Admin ein
 * Planer mit Verwaltungsrechten (Benutzer, Fächer, Termine, Sicherungen).
 * Er heißt jetzt Schuladmin; die Zentral-Admin-Ansicht ohne Schule entfällt.
 *
 * Zwei Schritte, weil SQLite die CHECK-Bedingung beim Umbau der Tabelle
 * gegen die vorhandenen Werte prüft: erst frei, dann umschreiben, dann
 * wieder eingeschränkt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('planner')->change();
        });

        DB::table('users')->whereIn('role', ['super_admin', 'admin'])->update(['role' => 'admin']);
        DB::table('users')->whereNotIn('role', ['admin', 'planner'])->update(['role' => 'planner']);

        // Konten ohne Schule (früher zentraler Super-Admin) bekommen die Schule.
        $schule = DB::table('schools')->value('school_number');
        if ($schule !== null) {
            DB::table('users')->whereNull('school_id')->update(['school_id' => $schule]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'planner'])->default('planner')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('planner')->change();
        });

        DB::table('users')->where('role', 'admin')->update(['role' => 'super_admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'planner'])->default('planner')->change();
        });
    }
};
