<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\AuditLog;
use App\Helpers\ReminderSender;

class ReminderController extends BaseController
{
    // ─── Compose + History page ───────────────────────────────────────────────

    public function index(array $params = []): void
    {
        $tenants = DB::rows("
            SELECT t.id, t.full_name, t.phone, t.email, r.room_number,
                   te.agreed_rent,
                   COALESCE(SUM(CASE WHEN ri.status != 'paid'
                                     THEN ri.amount_due - ri.amount_paid ELSE 0 END), 0) AS balance
            FROM tenants t
            JOIN tenancies te  ON te.tenant_id = t.id AND te.status = 'active'
            JOIN rooms r       ON r.id = te.room_id
            LEFT JOIN rent_invoices ri ON ri.tenancy_id = te.id
            WHERE t.status = 'active'
            GROUP BY t.id, t.full_name, t.phone, t.email, r.room_number, te.agreed_rent
            ORDER BY r.room_number
        ");

        $history = DB::rows("
            SELECT * FROM reminder_logs
            ORDER BY created_at DESC
            LIMIT 100
        ");

        $this->render('reminders/index', [
            'pageTitle' => 'Reminders',
            'tenants'   => $tenants,
            'history'   => $history,
            'flash'     => $this->flash(),
            'csrf'      => $this->csrfToken(),
            'user'      => $this->currentUser(),
        ]);
    }

    // ─── Send / Schedule ──────────────────────────────────────────────────────

    public function send(array $params = []): void
    {
        $this->verifyCsrf();

        $channel      = $_POST['channel']      ?? '';
        $recipientType = $_POST['recipient_type'] ?? 'selected';
        $message      = trim($_POST['message']  ?? '');
        $subject      = trim($_POST['subject']  ?? 'Rent Reminder');
        $scheduleAt   = trim($_POST['schedule_at'] ?? '');
        $tenantIds    = $_POST['tenant_ids']    ?? [];

        if (!in_array($channel, ['sms', 'email', 'whatsapp'], true)) {
            $this->json(['ok' => false, 'error' => 'Invalid channel'], 422);
        }
        if (!$message) {
            $this->json(['ok' => false, 'error' => 'Message is required'], 422);
        }

        // Build recipients list
        $recipients = $this->buildRecipients($recipientType, $tenantIds);
        if (empty($recipients)) {
            $this->json(['ok' => false, 'error' => 'No recipients found'], 422);
        }

        // Handle optional attachment
        $attachPath = null;
        if (!empty($_FILES['attachment']['tmp_name'])) {
            $attachPath = $this->storeAttachment($_FILES['attachment']);
        }

        $logId  = $this->uuid();
        $status = $scheduleAt ? 'scheduled' : 'queued';

        DB::insert('reminder_logs', [
            'id'             => $logId,
            'channel'        => $channel,
            'recipient_type' => $recipientType,
            'recipients'     => json_encode($recipients, JSON_UNESCAPED_UNICODE),
            'subject'        => $subject ?: null,
            'message'        => $message,
            'scheduled_at'   => $scheduleAt ?: null,
            'status'         => $status,
            'attachment_path'=> $attachPath,
            'created_by'     => $this->currentUser()['name'],
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        if (!$scheduleAt) {
            // Send immediately
            $result = ReminderSender::dispatch($channel, $recipients, $message, $subject, $attachPath);

            DB::update('reminder_logs', [
                'status'     => $result['failed'] === count($recipients) ? 'failed' : 'sent',
                'sent_at'    => date('Y-m-d H:i:s'),
                'sent_count' => $result['sent'],
                'fail_count' => $result['failed'],
                'error_log'  => $result['errors'] ? implode("\n", $result['errors']) : null,
            ], 'id = ?', [$logId]);

            AuditLog::record('reminder_sent', 'reminder_logs', $logId, [
                'channel'    => $channel,
                'recipients' => count($recipients),
                'sent'       => $result['sent'],
            ]);

            // For WhatsApp without WABA token, return wa.me links for manual open
            $waLinks = [];
            if ($channel === 'whatsapp' && !($_ENV['WABA_TOKEN'] ?? '')) {
                foreach ($recipients as $r) {
                    $waLinks[] = [
                        'name' => $r['name'],
                        'url'  => ReminderSender::waLink($r['phone'], $message),
                    ];
                }
            }

            $this->json([
                'ok'      => true,
                'sent'    => $result['sent'],
                'failed'  => $result['failed'],
                'errors'  => $result['errors'],
                'waLinks' => $waLinks,
                'logId'   => $logId,
            ]);
        } else {
            AuditLog::record('reminder_scheduled', 'reminder_logs', $logId, [
                'channel'      => $channel,
                'scheduled_at' => $scheduleAt,
                'recipients'   => count($recipients),
            ]);
            $this->json(['ok' => true, 'scheduled' => true, 'logId' => $logId, 'at' => $scheduleAt]);
        }
    }

    // ─── Resend ───────────────────────────────────────────────────────────────

    public function resend(array $params = []): void
    {
        $this->verifyCsrf();

        $log = DB::row('SELECT * FROM reminder_logs WHERE id = ?', [$params['id']]);
        if (!$log) { $this->json(['ok' => false, 'error' => 'Not found'], 404); }

        $recipients = json_decode($log['recipients'], true);
        $message    = trim($_POST['message'] ?? $log['message']);
        $subject    = trim($_POST['subject'] ?? $log['subject'] ?? 'Rent Reminder');

        // New attachment overrides old, or keep original if not re-uploaded
        $attachPath = $log['attachment_path'];
        if (!empty($_FILES['attachment']['tmp_name'])) {
            $attachPath = $this->storeAttachment($_FILES['attachment']);
        } elseif (isset($_POST['remove_attachment']) && $_POST['remove_attachment'] === '1') {
            $attachPath = null;
        }

        $newLogId = $this->uuid();
        DB::insert('reminder_logs', [
            'id'             => $newLogId,
            'channel'        => $log['channel'],
            'recipient_type' => $log['recipient_type'],
            'recipients'     => $log['recipients'],
            'subject'        => $subject,
            'message'        => $message,
            'scheduled_at'   => null,
            'status'         => 'queued',
            'attachment_path'=> $attachPath,
            'created_by'     => $this->currentUser()['name'],
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        $result = ReminderSender::dispatch($log['channel'], $recipients, $message, $subject, $attachPath);

        DB::update('reminder_logs', [
            'status'     => $result['failed'] === count($recipients) ? 'failed' : 'sent',
            'sent_at'    => date('Y-m-d H:i:s'),
            'sent_count' => $result['sent'],
            'fail_count' => $result['failed'],
            'error_log'  => $result['errors'] ? implode("\n", $result['errors']) : null,
        ], 'id = ?', [$newLogId]);

        AuditLog::record('reminder_resent', 'reminder_logs', $newLogId, ['original_id' => $params['id']]);

        $waLinks = [];
        if ($log['channel'] === 'whatsapp' && !($_ENV['WABA_TOKEN'] ?? '')) {
            foreach ($recipients as $r) {
                $waLinks[] = ['name' => $r['name'], 'url' => ReminderSender::waLink($r['phone'], $message)];
            }
        }

        $this->json([
            'ok'      => true,
            'sent'    => $result['sent'],
            'failed'  => $result['failed'],
            'errors'  => $result['errors'],
            'waLinks' => $waLinks,
            'logId'   => $newLogId,
        ]);
    }

    // ─── History detail (single log) ──────────────────────────────────────────

    public function detail(array $params = []): void
    {
        $log = DB::row('SELECT * FROM reminder_logs WHERE id = ?', [$params['id']]);
        if (!$log) { $this->json(['ok' => false, 'error' => 'Not found'], 404); }
        $log['recipients'] = json_decode($log['recipients'], true);
        $this->json(['ok' => true, 'log' => $log]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function buildRecipients(string $type, array $tenantIds): array
    {
        if ($type === 'all') {
            $rows = DB::rows("
                SELECT t.id, t.full_name AS name, t.phone, t.email
                FROM tenants t
                WHERE t.status = 'active'
                ORDER BY t.full_name
            ");
            return $rows;
        }

        if (empty($tenantIds)) return [];

        $ph   = implode(',', array_fill(0, count($tenantIds), '?'));
        $rows = DB::rows("
            SELECT id, full_name AS name, phone, email
            FROM tenants
            WHERE id IN ({$ph}) AND status = 'active'
        ", $tenantIds);

        return $rows;
    }

    private function storeAttachment(array $file): ?string
    {
        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        $mime    = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Attachment must be PDF, JPG, or PNG');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new \RuntimeException('Attachment must be under 5 MB');
        }

        $dir = ROOT . '/storage/reminder_attachments';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext  = match ($mime) { 'application/pdf' => 'pdf', 'image/png' => 'png', default => 'jpg' };
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new \RuntimeException('Failed to store attachment');
        }
        return $path;
    }
}
