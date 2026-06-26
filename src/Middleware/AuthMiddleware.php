<?php
declare(strict_types=1);

namespace App\Middleware;

class AuthMiddleware
{
    public function handle(): void
    {
        if (!empty($_SESSION['user_id'])) return;

        // Check remember-me cookie
        if (!empty($_COOKIE['rentops_remember'])) {
            $cookieValue = $_COOKIE['rentops_remember'];

            // FIX B16: The previous code used `explode(...) + ['', '']` (array union)
            // as a fallback. This is syntactically valid but silently passes empty
            // strings to the DB query when the cookie is malformed (e.g. missing ':').
            // A blank selector hits the DB unnecessarily and could match unexpected rows.
            // Validate the format explicitly before destructuring.
            if (substr_count($cookieValue, ':') < 1) {
                // Malformed cookie — clear it and fall through to login redirect
                $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                setcookie('rentops_remember', '', time() - 3600, '/', '', $secure, true);
            } else {
                [$selector, $token] = explode(':', $cookieValue, 2);

                $row = \App\DB::row(
                    'SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()',
                    [$selector]
                );
                if ($row && hash_equals($row['token_hash'], hash('sha256', $token))) {
                    $_SESSION['user_id']   = $row['user_id'];
                    $_SESSION['user_name'] = \App\DB::scalar(
                        'SELECT name FROM users WHERE id = ?', [$row['user_id']]
                    );
                    return;
                }
                // Token not found or hash mismatch — clear the stale cookie
                $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                setcookie('rentops_remember', '', time() - 3600, '/', '', $secure, true);
            }
        }

        // Redirect preserving the requested path (stripped of base) for post-login return
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $base       = rtrim(\App\Helpers\UrlHelper::base(), '/');
        $stripped   = $base !== '' && str_starts_with($requestUri, $base)
            ? substr($requestUri, strlen($base))
            : $requestUri;

        $redirect = urlencode($stripped ?: '/');
        header('Location: ' . url('/login') . '?redirect=' . $redirect);
        exit;
    }
}
