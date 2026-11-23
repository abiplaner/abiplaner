<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aggregierte Vorjahres-Absenzkennzahlen (Modul 1.1, Phase 3).
 *
 * Wird beim Schuljahreswechsel aus den Rohdaten des Vorjahres
 * eingefroren (AbsenceCarryoverService::freezeForPeriod), bevor diese
 * gelöscht werden. Zuordnung zum neuen Schülerdatensatz per
 * Namensabgleich (matched_student_id).
 *
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string|null $external_id
 * @property string $source_school_year
 * @property string|null $source_grade
 * @property int $sick_days
 * @property float|null $sick_percent
 * @property int $unexcused_hours
 * @property int $tardiness_count
 * @property int $tardiness_minutes
 * @property bool $had_attest_requirement
 * @property int|null $matched_student_id
 * @property \Illuminate\Support\Carbon|null $matched_at
 * @property string|null $match_method
 */
class AbsenceCarryover extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    /**
     * Jahrgangsstufe, die am Ende des Schuljahres die Schule verlässt.
     *
     * Für diese Schüler wird beim Wechsel zwar ein Carryover eingefroren
     * (der Abiturjahrgang hat ja Absenzen), es kann aber im neuen Jahr
     * nichts mehr dazu passen — außer bei Wiederholern. Ohne diese
     * Unterscheidung besteht die Klärungsliste zu ~95 % aus Abgängern:
     * Beim Probelauf mit echten Daten waren es 114 von 118 Einträgen.
     */
    public const GRADUATING_GRADE = '13';

    /** Carryovers des abgehenden Abiturjahrgangs. */
    public function scopeFromGraduatingGrade($query)
    {
        return $query->where('source_grade', self::GRADUATING_GRADE);
    }

    /**
     * Carryovers der Jahrgänge, die im neuen Schuljahr weiter an der Schule
     * sind. Einträge ohne Jahrgangsangabe zählen bewusst dazu — lieber ein
     * Eintrag zu viel in der Klärungsliste als einer, der stillschweigend
     * verschwindet.
     */
    public function scopeFromContinuingGrades($query)
    {
        return $query->where(function ($q): void {
            $q->where('source_grade', '!=', self::GRADUATING_GRADE)
                ->orWhereNull('source_grade');
        });
    }

    protected $fillable = [
        'firstname',
        'lastname',
        'external_id',
        'source_school_year',
        'source_grade',
        'sick_days',
        'sick_percent',
        'unexcused_hours',
        'tardiness_count',
        'tardiness_minutes',
        'had_attest_requirement',
        'matched_student_id',
        'matched_at',
        'match_method',
    ];

    protected $casts = [
        'sick_days' => 'integer',
        'sick_percent' => 'float',
        'unexcused_hours' => 'integer',
        'tardiness_count' => 'integer',
        'tardiness_minutes' => 'integer',
        'had_attest_requirement' => 'boolean',
        'matched_at' => 'datetime',
    ];

}
