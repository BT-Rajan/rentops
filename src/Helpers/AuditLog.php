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
        // FIX B04: HTTP_X_FORWARDED_FOR is a client-controlled header — blindly
        // trusting it lets any user spoof their IP in the audit log.
        // Only use it when the app is explicitly configured to trust a reverse proxy
        // (TRUSTED_PROXY=1 in .env), and always validate the extracted IP format.
        $trustProxy = !empty($_ENV['TRUSTED_PROXY']);

        if ($trustProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For can be a comma-separated list; the left-most is the client
            $candidates = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            foreach ($candidates as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $candidate;
                }
            }
        }

        // Fall back to the direct connection IP (always trustworthy)
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
