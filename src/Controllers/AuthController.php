<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\RateLimiter;
use App\Helpers\AuditLog;

class AuthController extends BaseController
{
    private const MAX_ATTEMPTS  = 5;
    private const WINDOW_SEC    = 300; // 5 minutes

    public function showLogin(array $params = []): void
    {
        if (!empty($_SESSION['user_id'])) $this->redirect('/');
        $csrf = $this->csrfToken();
        $this->render('auth/login', ['csrf' => $csrf, 'error' => null, 'remaining' => null], 'auth');
    }

    public function login(array $params = []): void
    {
        $this->verifyCsrf();

        $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);
        $rlKey    = 'login:' . $ip;

        // Rate limit check
        if (!RateLimiter::allow($rlKey, self::MAX_ATTEMPTS, self::WINDOW_SEC)) {
            $this->renderLoginError(
                'Too many failed attempts. Please wait 5 minutes before trying again.',
                0
            );
            return;
        }

        if (!$email || !$password) {
            $this->renderLoginError('Email and password are required.');
            return;
        }

        $user = DB::row('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            usleep(300000); // 300ms artificial delay
            AuditLog::record('login_failed', 'user', null, ['email' => $email], 'anonymous');
            $remaining = RateLimiter::remaining($rlKey, self::MAX_ATTEMPTS, self::WINDOW_SEC);
            $this->renderLoginError('Invalid email or password.', $remaining);
            return;
        }

        // Successful login — clear rate limit, regenerate session
        RateLimiter::clear($rlKey);
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];

        if ($remember) $this->setRememberCookie((string)$user['id']);

        AuditLog::record('login_success', 'user', $user['id'], [], $user['name']);

        $redirect = $_GET['redirect'] ?? '/';
        $redirect = filter_var($redirect, FILTER_SANITIZE_URL);
        if (!str_starts_with($redirect, '/')) $redirect = '/';

        $this->redirect($redirect, 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
    }

    public function logout(array $params = []): void
    {
        if (!empty($_COOKIE['rentops_remember'])) {
            [$selector] = explode(':', $_COOKIE['rentops_remember'], 2);
            DB::query('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
            setcookie('rentops_remember', '', time() - 3600, '/', '', false, true);
        }

        AuditLog::record('logout', 'user', $_SESSION['user_id'] ?? null);
        $_SESSION = [];
        session_destroy();
        $this->redirect('/login');
    }

    private function setRememberCookie(string $userId): void
    {
        $selector  = bin2hex(random_bytes(8));
        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires   = date('Y-m-d H:i:s', time() + 7 * 24 * 3600);

        DB::query(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE selector=VALUES(selector), token_hash=VALUES(token_hash), expires_at=VALUES(expires_at)',
            [$userId, $selector, $tokenHash, $expires]
        );

        setcookie('rentops_remember', "{$selector}:{$token}", time() + 7 * 24 * 3600, '/', '', false, true);
    }

    private function renderLoginError(string $error, ?int $remaining = null): void
    {
        $this->render('auth/login', [
            'csrf'      => $this->csrfToken(),
            'error'     => $error,
            'remaining' => $remaining,
        ], 'auth');
    }
}
