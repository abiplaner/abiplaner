<?php

namespace App\Http\Controllers;

use App\Models\HilfeKapitel;
use App\Models\School;
use App\Services\CurrentExamPeriodService;
use App\Services\HilfeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hilfesystem und Handbuch (Roadmap 6.3).
 *
 * Vier Ausgaben, eine Quelle: das Panel am „?" in der Kopfzeile, die
 * durchsuchbare Übersicht auf `/hilfe`, das einzelne Kapitel und das Handbuch
 * als PDF. Alle vier fragen `HilfeService`.
 */
class HilfeController extends Controller
{
    /**
     * Übersicht mit Inhaltsverzeichnis — und, wenn `q` gesetzt ist, den
     * Suchergebnissen.
     */
    public function index(Request $request, HilfeService $hilfe): View
    {
        $begriff = trim((string) $request->query('q', ''));

        return view('hilfe.index', [
            'gruppen' => $hilfe->gruppen(),
            'begriff' => $begriff,
            'treffer' => $begriff === '' ? null : $hilfe->suche($begriff),
        ]);
    }

    /**
     * Das Kapitel zur aktuellen Maske, als Ausschnitt für das Panel.
     *
     * Der Routenname kommt aus der Anfrage, nicht aus dem Verweis — das Panel
     * wird von jeder Seite aus geöffnet und weiß nur, wo es steht. Kennt der
     * Dienst dazu kein Kapitel, liefert die Antwort **200 mit einem Hinweis**
     * statt eines Fehlers: Ein „?" das gelegentlich 404 sagt, sieht kaputt aus.
     */
    public function panel(Request $request, HilfeService $hilfe): View
    {
        $kapitel = $hilfe->fuerRoute((string) $request->query('route', ''));

        return view('hilfe.partials.panel', [
            'kapitel' => $kapitel,
            'html' => $kapitel === null ? null : $hilfe->alsHtml($kapitel->markdown),
        ]);
    }

    /**
     * Ein einzelnes Kapitel als volle Seite — der Weg, den ein Verweis aus dem
     * Panel und aus der Übersicht nimmt.
     */
    public function show(string $slug, HilfeService $hilfe): View
    {
        $kapitel = $hilfe->kapitel($slug);

        abort_if(! $kapitel instanceof HilfeKapitel, 404);

        return view('hilfe.show', [
            'kapitel' => $kapitel,
            'html' => $hilfe->html($slug),
            'gruppen' => $hilfe->gruppen(),
        ]);
    }

    /**
     * Das Handbuch als PDF.
     *
     * Erzeugt aus **derselben** Zeichenkette wie der Datei-Export
     * (`handbuch:erzeugen`); der einzige Unterschied ist, dass sie hier durch
     * `alsHtml()` läuft. Die Stylesheets bringt die Ansicht mit — eine
     * fehlende CSS-Klasse fällt in dompdf nicht auf.
     */
    public function handbuch(HilfeService $hilfe, CurrentExamPeriodService $perioden): Response
    {
        // Die Route ist zentral und kennt keinen Mandanten mehr. Der Schulname
        // kommt deshalb aus dem angemeldeten Konto — beim Super-Admin, der
        // keiner Schule zugeordnet ist, bleibt die Fußzeile eben ohne.
        $schule = Auth::user()?->school;
        $schulname = $schule instanceof School ? $schule->name : null;

        $pdf = Pdf::loadView('hilfe.pdf.handbuch', [
            'inhalt' => $hilfe->alsHtml($hilfe->handbuchMarkdown($schulname)),
            'schulname' => $schulname,
            'jahr' => $perioden->currentYear(),
        ])->setPaper('a4');

        return $pdf->download('abiplaner-handbuch.pdf');
    }
}
