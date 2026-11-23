<?php

namespace App\Models;

use App\Support\SchoolYear;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $school_year
 * @property string $grade Q11|Q12|Q13
 * @property string $name
 * @property array|null $course_codes
 * @property array|null $time_slots
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseTrack newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseTrack newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseTrack query()
 * @mixin \Eloquent
 */
class CourseTrack extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    use HasFactory;


    protected $fillable = [
        'school_year',
        'grade',
        'name',
        'course_codes',
        'time_slots',
    ];

    protected $casts = [
        'course_codes' => 'array',
        'time_slots' => 'array',
    ];

    /**
     * Das laufende Schuljahr im Kurzformat ("2026/27"), Stichtag 1. August.
     *
     * Der Vorgabewert der Schienenplanung. Fest verdrahtet stand hier bis zum
     * 21.09.2026 "2025/26" — die Seite bot das laufende Schuljahr nicht an,
     * und die Schienen liessen sich dafuer nicht importieren.
     */
    public static function currentSchoolYear(?\Carbon\Carbon $heute = null): string
    {
        return SchoolYear::current($heute);
    }

    /**
     * Die Schuljahre, die die Schienenplanung zur Auswahl anbietet, neueste
     * zuerst — siehe SchoolYear::options(). Dazu jedes Schuljahr, fuer das
     * schon Schienen gespeichert sind, und das gerade gewaehlte.
     *
     * @param iterable<string> $vorhandene Schuljahre mit gespeicherten Schienen
     * @return list<string>
     */
    public static function schoolYearOptions(
        string $gewaehlt,
        iterable $vorhandene = [],
        ?\Carbon\Carbon $heute = null
    ): array {
        return SchoolYear::options($gewaehlt, $vorhandene, $heute);
    }

    /**
     * Die Jahrgangsstufe zu einem Abiturjahrgang der ASV-Dateien, gesehen aus
     * einem Schuljahr — oder null, wenn der Jahrgang darin nicht in der
     * Qualifikationsphase ist.
     *
     * ASV fuehrt den Jahrgang als das Schuljahr seines Abiturs ("2025/26"):
     * Im selben Schuljahr ist er Q13, im Schuljahr davor Q12. Fuer 2025/26 gilt
     * also 2025/26 → Q13 und 2026/27 → Q12; fuer 2026/27 dagegen 2026/27 → Q13
     * und 2027/28 → Q12. Die Abbildung war bis zum 21.09.2026 fest auf das
     * Schuljahr 2025/26 verdrahtet — im Schuljahr 2026/27 haette der Import
     * die Q13 stillschweigend als Q12 eingetragen.
     */
    public static function gradeForAbiturjahr(string $abiturjahr, string $schoolYear): ?string
    {
        if (!preg_match('#^(\d{4})/\d{2}$#', trim($abiturjahr), $abitur)
            || !preg_match('#^(\d{4})/\d{2}$#', trim($schoolYear), $schuljahr)) {
            return null;
        }

        return match ((int) $abitur[1] - (int) $schuljahr[1]) {
            0 => 'Q13',
            1 => 'Q12',
            default => null,
        };
    }
}
