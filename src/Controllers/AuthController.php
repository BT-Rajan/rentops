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

    // ── Forgot password ────────────────────────────────────────────────────────

    public function showForgotPassword(array $params = []): void
    {
        if (!empty($_SESSION['user_id'])) $this->redirect('/');
        $this->render('auth/forgot_password', [
            'csrf'    => $this->csrfToken(),
            'error'   => null,
            'success' => null,
        ], 'auth');
    }

    public function forgotPassword(array $params = []): void
    {
        $this->verifyCsrf();

        $email = trim($_POST['email'] ?? '');
        $csrf  = $this->csrfToken();

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('auth/forgot_password', [
                'csrf'    => $csrf,
                'error'   => 'Please enter a valid email address.',
                'success' => null,
            ], 'auth');
            return;
        }

        // Same message whether email exists or not — prevents user enumeration
        $success = 'If that email is registered, a reset link has been sent. Check your inbox.';

        $user = DB::row('SELECT id, name FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($user) {
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires   = date('Y-m-d H:i:s', time() + 1800); // 30 min

            DB::query(
                "DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL",
                [$user['id']]
            );

            DB::insert('password_reset_tokens', [
                'user_id'    => $user['id'],
                'token_hash' => $tokenHash,
                'expires_at' => $expires,
            ]);

            $resetUrl = url('/reset-password') . '?token=' . urlencode($token);
            $this->sendResetEmail($email, $user['name'], $resetUrl);

            AuditLog::record('password_reset_requested', 'user', $user['id'], ['email' => $email]);
        }

        $this->render('auth/forgot_password', [
            'csrf'    => $csrf,
            'error'   => null,
            'success' => $success,
        ], 'auth');
    }

    public function showResetPassword(array $params = []): void
    {
        if (!empty($_SESSION['user_id'])) $this->redirect('/');

        $token = $_GET['token'] ?? '';
        $csrf  = $this->csrfToken();

        if (!$token) {
            $this->render('auth/reset_password', [
                'csrf' => $csrf, 'token' => '', 'error' => null, 'expired' => true,
            ], 'auth');
            return;
        }

        $row = DB::row(
            "SELECT id FROM password_reset_tokens
             WHERE token_hash = ? AND expires_at > NOW() AND used_at IS NULL LIMIT 1",
            [hash('sha256', $token)]
        );

        $this->render('auth/reset_password', [
            'csrf' => $csrf, 'token' => $token, 'error' => null, 'expired' => !$row,
        ], 'auth');
    }

    public function resetPassword(array $params = []): void
    {
        $this->verifyCsrf();

        $token    = $_POST['token']            ?? '';
        $password = $_POST['password']         ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        $csrf     = $this->csrfToken();

        $row     = null;
        $expired = !$token;

        if (!$expired) {
            $row = DB::row(
                "SELECT * FROM password_reset_tokens
                 WHERE token_hash = ? AND expires_at > NOW() AND used_at IS NULL LIMIT 1",
                [hash('sha256', $token)]
            );
            if (!$row) $expired = true;
        }

        if ($expired) {
            $this->render('auth/reset_password', [
                'csrf' => $csrf, 'token' => $token, 'error' => null, 'expired' => true,
            ], 'auth');
            return;
        }

        if (strlen($password) < 8) {
            $this->render('auth/reset_password', [
                'csrf' => $csrf, 'token' => $token, 'expired' => false,
                'error' => 'Password must be at least 8 characters.',
            ], 'auth');
            return;
        }

        if ($password !== $confirm) {
            $this->render('auth/reset_password', [
                'csrf' => $csrf, 'token' => $token, 'expired' => false,
                'error' => 'Passwords do not match.',
            ], 'auth');
            return;
        }

        DB::update('users', ['password_hash' => password_hash($password, PASSWORD_BCRYPT)], 'id = ?', [$row['user_id']]);
        DB::query("UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = ?", [hash('sha256', $token)]);
        DB::query("DELETE FROM remember_tokens WHERE user_id = ?", [$row['user_id']]);

        AuditLog::record('password_reset_success', 'user', $row['user_id']);

        $this->redirect('/login', 'Password updated. Please sign in with your new password.');
    }

    private function sendResetEmail(string $to, string $name, string $resetUrl): void
    {
        $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';

        if (!class_exists($mailerClass)) {
            error_log("[RentOps] Password reset link for {$to}: {$resetUrl}");
            return;
        }

        try {
            $mail = new $mailerClass(true);
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST']       ?? 'localhost';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USER']       ?? '';
            $mail->Password   = $_ENV['MAIL_PASS']       ?? '';
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
            $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);
            $mail->setFrom($_ENV['MAIL_FROM'] ?? 'noreply@rentops.local', 'RentOps');
            $mail->addAddress($to, $name);
            $mail->Subject  = 'Reset your RentOps password';
            $mail->isHTML(true);
            $mail->Body     = "<p>Hi {$name},</p><p>Click to reset your password (expires in 30 minutes):</p>"
                            . "<p><a href=\"{$resetUrl}\">{$resetUrl}</a></p><p>— RentOps</p>";
            $mail->AltBody  = "Reset your password: {$resetUrl}\n\nExpires in 30 minutes.";
            $mail->send();
        } catch (\Throwable $e) {
            error_log("[RentOps] Reset email failed for {$to}: " . $e->getMessage());
            error_log("[RentOps] Reset link (manual): {$resetUrl}");
        }
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
