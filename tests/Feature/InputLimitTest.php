<?php

namespace Tests\Feature;

use ErrorException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Ueberlaufene Input-Limits (max_input_vars, max_input_nesting_level) sind
 * ein Fehler des Clients: 400 statt 500, und keine ERROR-Logzeile.
 *
 * Den echten Ueberlauf kann der Test nicht ausloesen — der passiert beim
 * Parsen des Query-Strings, bevor ein Test-Request ueberhaupt entsteht. Die
 * Route wirft deshalb dieselbe Warning, die Laravel daraus macht.
 */
class InputLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/input-limit/{art}', function (string $art) {
            throw new ErrorException(match ($art) {
                'nesting' => 'parse_str(): Input variable nesting level exceeded 64. To increase the limit change max_input_nesting_level in php.ini.',
                'vars' => 'parse_str(): Input variables exceeded 1000. To increase the limit change max_input_vars in php.ini.',
                default => 'Undefined variable $irgendwas',
            });
        });
    }

    public function test_zu_tiefe_verschachtelung_ist_ein_400_ohne_log(): void
    {
        $log = Log::spy();

        $this->get('/_test/input-limit/nesting')->assertStatus(400);

        $log->shouldNotHaveReceived('error');
    }

    public function test_zu_viele_variablen_sind_ein_400_ohne_log(): void
    {
        $log = Log::spy();

        $this->get('/_test/input-limit/vars')->assertStatus(400);

        $log->shouldNotHaveReceived('error');
    }

    /** Gegenprobe: Echte Programmierfehler bleiben 500 und landen im Log. */
    public function test_andere_warnings_bleiben_ein_gemeldeter_500(): void
    {
        $log = Log::spy();

        $this->get('/_test/input-limit/sonst')->assertStatus(500);

        $log->shouldHaveReceived('error')->once();
    }
}
