<?php

namespace App\Console\Commands;

use App\Models\Subject;
use App\Models\Course;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicateSubjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subjects:cleanup-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Entfernt doppelte Fächer (Groß-/Kleinschreibung) und migriert zu is_leistungsfach Flag auf Kurs-Ebene';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starte Bereinigung von doppelten Fächern...');
        
        DB::transaction(function () {
            // Alle Fächer, gruppiert nach dem Code in Kleinbuchstaben.
            //
            // Der Schlüssel hing früher zusätzlich an `school_id`. Die Spalte
            // gibt es in `subjects` nicht: Der Zugriff lieferte still null, der
            // Schlüssel endete bei jedem Fach auf „_" — die Absicht, je Schule
            // getrennt zu gruppieren, lief also ins Leere, ohne dass etwas
            // aufgefallen wäre. Sie wird auch nicht gebraucht: Der Fächerkatalog
            // steht in der Datenbank des Mandanten und gehört damit ohnehin
            // genau einer Schule.
            $subjects = Subject::all()->groupBy(
                fn (Subject $subject): string => strtolower((string) $subject->code)
            );
            
            $merged = 0;
            $deleted = 0;
            
            foreach ($subjects as $group) {
                if ($group->count() <= 1) {
                    continue; // Keine Duplikate
                }
                
                $this->info("Gefunden: " . $group->count() . " Varianten von '{$group->first()->code}'");
                
                // Finde das Kleinbuchstaben-Fach oder erstelle es
                $lowercaseCode = strtolower($group->first()->code);
                $targetSubject = $group->first(function ($subject) use ($lowercaseCode) {
                    return $subject->code === $lowercaseCode;
                });
                
                // Wenn kein Kleinbuchstaben-Fach existiert, konvertiere das erste
                if (!$targetSubject) {
                    $targetSubject = $group->first();
                    $this->info("  Konvertiere '{$targetSubject->code}' zu '{$lowercaseCode}'");
                    $targetSubject->code = $lowercaseCode;
                    $targetSubject->save();
                }
                
                // Migriere alle anderen Varianten
                foreach ($group as $subject) {
                    if ($subject->id === $targetSubject->id) {
                        continue;
                    }
                    
                    $isLeistungsfach = ctype_upper($subject->code[0]); // Erster Buchstabe groß = Leistungsfach
                    
                    $this->info("  Migriere Kurse von '{$subject->code}' (Leistungsfach: " . ($isLeistungsfach ? 'Ja' : 'Nein') . ")");
                    
                    // Aktualisiere alle Kurse, die auf dieses Fach zeigen
                    $coursesUpdated = Course::where('subject_id', $subject->id)
                        ->update([
                            'subject_id' => $targetSubject->id,
                            'is_leistungsfach' => $isLeistungsfach
                        ]);
                    
                    $this->info("    → {$coursesUpdated} Kurse aktualisiert");
                    
                    // Lösche das Duplikat
                    $subject->delete();
                    $deleted++;
                }
                
                $merged++;
            }
            
            $this->info("✓ Fertig! {$merged} Fächer zusammengeführt, {$deleted} Duplikate gelöscht.");
        });
        
        return Command::SUCCESS;
    }
}
