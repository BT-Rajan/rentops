<?php
declare(strict_types=1);

namespace App\Helpers;

use App\DB;

class AuditLog
{
    public static function record(
        string  $action,
        string  $entityType,
        ?string $entityId  = null,
        array   $payload   = [],
        ?string $actor     = null
    ): void {
        try {
            DB::insert('audit_log', [
                'actor'       => $actor ?? ($_SESSION['user_name'] ?? 'system'),
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'payload'     => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                'ip'          => self::ip(),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Never let audit failure break the main flow
        }
    }

    private static function ip(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return explode(',', $_SERVER[$key])[0];
            }
        }
        return 'unknown';
    }
}
