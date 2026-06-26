#!/usr/bin/env php
<?php
/**
 * RentOps cron — housekeeping
 *   - Purge expired remember_me tokens
 *   - Prune rate_limits older than 1 hour
 *   - Prune audit_log older than 90 days (configurable)
 *
 * Schedule (crontab):
 *   0 3 * * 0 php /path/to/rentops/cron/cleanup.php >> /var/log/rentops_cron.log 2>&1
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require ROOT . '/src/bootstrap.php';

$log      = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);
$auditDays = (int)($_ENV['AUDIT_LOG_RETENTION_DAYS'] ?? 90);

$log('Starting housekeeping...');

try {
    $tokens = \App\DB::query('DELETE FROM remember_tokens WHERE expires_at < NOW()')->rowCount();
    $log("Expired tokens pruned    : {$tokens}");

    $limits = \App\DB::query(
        "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    )->rowCount();
    $log("Rate limit rows pruned   : {$limits}");

    $audit = \App\DB::query(
        "DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$auditDays]
    )->rowCount();
    $log("Audit log rows pruned    : {$audit} (>{$auditDays}d)");

    $log('Housekeeping complete.');
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] FATAL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
