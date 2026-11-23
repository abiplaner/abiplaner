<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AwardDescription extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    // Tenant-DB – Umzug aus zentraler DB am 20.06.2026.
    protected $fillable = ['description'];

}
