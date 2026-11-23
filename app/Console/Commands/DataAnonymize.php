<?php

namespace App\Console\Commands;

use App\Models\ExternalApplicant;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Anonymisiert externe Bewerber, deren Aufbewahrungsfrist erreicht ist.
 *
 * Schliesst die Luecke aus dem alten Anonymisierungskonzept: `anonymize_at`
 * wird seit jeher beim Anlegen gesetzt (zwei Jahre nach dem Abitur, siehe
 * ExternalApplicantController::store()), aber nichts hat je darauf reagiert.
 * `first_name`/`last_name` sind NOT NULL — ein simples Nullen der Felder
 * (wie es das alte Konzept vorsah) ist deshalb nie moeglich gewesen. Statt
 * einer Schema-Aenderung: ein Platzhalter je Bewerber, ueber die interne ID
 * unterscheidbar, aber ohne Personenbezug.
 *
 * Bewusst NICHT angefasst: `birth_date` (NOT NULL, kein Platzhalter ohne
 * Rueckfrage gewaehlt) und die Fachwahl (Leistungsfach, Ersatzschule usw.) —
 * das sind Sachdaten, kein Personenbezug fuer sich allein.
 *
 * Laeuft ueber alle Mandanten, weil `external_applicants` eine Tenant-Tabelle
 * ist (anders als die Support-Fallakten aus Entscheidung 43). Ein Mandant,
 * der scheitert, darf die anderen nicht verhindern.
 */
class DataAnonymize extends Command
{
    protected $signature = 'data:anonymize {--probe : Nur anzeigen, nichts ändern} {--school= : Nur diese Schulnummer (sonst alle Mandanten)}';

    protected $description = 'Anonymisiert externe Bewerber, deren Aufbewahrungsfrist (anonymize_at) erreicht ist';

    public function handle(): int
    {
        $probe = (bool) $this->option('probe');
        $schoolNumber = $this->option('school');

        $schools = $schoolNumber !== null
            ? School::where('school_number', $schoolNumber)->get()
            : School::all();

        if ($schoolNumber !== null && $schools->isEmpty()) {
            $this->error("Schule {$schoolNumber} nicht gefunden.");

            return self::FAILURE;
        }

        $gesamtAnzahl = 0;

        foreach ($schools as $school) {
            try {
                app(\App\Services\AktuelleSchule::class)->betreten($school);

                $bewerber = ExternalApplicant::withTrashed()
                    ->where('is_anonymized', false)
                    ->whereNotNull('anonymize_at')
                    ->where('anonymize_at', '<=', now())
                    ->get();

                if ($bewerber->isEmpty()) {
                    continue;
                }

                $this->line("Schule {$school->school_number}: {$bewerber->count()} Bewerber fällig");

                foreach ($bewerber as $person) {
                    $this->line("  #{$person->id} (fällig seit {$person->anonymize_at->format('d.m.Y')})");

                    if (! $probe) {
                        $person->forceFill([
                            'first_name' => 'Bewerber',
                            'last_name' => (string) $person->id,
                            'email' => null,
                            'phone' => null,
                            'disability_compensation_notes' => null,
                            'notes' => null,
                            'is_anonymized' => true,
                        ])->save();
                    }
                }

                $gesamtAnzahl += $bewerber->count();
            } catch (\Throwable $e) {
                $this->error("Schule {$school->school_number}: fehlgeschlagen — {$e->getMessage()}");
                Log::error("data:anonymize fehlgeschlagen für Schule {$school->school_number}: {$e->getMessage()}");
            } finally {
                app(\App\Services\AktuelleSchule::class)->verlassen();
            }
        }

        if ($gesamtAnzahl === 0) {
            $this->info('Nichts zu anonymisieren.');

            return self::SUCCESS;
        }

        $this->info($probe
            ? "Probe — {$gesamtAnzahl} Bewerber wären betroffen, nichts geändert."
            : "{$gesamtAnzahl} Bewerber anonymisiert.");

        return self::SUCCESS;
    }
}
