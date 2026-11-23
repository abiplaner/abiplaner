<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Konkretes Datum eines Prüfungs-Slots für ein bestimmten Abitur-Jahrgang.
 *
 * Der Slot (WrittenExamSlot) definiert Fächer und Uhrzeiten.
 * Diese Tabelle ordnet dem Slot das jährlich wechselnde Datum zu.
 *
 * Auf diese Tabelle verweist written_exam_assignments.written_exam_year_date_id
 * (soft reference, kein FK über DB-Grenzen hinweg).
 *
 * @property int $id
 * @property int $exam_year
 * @property int $written_exam_slot_id
 * @property \Carbon\Carbon $exam_date
 * @property int|null $supervision_room_count
 * @property int|null $supervision_corridor_count
 * @property int|null $room_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\WrittenExamSlot $slot
 * @property-read \Carbon\Carbon $date             Alias für exam_date (Rückwärtskompatibilität)
 * @property-read \App\Models\Subject|null $subject Erstes Fach des Slots (Rückwärtskompatibilität)
 * @property-read \App\Models\ExamPeriod|null $examPeriod
 * @mixin \Eloquent
 */
class WrittenExamYearDate extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'exam_year',
        'written_exam_slot_id',
        'exam_date',
    ];

    protected $casts = [
        'exam_year' => 'integer',
        'exam_date' => 'date',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(WrittenExamSlot::class, 'written_exam_slot_id');
    }

    /**
     * Prüfungszeitraum des Jahrgangs (central DB, via exam_year).
     */
    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class, 'exam_year', 'year');
    }

    /**
     * Accessor: $date als Alias für $exam_date (Rückwärtskompatibilität).
     */
    public function getDateAttribute(): Carbon
    {
        return Carbon::parse($this->exam_date);
    }

    /**
     * Accessor: $subject – erstes Fach des Slots (Rückwärtskompatibilität).
     */
    public function getSubjectAttribute(): ?Subject
    {
        return $this->slot->subjects->first();
    }

    /**
     * Accessor: $materialsBySubject – Prüfungsmaterialien gruppiert nach Fach.
     *
     * Ein Termin kann mehrere Fächer haben; Materialien hängen am Fach
     * (Pivot exam_material_subject mit is_required/notes). Bewusst KEINE
     * Deduplizierung über Fächer hinweg – jedes Fach zeigt seine Materialien
     * eigenständig (mit den fachspezifischen Pivot-Werten).
     *
     * Cross-DB-sicher: Subject ist ZENTRAL, ExamMaterial + Pivot sind TENANT.
     * Es wird ausschließlich auf der Tenant-Pivot-Tabelle exam_material_subject
     * über die Integer-Spalte subject_id gefiltert – KEIN whereHas/Join in die
     * zentrale subjects-Tabelle (das würde "subjects doesn't exist" auf der
     * Tenant-Connection auslösen). Die Pivot-Werte is_required/notes werden
     * direkt aus der Pivot gelesen und als Attribute ans Material gehängt.
     *
     * @return Collection<int, array{subject: Subject, materials: \Illuminate\Support\Collection<int, ExamMaterial>}>
     */
    public function getMaterialsBySubjectAttribute(): Collection
    {
        // Connection des Tenant-Models verwenden (kein hartkodierter Name).
        $tenantConnection = (new ExamMaterial())->getConnectionName();
        $pivotTable = 'exam_material_subject';

        return $this->slot->subjects->map(function (Subject $subject) use ($tenantConnection, $pivotTable) {
            // Pivot-Zeilen für dieses Fach direkt aus der Tenant-Pivot lesen.
            $pivotRows = DB::connection($tenantConnection)
                ->table($pivotTable)
                ->where('subject_id', $subject->id)
                ->get(['exam_material_id', 'is_required', 'notes']);

            $materialIds = $pivotRows->pluck('exam_material_id')->all();

            if (count($materialIds) === 0) {
                /** @var \Illuminate\Support\Collection<int, ExamMaterial> $empty */
                $empty = collect();
                return ['subject' => $subject, 'materials' => $empty];
            }

            $pivotById = $pivotRows->keyBy('exam_material_id');

            /** @var \Illuminate\Support\Collection<int, ExamMaterial> $materials */
            $materials = ExamMaterial::whereIn('id', $materialIds)
                ->get()
                ->map(function (ExamMaterial $material) use ($pivotById) {
                    $pivot = $pivotById->get($material->id);
                    // Pivot-Werte als einfache Attribute anhängen (View liest diese).
                    $material->setAttribute('pivot_is_required', (bool) ($pivot->is_required ?? false));
                    $material->setAttribute('pivot_notes', $pivot->notes ?? null);
                    return $material;
                })
                ->values();

            return ['subject' => $subject, 'materials' => $materials];
        })->filter(fn (array $group) => $group['materials']->isNotEmpty())->values();
    }

    /**
     * Accessor: $materials – flache, eindeutige Materialliste (für Zählungen).
     */
    public function getMaterialsAttribute(): Collection
    {
        return $this->materialsBySubject
            ->flatMap(fn (array $group) => $group['materials'])
            ->unique('id')
            ->values();
    }

    /**
     * Relationship: has many WrittenExamAssignments (in tenant DB, soft reference).
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(WrittenExamAssignment::class, 'written_exam_year_date_id');
    }

    /**
     * Start time inherited from the slot.
     */
    public function getStartTime(): Carbon
    {
        return Carbon::parse($this->slot->start_time);
    }

    /**
     * End time inherited from the slot, with optional NTA compensation.
     */
    public function getEndTime(?int $ntaPercent = null): Carbon
    {
        if ($ntaPercent) {
            return $this->slot->getEndTimeWithNta($ntaPercent);
        }
        return Carbon::parse($this->slot->end_time);
    }

    /**
     * End time für ein bestimmtes Anforderungsniveau (grundlegend/erhoeht) mit optionalem NTA.
     * Alias für getEndTime() – Rückwärtskompatibilität nach Modell-Migration.
     */
    public function getEndTimeForLevel(string $level, ?int $ntaPercent = null): Carbon
    {
        return $this->getEndTime($ntaPercent);
    }

    /**
     * End time für HV-Prüfungen (Hörverstehen): fix 30 Minuten + optionaler NTA-Zeitzuschlag.
     */
    public function getEndTimeHv(?int $ntaPercent = null): Carbon
    {
        $duration = 30;
        if ($ntaPercent) {
            $duration = (int) ceil($duration * (1 + $ntaPercent / 100));
        }
        return $this->getStartTime()->addMinutes($duration);
    }

    /**
     * Scope: only dates for a specific year.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     */
    public function scopeForYear($query, int $year): void
    {
        $query->where('exam_year', $year);
    }

}
