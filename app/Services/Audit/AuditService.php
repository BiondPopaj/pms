<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an audit event.
     */
    public function log(
        string  $event,
        ?User   $user       = null,
        ?int    $propertyId = null,
        string  $description= '',
        ?Model  $subject    = null,
        array   $oldValues  = [],
        array   $newValues  = [],
        array   $tags       = [],
    ): AuditLog {
        $user       = $user ?? Auth::user();
        $propertyId = $propertyId ?? Request::instance()->get('current_property')?->id;

        return AuditLog::create([
            'property_id'    => $propertyId,
            'user_id'        => $user?->id,
            'event'          => $event,
            'auditable_type' => $subject ? get_class($subject) : null,
            'auditable_id'   => $subject?->getKey(),
            'old_values'     => !empty($oldValues) ? $oldValues : null,
            'new_values'     => !empty($newValues) ? $newValues : null,
            'url'            => Request::fullUrl(),
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
            'tags'           => !empty($tags) ? $tags : null,
        ]);
    }

    /**
     * Log a model change event (create/update/delete).
     */
    public function logModelChange(
        string $event,
        Model  $model,
        array  $oldValues = [],
        array  $newValues = [],
        ?User  $user      = null,
    ): AuditLog {
        return $this->log(
            event:      $event,
            user:       $user,
            subject:    $model,
            oldValues:  $oldValues,
            newValues:  $newValues,
        );
    }

    /**
     * Log a financial event (payment, refund, etc.).
     */
    public function logFinancial(
        string $event,
        int    $propertyId,
        array  $details,
        ?User  $user = null,
    ): AuditLog {
        return $this->log(
            event:      $event,
            user:       $user,
            propertyId: $propertyId,
            newValues:  $details,
            tags:       ['financial'],
        );
    }

    /**
     * Log a security event.
     */
    public function logSecurity(
        string $event,
        string $description,
        ?User  $user = null,
    ): AuditLog {
        return $this->log(
            event:       $event,
            user:        $user,
            description: $description,
            tags:        ['security'],
        );
    }
}
