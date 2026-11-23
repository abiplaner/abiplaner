<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string|null $school_id FK zu schools.school_number
 * @property string $name
 * @property string $email
 * @property \Carbon\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret entschlüsselt via Cast
 * @property \Carbon\Carbon|null $two_factor_confirmed_at
 * @property string $role super_admin|planner
 * @property bool $is_active
 * @property \Carbon\Carbon|null $last_login_at
 * @property string|null $last_visited_path Modul 1.6: zuletzt besuchte Seite, geräteübergreifend
 * @property string|null $remember_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ActivityLog> $activityLogs
 * @property-read int|null $activity_logs_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\School|null $school
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TwoFactorTrustedDevice> $trustedDevices
 * @property-read int|null $trusted_devices_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User active()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * This is a CENTRAL table - uses central database connection.
     * Users are managed centrally and can be assigned to schools.
     */
    
    protected $fillable = [
        'school_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function school(): BelongsTo
    {
        // Explizit Foreign Key und Owner Key angeben, da schools.school_number der Primary Key ist
        return $this->belongsTo(School::class, 'school_id', 'school_number');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TwoFactorTrustedDevice::class);
    }

    /**
     * Hat der Nutzer die 2FA vollständig eingerichtet (Secret hinterlegt UND bestätigt)?
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    // Role Checks
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function isPlanner(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'planner']);
    }

    /**
     * Arbeitet in der Planung einer Schule (Schul-Dashboard, Planungsmenü).
     *
     * Web: Planer (und die Altrolle 'admin'). Der Super-Admin pflegt dort nur
     * die zentralen Daten aller Schulen und landet im Admin-Dashboard.
     *
     * Lokal: zusätzlich der Super-Admin mit Schule. Den ersten Nutzer legt der
     * Einrichtungsassistent als Super-Admin an, und das ist fast immer der
     * Oberstufenkoordinator — er pflegt Stammdaten UND plant.
     */
    public function isSchoolPlanner(): bool
    {
        if (in_array($this->role, ['admin', 'planner'], true)) {
            return true;
        }

        // Der Super-Admin der Schule plant mit (meist der OSK).
        return $this->isSuperAdmin()
            && $this->school_id !== null;
    }

    /** Nur zentrale Verwaltung, keine Schulplanung (Super-Admin der Web-Version). */
    public function isCentralAdmin(): bool
    {
        return $this->isSuperAdmin() && ! $this->isSchoolPlanner();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
