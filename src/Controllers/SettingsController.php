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

        $oldEbPrice = (float)($property['eb_unit_price'] ?? 0);
        $oldGstRate = (float)($property['rent_gst_rate'] ?? 18);
        $newEbPrice = (float)($_POST['eb_unit_price'] ?? 0);
        $newGstRate = min(100, max(0, (float)($_POST['rent_gst_rate'] ?? 18)));

        $changed = abs($oldEbPrice - $newEbPrice) > 0.001 || abs($oldGstRate - $newGstRate) > 0.001;

        DB::update('properties', [
            'eb_unit_price' => $newEbPrice,
            'rent_gst_rate' => $newGstRate,
        ], 'id = ?', [$property['id']]);

        AuditLog::record('billing_settings_updated', 'property', $property['id'], [
            'old_eb_unit_price' => $oldEbPrice, 'new_eb_unit_price' => $newEbPrice,
            'old_gst_rate'      => $oldGstRate, 'new_gst_rate'      => $newGstRate,
        ]);

        // FIX B-flow-11: this change is forward-only — existing invoices keep
        // whatever EB rate / GST they were generated with. Say so explicitly
        // instead of letting the landlord assume (reasonably) that correcting
        // a typo'd rate also fixes invoices already sitting unpaid.
        $msg = 'Billing settings saved.';
        if ($changed) {
            $unpaidCount = (int)DB::scalar("
                SELECT COUNT(*) FROM rent_invoices WHERE status IN ('unpaid','partial','overdue')
            ");
            if ($unpaidCount > 0) {
                $msg .= " This rate applies to invoices generated from now on — "
                       . "{$unpaidCount} existing unpaid invoice(s) keep the rate they were originally billed at "
                       . "and will not be recalculated automatically.";
            }
        }

        $this->redirect('/settings', $msg);
    }

    public function updateRazorpay(array $params = []): void
    {
        $this->verifyCsrf();
        $property = DB::row('SELECT * FROM properties LIMIT 1');
        if (!$property) { $this->redirect('/settings', 'No property found.', 'error'); return; }

        $keyId          = trim($_POST['razorpay_key_id']         ?? '');
        $secret         = trim($_POST['razorpay_key_secret']     ?? '');
        $webhookSecret  = trim($_POST['razorpay_webhook_secret'] ?? '');

        $update = ['razorpay_key_id' => $keyId ?: null];

        if ($secret) {
            $update['razorpay_key_secret'] = \App\Helpers\Crypto::encrypt($secret);
        }
        if ($webhookSecret) {
            $update['razorpay_webhook_secret'] = \App\Helpers\Crypto::encrypt($webhookSecret);
        }

        DB::update('properties', $update, 'id = ?', [$property['id']]);
        AuditLog::record('razorpay_settings_updated', 'property', $property['id']);
        $this->redirect('/settings', 'Razorpay keys saved.');
    }

}
