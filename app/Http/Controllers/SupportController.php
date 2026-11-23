<?php

namespace App\Http\Controllers;

use App\Models\SupportAnhang;
use App\Models\SupportFall;
use App\Models\SupportNachricht;
use App\Services\ActivityLogger;
use App\Services\FehlerKennung;
use App\Services\HilfeService;
use App\Services\SupportAnhangService;
use App\Services\SupportBenachrichtigungsService;
use App\Services\SupportKontextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fehler melden — die Sicht der Schule (Modul 6.4, Stufe 1).
 *
 * Vier Ausgaben: der Kontext-Ausschnitt und die Kapitelvorschlaege fuer das
 * Panel neben dem „?", die Liste der eigenen Faelle und der einzelne Fall mit
 * seinem Verlauf.
 *
 * **Der Kontext wird beim Absenden neu erhoben und nicht aus dem Formular
 * uebernommen.** Die Anzeige im Panel ist eine Vorschau fuer den Melder; was
 * in die Akte wandert, bestimmt der Server. Sonst waere der Inhalt der
 * Fallakte ein Feld, das jeder aendern kann.
 */
class SupportController extends Controller
{
    /**
     * Der Kontext-Ausschnitt fuer das Panel.
     *
     * Wie beim Hilfe-Panel: Der Routenname kommt aus der Anfrage, nicht aus
     * dem Verweis — das Panel wird von jeder Seite aus geoeffnet und weiss nur,
     * wo es steht.
     */
    public function kontext(Request $request, SupportKontextService $kontext): View
    {
        return view('support.partials.kontext', [
            'kontext' => $kontext->erheben($request, Auth::user()),
        ]);
    }

    /**
     * Kapitelvorschlaege zum getippten Text (Stufe 1.5).
     *
     * **Die billigste Stufe des Moduls und die unterschaetzte:** Die Suche
     * gibt es seit 6.3; hier faengt sie die Fragen ab, bevor daraus ein Fall
     * wird. Der Melder sieht die Vorschlaege, waehrend er schreibt.
     */
    public function vorschlag(Request $request, HilfeService $hilfe): View
    {
        $begriff = trim((string) $request->query('q', ''));

        // Nur die besten drei: Eine lange Liste liest niemand, der gerade
        // einen Fehler melden will.
        $treffer = array_slice($hilfe->suche($begriff), 0, 3);

        return view('support.partials.vorschlaege', [
            'begriff' => $begriff,
            'treffer' => $treffer,
        ]);
    }

    /**
     * Die Meldung annehmen.
     *
     * Reihenfolge mit Absicht: **erst speichern, dann benachrichtigen.** Der
     * Mailversand laeuft bei `sync` im Request und darf die Meldung nicht
     * gefaehrden (siehe SupportBenachrichtigungsService).
     */
    public function store(
        Request $request,
        SupportKontextService $kontext,
        HilfeService $hilfe,
        SupportBenachrichtigungsService $benachrichtigung,
        SupportAnhangService $anhaenge,
    ): RedirectResponse {
        $daten = $request->validate([
            'betreff' => ['required', 'string', 'max:200'],
            'beschreibung' => ['required', 'string', 'max:5000'],
            'herkunft_route' => ['nullable', 'string', 'max:190'],
            'herkunft_url' => ['nullable', 'string', 'max:490'],
            // Bildschirmfotos (Entscheidung 48). Die Regeln stehen im Dienst,
            // damit Maske, Controller und Test nicht auseinanderlaufen.
            'anhaenge' => ['nullable', 'array', 'max:' . SupportAnhangService::MAX_ANZAHL],
            'anhaenge.*' => SupportAnhangService::regeln(),
        ]);

        $nutzer = Auth::user();
        $erhoben = $kontext->erheben($request, $nutzer);
        $route = $kontext->herkunftsroute($request);
        $schule = app(\App\Services\AktuelleSchule::class)->schule();

        $fall = SupportFall::query()->create([
            'school_id' => $nutzer?->school_id,
            'schule_name' => $schule instanceof \App\Models\School ? $schule->name : null,
            'user_id' => $nutzer?->id,
            'melder_name' => $nutzer?->name,
            'melder_email' => $nutzer?->email,
            'betreff' => $daten['betreff'],
            'beschreibung' => $daten['beschreibung'],
            'route_name' => $route,
            'url' => $kontext->herkunftsUrl($request),
            // Die Modulzuordnung kommt aus dem Hilfesystem und wird nicht
            // zweitgepflegt — eine zweite Zuordnung liefe neben der ersten her.
            'hilfe_slug' => $hilfe->fuerRoute($route)?->slug,
            'status' => SupportFall::STATUS_NEU,
            'kontext' => $erhoben,
            'fehler_id' => is_array($erhoben['fehler'] ?? null)
                ? ($erhoben['fehler']['id'] ?? null)
                : null,
        ]);

        // Nach dem Anlegen, nicht davor: Die Dateien liegen unter der Fall-Id.
        $anhaenge->speichere($fall, array_values($request->file('anhaenge', [])), $nutzer?->id);

        // Der Kurzbefund hat seinen Zweck erfuellt. Bliebe er stehen, haenge
        // er an der naechsten Meldung noch einmal dran.
        $request->session()->forget(FehlerKennung::SESSION_SCHLUESSEL);

        ActivityLogger::log('support_gemeldet', SupportFall::class, $fall->id, $daten['betreff']);

        $zugestellt = $benachrichtigung->benachrichtige($fall);

        return redirect()->back()->with(
            'success',
            $zugestellt
                ? 'Ihre Meldung ist eingegangen. Sie finden sie unter „Support".'
                : 'Ihre Meldung ist gespeichert. Die Benachrichtigung konnte noch nicht '
                    . 'zugestellt werden und wird nachgeholt.'
        );
    }

    /** Die Faelle der eigenen Schule — nicht nur die eigenen. */
    public function index(): View
    {
        $nutzer = Auth::user();

        $faelle = SupportFall::query()
            ->where('school_id', $nutzer?->school_id)
            // Der Zaehler als Unterabfrage und nicht je Zeile: hatUngeleseneAntwort()
            // waere in einer Liste eine Abfrage pro Fall.
            ->withCount(['nachrichten as ungelesen_count' => static fn ($abfrage) => $abfrage
                ->where('autor_rolle', '!=', SupportNachricht::ROLLE_SCHULE)
                ->whereNull('gelesen_am_schule')])
            ->orderByDesc('id')
            ->paginate(20);

        return view('support.index', ['faelle' => $faelle]);
    }

    public function show(SupportFall $fall): View
    {
        $this->authorize('view', $fall);

        // Antworten des Supports gelten mit dem Oeffnen als gelesen — der
        // Zaehler in der Kopfzeile haengt daran.
        SupportNachricht::query()
            ->where('support_fall_id', $fall->id)
            ->where('autor_rolle', '!=', SupportNachricht::ROLLE_SCHULE)
            ->whereNull('gelesen_am_schule')
            ->update(['gelesen_am_schule' => now()]);

        return view('support.show', [
            'fall' => $fall->load('nachrichten', 'anhaenge'),
        ]);
    }

    /**
     * Ein Bildschirmfoto ausliefern.
     *
     * Der Fall steht im Pfad und wird gegen den Anhang geprueft: Sonst waere
     * die Policy am Fall wirkungslos, sobald jemand eine fremde Anhang-Id
     * unter den eigenen Fall haengt.
     */
    public function anhang(SupportFall $fall, SupportAnhang $anhang, SupportAnhangService $dienst): StreamedResponse
    {
        $this->authorize('view', $fall);
        abort_unless($anhang->support_fall_id === $fall->id, 404);

        return $dienst->ausliefern($anhang);
    }

    /**
     * Eine Nachfrage der Schule.
     *
     * Sie setzt den Status zurueck auf „neu": Ein beantworteter Fall, in dem
     * jemand nachfragt, ist nicht beantwortet.
     */
    public function nachricht(Request $request, SupportFall $fall): RedirectResponse
    {
        $this->authorize('antworten', $fall);

        $daten = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $nutzer = Auth::user();

        SupportNachricht::query()->create([
            'support_fall_id' => $fall->id,
            'autor_rolle' => SupportNachricht::ROLLE_SCHULE,
            'autor_user_id' => $nutzer?->id,
            'autor_name' => $nutzer?->name,
            'text' => $daten['text'],
            'gelesen_am_schule' => now(),
        ]);

        $fall->forceFill([
            'status' => SupportFall::STATUS_NEU,
            'geschlossen_am' => null,
        ])->save();

        return redirect()
            ->route('support.show', $fall)
            ->with('success', 'Ihre Nachricht ist im Fall vermerkt.');
    }
}
