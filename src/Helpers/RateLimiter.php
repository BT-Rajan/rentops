<?php
declare(strict_types=1);

namespace App\Helpers;

use App\DB;

/**
 * Simple token-bucket rate limiter backed by MySQL.
 * Used for login and any sensitive POST endpoints.
 */
class RateLimiter
{
    /**
     * Check if an action is allowed for a given key.
     * Returns true if allowed, false if limit exceeded.
     *
     * FIX B01: Hit is recorded ONLY when the request is within the limit.
     * Previously the hit was always inserted after the check, giving attackers
     * maxHits+1 attempts before lockout (e.g. 6 instead of 5 login tries).
     *
     * @param string $key       e.g. 'login:127.0.0.1'
     * @param int    $maxHits   max attempts allowed
     * @param int    $windowSec rolling window in seconds
     */
    public static function allow(string $key, int $maxHits = 5, int $windowSec = 300): bool
    {
        self::ensureTable();

        $window = date('Y-m-d H:i:s', time() - $windowSec);

        // Count recent hits within the rolling window
        $hits = (int)DB::scalar(
            "SELECT COUNT(*) FROM rate_limits WHERE `key` = ? AND created_at > ?",
            [$key, $window]
        );

        // Reject before recording — do NOT insert a hit when already at the limit
        if ($hits >= $maxHits) return false;

        // Record this hit only when the request is permitted
        DB::query(
            "INSERT INTO rate_limits (`key`, created_at) VALUES (?, NOW())",
            [$key]
        );

        // Prune old records (1% of the time to avoid overhead)
        if (mt_rand(1, 100) === 1) {
            DB::query("DELETE FROM rate_limits WHERE created_at < ?", [
                date('Y-m-d H:i:s', time() - 3600)
            ]);
        }

        return true;
    }

    /**
     * Clear all hits for a key (e.g. on successful login).
     */
    public static function clear(string $key): void
    {
        DB::query("DELETE FROM rate_limits WHERE `key` = ?", [$key]);
    }

    /**
     * Remaining attempts before lockout.
     */
    public static function remaining(string $key, int $maxHits = 5, int $windowSec = 300): int
    {
        $window = date('Y-m-d H:i:s', time() - $windowSec);
        $hits   = (int)DB::scalar(
            "SELECT COUNT(*) FROM rate_limits WHERE `key` = ? AND created_at > ?",
            [$key, $window]
        );
        return max(0, $maxHits - $hits);
    }

    private static bool $tableChecked = false;

    private static function ensureTable(): void
    {
        if (self::$tableChecked) return;
        self::$tableChecked = true;
        // Table created via migration 004
    }
}
