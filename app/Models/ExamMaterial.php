<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ein Hilfsmittel, das in einer schriftlichen Pruefung zugelassen ist —
 * Woerterbuch, Formelsammlung, Taschenrechner, Atlas.
 *
 * Zugeordnet wird je Fach, mit zwei Angaben an der Zuordnung: ob das Material
 * Pflicht ist und eine Notiz. Gelesen wird das in der Aufsichtsliste
 * (WrittenExamYearDate::getMaterialsBySubjectAttribute()).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $icon
 * @property bool $is_default Grundausstattung: wird beim Zuordnen fuer alle Faecher vorgeschlagen
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property int|null $faecher_anzahl Dynamisch gesetzt: Zahl der Zuordnungen, ExamMaterialController::index()
 * @property bool|null $pivot_is_required Dynamisch gesetzt: Pivot-Wert aus WrittenExamYearDate::getMaterialsBySubjectAttribute()
 * @property string|null $pivot_notes Dynamisch gesetzt: Pivot-Wert aus WrittenExamYearDate::getMaterialsBySubjectAttribute()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamMaterial newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamMaterial newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamMaterial query()
 * @mixin \Eloquent
 */
class ExamMaterial extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;

    /** Die Zuordnungstabelle. Sie liegt beim Mandanten, `subjects` dagegen zentral. */
    public const PIVOT = 'exam_material_subject';

    protected $fillable = [
        'name',
        'description',
        'icon',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // Hier stand eine belongsToMany-Beziehung auf Subject. Sie war nicht
    // benutzbar — und zwar keine Sekunde lang:
    //
    // ExamMaterial und die Zuordnungstabelle liegen beim MANDANTEN, Subject
    // liegt ZENTRAL. Eloquent baut die Abfrage einer belongsToMany auf der
    // Verbindung des Ziels, also der zentralen — und suchte die
    // Zuordnungstabelle dort:
    //
    //     SQLSTATE[42S02]: Table 'abiplaner.exam_material_subject'
    //     doesn't exist
    //
    // Das traf jeden Aufruf: with('subjects') in der Liste,
    // subjects()->count() beim Loeschen, jedes sync(). Alle sieben Routen des
    // Moduls antworteten damit mit 500. (31.08.2026)
    //
    // Ausgerechnet der Kommentar darueber beschrieb den Fall richtig und die
    // Beziehung darunter tat es trotzdem. Deshalb steht der Weg jetzt in
    // Methoden, die die Verbindung selbst waehlen — dieselbe Loesung, die
    // WrittenExamYearDate::getMaterialsBySubjectAttribute() beim Lesen schon
    // benutzt.

    /**
     * Die Zuordnungen dieses Materials, nach subject_id.
     *
     * @return Collection<int, Materialzuordnung>
     */
    public function zuordnungen(): Collection
    {
        $zuordnungen = [];

        foreach ($this->pivot()
            ->where('exam_material_id', $this->getKey())
            ->get(['subject_id', 'is_required', 'notes']) as $zeile
        ) {
            $zuordnungen[(int) $zeile->subject_id] = new Materialzuordnung(
                (bool) $zeile->is_required,
                $zeile->notes === null ? null : (string) $zeile->notes,
            );
        }

        return collect($zuordnungen);
    }

    /**
     * Die zugeordneten Faecher, in Anzeigereihenfolge.
     *
     * Zwei Abfragen auf zwei Datenbanken, bewusst: erst die Schluessel aus der
     * Zuordnungstabelle beim Mandanten, dann die Faecher aus der zentralen.
     *
     * @return EloquentCollection<int, Subject>
     */
    public function zugeordneteFaecher(): EloquentCollection
    {
        $ids = $this->pivot()
            ->where('exam_material_id', $this->getKey())
            ->pluck('subject_id')
            ->all();

        if ($ids === []) {
            /** @var EloquentCollection<int, Subject> $leer */
            $leer = new EloquentCollection();

            return $leer;
        }

        return Subject::query()->whereIn('id', $ids)->orderedForDisplay()->get();
    }

    public function anzahlZugeordneterFaecher(): int
    {
        return $this->pivot()->where('exam_material_id', $this->getKey())->count();
    }

    /**
     * Setzt die Zuordnungen dieses Materials auf genau die uebergebenen —
     * das Gegenstueck zu sync(), nur auf der richtigen Verbindung.
     *
     * @param array<int, array{is_required: bool, notes: string|null}> $zuordnungen subject_id => Angaben
     */
    public function zuordnungenSetzen(array $zuordnungen): void
    {
        $this->pivot()->getConnection()->transaction(function () use ($zuordnungen): void {
            $this->pivot()->where('exam_material_id', $this->getKey())->delete();

            if ($zuordnungen === []) {
                return;
            }

            $jetzt = now();

            $this->pivot()->insert(array_map(
                fn (int $subjectId): array => [
                    'exam_material_id' => $this->getKey(),
                    'subject_id' => $subjectId,
                    'is_required' => $zuordnungen[$subjectId]['is_required'],
                    'notes' => $zuordnungen[$subjectId]['notes'],
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ],
                array_keys($zuordnungen)
            ));
        });
    }

    /**
     * Die Zuordnungstabelle auf der Verbindung dieses Modells — also beim
     * Mandanten, nie zentral.
     */
    private function pivot(): \Illuminate\Database\Query\Builder
    {
        return DB::connection($this->getConnectionName())->table(self::PIVOT);
    }

    // scopeForSchool() ist entfallen (30.08.2026).
    //
    // Er filterte über `school_id` — eine Spalte, die `exam_materials` nicht
    // hat. Jeder Aufruf wäre in „Unknown column" gelaufen; die beiden
    // Aufrufstellen im ExamMaterialController waren bereits auskommentiert.
    // In einer Datenbank je Schule gehören alle Materialien ohnehin dieser
    // einen Schule.
}
