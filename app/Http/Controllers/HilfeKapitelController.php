<?php

namespace App\Http\Controllers;

use App\Models\HilfeKapitel;
use App\Models\HilfeKapitelVersion;
use App\Services\HilfeDeckungsPruefung;
use App\Services\HilfeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Verwaltung der Hilfekapitel (Roadmap 6.3, zweite Stufe).
 *
 * Zentraler Bestand, deshalb Super-Admin: Ein Satz Texte gilt fuer alle
 * Schulen (Entscheidung Wilfried, 29.08.2026).
 *
 * **Vor jeder Aenderung wird die bisherige Fassung weggeschrieben**, nicht die
 * neue danach. Nur so ist der Stand zu retten, den es zu retten gaebe — und
 * die zentrale Datenbank sichert die Backup-Maske der Anwendung nicht.
 */
class HilfeKapitelController extends Controller
{
    public function index(HilfeService $hilfe, HilfeDeckungsPruefung $pruefung): View
    {
        return view('admin.hilfe.index', [
            'gruppen' => $hilfe->gruppen(),
            'anzahl' => count($hilfe->alle()),
            'luecken' => $pruefung->luecken(),
            'toteVerweise' => $pruefung->toteVerweise(),
        ]);
    }

    public function create(): View
    {
        return view('admin.hilfe.form', [
            'kapitel' => new HilfeKapitel(['reihenfolge' => 999, 'gruppe' => 'Weiteres']),
            'neu' => true,
        ]);
    }

    public function store(Request $request, HilfeService $hilfe): RedirectResponse
    {
        $daten = $this->pruefe($request);

        $kapitel = HilfeKapitel::query()->create($daten);
        $hilfe->neuLaden();

        return redirect()
            ->route('admin.hilfe.edit', $kapitel)
            ->with('success', 'Kapitel „' . $kapitel->titel . '" angelegt.');
    }

    public function edit(HilfeKapitel $hilfeKapitel, HilfeService $hilfe): View
    {
        return view('admin.hilfe.form', [
            'kapitel' => $hilfeKapitel,
            'neu' => false,
            'vorschau' => $hilfe->alsHtml($hilfeKapitel->markdown),
            'versionen' => $hilfeKapitel->versionen()->limit(25)->get(),
        ]);
    }

    public function update(Request $request, HilfeKapitel $hilfeKapitel, HilfeService $hilfe): RedirectResponse
    {
        $daten = $this->pruefe($request, $hilfeKapitel);

        DB::transaction(function () use ($hilfeKapitel, $daten): void {
            $this->fasseZusammen($hilfeKapitel, 'vor Bearbeitung');

            $hilfeKapitel->fill($daten);
            // Der Fingerabdruck der Grundfassung faellt: Ab jetzt ist das
            // Kapitel redigiert, und der Seeder laesst es in Ruhe.
            $hilfeKapitel->grundfassung_hash = null;
            $hilfeKapitel->save();
        });

        $hilfe->neuLaden();

        return redirect()
            ->route('admin.hilfe.edit', $hilfeKapitel)
            ->with('success', 'Kapitel gespeichert.');
    }

    public function destroy(HilfeKapitel $hilfeKapitel, HilfeService $hilfe): RedirectResponse
    {
        $titel = $hilfeKapitel->titel;

        // Die Versionen fallen per Fremdschluessel mit — das Kapitel ist damit
        // endgueltig weg. Es laesst sich aber aus der Grundfassung wiederholen,
        // solange es dort steht: "php artisan hilfe:einspielen".
        $hilfeKapitel->delete();
        $hilfe->neuLaden();

        return redirect()
            ->route('admin.hilfe.index')
            ->with('success', 'Kapitel „' . $titel . '" gelöscht.');
    }

    /**
     * Vorschau waehrend des Schreibens — dieselbe Umwandlung wie in der Hilfe,
     * damit niemand etwas veroeffentlicht, das anders aussieht als gedacht.
     */
    public function vorschau(Request $request, HilfeService $hilfe): JsonResponse
    {
        $markdown = (string) $request->input('markdown', '');

        return response()->json([
            'html' => $hilfe->alsHtml($markdown),
        ]);
    }

    public function versionen(HilfeKapitel $hilfeKapitel): View
    {
        return view('admin.hilfe.versionen', [
            'kapitel' => $hilfeKapitel,
            'versionen' => $hilfeKapitel->versionen()->get(),
        ]);
    }

    /**
     * Holt eine Fassung zurueck — und schreibt vorher die aktuelle weg, damit
     * auch das Zurueckholen umkehrbar bleibt.
     */
    public function zurueckholen(
        HilfeKapitel $hilfeKapitel,
        HilfeKapitelVersion $version,
        HilfeService $hilfe,
    ): RedirectResponse {
        abort_if($version->hilfe_kapitel_id !== $hilfeKapitel->id, 404);

        DB::transaction(function () use ($hilfeKapitel, $version): void {
            $this->fasseZusammen($hilfeKapitel, 'vor Zurückholen');

            $hilfeKapitel->fill($version->inhalt());
            $hilfeKapitel->grundfassung_hash = null;
            $hilfeKapitel->save();
        });

        $hilfe->neuLaden();

        return redirect()
            ->route('admin.hilfe.edit', $hilfeKapitel)
            ->with('success', 'Fassung vom ' . optional($version->created_at)->format('d.m.Y H:i') . ' zurückgeholt.');
    }

    /**
     * Schreibt den aktuellen Stand als Version weg.
     */
    private function fasseZusammen(HilfeKapitel $kapitel, string $anlass): void
    {
        $benutzer = Auth::user();

        HilfeKapitelVersion::query()->create($kapitel->inhaltFuerVersion() + [
            'hilfe_kapitel_id' => $kapitel->id,
            'user_id' => $benutzer?->id,
            'benutzername' => $benutzer?->name,
            'anlass' => $anlass,
        ]);
    }

    /**
     * @return array{slug: string, titel: string, gruppe: string, reihenfolge: int, routen: string|null, stichworte: string|null, markdown: string}
     */
    private function pruefe(Request $request, ?HilfeKapitel $bestehend = null): array
    {
        $eindeutig = 'unique:hilfe_kapitel,slug' . ($bestehend !== null ? ',' . $bestehend->id : '');

        $daten = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', $eindeutig],
            'titel' => ['required', 'string', 'max:200'],
            'gruppe' => ['required', 'string', 'max:100'],
            'reihenfolge' => ['required', 'integer', 'min:0', 'max:9999'],
            'routen' => ['nullable', 'string', 'max:2000'],
            'stichworte' => ['nullable', 'string', 'max:2000'],
            'markdown' => ['required', 'string'],
        ], [
            'slug.regex' => 'Der Schlüssel darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten — er steht in der Adresse.',
            'slug.unique' => 'Diesen Schlüssel trägt bereits ein anderes Kapitel.',
        ]);

        // Kommalisten in einheitlicher Schreibweise ablegen: Der Vergleich mit
        // der Grundfassung laeuft ueber einen Fingerabdruck, und ein
        // zusaetzliches Leerzeichen darf ihn nicht verschieben.
        $daten['routen'] = $this->normiert($daten['routen'] ?? null);
        $daten['stichworte'] = $this->normiert($daten['stichworte'] ?? null);
        $daten['markdown'] = trim(str_replace("\r\n", "\n", (string) $daten['markdown']));
        $daten['slug'] = Str::lower($daten['slug']);

        return $daten;
    }

    private function normiert(?string $wert): ?string
    {
        $liste = HilfeKapitel::liste($wert);

        return $liste === [] ? null : implode(', ', $liste);
    }
}
