#!/usr/bin/env php
<?php
/**
 * RentOps cron — generate monthly rent invoices
 * Run on 1st of each month:
 *   0 6 1 * * php /path/to/rentops/cron/generate_invoices.php >> /var/log/rentops_cron.log 2>&1
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require ROOT . '/src/bootstrap.php';

$yearMonth = $argv[1] ?? date('Y-m');

echo "[" . date('Y-m-d H:i:s') . "] Generating invoices for {$yearMonth}\n";

try {
    $engine  = new \App\Helpers\RentEngine();
    $created = $engine->generateMonthlyInvoices($yearMonth);
    $updated = $engine->refreshOverdueStatus();
    echo "[" . date('Y-m-d H:i:s') . "] Created: {$created}, overdue refreshed: {$updated}\n";
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
