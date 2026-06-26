<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * URL & asset helper.
 *
 * Reads APP_BASE from config (derived from APP_URL in .env).
 * APP_BASE = the subfolder path, e.g. "/rentops/public" or "" for root.
 *
 * Examples (.env APP_URL=http://localhost/rentops/public):
 *   url('/login')            → /rentops/public/login
 *   url('/tenants/abc-123')  → /rentops/public/tenants/abc-123
 *   asset('/css/main.css')   → /rentops/public/assets/css/main.css
 *
 * Examples (.env APP_URL=https://yourdomain.com):
 *   url('/login')            → /login
 *   asset('/css/main.css')   → /assets/css/main.css
 */
class UrlHelper
{
    private static string $base = '';
    private static bool   $init = false;

    public static function init(string $appUrl): void
    {
        if (self::$init) return;
        self::$init = true;
        $parsed     = parse_url(rtrim($appUrl, '/'));
        self::$base = $parsed['path'] ?? '';
        // Never double-slash
        self::$base = rtrim(self::$base, '/');
    }

    /**
     * Generate an app URL (for links, redirects, form actions).
     * $path must start with /
     */
    public static function url(string $path): string
    {
        return self::$base . '/' . ltrim($path, '/');
    }

    /**
     * Generate an asset URL (CSS, JS, images).
     * $path must start with /  (relative to public/)
     */
    public static function asset(string $path): string
    {
        return self::$base . '/' . ltrim($path, '/');
    }

    /**
     * Return just the base path (e.g. /rentops/public).
     */
    public static function base(): string
    {
        return self::$base ?: '/';
    }
}
