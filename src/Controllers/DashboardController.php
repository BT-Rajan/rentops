<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;

class DashboardController extends BaseController
{
    public function index(array $params = []): void
    {
        $this->render('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
        ]);
    }

    public function api(array $params = []): void
    {
        $month  = $_GET['month'] ?? date('Y-m');
        $period = $month . '-01';

        // Collection stats for the selected month
        $stats = DB::row("
            SELECT
                COUNT(DISTINCT ri.id)                                          AS total_invoices,
                COALESCE(SUM(ri.amount_due), 0)                                AS total_due,
                COALESCE(SUM(ri.amount_paid), 0)                               AS total_paid,
                COALESCE(SUM(CASE WHEN ri.status = 'overdue'  THEN 1 ELSE 0 END), 0) AS overdue_count,
                COALESCE(SUM(CASE WHEN ri.status = 'paid'     THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(CASE WHEN ri.status = 'partial'  THEN 1 ELSE 0 END), 0) AS partial_count,
                COALESCE(SUM(CASE WHEN ri.status = 'unpaid'   THEN 1 ELSE 0 END), 0) AS unpaid_count
            FROM rent_invoices ri
            WHERE ri.period_month = ?
        ", [$period]);

        // Rooms summary
        $rooms = DB::row("
            SELECT
                COUNT(*)                                                    AS total_rooms,
                SUM(CASE WHEN status = 'vacant' THEN 1 ELSE 0 END)         AS vacant,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END)       AS occupied,
                SUM(CASE WHEN status = 'partially_occupied' THEN 1 ELSE 0 END) AS partially_occupied,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END)    AS maintenance
            FROM rooms
        ");

        // Recent payments (last 5)
        $recent = DB::rows("
            SELECT p.amount, p.payment_date, p.mode, t.full_name, r.room_number
            FROM payments p
            JOIN rent_invoices ri ON ri.id = p.invoice_id
            JOIN tenancies te     ON te.id = ri.tenancy_id
            JOIN tenants t        ON t.id  = te.tenant_id
            JOIN rooms r          ON r.id  = te.room_id
            ORDER BY p.payment_date DESC, p.id DESC
            LIMIT 5
        ");

        // Collection % trend (last 6 months)
        $trend = DB::rows("
            SELECT
                DATE_FORMAT(ri.period_month, '%b %Y')   AS label,
                COALESCE(SUM(ri.amount_due), 0)         AS due,
                COALESCE(SUM(ri.amount_paid), 0)        AS paid
            FROM rent_invoices ri
            WHERE ri.period_month >= DATE_FORMAT(DATE_SUB(?, INTERVAL 5 MONTH), '%Y-%m-01')
              AND ri.period_month <= ?
            GROUP BY ri.period_month
            ORDER BY ri.period_month ASC
        ", [$period, $period]);

        $due   = (float)($stats['total_due']  ?? 0);
        $paid  = (float)($stats['total_paid'] ?? 0);
        $pct   = $due > 0 ? round(($paid / $due) * 100, 1) : 0;

        $this->json([
            'month'          => $month,
            'collection_pct' => $pct,
            'total_due'      => $due,
            'total_paid'     => $paid,
            'outstanding'    => round($due - $paid, 2),
            'invoices'       => $stats,
            'rooms'          => $rooms,
            'recent_payments'=> $recent,
            'trend'          => $trend,
        ]);
    }
}
