#!/usr/bin/env php
<?php
/**
 * RentOps — patch_login_and_forgot_password.php
 *
 * Fixes:
 *  1. public/.htaccess — adds RewriteBase so subfolder installs route POST /login correctly
 *  2. views/auth/login.php — adds "Forgot password?" link below the form
 *  3. src/Controllers/AuthController.php — adds showForgotPassword, forgotPassword,
 *     showResetPassword, resetPassword methods
 *  4. src/routes.php — registers the four new forgot/reset routes
 *  5. Runs migration 005 (password_reset_tokens table) if DB creds are in .env
 *
 * Usage:
 *   php patch_login_and_forgot_password.php [--dry-run]
 *
 * Safe to re-run — every change is idempotent (checks before applying).
 */

define('ROOT', __DIR__);
$dry = in_array('--dry-run', $argv ?? [], true);

/* ─── helpers ──────────────────────────────────────────────────────────────── */
function patch(string $file, string $search, string $replace, string $label): void
{
    global $dry;
    $path = ROOT . '/' . $file;
    if (!file_exists($path)) { echo "  [SKIP] $label — file not found: $file\n"; return; }

    $content = file_get_contents($path);
    if (str_contains($content, $replace)) { echo "  [OK]   $label — already applied\n"; return; }
    if (!str_contains($content, $search))  { echo "  [WARN] $label — anchor not found in $file\n"; return; }

    $new = str_replace($search, $replace, $content);
    if ($dry) { echo "  [DRY]  $label — would patch $file\n"; return; }
    file_put_contents($path, $new);
    echo "  [DONE] $label\n";
}

function append_before(string $file, string $anchor, string $insertion, string $label): void
{
    global $dry;
    $path = ROOT . '/' . $file;
    if (!file_exists($path)) { echo "  [SKIP] $label — file not found: $file\n"; return; }

    $content = file_get_contents($path);

    // FIX B13: The old guard checked str_contains($content, $insertion) which
    // compares the ENTIRE insertion block against the file. If the committed code
    // differs by even a single character (whitespace, a comment edit), the check
    // fails and the block is inserted again, creating duplicate methods and a PHP
    // fatal error. Instead, extract and check only the first non-empty line of the
    // insertion as a short unique sentinel — much less likely to drift.
    $sentinelLine = '';
    foreach (explode("\n", $insertion) as $line) {
        $trimmed = trim($line);
        if ($trimmed !== '') { $sentinelLine = $trimmed; break; }
    }
    $alreadyApplied = $sentinelLine !== '' && str_contains($content, $sentinelLine);

    if ($alreadyApplied) { echo "  [OK]   $label — already applied\n"; return; }
    if (!str_contains($content, $anchor)) { echo "  [WARN] $label — anchor not found in $file\n"; return; }

    $new = str_replace($anchor, $insertion . $anchor, $content);
    if ($dry) { echo "  [DRY]  $label — would patch $file\n"; return; }
    file_put_contents($path, $new);
    echo "  [DONE] $label\n";
}

function append_after(string $file, string $anchor, string $insertion, string $label): void
{
    global $dry;
    $path = ROOT . '/' . $file;
    if (!file_exists($path)) { echo "  [SKIP] $label — file not found: $file\n"; return; }

    $content = file_get_contents($path);

    // FIX B13 (same fix as append_before): use first non-empty line as sentinel
    $sentinelLine = '';
    foreach (explode("\n", $insertion) as $line) {
        $trimmed = trim($line);
        if ($trimmed !== '') { $sentinelLine = $trimmed; break; }
    }
    $alreadyApplied = $sentinelLine !== '' && str_contains($content, $sentinelLine);

    if ($alreadyApplied) { echo "  [OK]   $label — already applied\n"; return; }
    if (!str_contains($content, $anchor)) { echo "  [WARN] $label — anchor not found in $file\n"; return; }

    $new = str_replace($anchor, $anchor . $insertion, $content);
    if ($dry) { echo "  [DRY]  $label — would patch $file\n"; return; }
    file_put_contents($path, $new);
    echo "  [DONE] $label\n";
}

/* ─── 1. .htaccess — add RewriteBase ──────────────────────────────────────── */
echo "\n[1/5] .htaccess — RewriteBase\n";

// Read APP_URL from .env to derive the base path
$envPath = ROOT . '/.env';
$appBase = '';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), 'APP_URL=')) {
            $url     = trim(explode('=', $line, 2)[1], " \t\"'");
            $parsed  = parse_url(rtrim($url, '/'));
            $appBase = rtrim($parsed['path'] ?? '', '/');
            break;
        }
    }
}

$rewriteBase = $appBase !== '' ? $appBase . '/' : '/';

patch(
    'public/.htaccess',
    "RewriteEngine On\n",
    "RewriteEngine On\nRewriteBase {$rewriteBase}\n",
    "Add RewriteBase {$rewriteBase}"
);

/* ─── 2. login view — forgot-password link ─────────────────────────────────── */
echo "\n[2/5] Login view — forgot-password link\n";

append_after(
    'views/auth/login.php',
    '<button type="submit" class="btn btn-primary w-full" style="justify-content:center;padding:11px">Sign in</button>',
    "\n\n  <p style=\"text-align:center;margin-top:16px;font-size:13px;color:var(--text-secondary)\">\n    <a href=\"<?= url('/forgot-password') ?>\" style=\"color:var(--c-primary)\">Forgot your password?</a>\n  </p>",
    'Add Forgot password link'
);

/* ─── 3. AuthController — forgot/reset methods ─────────────────────────────── */
echo "\n[3/5] AuthController — forgot/reset methods\n";

$newMethods = <<<'PHP'

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

        // Always show the same message to prevent user enumeration
        $success = 'If that email is registered, a reset link has been sent. Check your inbox.';

        $user = DB::row('SELECT id, name FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($user) {
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires   = date('Y-m-d H:i:s', time() + 1800); // 30 minutes

            // Invalidate any existing unused tokens for this user
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

            // Attempt to send email via PHPMailer if configured, else log the link
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
                'csrf'    => $csrf,
                'token'   => '',
                'error'   => null,
                'expired' => true,
            ], 'auth');
            return;
        }

        $tokenHash = hash('sha256', $token);
        $row = DB::row(
            "SELECT * FROM password_reset_tokens
             WHERE token_hash = ? AND expires_at > NOW() AND used_at IS NULL
             LIMIT 1",
            [$tokenHash]
        );

        $this->render('auth/reset_password', [
            'csrf'    => $csrf,
            'token'   => $token,
            'error'   => null,
            'expired' => !$row,
        ], 'auth');
    }

    public function resetPassword(array $params = []): void
    {
        $this->verifyCsrf();

        $token    = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        $csrf     = $this->csrfToken();

        $expired = false;

        if (!$token) { $expired = true; }

        if (!$expired) {
            $tokenHash = hash('sha256', $token);
            $row = DB::row(
                "SELECT * FROM password_reset_tokens
                 WHERE token_hash = ? AND expires_at > NOW() AND used_at IS NULL
                 LIMIT 1",
                [$tokenHash]
            );
            if (!$row) { $expired = true; }
        }

        if ($expired) {
            $this->render('auth/reset_password', [
                'csrf'    => $csrf,
                'token'   => $token,
                'error'   => null,
                'expired' => true,
            ], 'auth');
            return;
        }

        // Validate password
        if (strlen($password) < 8) {
            $this->render('auth/reset_password', [
                'csrf'    => $csrf,
                'token'   => $token,
                'error'   => 'Password must be at least 8 characters.',
                'expired' => false,
            ], 'auth');
            return;
        }

        if ($password !== $confirm) {
            $this->render('auth/reset_password', [
                'csrf'    => $csrf,
                'token'   => $token,
                'error'   => 'Passwords do not match.',
                'expired' => false,
            ], 'auth');
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        DB::update('users', ['password_hash' => $hash], 'id = ?', [$row['user_id']]);
        DB::query(
            "UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = ?",
            [$tokenHash]
        );

        // Invalidate all active sessions for security (clear remember tokens too)
        DB::query("DELETE FROM remember_tokens WHERE user_id = ?", [$row['user_id']]);

        AuditLog::record('password_reset_success', 'user', $row['user_id']);

        $this->redirect('/login', 'Password updated. Please sign in with your new password.');
    }

    private function sendResetEmail(string $to, string $name, string $resetUrl): void
    {
        $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';

        // If PHPMailer is not installed, log the link and return
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

            $mail->Subject = 'Reset your RentOps password';
            $mail->isHTML(true);
            $mail->Body = "
                <p>Hi {$name},</p>
                <p>Click the link below to reset your password. This link expires in 30 minutes.</p>
                <p><a href=\"{$resetUrl}\">{$resetUrl}</a></p>
                <p>If you did not request a password reset, you can safely ignore this email.</p>
                <p>— RentOps</p>
            ";
            $mail->AltBody = "Reset your password: {$resetUrl}\n\nLink expires in 30 minutes.";

            $mail->send();
        } catch (\Throwable $e) {
            error_log("[RentOps] Failed to send reset email to {$to}: " . $e->getMessage());
            error_log("[RentOps] Reset link (manual): {$resetUrl}");
        }
    }

PHP;

append_before(
    'src/Controllers/AuthController.php',
    '    private function setRememberCookie',
    $newMethods,
    'Add showForgotPassword / forgotPassword / showResetPassword / resetPassword'
);

/* ─── 4. routes.php — register forgot/reset routes ─────────────────────────── */
echo "\n[4/5] routes.php — forgot/reset routes\n";

$newRoutes = <<<'PHP'
$router->get('/forgot-password',  [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('/reset-password',   [AuthController::class, 'showResetPassword']);
$router->post('/reset-password',  [AuthController::class, 'resetPassword']);

PHP;

append_after(
    'src/routes.php',
    "\$router->post('/logout',        [AuthController::class, 'logout']);\n",
    $newRoutes,
    'Add forgot/reset routes after logout'
);

/* ─── 5. DB migration 005 ───────────────────────────────────────────────────── */
echo "\n[5/5] Database migration 005 — password_reset_tokens\n";

$migrationFile = ROOT . '/database/migrations/005_password_reset.sql';
if (!file_exists($migrationFile)) {
    echo "  [SKIP] Migration file not found — create it first\n";
} else {
    // Load .env to get DB creds
    $env = [];
    if (file_exists($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            $env[trim($k)] = trim($v, " \t\"'");
        }
    }

    $host   = $env['DB_HOST'] ?? 'localhost';
    $port   = $env['DB_PORT'] ?? '3306';
    $dbname = $env['DB_NAME'] ?? 'rentops';
    $user   = $env['DB_USER'] ?? 'root';
    $pass   = $env['DB_PASS'] ?? '';

    if ($dry) {
        echo "  [DRY]  Would run migration 005 on {$dbname}@{$host}\n";
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
                $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Check if table already exists
            $exists = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'password_reset_tokens'"
            )->fetchColumn();

            if ($exists) {
                echo "  [OK]   password_reset_tokens table already exists\n";
            } else {
                $sql = file_get_contents($migrationFile);
                $pdo->exec($sql);
                echo "  [DONE] Migration 005 applied — password_reset_tokens created\n";
            }
        } catch (PDOException $e) {
            echo "  [WARN] Could not run migration: " . $e->getMessage() . "\n";
            echo "         Run manually: mysql -u{$user} -p {$dbname} < database/migrations/005_password_reset.sql\n";
        }
    }
}

echo "\n✓ Patch complete.\n";
echo "\nPost-run checklist:\n";
echo "  □ Verify APP_URL in .env matches your actual server path\n";
echo "  □ Test: GET /login loads correctly\n";
echo "  □ Test: POST /login with correct credentials redirects to dashboard\n";
echo "  □ Test: GET /forgot-password shows the form\n";
echo "  □ Test: POST /forgot-password with a valid email — check PHP error_log for reset link\n";
echo "    (PHPMailer email is optional — link always logs to error_log as fallback)\n";
echo "  □ Test: reset link from log → set new password → sign in\n";
echo "  □ Add MAIL_HOST / MAIL_USER / MAIL_PASS / MAIL_PORT / MAIL_FROM to .env when ready\n";
