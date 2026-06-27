<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;

class ReportController extends BaseController
{
    public function index(array $params = []): void
    {
        $month  = $_GET['month'] ?? date('Y-m');
        $period = $month . '-01';

        $summary = DB::row("
            SELECT
                COUNT(DISTINCT ri.id)                                    AS invoices,
                COALESCE(SUM(ri.amount_due), 0)                          AS total_due,
                COALESCE(SUM(ri.amount_paid), 0)                         AS total_paid,
                COALESCE(SUM(ri.amount_due) - SUM(ri.amount_paid), 0)    AS outstanding,
                SUM(CASE WHEN ri.status='paid'    THEN 1 ELSE 0 END)     AS paid_count,
                SUM(CASE WHEN ri.status='partial' THEN 1 ELSE 0 END)     AS partial_count,
                SUM(CASE WHEN ri.status='overdue' THEN 1 ELSE 0 END)     AS overdue_count
            FROM rent_invoices ri
            WHERE ri.period_month = ?
        ", [$period]);

        $rows = DB::rows("
            SELECT r.room_number, t.full_name, te.agreed_rent,
                   ri.amount_due, ri.amount_paid, ri.status,
                   GROUP_CONCAT(DISTINCT p.mode ORDER BY p.payment_date SEPARATOR ', ') AS modes,
                   MAX(p.payment_date) AS last_paid
            FROM rent_invoices ri
            JOIN tenancies te ON te.id = ri.tenancy_id
            JOIN tenants t    ON t.id  = te.tenant_id
            JOIN rooms r      ON r.id  = te.room_id
            LEFT JOIN payments p ON p.invoice_id = ri.id
            WHERE ri.period_month = ?
            GROUP BY ri.id
            ORDER BY r.room_number
        ", [$period]);

        $this->render('reports/index', [
            'pageTitle' => 'Monthly Report',
            'month'     => $month,
            'summary'   => $summary,
            'rows'      => $rows,
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
        ]);
    }

    public function export(array $params = []): void
    {
        $month  = $_GET['month'] ?? date('Y-m');
        $period = $month . '-01';

        $rows = DB::rows("
            SELECT r.room_number AS 'Room', t.full_name AS 'Tenant', t.phone AS 'Phone',
                   te.agreed_rent AS 'Agreed Rent', ri.amount_due AS 'Amount Due',
                   ri.amount_paid AS 'Amount Paid',
                   ri.amount_due - ri.amount_paid AS 'Balance',
                   ri.status AS 'Status', ri.due_date AS 'Due Date',
                   GROUP_CONCAT(DISTINCT p.mode ORDER BY p.payment_date SEPARATOR ', ') AS 'Payment Mode',
                   MAX(p.payment_date) AS 'Last Payment'
            FROM rent_invoices ri
            JOIN tenancies te ON te.id = ri.tenancy_id
            JOIN tenants t    ON t.id  = te.tenant_id
            JOIN rooms r      ON r.id  = te.room_id
            LEFT JOIN payments p ON p.invoice_id = ri.id
            WHERE ri.period_month = ?
            GROUP BY ri.id
            ORDER BY r.room_number
        ", [$period]);

        // FIX B21: Discard any buffered output (layout partials, PHP notices) before
        // sending CSV headers. Without this, any prior output causes header() calls
        // to silently fail, producing a corrupted download instead of a proper CSV.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = "RentOps_Report_{$month}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel opens the file without encoding issues
        fwrite($out, "\xEF\xBB\xBF");
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}
