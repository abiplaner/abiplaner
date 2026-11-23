<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vertrauenswürdiges Gerät für die 2FA (zentrale Tabelle).
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string|null $device_label
 * @property string|null $ip_address
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User $user
 */
class TwoFactorTrustedDevice extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    /**
     * Zentrale Tabelle – analog zum User-Model.
     */

    protected $fillable = [
        'user_id',
        'token_hash',
        'device_label',
        'ip_address',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ist das Gerät abgelaufen? expires_at = null bedeutet unbegrenzt gültig.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
