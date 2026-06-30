<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\RentEngine;
use App\Helpers\AuditLog;
use App\Helpers\Crypto;

/**
 * RazorpayWebhookController — server-to-server payment reconciliation.
 *
 * Fixes B-flow-7: previously the only payment confirmation mechanism was
 * `callback_url`, which is just a browser GET redirect after checkout —
 * easily spoofed (anyone can hit that URL with arbitrary query params) and
 * not delivered at all if the tenant closes the tab before the redirect
 * fires. Razorpay's documented best practice is a server-side webhook with
 * HMAC signature verification, which is what this implements.
 *
 * Configure in Razorpay Dashboard → Settings → Webhooks:
 *   URL: https://yourdomain.com/payments/razorpay/webhook
 *   Events: payment_link.paid
 */
class RazorpayWebhookController extends BaseController
{
    public function handle(array $params = []): void
    {
        $rawBody  = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

        $property = DB::row('SELECT * FROM properties LIMIT 1');
        $webhookSecret = Crypto::decrypt($property['razorpay_webhook_secret'] ?? null);

        if (!$webhookSecret) {
            error_log('[RentOps] Razorpay webhook received but no webhook secret configured — rejecting.');
            http_response_code(400);
            echo 'Webhook secret not configured.';
            return;
        }

        // ─── Verify signature — this is the entire trust boundary ──────────
        $expected = hash_hmac('sha256', $rawBody, $webhookSecret);
        if (!hash_equals($expected, $signature)) {
            error_log('[RentOps] Razorpay webhook signature mismatch — possible spoofed request.');
            http_response_code(400);
            echo 'Invalid signature.';
            return;
        }

        $payload = json_decode($rawBody, true);
        if (!$payload) {
            http_response_code(400);
            echo 'Invalid payload.';
            return;
        }

        $eventId   = $payload['id']    ?? null;
        $eventType = $payload['event'] ?? '';

        // ─── Idempotency — Razorpay retries webhooks on timeout; never double-apply ──
        if ($eventId) {
            $already = DB::row('SELECT id FROM razorpay_webhook_events WHERE event_id = ?', [$eventId]);
            if ($already) {
                http_response_code(200);
                echo 'Already processed.';
                return;
            }
        }

        if ($eventType !== 'payment_link.paid') {
            // Acknowledge but ignore — we only act on the one event we subscribed to
            http_response_code(200);
            echo 'Event ignored.';
            return;
        }

        $linkEntity = $payload['payload']['payment_link']['entity'] ?? null;
        $paymentEntity = $payload['payload']['payment']['entity'] ?? null;

        if (!$linkEntity) {
            http_response_code(200);
            echo 'No payment_link entity in payload.';
            return;
        }

        $invoiceId = $linkEntity['notes']['invoice_id'] ?? null;
        $linkId    = $linkEntity['id'] ?? null;
        $amountPaid = isset($linkEntity['amount_paid']) ? $linkEntity['amount_paid'] / 100 : 0;

        if (!$invoiceId) {
            error_log('[RentOps] Razorpay webhook payment_link.paid with no invoice_id in notes — link ' . $linkId);
            http_response_code(200);
            echo 'No invoice reference found.';
            return;
        }

        $invoice = DB::row('SELECT * FROM rent_invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            error_log('[RentOps] Razorpay webhook references unknown invoice ' . $invoiceId);
            http_response_code(200);
            echo 'Invoice not found.';
            return;
        }

        DB::beginTransaction();
        try {
            $currentPaid = (float)$invoice['amount_paid'];
            $due         = (float)$invoice['amount_due'];
            $overpayment = max(0, ($currentPaid + $amountPaid) - $due);

            DB::insert('payments', [
                'id'           => $this->uuid(),
                'invoice_id'   => $invoiceId,
                'amount'       => $amountPaid,
                'payment_date' => date('Y-m-d'),
                'mode'         => 'razorpay',
                'note'         => 'Auto-reconciled via Razorpay webhook (link ' . $linkId . ', payment ' . ($paymentEntity['id'] ?? 'n/a') . ')',
                'recorded_by'  => 'Razorpay (auto)',
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            (new RentEngine())->updateInvoiceStatus($invoiceId, $overpayment);

            DB::update('rent_invoices', ['razorpay_link_status' => 'paid'], 'id = ?', [$invoiceId]);

            if ($eventId) {
                DB::insert('razorpay_webhook_events', [
                    'id'         => $this->uuid(),
                    'event_id'   => $eventId,
                    'event_type' => $eventType,
                    'invoice_id' => $invoiceId,
                    'payload'    => json_encode($payload),
                ]);
            }

            AuditLog::record('payment_auto_reconciled', 'rent_invoices', $invoiceId, [
                'amount'  => $amountPaid,
                'link_id' => $linkId,
                'source'  => 'razorpay_webhook',
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            error_log('[RentOps] Razorpay webhook processing failed for invoice ' . $invoiceId . ': ' . $e->getMessage());
            http_response_code(500);
            echo 'Processing failed — Razorpay will retry.';
            return;
        }

        http_response_code(200);
        echo 'OK';
    }

    /**
     * Browser-redirect fallback (callback_url). Not trusted for reconciliation
     * — the webhook above is the source of truth — this just gives the tenant
     * a friendly "thanks" page and nudges the landlord's dashboard to refresh.
     */
    public function callback(array $params = []): void
    {
        $linkId = $_GET['razorpay_payment_link_id'] ?? '';
        $status = $_GET['razorpay_payment_link_status'] ?? '';

        $invoice = $linkId ? DB::row('SELECT id FROM rent_invoices WHERE razorpay_link_id = ?', [$linkId]) : null;

        if ($invoice && $status === 'paid') {
            // Webhook is authoritative for actually recording the payment;
            // this redirect just routes the tenant somewhere sensible.
            $this->redirect('/invoices/' . $invoice['id'], 'Payment received — thank you! It may take a minute to reflect here.');
            return;
        }

        $this->redirect('/', $status === 'paid' ? 'Payment received — thank you!' : 'Payment was not completed.');
    }
}
