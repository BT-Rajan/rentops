<?php
/**
 * Cron: process_scheduled_moveouts.php
 * Run once daily: 0 0 * * * php /path/to/cron/process_scheduled_moveouts.php
 *
 * Executes move-outs that were scheduled for a future date and whose date
 * has now arrived (or passed). Frees the room, closes the tenancy, and
 * marks the tenant vacated — exactly what TenantController::moveOut() does
 * for same-day move-outs, just deferred until the actual date.
 */
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
require ROOT . '/src/bootstrap.php';

use App\DB;
use App\Helpers\RentEngine;
use App\Helpers\AuditLog;

$due = DB::rows("
    SELECT te.*, t.id AS tenant_id, t.full_name
    FROM tenancies te
    JOIN tenants t ON t.id = te.tenant_id
    WHERE te.status = 'active'
      AND te.scheduled_move_out_date IS NOT NULL
      AND te.scheduled_move_out_date <= CURDATE()
");

$engine = new RentEngine();
$processed = 0;

foreach ($due as $tenancy) {
    DB::beginTransaction();
    try {
        $engine->processMoveOut(
            $tenancy['id'],
            $tenancy['scheduled_move_out_date'],
            (float)($tenancy['scheduled_deduction'] ?? 0)
        );

        DB::update('tenants', ['status' => 'vacated'], 'id = ?', [$tenancy['tenant_id']]);

        // Clear the scheduling fields now that it's executed
        DB::update('tenancies', [
            'scheduled_move_out_date' => null,
            'scheduled_deduction'     => null,
        ], 'id = ?', [$tenancy['id']]);

        DB::commit();
        AuditLog::record('scheduled_moveout_executed', 'tenancies', $tenancy['id'], [
            'tenant' => $tenancy['full_name'],
            'date'   => $tenancy['scheduled_move_out_date'],
        ]);
        $processed++;
    } catch (\Throwable $e) {
        DB::rollback();
        error_log('[RentOps] Scheduled move-out failed for tenancy ' . $tenancy['id'] . ': ' . $e->getMessage());
    }
}

echo "Processed {$processed} scheduled move-out(s).\n";
