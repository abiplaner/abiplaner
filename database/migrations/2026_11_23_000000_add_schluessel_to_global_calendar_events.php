<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fester Schlüssel für die bayernweiten Termine (23.11.2026).
 *
 * Die Termine kommen aus database/data/termine/<schuljahr>.json und werden bei
 * jedem Start abgeglichen (`termine:einspielen`). Ohne Schlüssel ließe sich ein
 * korrigiertes Datum nicht vom alten unterscheiden — der Abgleich legte dann
 * einen zweiten Eintrag daneben, statt den ersten zu berichtigen.
 *
 * Form: "2026-27/pfingstferien". Von Hand angelegte Termine haben keinen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_calendar_events', function (Blueprint $table) {
            $table->string('schluessel')->nullable()->after('id');
            $table->unique('schluessel');
        });
    }

    public function down(): void
    {
        Schema::table('global_calendar_events', function (Blueprint $table) {
            $table->dropUnique(['schluessel']);
            $table->dropColumn('schluessel');
        });
    }
};
