<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Basisschema: eine Datenbank für genau eine Schule.
 *
 * Bis 22.11.2026 gab es zwei Migrationssätze — zentrale Tabellen und eine
 * Tenant-Datenbank je Schule (stancl/tenancy). Beide sind hier zu einem
 * Schema zusammengeführt (database/schema/basis.sql), eingespielt per PDO
 * statt per `schema:dump`/`sqlite3`, weil das sqlite3-Programm auf den
 * Rechnern der Schulen nicht vorhanden ist.
 *
 * Neue Schemaänderungen kommen als eigene Migration danach.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schutz: nie über eine bestehende Datenbank spielen.
        if (Schema::hasTable('schools')) {
            return;
        }

        DB::unprepared(file_get_contents(database_path('schema/basis.sql')));
    }

    public function down(): void
    {
        throw new RuntimeException('Das Basisschema lässt sich nicht zurückrollen.');
    }
};
