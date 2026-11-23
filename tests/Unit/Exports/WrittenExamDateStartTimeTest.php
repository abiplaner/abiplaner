<?php

namespace Tests\Unit\Exports;

use App\Models\WrittenExamSlot;
use App\Models\WrittenExamYearDate;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Regressionsschutz gegen den Termin-Export-Bug nach der Slot/YearDate-Migration.
 *
 * Hintergrund: Mehrere PDF-Export-Views nutzten zur Renderzeit das frühere
 * Property `$writtenExamDate->start_time`. Nach der Migration existiert dieses
 * Attribut am Model nicht mehr – die Uhrzeit lebt am zugehörigen Slot und wird
 * ausschließlich über `getStartTime()` (= Carbon::parse($this->slot->start_time))
 * bereitgestellt. Der direkte Property-Zugriff lieferte daher `null` und ließ
 * `->format('H:i')` zur PDF-Renderzeit crashen.
 *
 * Diese Tests sichern die Ursache (Methode liefert Carbon, Property nicht) und
 * verhindern per Inhalts-Guard, dass sich der alte Property-Zugriff erneut in
 * eine Export-View einschleicht. Das vollständige End-to-End-Rendering der
 * Exporte wird später gegen die anonymisierte Testschule geprüft.
 */
class WrittenExamDateStartTimeTest extends TestCase
{
    /**
     * getStartTime() liefert die Uhrzeit als Carbon aus dem zugehörigen Slot –
     * ohne Datenbankzugriff (Slot per setRelation in-memory gesetzt). Genau
     * dieser Aufruf ersetzt in den Export-Views den alten Property-Zugriff.
     */
    public function test_getStartTime_liefert_carbon_aus_slot_ohne_db(): void
    {
        $slot = new WrittenExamSlot([
            'start_time' => '09:00:00',
            'end_time'   => '12:00:00',
        ]);

        $date = new WrittenExamYearDate();
        $date->setRelation('slot', $slot);

        $start = $date->getStartTime();

        $this->assertInstanceOf(Carbon::class, $start);
        $this->assertSame('09:00', $start->format('H:i'));
    }

    /**
     * Das frühere Attribut `start_time` existiert am Model nicht mehr und liefert
     * `null`. Dieser Test dokumentiert die Bug-Ursache: `null->format('H:i')`
     * crashte zur PDF-Renderzeit. Schlägt der Test fehl (Property liefert wieder
     * einen Wert), wurde das alte Verhalten versehentlich reaktiviert.
     */
    public function test_start_time_property_existiert_nicht_mehr(): void
    {
        $slot = new WrittenExamSlot([
            'start_time' => '09:00:00',
            'end_time'   => '12:00:00',
        ]);

        $date = new WrittenExamYearDate();
        $date->setRelation('slot', $slot);

        // Trotz gesetztem Slot liefert der Attribut-Zugriff null – die Uhrzeit
        // ist ausschließlich über getStartTime() erreichbar. Zugriff über
        // getAttribute() statt der ->start_time-Property: identischer Eloquent-
        // Pfad (__get → getAttribute), aber kein statischer Property-Zugriff auf
        // ein bewusst entferntes Attribut, den PHPStan (zu Recht) beanstanden
        // würde. Geprüft wird das Laufzeitverhalten für ein entferntes Attribut.
        $this->assertNull($date->getAttribute('start_time'));
    }

    /**
     * Inhalts-Guard: Keine Export-View darf den alten Property-Zugriff
     * `$...Date->start_time` (ohne getStartTime()) zur Ausgabe einer Uhrzeit
     * verwenden. Erfasst alle Blade-Dateien unter resources/views/exports und
     * schützt damit auch Views, die in diesem Fix nicht direkt berührt wurden.
     */
    public function test_keine_export_view_nutzt_start_time_property(): void
    {
        $exportDir = resource_path('views/exports');
        $this->assertDirectoryExists($exportDir);

        $blades = new \RegexIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($exportDir, \FilesystemIterator::SKIP_DOTS)
            ),
            '/\.blade\.php$/'
        );

        // Trifft Property-Zugriffe wie ->start_time / ->end_time auf einem
        // ...Date-Objekt, lässt aber Methodenaufrufe (getStartTime/getEndTime)
        // und gleichnamige Felder anderer Objekte (z. B. Slots) unberührt.
        $pattern = '/Date->(?:start_time|end_time)\b/';

        $offenders = [];
        foreach ($blades as $file) {
            $content = file_get_contents($file->getPathname());
            if ($content !== false && preg_match($pattern, $content)) {
                $offenders[] = str_replace($exportDir . '/', '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Export-Views mit verbotenem Date->start_time/-end_time-Property-Zugriff "
            . "(stattdessen getStartTime()/getEndTimeForLevel() verwenden): "
            . implode(', ', $offenders)
        );
    }
}
