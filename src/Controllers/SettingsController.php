<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\AuditLog;

class SettingsController extends BaseController
{
    public function index(array $params = []): void
    {
        $property = DB::row('SELECT * FROM properties LIMIT 1');
        $this->render('settings/index', [
            'pageTitle' => 'Settings',
            'property'  => $property,
            'csrf'      => $this->csrfToken(),
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
        ]);
    }

    public function update(array $params = []): void
    {
        $this->verifyCsrf();
        $property = DB::row('SELECT * FROM properties LIMIT 1');
        if (!$property) {
            $this->redirect('/settings', 'No property configured.', 'error');
            return;
        }

        DB::update('properties', [
            'name'            => trim($_POST['name']             ?? $property['name']),
            'address'         => trim($_POST['address']          ?? $property['address']),
            'default_due_day' => (int)($_POST['default_due_day'] ?? $property['default_due_day']),
        ], 'id = ?', [$property['id']]);

        AuditLog::record('settings_updated', 'property', $property['id']);
        $this->redirect('/settings', 'Settings saved.');
    }

    public function changePassword(array $params = []): void
    {
        $this->verifyCsrf();

        $userId  = $_SESSION['user_id'] ?? null;
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$userId) {
            $this->redirect('/settings', 'Not authenticated.', 'error');
            return;
        }

        // Validation
        if (!$current || !$new || !$confirm) {
            $this->redirect('/settings', 'All password fields are required.', 'error');
            return;
        }

        if ($new !== $confirm) {
            $this->redirect('/settings', 'New passwords do not match.', 'error');
            return;
        }

        if (strlen($new) < 8) {
            $this->redirect('/settings', 'New password must be at least 8 characters.', 'error');
            return;
        }

        // Strength: require at least one letter and one number
        if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
            $this->redirect('/settings', 'Password must contain at least one letter and one number.', 'error');
            return;
        }

        $user = DB::row('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$user || !password_verify($current, $user['password_hash'])) {
            $this->redirect('/settings', 'Current password is incorrect.', 'error');
            return;
        }

        $hash = password_hash($new, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ]);

        DB::update('users', ['password_hash' => $hash], 'id = ?', [$userId]);

        // Invalidate all remember-me tokens for this user
        DB::query('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
        if (!empty($_COOKIE['rentops_remember'])) {
            setcookie('rentops_remember', '', time() - 3600, '/', '', false, true);
        }

        AuditLog::record('password_changed', 'user', $userId);
        $this->redirect('/settings', 'Password changed successfully. All saved sessions cleared.');
    }
    public function updateBilling(array $params = []): void
    {
        $this->verifyCsrf();
        $property = DB::row('SELECT * FROM properties LIMIT 1');
        if (!$property) { $this->redirect('/settings', 'No property found.', 'error'); return; }

        DB::update('properties', [
            'eb_unit_price' => (float)($_POST['eb_unit_price'] ?? 0),
            'rent_gst_rate' => min(100, max(0, (float)($_POST['rent_gst_rate'] ?? 18))),
        ], 'id = ?', [$property['id']]);

        AuditLog::record('billing_settings_updated', 'property', $property['id']);
        $this->redirect('/settings', 'Billing settings saved.');
    }

    public function updateRazorpay(array $params = []): void
    {
        $this->verifyCsrf();
        $property = DB::row('SELECT * FROM properties LIMIT 1');
        if (!$property) { $this->redirect('/settings', 'No property found.', 'error'); return; }

        $keyId  = trim($_POST['razorpay_key_id']     ?? '');
        $secret = trim($_POST['razorpay_key_secret'] ?? '');

        $update = ['razorpay_key_id' => $keyId ?: null];

        if ($secret) {
            // Encrypt secret with AES-256-CBC
            $encKey = base64_decode($_ENV['ENCRYPT_KEY'] ?? '');
            if ($encKey) {
                $iv       = random_bytes(16);
                $cipher   = openssl_encrypt($secret, 'AES-256-CBC', $encKey, OPENSSL_RAW_DATA, $iv);
                $update['razorpay_key_secret'] = base64_encode($iv . $cipher);
            } else {
                $update['razorpay_key_secret'] = $secret; // plain fallback if no ENCRYPT_KEY
            }
        }

        DB::update('properties', $update, 'id = ?', [$property['id']]);
        AuditLog::record('razorpay_settings_updated', 'property', $property['id']);
        $this->redirect('/settings', 'Razorpay keys saved.');
    }

}
