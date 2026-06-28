<?php
/**
 * Cron: send_scheduled_reminders.php
 * Run every minute: * * * * * php /path/to/cron/send_scheduled_reminders.php
 */
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
require ROOT . '/src/bootstrap.php';

use App\DB;
use App\Helpers\ReminderSender;

$due = DB::rows("
    SELECT * FROM reminder_logs
    WHERE status = 'scheduled'
      AND scheduled_at <= NOW()
    ORDER BY scheduled_at
    LIMIT 50
");

foreach ($due as $log) {
    try {
        $recipients = json_decode($log['recipients'], true);
        $result = ReminderSender::dispatch(
            $log['channel'],
            $recipients,
            $log['message'],
            $log['subject'],
            $log['attachment_path']
        );
        DB::update('reminder_logs', [
            'status'     => 'sent',
            'sent_at'    => date('Y-m-d H:i:s'),
            'sent_count' => $result['sent'],
            'fail_count' => $result['failed'],
            'error_log'  => $result['errors'] ? implode("\n", $result['errors']) : null,
        ], 'id = ?', [$log['id']]);
    } catch (\Throwable $e) {
        DB::update('reminder_logs', [
            'status'    => 'failed',
            'error_log' => $e->getMessage(),
        ], 'id = ?', [$log['id']]);
    }
}
