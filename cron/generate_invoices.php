#!/usr/bin/env php
<?php
/**
 * RentOps cron — monthly invoice generation + overdue sweep
 *
 * Schedule (crontab):
 *   0 6 1 * * php /path/to/rentops/cron/generate_invoices.php >> /var/log/rentops_cron.log 2>&1
 *
 * Manual backfill:
 *   php cron/generate_invoices.php 2024-11
 *
 * Dry run (check only, no writes):
 *   php cron/generate_invoices.php 2025-01 --dry-run
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// CLI-only guard
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require ROOT . '/src/bootstrap.php';

$yearMonth = $argv[1] ?? date('Y-m');
$dryRun    = in_array('--dry-run', $argv, true);

// Validate month format
if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
    fwrite(STDERR, "Usage: php generate_invoices.php [YYYY-MM] [--dry-run]\n");
    exit(1);
}

$ts = microtime(true);
$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

$log("Starting invoice generation for {$yearMonth}" . ($dryRun ? ' [DRY RUN]' : ''));

try {
    $engine = new \App\Helpers\RentEngine();

    if ($dryRun) {
        // Dry run: count what would be created
        $active = \App\DB::scalar("SELECT COUNT(*) FROM tenancies WHERE status = 'active'");
        $period = $yearMonth . '-01';
        $existing = \App\DB::scalar(
            "SELECT COUNT(*) FROM rent_invoices WHERE period_month = ?", [$period]
        );
        $log("Active tenancies : {$active}");
        $log("Existing invoices: {$existing} for {$yearMonth}");
        $log("Would create     : " . max(0, $active - $existing) . " new invoice(s)");
        $log("Dry run complete — no changes made.");
    } else {
        $created  = $engine->generateMonthlyInvoices($yearMonth);
        $overdue  = $engine->refreshOverdueStatus();
        $elapsed  = round(microtime(true) - $ts, 3);
        $log("Invoices created : {$created}");
        $log("Overdue updated  : {$overdue}");
        $log("Completed in {$elapsed}s");
    }

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] FATAL: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
