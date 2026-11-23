<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CalendarEventSeeder extends Seeder
{
    /**
     * Seed calendar events for school year 2025/26
     */
    public function run(): void
    {
        $events = [
            // Feiertage
            [
                'title' => 'Tag der Deutschen Einheit',
                'event_date' => '2025-10-03',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Allerheiligen',
                'event_date' => '2025-11-01',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Buß- und Bettag',
                'event_date' => '2025-11-19',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Heiligabend',
                'event_date' => '2025-12-24',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => '1. Weihnachtstag',
                'event_date' => '2025-12-25',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => '2. Weihnachtstag',
                'event_date' => '2025-12-26',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Silvester',
                'event_date' => '2025-12-31',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Neujahr',
                'event_date' => '2026-01-01',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Heilige Drei Könige',
                'event_date' => '2026-01-06',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Karfreitag',
                'event_date' => '2026-04-03',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Ostermontag',
                'event_date' => '2026-04-06',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Tag der Arbeit',
                'event_date' => '2026-05-01',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Christi Himmelfahrt',
                'event_date' => '2026-05-14',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Pfingstmontag',
                'event_date' => '2026-05-25',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],
            [
                'title' => 'Fronleichnam',
                'event_date' => '2026-06-04',
                'event_type' => 'public_holiday',
                'grade_level' => 'all',
                'color' => '#DC2626',
            ],

            // Wichtige Fristen
            [
                'title' => 'Abgabe der Seminararbeit',
                'event_date' => '2025-11-11',
                'event_type' => 'deadline',
                'grade_level' => '13',
                'is_mandatory' => true,
                'priority' => 10,
                'color' => '#EF4444',
                'description' => 'Letzter Termin zur Abgabe der Seminararbeit',
            ],
            [
                'title' => 'Meldung anderer Bewerber für Abiturprüfung',
                'event_date' => '2025-12-15',
                'event_type' => 'deadline',
                'grade_level' => '13',
                'is_mandatory' => true,
                'priority' => 9,
                'color' => '#EF4444',
            ],
            [
                'title' => 'Zeugnis AA 13/1',
                'event_date' => '2026-01-26',
                'event_type' => 'grading',
                'grade_level' => '13',
                'is_mandatory' => true,
                'priority' => 8,
                'color' => '#8B5CF6',
                'description' => 'Zeugnis über den Ausbildungsabschnitt 13/1 und Information über zulassungsschädliche Seminararbeiten',
            ],
            [
                'title' => 'Endgültige Festlegung der schriftlichen Abiturprüfungsfächer',
                'event_date' => '2026-01-31',
                'event_type' => 'deadline',
                'grade_level' => '13',
                'is_mandatory' => true,
                'priority' => 10,
                'color' => '#EF4444',
            ],
            [
                'title' => 'Zeugnis AA 12/1',
                'event_date' => '2026-02-13',
                'event_type' => 'grading',
                'grade_level' => '12',
                'is_mandatory' => true,
                'priority' => 7,
                'color' => '#8B5CF6',
                'description' => 'Zeugnis über den AA 12/1, Vergabe der Seminararbeitsthemen, Entscheidung über Bestehen der Probezeit',
            ],
            [
                'title' => 'Frühester Beginn praktische Prüfungen Musik',
                'event_date' => '2026-03-09',
                'event_type' => 'exam_practical',
                'grade_level' => '13',
                'color' => '#10B981',
            ],
            [
                'title' => 'Endgültige Festlegung der mündlichen Abiturprüfungsfächer',
                'event_date' => '2026-03-11',
                'event_type' => 'deadline',
                'grade_level' => '13',
                'is_mandatory' => true,
                'priority' => 10,
                'color' => '#EF4444',
                'description' => 'Spätestens sechs Wochen vor Beginn der schriftlichen Abiturprüfungen',
            ],
