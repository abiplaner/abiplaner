<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Log an activity
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?string $description = null
    ): void {
        if (!auth()->check()) {
            return;
        }
        
        $data = [
            'user_id' => auth()->id(),
            'user_email' => auth()->user()->email,
            'action' => $action,
            'model_type' => $entityType,
            'model_id' => $entityId,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];

        // Add description to new_values as JSON
        if ($description) {
            $data['new_values'] = json_encode(['description' => $description]);
        }

        // Nur für zentrale Datenbank school_id setzen
        if (! app(AktuelleSchule::class)->aktiv()) {
            $data['school_id'] = auth()->user()->school_id;
        }
        
        ActivityLog::create($data);
    }
}
