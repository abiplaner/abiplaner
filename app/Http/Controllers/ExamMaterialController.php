<?php

namespace App\Http\Controllers;

use App\Models\ExamMaterial;
use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pruefungsmaterialien und ihre Zuordnung zu den Faechern.
 *
 * Die Zuordnung ist der eigentliche Zweck des Moduls: Die Aufsichtsliste
 * druckt je Fach eine Material-Checkliste, und die liest genau diese
 * Zuordnungen. Ohne sie bleibt die Checkliste leer.
 *
 * Faecher liegen ZENTRAL, Materialien und Zuordnungen beim MANDANTEN. Kein
 * Join ueber die Grenze — siehe die Methoden am Modell.
 */
class ExamMaterialController extends Controller
{
    /** Obergrenze fuer die Notiz an einer Zuordnung. */
    private const NOTIZ_MAX = 500;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ExamMaterial::class);

        $query = ExamMaterial::query();

        if ($request->filled('search')) {
            $suche = (string) $request->input('search');
            $query->where(function ($q) use ($suche): void {
                $q->where('name', 'like', "%{$suche}%")
                  ->orWhere('description', 'like', "%{$suche}%");
            });
        }

        $materials = $query->orderBy('name')->paginate(20)->withQueryString();

        // Die Zahl der Faecher je Material in EINER Abfrage auf die
        // Zuordnungstabelle, statt je Karte einer. Ein withCount() gibt es
        // nicht: Dafuer braeuchte es die Beziehung, die es aus gutem Grund
        // nicht mehr gibt.
        $anzahlen = DB::connection((new ExamMaterial())->getConnectionName())
            ->table(ExamMaterial::PIVOT)
            ->whereIn('exam_material_id', $materials->pluck('id'))
            ->selectRaw('exam_material_id, count(*) as anzahl')
            ->groupBy('exam_material_id')
            ->pluck('anzahl', 'exam_material_id');

        foreach ($materials as $material) {
            $material->faecher_anzahl = (int) ($anzahlen[$material->id] ?? 0);
        }

        return view('exam-materials.index', compact('materials'));
    }

    public function create(): View
    {
        $this->authorize('create', ExamMaterial::class);

        return view('exam-materials.create', [
            'faecher' => $this->faecher(),
            'zuordnungen' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ExamMaterial::class);

        $validated = $request->validate($this->regeln());

        $material = ExamMaterial::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'is_default' => $request->boolean('is_default'),
        ]);

        $material->zuordnungenSetzen($this->zuordnungenAus($request));

        Log::info('Pruefungsmaterial angelegt', [
            'material_id' => $material->id,
            'name' => $material->name,
            'faecher' => $material->anzahlZugeordneterFaecher(),
            'user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('written-exams.materials.index')
            ->with('success', 'Material „' . $material->name . '" angelegt.');
    }

    public function show(ExamMaterial $examMaterial): View
    {
        $this->authorize('view', $examMaterial);

        return view('exam-materials.show', [
            'examMaterial' => $examMaterial,
            'faecher' => $examMaterial->zugeordneteFaecher(),
            'zuordnungen' => $examMaterial->zuordnungen(),
        ]);
    }

    public function edit(ExamMaterial $examMaterial): View
    {
        $this->authorize('update', $examMaterial);

        return view('exam-materials.edit', [
            'examMaterial' => $examMaterial,
            'faecher' => $this->faecher(),
            'zuordnungen' => $examMaterial->zuordnungen(),
        ]);
    }

    public function update(Request $request, ExamMaterial $examMaterial): RedirectResponse
    {
        $this->authorize('update', $examMaterial);

        $validated = $request->validate($this->regeln());

        $examMaterial->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'is_default' => $request->boolean('is_default'),
        ]);

        $examMaterial->zuordnungenSetzen($this->zuordnungenAus($request));

        Log::info('Pruefungsmaterial geaendert', [
            'material_id' => $examMaterial->id,
            'name' => $examMaterial->name,
            'faecher' => $examMaterial->anzahlZugeordneterFaecher(),
            'user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('written-exams.materials.index')
            ->with('success', 'Material „' . $examMaterial->name . '" gespeichert.');
    }

    public function destroy(ExamMaterial $examMaterial): RedirectResponse
    {
        $this->authorize('delete', $examMaterial);

        $anzahl = $examMaterial->anzahlZugeordneterFaecher();

        if ($anzahl > 0) {
            return redirect()
                ->route('written-exams.materials.index')
                ->with('error', sprintf(
                    'Das Material „%s" ist noch %d %s zugeordnet und wurde nicht geloescht.'
                    . ' Die Zuordnungen lassen sich beim Bearbeiten entfernen.',
                    $examMaterial->name,
                    $anzahl,
                    $anzahl === 1 ? 'Fach' : 'Faechern'
                ));
        }

        $name = $examMaterial->name;
        $id = $examMaterial->id;
        $examMaterial->delete();

        Log::info('Pruefungsmaterial geloescht', [
            'material_id' => $id,
            'name' => $name,
            'user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('written-exams.materials.index')
            ->with('success', 'Material „' . $name . '" geloescht.');
    }

    /**
     * @return array<string, string>
     */
    private function regeln(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'icon' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
            'faecher' => 'nullable|array',
            'faecher.*.zugeordnet' => 'nullable|boolean',
            'faecher.*.pflicht' => 'nullable|boolean',
            'faecher.*.notiz' => 'nullable|string|max:' . self::NOTIZ_MAX,
        ];
    }

    /**
     * Liest die angekreuzten Faecher aus dem Formular.
     *
     * Die Schluessel werden gegen den Faecherkatalog geprueft, nicht per
     * exists-Regel: Der Katalog liegt zentral, die Regel wuerde auf der
     * Verbindung des Mandanten nachsehen und dort nichts finden.
     *
     * @return array<int, array{is_required: bool, notes: string|null}>
     */
    private function zuordnungenAus(Request $request): array
    {
        $gueltig = $this->faecher()->pluck('id')->all();
        $zuordnungen = [];

        /** @var array<int|string, array<string, mixed>> $eingabe */
        $eingabe = $request->input('faecher', []);

        foreach ($eingabe as $subjectId => $angaben) {
            if (!is_array($angaben) || !filter_var($angaben['zugeordnet'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $id = (int) $subjectId;

            if (!in_array($id, $gueltig, true)) {
                continue;
            }

            $notiz = trim((string) ($angaben['notiz'] ?? ''));

            $zuordnungen[$id] = [
                'is_required' => filter_var($angaben['pflicht'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'notes' => $notiz === '' ? null : $notiz,
            ];
        }

        return $zuordnungen;
    }

    /**
     * @return EloquentCollection<int, Subject>
     */
    private function faecher(): EloquentCollection
    {
        return Subject::query()->orderedForDisplay()->get();
    }
}
