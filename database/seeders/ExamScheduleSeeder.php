<?php

namespace Database\Seeders;

use App\Models\ExamSchedulePeriod;
use App\Models\ExamScheduleEntry;
use App\Models\SchoolEvent;
use Illuminate\Database\Seeder;

class ExamScheduleSeeder extends Seeder
{
    /**
     * Seed exam schedule based on actual Q12/1 2025/26 plan
     */
    public function run(): void
    {
        $schoolYear = '2025/26';

        // 1. Create Exam Schedule Period for Q12/1
        $q12_1 = ExamSchedulePeriod::create([
            'name' => 'Q12/1',
            'school_year' => $schoolYear,
            'start_date' => '2025-10-13',
            'end_date' => '2026-02-13',
            'is_active' => true,
            'notes' => 'Erstes Halbjahr Q12, Schuljahr 2025/26',
        ]);

        // 2. School Events (Ferien, Feiertage, Veranstaltungen)
        $events = [
            // Exkursionen
            [
                'name' => 'Exkursion Q13',
                'type' => 'exkursion',
                'start_date' => '2025-10-22',
                'end_date' => null,
                'affected_grades' => ['Q13'],
                'blocks_exams' => true,
            ],
            [
                'name' => 'Exkursion Q12',
                'type' => 'exkursion',
                'start_date' => '2025-10-23',
                'end_date' => null,
                'affected_grades' => ['Q12'],
                'blocks_exams' => true,
            ],
            
            // SMV-Fahrt
            [
                'name' => 'SMV-Fahrt',
                'type' => 'veranstaltung',
                'start_date' => '2025-10-27',
                'end_date' => '2025-10-31',
                'affected_grades' => null, // Betrifft ausgewählte Schüler
                'blocks_exams' => false,
            ],
            
            // Herbstferien
            [
                'name' => 'Herbstferien',
                'type' => 'ferien',
                'start_date' => '2025-10-27',
                'end_date' => '2025-10-31',
                'affected_grades' => null,
                'blocks_exams' => true,
            ],
            
            // Digitaler Tag
            [
                'name' => 'Digitaler Tag',
                'type' => 'veranstaltung',
                'start_date' => '2025-11-12',
                'end_date' => null,
                'affected_grades' => null,
                'blocks_exams' => true,
            ],
            
            // Buß- und Bettag
            [
                'name' => 'Buß- und Bettag',
                'type' => 'feiertag',
                'start_date' => '2025-11-19',
                'end_date' => null,
                'affected_grades' => null,
                'blocks_exams' => true,
            ],
            
            // Q12 Messe Einstieg Abi
            [
                'name' => 'Q12 Messe Einstieg Abi',
                'type' => 'veranstaltung',
                'start_date' => '2025-11-21',
                'end_date' => null,
                'affected_grades' => ['Q12'],
                'blocks_exams' => true,
            ],
            
            // Konzert
            [
                'name' => 'Konzert',
                'type' => 'veranstaltung',
                'start_date' => '2025-12-05',
                'end_date' => null,
                'affected_grades' => null,
                'blocks_exams' => false,
            ],
            
            // Weihnachtsferien
            [
                'name' => 'Weihnachtsferien',
                'type' => 'ferien',
                'start_date' => '2025-12-22',
                'end_date' => '2026-01-03',
                'affected_grades' => null,
                'blocks_exams' => true,
            ],
            
            // Heilige Drei Könige
            [
                'name' => 'Hl. Drei Könige',
                'type' => 'feiertag',
                'start_date' => '2026-01-06',
                'end_date' => null,
                'affected_grades' => null,
                'blocks_exams' => true,
            ],
            
            // Praktische/Mündliche Prüfungen
            [
                'name' => 'Praktische und mündliche Prüfungen',
                'type' => 'pruefung',
                'start_date' => '2026-01-26',
                'end_date' => '2026-01-30',
                'description' => '1ins1, 1ins2, 3voc1, dsp1, 1iko1, 1eko1 - Termine nach Absprache mit Kursleitung',
                'affected_grades' => ['Q12', 'Q13'],
                'blocks_exams' => false, // Parallele Klausuren möglich
            ],
            
            // Notenschluss
            [
                'name' => 'Notenschluss',
                'type' => 'sonstiges',
                'start_date' => '2026-02-02',
                'end_date' => '2026-02-06',
                'affected_grades' => null,
                'blocks_exams' => true,
            ],
            
            // Zeugnisausgabe
            [
                'name' => 'Zeugnisausgabe',
                'type' => 'sonstiges',
                'start_date' => '2026-02-09',
                'end_date' => '2026-02-13',
                'affected_grades' => null,
                'blocks_exams' => true,
            ],
        ];

        foreach ($events as $event) {
            SchoolEvent::create(array_merge($event, ['school_year' => $schoolYear]));
        }

        // 3. Exam Schedule Entries (Klausurtermine)
        $examEntries = [
            // Woche 10.11. - 14.11.
            [
                'exam_date' => '2025-11-10',
                'course_codes' => ['1eth1', '1eth2', '1eth3', '1ev1', '1k1', '1k2'],
                'subject' => 'Ethik/Religion',
                'level' => 'grundlegend',
                'start_period' => 1,
                'end_period' => 2,
            ],
            [
                'exam_date' => '2025-11-11',
                'course_codes' => ['1pug1', '1pug2', '1pug3', '1pug4', '1pug5', 'PuG-L'],
                'subject' => 'Politik und Gesellschaft',
                'level' => null, // Mix
                'start_period' => 5,
                'end_period' => 6,
            ],
            
            // Woche 17.11. - 21.11.
            [
                'exam_date' => '2025-11-17',
                'course_codes' => ['3inf1', '1e1', '1e2', '1it1', '1f1', '1Inf-L'],
                'subject' => 'Informatik/Sprachen',
                'level' => null,
                'start_period' => 3,
                'end_period' => 4,
                'notes' => '3inf1 ist jahrgangsübergreifend (Q12+Q13)',
            ],
            [
                'exam_date' => '2025-11-20',
                'course_codes' => ['1vkd1', '1vkm1'],
                'subject' => 'Vertiefungskurse',
                'level' => 'grundlegend',
                'start_period' => 1,
                'end_period' => 2,
            ],
            
            // Woche 24.11. - 28.11.
            [
                'exam_date' => '2025-11-24',
                'course_codes' => ['W-Sem'],
                'subject' => 'W-Seminar Physik',
                'level' => null,
                'start_period' => 1,
                'end_period' => 2,
                'notes' => 'KA (Kleine Arbeit)',
            ],
            [
                'exam_date' => '2025-11-26',
                'course_codes' => ['1Smw-L'],
                'subject' => 'Sport',
                'level' => 'erhöht',
                'start_period' => 3,
                'end_period' => 4,
            ],
            [
                'exam_date' => '2025-11-27',
                'course_codes' => ['1Ku-L'],
                'subject' => 'Kunst',
                'level' => 'erhöht',
                'start_period' => 3,
                'end_period' => 5,
            ],
            [
                'exam_date' => '2025-11-28',
                'course_codes' => ['1d1', '1d2', '1d3', '1d4', '1d5', '1d6'],
                'subject' => 'Deutsch',
                'level' => 'grundlegend',
                'start_period' => 1,
                'end_period' => 4,
                'notes' => 'Unterricht in 3/4 entfällt',
            ],
            
            // Woche 01.12. - 05.12.
            [
                'exam_date' => '2025-12-01',
                'course_codes' => ['1geo1', '1geo2', '1wr1', '1wr2', '1wr3', '1WR-L'],
                'subject' => 'Geographie/Wirtschaft',
                'level' => null,
                'start_period' => 5,
                'end_period' => 6,
            ],
            [
                'exam_date' => '2025-12-04',
                'course_codes' => ['1b1', '1b2', '1c2', '1fs1', '1ph2'],
                'subject' => 'Naturwissenschaften',
                'level' => 'grundlegend',
                'start_period' => 5,
                'end_period' => 6,
            ],
            
            // Woche 08.12. - 12.12.
            [
                'exam_date' => '2025-12-08',
                'course_codes' => ['3l1', '1E-L', '1e3', '1e4', '1f2'],
                'subject' => 'Fremdsprachen',
                'level' => null,
                'start_period' => 1,
                'end_period' => 2,
                'notes' => '3l1 ist jahrgangsübergreifend',
            ],
            [
                'exam_date' => '2025-12-11',
                'course_codes' => ['1g1', '1g2', '1g3', '1g4', '1g5', '1G-L'],
                'subject' => 'Geschichte',
                'level' => null,
                'start_period' => 3,
                'end_period' => 4,
            ],
            
            // Woche 15.12. - 19.12.
            [
                'exam_date' => '2025-12-15',
                'course_codes' => ['1trs1'],
                'subject' => 'Theaterstück',
                'level' => null,
                'start_period' => 1,
                'end_period' => 2,
            ],
            [
                'exam_date' => '2025-12-18',
                'course_codes' => ['1m1', '1m2', '1m3', '1m4', '1m5', '1m6'],
                'subject' => 'Mathematik',
                'level' => 'grundlegend',
                'start_period' => 1,
                'end_period' => 2,
            ],
            
            // Woche 05.01. - 09.01. (nach Weihnachten)
            [
                'exam_date' => '2026-01-08',
                'course_codes' => ['1psy1', '1psy2'],
                'subject' => 'Psychologie',
                'level' => 'grundlegend',
                'start_period' => 1,
                'end_period' => 2,
            ],
            
            // Woche 12.01. - 16.01.
            [
                'exam_date' => '2026-01-12',
                'course_codes' => ['1ku1', '1ku2', '1ku3', '1mu1', '1mu2'],
                'subject' => 'Kunst/Musik',
                'level' => 'grundlegend',
                'start_period' => 5,
                'end_period' => 7,
            ],
            [
                'exam_date' => '2026-01-15',
                'course_codes' => ['1c1', '1ph1', '1b3', '1b4', '1BL', '1Ph-L'],
                'subject' => 'Naturwissenschaften',
                'level' => null,
                'start_period' => 5,
                'end_period' => 6,
            ],
        ];

        foreach ($examEntries as $entry) {
            ExamScheduleEntry::create(array_merge($entry, [
                'exam_schedule_period_id' => $q12_1->id,
                'status' => 'bestätigt',
            ]));
        }

        $this->command->info('✓ Exam schedule seeded: Q12/1 2025/26');
        $this->command->info('  - ' . count($examEntries) . ' exam entries');
        $this->command->info('  - ' . count($events) . ' school events');
    }
}
