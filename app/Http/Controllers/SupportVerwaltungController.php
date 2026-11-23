<?php

namespace App\Http\Controllers;

use App\Models\SupportAnhang;
use App\Models\SupportFall;
use App\Models\SupportNachricht;
use App\Services\ActivityLogger;
use App\Services\SupportAnhangService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Die Faelle bearbeiten — die Sicht des Supports (Modul 6.4, Stufe 1).
 *
 * Laeuft **ohne Tenancy** unter `/admin`: Der Support sieht die Faelle aller
 * Schulen in einer Liste, und genau dafuer liegen sie zentral (Entscheidung
 * 43). Die Rollenpruefung macht die Middleware in `routes/central.php`; die
 * Policy zieht die Grenze noch einmal am einzelnen Fall.
 */
class SupportVerwaltungController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'offen');

        $abfrage = SupportFall::query()->with('school')->orderByDesc('id');

        if ($status === 'offen') {
            $abfrage->offen();
        } elseif (in_array($status, SupportFall::STATUS, true)) {
            $abfrage->where('status', $status);
        }

        return view('admin.support.index', [
            'faelle' => $abfrage->paginate(25)->withQueryString(),
            'status' => $status,
            'offen' => SupportFall::query()->offen()->count(),
            // Entscheidung 53: Alle Aufbewahrungsfristen haengen an
            // `geschlossen_am`. Ein Fall, der nie geschlossen wird, wird nie
            // geloescht — deshalb der Hinweis.
            'langeOffen' => SupportFall::query()->langeOffen()->count(),
            'langeOffenMonate' => max(1, (int) config('support.aufbewahrung.offen_hinweis_monate')),
        ]);
    }

    public function show(SupportFall $fall): View
    {
        $this->authorize('view', $fall);

        SupportNachricht::query()
            ->where('support_fall_id', $fall->id)
            ->where('autor_rolle', SupportNachricht::ROLLE_SCHULE)
            ->whereNull('gelesen_am_support')
            ->update(['gelesen_am_support' => now()]);

        return view('admin.support.show', [
            'fall' => $fall->load('nachrichten', 'anhaenge', 'school'),
        ]);
    }

    /** Wie in der Sicht der Schule: der Fall steht im Pfad und wird geprueft. */
    public function anhang(SupportFall $fall, SupportAnhang $anhang, SupportAnhangService $dienst): StreamedResponse
    {
        $this->authorize('view', $fall);
        abort_unless($anhang->support_fall_id === $fall->id, 404);

        return $dienst->ausliefern($anhang);
    }

    /**
     * Antworten. Der Status springt auf „beantwortet" — die Schule sieht die
     * Antwort dann als ungelesen in der Kopfzeile.
     */
    public function antwort(Request $request, SupportFall $fall): RedirectResponse
    {
        $this->authorize('bearbeiten', $fall);

        $daten = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $nutzer = Auth::user();

        SupportNachricht::query()->create([
            'support_fall_id' => $fall->id,
            'autor_rolle' => SupportNachricht::ROLLE_SUPPORT,
            'autor_user_id' => $nutzer?->id,
            'autor_name' => $nutzer?->name,
            'text' => $daten['text'],
            'gelesen_am_support' => now(),
        ]);

        $fall->forceFill(['status' => SupportFall::STATUS_BEANTWORTET])->save();

        ActivityLogger::log('support_beantwortet', SupportFall::class, $fall->id);

        return redirect()
            ->route('admin.support.show', $fall)
            ->with('success', 'Die Antwort steht im Fall.');
    }

    public function status(Request $request, SupportFall $fall, SupportAnhangService $anhaenge): RedirectResponse
    {
        $this->authorize('bearbeiten', $fall);

        $daten = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', SupportFall::STATUS)],
        ]);

        $erledigt = $daten['status'] === SupportFall::STATUS_ERLEDIGT;

        $fall->forceFill([
            'status' => $daten['status'],
            'geschlossen_am' => $erledigt ? now() : null,
        ])->save();

        // Entscheidung 48: Mit dem Abschluss verschwinden die Bildschirmfotos.
        // Ein Bildschirmfoto aus dieser Anwendung zeigt fast immer Namen; der
        // Text der Akte bleibt, die Bilder nicht.
        $entfernt = $erledigt ? $anhaenge->loesche($fall) : 0;

        ActivityLogger::log('support_status', SupportFall::class, $fall->id, $daten['status']);

        return redirect()
            ->route('admin.support.show', $fall)
            ->with('success', 'Der Status steht jetzt auf „' . $fall->statusText() . '".'
                . ($entfernt > 0
                    ? ' ' . $entfernt . ($entfernt === 1 ? ' Bildschirmfoto wurde' : ' Bildschirmfotos wurden')
                        . ' dabei gelöscht.'
                    : ''));
    }
}
