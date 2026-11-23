<?php

namespace App\Console\Commands;

use App\Models\School;
use Illuminate\Console\Command;

class GenerateCalendarTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar:generate-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate calendar tokens for all schools without one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $schools = School::whereNull('calendar_token')->get();
        
        if ($schools->isEmpty()) {
            $this->info('✓ All schools already have calendar tokens.');
            return 0;
        }
        
        $this->info("Generating calendar tokens for {$schools->count()} schools...");
        
        $bar = $this->output->createProgressBar($schools->count());
        $bar->start();
        
        foreach ($schools as $school) {
            $school->generateCalendarToken();
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        $this->info("✓ Successfully generated {$schools->count()} calendar tokens.");
        
        return 0;
    }
}
