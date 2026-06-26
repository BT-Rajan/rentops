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
            [$selector, $token] = explode(':', $_COOKIE['rentops_remember'], 2) + ['', ''];
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
            setcookie('rentops_remember', '', time() - 3600, '/', '', false, true);
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
