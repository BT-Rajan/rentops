<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;

class ReminderController extends BaseController
{
    public function index(array $params = []): void
    {
        $month  = $_GET['month'] ?? date('Y-m');
        $period = $month . '-01';

        $overdue = DB::rows("
            SELECT t.id AS tenant_id, t.full_name, t.phone, r.room_number,
                   ri.id AS invoice_id, ri.amount_due, ri.amount_paid,
                   ri.amount_due - ri.amount_paid AS balance,
                   ri.due_date, ri.status,
                   DATEDIFF(CURDATE(), ri.due_date) AS days_overdue
            FROM rent_invoices ri
            JOIN tenancies te ON te.id = ri.tenancy_id
            JOIN tenants t    ON t.id  = te.tenant_id
            JOIN rooms r      ON r.id  = te.room_id
            WHERE ri.period_month = ?
              AND ri.status IN ('unpaid','partial','overdue')
            ORDER BY days_overdue DESC
        ", [$period]);

        $this->render('reminders/index', [
            'pageTitle' => 'Reminder Generator',
            'overdue'   => $overdue,
            'month'     => $month,
            'csrf'      => $this->csrfToken(),
            'user'      => $this->currentUser(),
        ]);
    }

    public function preview(array $params = []): void
    {
        $this->verifyCsrf();
        $ids = $_POST['invoice_ids'] ?? [];
        if (empty($ids)) { $this->json(['messages' => []]); return; }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::rows("
            SELECT t.full_name, t.phone, r.room_number,
                   ri.amount_due - ri.amount_paid AS balance,
                   ri.due_date, ri.status,
                   DATEDIFF(CURDATE(), ri.due_date) AS days_overdue
            FROM rent_invoices ri
            JOIN tenancies te ON te.id = ri.tenancy_id
            JOIN tenants t    ON t.id  = te.tenant_id
            JOIN rooms r      ON r.id  = te.room_id
            WHERE ri.id IN ({$placeholders})
        ", $ids);

        $messages = array_map(fn($r) => [
            'name'    => $r['full_name'],
            'phone'   => $r['phone'],
            'message' => $this->buildMessage($r),
        ], $rows);

        $this->json(['messages' => $messages]);
    }

    private function buildMessage(array $r): string
    {
        $balance = '₹' . number_format((float)$r['balance']);
        $due     = date('d M Y', strtotime($r['due_date']));
        $days    = (int)$r['days_overdue'];
        $name    = $r['full_name'];
        $room    = $r['room_number'];

        if ($days > 0) {
            return "Dear {$name},\n\nThis is a reminder that your rent for Room {$room} is *{$balance}* overdue by {$days} day(s) (was due on {$due}).\n\nKindly arrange payment at the earliest.\n\nThank you.";
        }
        return "Dear {$name},\n\nYour rent of *{$balance}* for Room {$room} is due on {$due}.\n\nPlease arrange payment before the due date.\n\nThank you.";
    }
}
