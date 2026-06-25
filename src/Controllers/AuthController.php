<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;

class AuthController extends BaseController
{
    public function showLogin(array $params = []): void
    {
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/');
        }
        $csrf = $this->csrfToken();
        $this->render('auth/login', ['csrf' => $csrf, 'error' => null], 'auth');
    }

    public function login(array $params = []): void
    {
        $this->verifyCsrf();

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (!$email || !$password) {
            $this->renderLoginError('Email and password are required.');
            return;
        }

        $user = DB::row('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Intentional delay to slow brute-force
            usleep(300000);
            $this->renderLoginError('Invalid email or password.');
            return;
        }

        // Regenerate session ID on login
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];

        if ($remember) {
            $this->setRememberCookie((string) $user['id']);
        }

        $redirect = $_GET['redirect'] ?? '/';
        $redirect = filter_var($redirect, FILTER_SANITIZE_URL);
        if (!str_starts_with($redirect, '/')) $redirect = '/';

        $this->redirect($redirect, 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
    }

    public function logout(array $params = []): void
    {
        // Clear remember-me token from DB
        if (!empty($_COOKIE['rentops_remember'])) {
            [$selector] = explode(':', $_COOKIE['rentops_remember'], 2);
            DB::query('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
            setcookie('rentops_remember', '', time() - 3600, '/', '', false, true);
        }

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

        setcookie(
            'rentops_remember',
            "{$selector}:{$token}",
            time() + 7 * 24 * 3600,
            '/',
            '',
            false,
            true
        );
    }

    private function renderLoginError(string $error): void
    {
        $csrf = $this->csrfToken();
        $this->render('auth/login', ['csrf' => $csrf, 'error' => $error], 'auth');
    }
}
