#!/usr/bin/env php
<?php
/**
 * RentOps cron — daily overdue status sweep
 *
 * Schedule (crontab):
 *   0 7 * * * php /path/to/rentops/cron/refresh_overdue.php >> /var/log/rentops_cron.log 2>&1
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require ROOT . '/src/bootstrap.php';

$ts  = microtime(true);
$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

$log('Starting overdue refresh...');

try {
    $updated = (new \App\Helpers\RentEngine())->refreshOverdueStatus();
    $elapsed = round(microtime(true) - $ts, 3);
    $log("Marked overdue: {$updated} invoice(s) — {$elapsed}s");
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] FATAL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
