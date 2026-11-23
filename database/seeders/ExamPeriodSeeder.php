<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExamPeriod;

class ExamPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Erstellt Prüfungszeiträume für das bayerische Abitur
     */
    public function run(): void
    {
        $periods = [
            [
                'name' => 'Abitur 2026',
                'year' => 2026,
                'week_1_start' => '2026-05-18',
                'week_1_end' => '2026-05-22',
                'week_2_start' => '2026-06-08',
                'week_2_end' => '2026-06-12',
            ],
        ];

        foreach ($periods as $period) {
            ExamPeriod::create($period);
        }

        $this->command->info('✓ ' . count($periods) . ' Prüfungszeiträume angelegt');
    }
}
