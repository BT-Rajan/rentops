<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\RentEngine;
use App\Helpers\AuditLog;

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
            'flash'      => $this->flash(),
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

        // Overpayment guard
        $currentPaid = (float)$invoice['amount_paid'];
        $due         = (float)$invoice['amount_due'];
        $overpayment = max(0, ($currentPaid + $amount) - $due);

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

            (new RentEngine())->updateInvoiceStatus($invoiceId, $overpayment);

            // FIX B-flow-7: a manual payment changes the invoice balance —
            // any previously issued Razorpay link still points at the OLD
            // balance and would double-charge the tenant if reused. We don't
            // call the Razorpay API here (no key context needed for a status
            // flip), we just flag it stale; razorpayLink() detects the
            // amount mismatch on next click and cancels+reissues automatically.
            DB::update('rent_invoices', [
                'razorpay_link_status' => 'expired',
            ], 'id = ? AND razorpay_link IS NOT NULL', [$invoiceId]);

            AuditLog::record('payment_recorded', 'payment', null, [
                'invoice_id' => $invoiceId,
                'amount'     => $amount,
                'mode'       => $_POST['mode'],
                'overpayment'=> $overpayment,
            ]);

            DB::commit();
            $msg = 'Payment of ₹' . number_format($amount) . ' recorded.';
            if ($overpayment > 0) {
                $msg .= ' ⚠ Overpayment of ₹' . number_format($overpayment) . ' — please verify.';
            }
            $this->redirect('/dues', $msg);
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
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
        ]);
    }

    // uuid() inherited from BaseController
}
