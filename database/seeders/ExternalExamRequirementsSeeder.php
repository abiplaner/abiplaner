<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExternalExamRequirementsSeeder extends Seeder
{
    /**
     * Seeder für §61 GSO Anforderungen an externe Bewerber
     * 
     * Basierend auf GSO Par 61: Prüfungsgegenstände und -verfahren
     * https://www.gesetze-bayern.de/Content/Document/BayGSO-61
     */
    public function run(): void
    {
        // Lösche alle existierenden Requirements
        DB::table('external_exam_requirements')->truncate();
        
        $requirements = [
            // ================================================================
            // PFLICHTFÄCHER (§61 Abs. 1 Satz 2)
            // ================================================================
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'pflichtfach',
                'requirement_key' => 'deutsch',
                'requirement_value' => json_encode([
                    'subject' => 'Deutsch',
                    'mandatory' => true,
                ]),
                'description' => 'Deutsch muss als Prüfungsfach gewählt werden (schriftlich oder mündlich) - §61 Abs. 1 Satz 2',
                'is_active' => true,
            ],
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'pflichtfach',
                'requirement_key' => 'geschichte',
                'requirement_value' => json_encode([
                    'subject' => 'Geschichte',
                    'mandatory' => true,
                ]),
                'description' => 'Geschichte muss als Prüfungsfach gewählt werden (schriftlich oder mündlich) - §61 Abs. 1 Satz 2',
                'is_active' => true,
            ],
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'pflichtfach',
                'requirement_key' => 'mathematik',
                'requirement_value' => json_encode([
                    'subject' => 'Mathematik',
                    'exam_part' => 'part1_written',
                    'level' => 'advanced',
                    'is_leistungsfach' => true,
                    'mandatory' => true,
                ]),
                'description' => 'Mathematik muss schriftlich auf erhöhtem Niveau als Leistungsfach geprüft werden - §61 Abs. 2 Satz 3',
                'is_active' => true,
            ],
            
            // ================================================================
            // ANZAHL PRÜFUNGSFÄCHER (§61 Abs. 1 Satz 1)
            // ================================================================
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'subject_count',
                'requirement_key' => 'total',
                'requirement_value' => json_encode([
                    'count' => 8,
                    'mandatory' => true,
                ]),
                'description' => 'Insgesamt 8 Prüfungsfächer - §61 Abs. 1 Satz 1',
                'is_active' => true,
            ],
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'subject_count',
                'requirement_key' => 'part1_written',
                'requirement_value' => json_encode([
                    'count' => 4,
                    'exam_part' => 'part1',
                    'mandatory' => true,
                ]),
                'description' => 'Teil 1: 4 schriftliche Prüfungen (und optional mündliche Zusatzprüfungen) - §61 Abs. 1 Satz 1',
                'is_active' => true,
            ],
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'subject_count',
                'requirement_key' => 'part2_oral',
                'requirement_value' => json_encode([
                    'count' => 4,
                    'exam_part' => 'part2',
                    'mandatory' => true,
                ]),
                'description' => 'Teil 2: 4 mündliche Prüfungen - §61 Abs. 1 Satz 1',
                'is_active' => true,
            ],
            
            // ================================================================
            // FREMDSPRACHEN UND NATURWISSENSCHAFTEN (§61 Abs. 1 Satz 2)
            // ================================================================
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'subject_count',
                'requirement_key' => 'fremdsprachen',
                'requirement_value' => json_encode([
                    'count' => 2,
                    'min_count' => 2,
                    'subjects' => ['Englisch', 'Französisch', 'Spanisch', 'Latein', 'Italienisch', 'Russisch'],
                    'mandatory' => true,
                ]),
                'description' => 'Mindestens 2 Fremdsprachen müssen als Prüfungsfächer gewählt werden - §61 Abs. 1 Satz 2',
                'is_active' => true,
            ],
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'subject_count',
                'requirement_key' => 'naturwissenschaft',
                'requirement_value' => json_encode([
                    'count' => 1,
                    'min_count' => 1,
                    'subjects' => ['Biologie', 'Chemie', 'Physik'],
                    'mandatory' => true,
                ]),
                'description' => 'Mindestens eine Naturwissenschaft (Biologie, Chemie oder Physik) - §61 Abs. 1 Satz 2',
                'is_active' => true,
            ],
            
            // ================================================================
            // ANFORDERUNGSNIVEAU (§61 Abs. 2 Satz 3)
            // ================================================================
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'level',
                'requirement_key' => 'leistungsfaecher_part1',
                'requirement_value' => json_encode([
                    'min_count' => 2,
                    'level' => 'advanced',
                    'exam_part' => 'part1_written',
                    'mandatory' => true,
                ]),
                'description' => 'Mindestens 2 Leistungsfächer (erhöhtes Niveau) in Teil 1, darunter Mathematik - §61 Abs. 2 Satz 3',
                'is_active' => true,
            ],
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'level',
                'requirement_key' => 'fach4_grundlegend',
                'requirement_value' => json_encode([
                    'subject_number' => 4,
                    'exam_part' => 'part1_written',
                    'level' => 'basic',
                    'only_basic' => true,
                ]),
                'description' => 'Das 4. Fach in Teil 1 (schriftlich) kann nur auf grundlegendem Niveau geprüft werden - §61 Abs. 2 Satz 5',
                'is_active' => true,
            ],
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'level',
                'requirement_key' => 'deutsch_oder_fremdsprache_leistungsfach',
                'requirement_value' => json_encode([
                    'condition' => 'if_deutsch_not_part1',
                    'then_required' => 'foreign_language_as_leistungsfach_in_part1',
                ]),
                'description' => 'Wenn Deutsch nicht Teil 1 ist, muss eine Fremdsprache als Leistungsfach in Teil 1 sein - §61 Abs. 2 Satz 4',
                'is_active' => true,
            ],
            
            // ================================================================
            // AUFGABENFELDER (§61 Abs. 2 Satz 1)
            // ================================================================
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'aufgabenfeld',
                'requirement_key' => 'all_covered',
                'requirement_value' => json_encode([
                    'aufgabenfelder' => [
                        'sprachlich_literarisch',
                        'gesellschaftswissenschaftlich',
                        'mint'
                    ],
                    'all_required' => true,
                ]),
                'description' => 'Die vier Fächer des ersten Prüfungsteils müssen die drei Aufgabenfelder abdecken - §61 Abs. 2 Satz 1',
                'is_active' => true,
            ],
            
            // ================================================================
            // SPÄT BEGINNENDE FREMDSPRACHE (§61 Abs. 3 Satz 7)
            // ================================================================
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'level',
                'requirement_key' => 'late_starting_foreign_language',
                'requirement_value' => json_encode([
                    'applies_to' => 'second_foreign_language',
                    'level' => 'late_starting',
                    'note' => 'Nur auf Niveau einer spät beginnenden Fremdsprache',
                ]),
                'description' => 'Die zweite Fremdsprache wird nur auf dem Niveau einer spät beginnenden Fremdsprache geprüft - §61 Abs. 3 Satz 7',
                'is_active' => true,
            ],
            
            // ================================================================
            // LERNINHALTE (§61 Abs. 3 Satz 4-5)
            // ================================================================
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'aufgabenfeld',
                'requirement_key' => 'lerninhalte_basis',
                'requirement_value' => json_encode([
                    'base_content' => 'last_two_semesters',
                    'note' => 'Grundkenntnisse + letzte 2 Kurshalbjahre',
                ]),
                'description' => 'Prüfungsanforderungen basieren auf Lerninhalten der letzten beiden Kurshalbjahre (unbeschadet notwendiger Grundkenntnisse) - §61 Abs. 3 Satz 4',
                'is_active' => true,
            ],
            [
                'valid_from' => 2024,
                'valid_to' => null,
                'requirement_type' => 'aufgabenfeld',
                'requirement_key' => 'lerninhalte_leistungsfach',
                'requirement_value' => json_encode([
                    'base_content' => 'last_four_semesters',
                    'applies_to' => ['leistungsfach', 'deutsch_if_part2'],
                    'note' => 'Grundkenntnisse + letzte 4 Ausbildungsabschnitte',
                ]),
                'description' => 'Bei Leistungsfach oder Deutsch in Teil 2: Lerninhalte der letzten vier Ausbildungsabschnitte - §61 Abs. 3 Satz 5',
                'is_active' => true,
            ],
        ];
        
        foreach ($requirements as $requirement) {
            DB::table('external_exam_requirements')->insert(array_merge($requirement, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
        
        $this->command->info('✅ ' . count($requirements) . ' §61 GSO Anforderungen geseedet');
    }
}
