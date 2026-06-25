<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\RentEngine;

class PaymentController extends BaseController
{
    public function create(array $params = []): void
    {
        $invoiceId = $_GET['invoice_id'] ?? null;
        $invoice   = $invoiceId ? DB::row("
            SELECT ri.*, te.agreed_rent, te.tenant_id, t.full_name, r.room_number,
                   ri.amount_due - ri.amount_paid AS balance
            FROM rent_invoices ri
            JOIN tenancies te ON te.id = ri.tenancy_id
            JOIN tenants t    ON t.id  = te.tenant_id
            JOIN rooms r      ON r.id  = te.room_id
            WHERE ri.id = ?
        ", [$invoiceId]) : null;

        // Pending invoices for dropdown
        $invoices = DB::rows("
            SELECT ri.id, ri.period_month, ri.amount_due, ri.amount_paid,
                   ri.amount_due - ri.amount_paid AS balance,
                   t.full_name, r.room_number, ri.status
            FROM rent_invoices ri
            JOIN tenancies te ON te.id = ri.tenancy_id
            JOIN tenants t    ON t.id  = te.tenant_id
            JOIN rooms r      ON r.id  = te.room_id
            WHERE ri.status IN ('unpaid','partial','overdue')
            ORDER BY ri.due_date ASC, t.full_name
        ");

        $this->render('payments/create', [
            'pageTitle'  => 'Record Payment',
            'invoice'    => $invoice,
            'invoices'   => $invoices,
            'csrf'       => $this->csrfToken(),
            'user'       => $this->currentUser(),
        ]);
    }

    public function store(array $params = []): void
    {
        $this->verifyCsrf();
        $err = $this->requireFields(['invoice_id', 'amount', 'payment_date', 'mode']);
        if ($err) { $this->redirect('/payments/new', $err, 'error'); return; }

        $invoiceId = $_POST['invoice_id'];
        $amount    = (float)$_POST['amount'];

        if ($amount <= 0) {
            $this->redirect('/payments/new', 'Amount must be greater than zero.', 'error');
            return;
        }

        $invoice = DB::row('SELECT * FROM rent_invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) { $this->redirect('/payments/new', 'Invoice not found.', 'error'); return; }

        DB::beginTransaction();
        try {
            DB::insert('payments', [
                'id'           => $this->uuid(),
                'invoice_id'   => $invoiceId,
                'amount'       => $amount,
                'payment_date' => $_POST['payment_date'],
                'mode'         => $_POST['mode'],
                'note'         => trim($_POST['note'] ?? ''),
                'recorded_by'  => $this->currentUser()['name'],
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            (new RentEngine())->updateInvoiceStatus($invoiceId);

            DB::commit();
            $this->redirect('/dues', 'Payment of ₹' . number_format($amount) . ' recorded.');
        } catch (\Throwable $e) {
            DB::rollback();
            $this->redirect('/payments/new', 'Payment failed: ' . $e->getMessage(), 'error');
        }
    }

    public function show(array $params = []): void
    {
        $payment = DB::row("
            SELECT p.*, t.full_name, r.room_number,
                   ri.period_month, ri.amount_due, ri.amount_paid, ri.status AS invoice_status
            FROM payments p
            JOIN rent_invoices ri ON ri.id = p.invoice_id
            JOIN tenancies te     ON te.id = ri.tenancy_id
            JOIN tenants t        ON t.id  = te.tenant_id
            JOIN rooms r          ON r.id  = te.room_id
            WHERE p.id = ?
        ", [$params['id']]);

        if (!$payment) { http_response_code(404); return; }

        $this->render('payments/show', [
            'pageTitle' => 'Payment Receipt',
            'payment'   => $payment,
            'user'      => $this->currentUser(),
        ]);
    }

    private function uuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
