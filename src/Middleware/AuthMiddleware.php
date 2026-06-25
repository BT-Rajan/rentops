<?php
declare(strict_types=1);

namespace App\Middleware;

class AuthMiddleware
{
    public function handle(): void
    {
        // Check session
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
                $_SESSION['user_name'] = \App\DB::scalar('SELECT name FROM users WHERE id = ?', [$row['user_id']]);
                return;
            }
            // Invalid cookie — clear it
            setcookie('rentops_remember', '', time() - 3600, '/', '', false, true);
        }

        // Redirect to login
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header("Location: /login?redirect={$redirect}");
        exit;
    }
}
