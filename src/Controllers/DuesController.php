<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\RentEngine;

class DuesController extends BaseController
{
    public function index(array $params = []): void
    {
        $filter = $_GET['filter'] ?? 'all';
        $month  = $_GET['month']  ?? date('Y-m');
        $period = $month . '-01';

        $where = "WHERE ri.period_month = ?";
        $bind  = [$period];

        if (in_array($filter, ['unpaid', 'partial', 'overdue', 'paid'])) {
            $where .= ' AND ri.status = ?';
            $bind[] = $filter;
        } elseif ($filter === 'pending') {
            $where .= " AND ri.status IN ('unpaid','partial','overdue')";
        }

        // FIX B23: refreshOverdueStatus() runs an unbounded UPDATE on every dues page
        // load. With many invoices this adds unnecessary DB load on every request.
        // The daily cron handles the authoritative sweep; the on-demand call here is
        // only a convenience safety net. Throttle it to at most once per hour per session.
        $lastRefresh = $_SESSION['overdue_refresh_ts'] ?? 0;
        if (time() - $lastRefresh > 3600) {
            (new RentEngine())->refreshOverdueStatus();
            $_SESSION['overdue_refresh_ts'] = time();
        }

        $dues = DB::rows("
            SELECT ri.*,
                   ri.amount_due - ri.amount_paid AS balance,
                   t.full_name, t.phone, t.id AS tenant_id,
                   r.room_number,
                   DATEDIFF(CURDATE(), ri.due_date) AS days_overdue
            FROM rent_invoices ri
            JOIN tenancies te ON te.id = ri.tenancy_id
            JOIN tenants t    ON t.id  = te.tenant_id
            JOIN rooms r      ON r.id  = te.room_id
            {$where}
            ORDER BY days_overdue DESC, ri.due_date ASC
        ", $bind);

        $summary = DB::row("
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(amount_due), 0)  AS total_due,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                SUM(CASE WHEN status = 'overdue'  THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN status = 'partial'  THEN 1 ELSE 0 END) AS partial,
                SUM(CASE WHEN status = 'unpaid'   THEN 1 ELSE 0 END) AS unpaid,
                SUM(CASE WHEN status = 'paid'     THEN 1 ELSE 0 END) AS paid
            FROM rent_invoices WHERE period_month = ?
        ", [$period]);

        $this->render('dues/index', [
            'pageTitle' => 'Dues & Overdue',
            'dues'      => $dues,
            'summary'   => $summary,
            'filter'    => $filter,
            'month'     => $month,
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
            'csrf'      => $this->csrfToken(),
        ]);
    }

    public function generate(array $params = []): void
    {
        $this->verifyCsrf();
        try {
            $month = trim($_POST['month'] ?? date('Y-m'));
            // Validate format — input[type=month] sends Y-m, guard against empty/garbage
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                $this->json(['ok' => false, 'error' => 'Invalid month format'], 422);
                return;
            }
            $engine  = new RentEngine();
            $created = $engine->generateMonthlyInvoices($month);
            $this->json(['ok' => true, 'created' => $created, 'month' => $month]);
        } catch (\Throwable $e) {
            error_log('[RentOps] generate invoices: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
