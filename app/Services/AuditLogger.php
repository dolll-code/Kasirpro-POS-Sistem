<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Keys that should never be recorded in audit logs.
     */
    protected const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'old_password',
        'remember_token',
        'token',
        'secret',
        '_token',
        'authorization',
        'credit_card',
        'cvv',
    ];

    /**
     * Record an audit event into audit_logs.
     */
    public static function log(
        string $action,
        string $module,
        Model|string|int|null $reference = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): AuditLog {
        $actorId = $userId ?? Auth::id();

        $refType = null;
        $refId = null;

        if ($reference instanceof Model) {
            $refType = get_class($reference);
            $refId = (string) $reference->getKey();
        } elseif (is_string($reference) || is_numeric($reference)) {
            $refId = (string) $reference;
        }

        $sanitizedOld = $oldValues !== null ? self::sanitize($oldValues) : null;
        $sanitizedNew = $newValues !== null ? self::sanitize($newValues) : null;

        $correlationId = Request::header('X-Request-Id');
        $ipAddress = Request::ip();
        $userAgent = Request::userAgent();

        return AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'module' => $module,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'old_values' => $sanitizedOld,
            'new_values' => $sanitizedNew,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'correlation_id' => $correlationId,
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively strip out sensitive keys from audit log values.
     */
    public static function sanitize(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
