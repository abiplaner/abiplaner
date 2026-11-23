<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $school_number Primary Key (4-stellige Schulnummer)
 * @property string $name
 * @property string $short_name
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postal_code
 * @property string $state
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $logo_path
 * @property string|null $calendar_token
 * @property array|null $settings
 * @property bool $is_active
 * @property array|null $data Frei verwendbare Zusatzdaten (früher stancl/tenancy)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ActivityLog> $activityLogs
 * @property-read int|null $activity_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereCalendarToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereLogoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereSchoolNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereShortName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereUpdatedAt($value)
 * Es gibt KEIN $id. Die Tabelle hat keine solche Spalte, und Eloquent legt
 * auch keinen Alias an — `$school->id` liefert null. Hier stand bis zum
 * 30.08.2026 das Gegenteil („Alias für school_number"), und genau darauf ist
 * SchoolPolicy::view() hereingefallen: Der Vergleich gegen `$school->id` war
 * immer falsch, ein Planer kam an die Seite seiner eigenen Schule nicht heran.
 * Der Schlüssel heißt `school_number`, ersatzweise `getKey()`.
 *
 * @mixin \Eloquent
 */
class School extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    /**
     * Primary key is school_number (VARCHAR), not auto-increment id
     */
    protected $primaryKey = 'school_number';
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'name',
        'short_name',
        'school_number',
        'address',
        'city',
        'postal_code',
        'state',
        'email',
        'phone',
        'logo_path',
        'settings',
        'is_active',
        'calendar_token',
        'data',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'data' => 'array',
    ];

    // Relationships
    public function users(): HasMany
    {
        // Explizit Foreign Key und Local Key angeben, da school_number der Primary Key ist
        return $this->hasMany(User::class, 'school_id', 'school_number');
    }

    // Lehrkräfte und Schüler haben hier bewusst KEINE Beziehung.
    //
    // Sie stehen in der Datenbank des Mandanten, School steht zentral — eine
    // Eloquent-Beziehung kann diese Grenze nicht überschreiten. Die beiden
    // frueheren `hasMany(..., 'school_id', 'school_number')` hätten obendrein
    // auf eine Spalte gezeigt, die `teachers` und `students` gar nicht haben.
    // Aufgerufen wurden sie nie; mit dem Mandanten geladen kommt man über
    // `Teacher::all()` bzw. `Student::all()` an dieselben Daten.

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'school_id', 'school_number');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helper Methods
    /**
     * Generate a new calendar token
     */
    public function generateCalendarToken(): string
    {
        $this->calendar_token = bin2hex(random_bytes(32));
        $this->save();
        return $this->calendar_token;
    }
    
    /**
     * Get or generate calendar token
     */
    public function getCalendarToken(): string
    {
        if (!$this->calendar_token) {
            return $this->generateCalendarToken();
        }
        return $this->calendar_token;
    }
    
    /**
     * Get the calendar feed URL
     */
    public function getCalendarFeedUrl(?int $gradeLevel = null): string
    {
        $token = $this->getCalendarToken();
        
        if ($gradeLevel) {
            return route('calendar.feed.grade', ['token' => $token, 'gradeLevel' => $gradeLevel]);
        }
        
        return route('calendar.feed', ['token' => $token]);
    }
    
    /**
     * Get all calendar feed URLs (all grades)
     */
    public function getCalendarFeedUrls(): array
    {
        return [
            'all' => $this->getCalendarFeedUrl(),
            11 => $this->getCalendarFeedUrl(11),
            12 => $this->getCalendarFeedUrl(12),
            13 => $this->getCalendarFeedUrl(13),
        ];
    }
}
