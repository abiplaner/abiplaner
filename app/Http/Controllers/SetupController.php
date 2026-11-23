<?php

namespace App\Http\Controllers;

use App\Services\ImportLeitfadenService;
use App\Services\SetupChecklistService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Einrichtungsstand einer Schule (Roadmap 6.2).
 *
 * Zeigt, was eine Schule bis zur Planungsreife noch braucht. Reine Anzeige —
 * erledigt wird jeder Punkt in der Funktion, auf die er verweist.
 */
class SetupController extends Controller
{
    public function index(SetupChecklistService $checklist): View
    {
        $items = $checklist->items();

        return view('setup.index', [
            'adminItems' => array_values(array_filter(
                $items,
                fn (array $item) => $item['scope'] === SetupChecklistService::SCOPE_ADMIN
            )),
            'schoolItems' => array_values(array_filter(
                $items,
                fn (array $item) => $item['scope'] === SetupChecklistService::SCOPE_SCHULE
            )),
            'progress' => $checklist->progress(),
        ]);
    }

    /**
     * Geführte Importe (Roadmap 6.2, zweite Stufe).
     *
     * Die Checkliste sagt, dass ein Import fehlt, und verlinkt die Maske.
     * Diese Seite sagt, was hineingehört: der Weg im Quellsystem, die
     * Spalten und eine Vorlage zum Herunterladen.
     */
    public function importe(ImportLeitfadenService $leitfaeden): View
    {
        return view('setup.importe', [
            'leitfaeden' => $leitfaeden->alle(),
        ]);
    }

    /**
     * Die Vorlage als Datei. Sie wird nicht aus dem Dateisystem gelesen,
     * sondern aus derselben Beschreibung erzeugt, die die Seite anzeigt —
     * so können Anzeige und Datei nicht auseinanderlaufen.
     */
    public function vorlage(string $schluessel, ImportLeitfadenService $leitfaeden): Response
    {
        $leitfaden = $leitfaeden->fuer($schluessel);
        $inhalt = $leitfaeden->vorlage($schluessel);

        abort_if($leitfaden === null || $inhalt === null, 404);

        $typ = str_ends_with($leitfaden['dateiname'], '.txt') ? 'text/plain' : 'text/csv';

        return response($inhalt, 200, [
            'Content-Type' => $typ . '; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $leitfaden['dateiname'] . '"',
        ]);
    }

    /**
     * Eine ASV-Berichtsvorlage (.bsbj), die die Schule einmalig in ihre
     * Berichtsbibliothek importiert. Welche es gibt, steht im Leitfaden
     * (`asv_berichte`) — nur dort Eingetragenes wird ausgeliefert.
     */
    public function berichtsvorlage(string $schluessel, string $datei, ImportLeitfadenService $leitfaeden): BinaryFileResponse
    {
        $bericht = $leitfaeden->berichtsvorlage($schluessel, $datei);

        abort_if($bericht === null || !is_file($bericht['pfad']), 404);

        return response()->download($bericht['pfad'], $bericht['anzeigename'], [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
