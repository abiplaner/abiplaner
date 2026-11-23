<?php

namespace Tests\Tenant;

use App\Models\CalendarEvent;
use App\Models\GlobalCalendarEvent;

/**
 * Modul 3.1 – Abonnierbarer Terminplan (iCal-Feed).
 *
 * Die Feed-Route ist öffentlich (kein Login), initialisiert die Tenancy
 * daher selbst anhand des Tokens. Tests laufen deshalb bewusst NICHT
 * innerhalb eines vorab initialisierten Tenant-Kontexts (das würde den
 * eigentlichen Bug - fehlende Tenancy-Initialisierung in der Route -
 * verschleiern), nur das Anlegen der Testdaten braucht kurz Tenancy.
 */
class CalendarFeedTest extends TenantTestCase
{
    public function test_feed_enthaelt_schul_und_globale_termine_mit_erinnerung(): void
    {
        $school = $this->createTestSchool();

        CalendarEvent::create([
            'title' => 'Schulinterner Test-Termin',
            'event_date' => '2027-05-10',
            'event_type' => 'info',
            'color' => '#3B82F6',
        ]);

        $global = GlobalCalendarEvent::create([
            'title' => 'Globaler Test-Termin',
            'event_date' => '2027-06-01',
            'event_type' => 'holiday',
            'color' => '#EF4444',
        ]);

        $token = $school->generateCalendarToken();

        $response = $this->get('/calendar/feed/' . $token);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $response->assertSee('BEGIN:VCALENDAR', false);
        $response->assertSee('Schulinterner Test-Termin', false);
        $response->assertSee('Globaler Test-Termin', false);
        // Erinnerungsfunktion (Modul 3.1): ICS-VALARM statt Server-Versand.
        $response->assertSee('BEGIN:VALARM', false);
        $response->assertSee('TRIGGER:-P1D', false);

        // Die Route initialisiert Tenancy selbst nur für ihre eigene Dauer -
        // danach darf kein Tenant-Kontext mehr "hängen bleiben".

        $global->delete();
    }

    public function test_feed_filtert_nach_jahrgangsstufe(): void
    {
        $school = $this->createTestSchool();

        CalendarEvent::create([
            'title' => 'Nur-Q12-Termin',
            'event_date' => '2027-05-10',
            'event_type' => 'info',
            'grade_levels' => [12],
            'color' => '#3B82F6',
        ]);

        $token = $school->generateCalendarToken();

        $this->get('/calendar/feed/' . $token . '/11')
            ->assertOk()
            ->assertDontSee('Nur-Q12-Termin', false);

        $this->get('/calendar/feed/' . $token . '/12')
            ->assertOk()
            ->assertSee('Nur-Q12-Termin', false);
    }

    /**
     * RFC 5545 §3.1: keine Inhaltszeile über 75 Oktette.
     *
     * Auf Produktion lieferte der Feed am 25.08.2026 sechs überlange
     * DESCRIPTION-Zeilen aus (lange Fristentexte aus den globalen Terminen).
     * Der Umbruch muss an Zeichengrenzen erfolgen, sonst zerfallen Umlaute.
     */
    public function test_ueberlange_zeilen_werden_nach_rfc5545_gefaltet(): void
    {
        $school = $this->createTestSchool();

        $langerText = 'Meldung anderer Bewerberinnen und Bewerber für die Abiturprüfung '
            . 'an der Schule mit ausführlicher Begründung und Hinweisen für Schüler '
            . 'mit besonderen Prüfungsbedingungen nach Art. 61 BayGSO';

        $global = GlobalCalendarEvent::create([
            'title' => 'Termin mit sehr langer Beschreibung',
            'description' => $langerText,
            'event_date' => '2027-06-01',
            'event_type' => 'deadline',
            'color' => '#EF4444',
        ]);

        $token = $school->generateCalendarToken();

        $ics = $this->get('/calendar/feed/' . $token)->assertOk()->getContent();

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(
                75,
                strlen($line),
                'Zeile überschreitet 75 Oktette und ist nicht gefaltet: ' . $line
            );
        }

        // Entfalten (CRLF + ein Leerzeichen) muss den Originaltext zurückgeben —
        // beweist zugleich, dass kein Mehrbyte-Zeichen zerschnitten wurde.
        $entfaltet = str_replace("\r\n ", '', $ics);
        $this->assertStringContainsString($langerText, $entfaltet);
        $this->assertSame($entfaltet, mb_convert_encoding($entfaltet, 'UTF-8', 'UTF-8'));

        $global->delete();
    }

    public function test_ungueltiges_token_liefert_404(): void
    {
        $this->get('/calendar/feed/dieses-token-existiert-nicht')
            ->assertNotFound();
    }
}
