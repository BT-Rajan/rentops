<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\AuditLog;
use App\Helpers\UuidHelper;

class InvoiceController extends BaseController
{
    // ─── Invoice listing — search by tenant / room / month, XLSX export ────────

    public function index(array $params = []): void
    {
        $tenant = trim($_GET['tenant'] ?? '');
        $room   = trim($_GET['room']   ?? '');
        $month  = trim($_GET['month']  ?? '');
        $status = trim($_GET['status'] ?? '');
        $tenantId = trim($_GET['tenant_id'] ?? '');

        $where  = [];
        $args   = [];

        if ($tenantId) { $where[] = 't.id = ?';                $args[] = $tenantId; }
        if ($tenant)   { $where[] = 't.full_name LIKE ?';       $args[] = "%{$tenant}%"; }
        if ($room)     { $where[] = 'r.room_number LIKE ?';     $args[] = "%{$room}%"; }
        if ($month)    { $where[] = "ri.period_month LIKE ?";   $args[] = "{$month}%"; }
        if ($status)   { $where[] = 'ri.status = ?';            $args[] = $status; }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $invoices = DB::rows("
            SELECT ri.*, t.id AS tenant_id, t.full_name, r.room_number, te.agreed_rent
            FROM rent_invoices ri
            JOIN tenancies te ON te.id = ri.tenancy_id
            JOIN tenants t    ON t.id  = te.tenant_id
            JOIN rooms r      ON r.id  = te.room_id
            {$whereSql}
            ORDER BY ri.period_month DESC, r.room_number
        ", $args);

        if (($_GET['download'] ?? '') === 'xlsx') {
            $this->exportXlsx($invoices);
            return;
        }

        $this->render('invoices/index', [
            'pageTitle' => 'Invoices',
            'invoices'  => $invoices,
            'csrf'      => $this->csrfToken(),
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
        ]);
    }

    private function exportXlsx(array $invoices): void
    {
        require_once ROOT . '/src/Helpers/XlsxWriter.php';

        $w = new \App\Helpers\XlsxWriter([
            'Period', 'Tenant', 'Room', 'Rent', 'EB Units', 'EB Amount',
            'GST', 'Other Charges', 'Total Due', 'Paid', 'Balance', 'Status', 'Due Date',
        ]);

        foreach ($invoices as $inv) {
            $balance = (float)$inv['amount_due'] - (float)$inv['amount_paid'];
            $w->addRow([
                date('M Y', strtotime($inv['period_month'])),
                $inv['full_name'],
                $inv['room_number'],
                (float)$inv['agreed_rent'],
                (float)$inv['eb_units'],
                (float)$inv['eb_amount'],
                (float)$inv['rent_gst'],
                (float)$inv['other_charges'],
                (float)$inv['amount_due'],
                (float)$inv['amount_paid'],
                $balance,
                ucfirst($inv['status']),
                date('d-m-Y', strtotime($inv['due_date'])),
            ]);
        }

        $tmpPath = sys_get_temp_dir() . '/rentops_invoices_' . bin2hex(random_bytes(4)) . '.xlsx';
        $w->save($tmpPath);

        $filename = 'invoices_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpPath));
        readfile($tmpPath);
        unlink($tmpPath);
        exit;
    }

    // ─── Bill generator form ─────────────────────────────────────────────────

    public function create(array $params = []): void
    {
        $tenants = DB::rows("
            SELECT t.id, t.full_name, t.phone, r.room_number,
                   te.id AS tenancy_id, te.agreed_rent, te.rent_due_day
            FROM tenants t
            JOIN tenancies te ON te.tenant_id = t.id AND te.status = 'active'
            JOIN rooms r      ON r.id = te.room_id
            WHERE t.status = 'active'
            ORDER BY r.room_number, t.full_name
        ");

        $property = DB::row('SELECT * FROM properties LIMIT 1');

        $this->render('invoices/create', [
            'pageTitle' => 'Generate Invoice',
            'tenants'   => $tenants,
            'property'  => $property,
            'csrf'      => $this->csrfToken(),
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
        ]);
    }

    // ─── Store / generate PDF ────────────────────────────────────────────────

    public function store(array $params = []): void
    {
        $this->verifyCsrf();

        $tenancyId      = $_POST['tenancy_id']       ?? '';
        $month          = trim($_POST['month']        ?? date('Y-m'));
        // FIX B-flow-12: floor at 0 — negative units/charges must not be possible
        $ebUnits        = max(0, (float)($_POST['eb_units']        ?? 0));
        $otherCharges   = max(0, (float)($_POST['other_charges']   ?? 0));
        $otherDesc      = trim($_POST['other_charges_desc'] ?? '');

        if (!$tenancyId || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->redirect('/invoices/new', 'Invalid input.', 'error');
            return;
        }

        $tenancy = DB::row("
            SELECT te.*, t.full_name, t.phone, t.email,
                   r.room_number, r.room_type
            FROM tenancies te
            JOIN tenants t ON t.id = te.tenant_id
            JOIN rooms r   ON r.id = te.room_id
            WHERE te.id = ? AND te.status = 'active'
        ", [$tenancyId]);

        if (!$tenancy) {
            $this->redirect('/invoices/new', 'Tenancy not found or no longer active.', 'error');
            return;
        }

        $property = DB::row('SELECT * FROM properties LIMIT 1');
        $ebUnitPrice = (float)($property['eb_unit_price'] ?? 0);
        $gstRate     = (float)($property['rent_gst_rate'] ?? 18);

        // FIX B-flow-1: resolve rent through RentEngine::effectiveRent() — the
        // same source the bulk/cron generator uses — so a manually generated
        // invoice respects any mid-tenancy rent_changes entry instead of
        // always falling back to the tenancy's current agreed_rent.
        $engine  = new RentEngine();
        $rent    = $engine->effectiveRent($tenancyId, (float)$tenancy['agreed_rent'], $month);
        $ebAmount = round($ebUnits * $ebUnitPrice, 2);
        $rentGst  = round($rent * ($gstRate / 100), 2);
        $totalDue = $rent + $rentGst + $ebAmount + $otherCharges;

        $period  = $month . '-01';
        $periodObj = new \DateTimeImmutable($period);
        $dueDate = $engine->dueDate($periodObj, (int)$tenancy['rent_due_day']);

        $existing = DB::row(
            'SELECT * FROM rent_invoices WHERE tenancy_id = ? AND period_month = ?',
            [$tenancyId, $period]
        );

        $isRegenerate = (bool)$existing;
        $previousPaid = $existing ? (float)$existing['amount_paid'] : 0.0;

        // FIX B-flow-5: recalculate status against whatever has actually been
        // paid so far, instead of leaving a stale 'unpaid'/'partial' value
        // when the total changes (e.g. correcting EB units after the fact).
        $newStatus = $engine->resolveStatus($totalDue, $previousPaid);

        if ($existing) {
            $invoiceId = $existing['id'];
            DB::update('rent_invoices', [
                'amount_due'          => $totalDue,
                'status'              => $newStatus,
                'eb_units'            => $ebUnits,
                'eb_amount'           => $ebAmount,
                'rent_gst'            => $rentGst,
                'other_charges'       => $otherCharges,
                'other_charges_desc'  => $otherDesc ?: null,
                'due_date'            => $dueDate,
            ], 'id = ?', [$invoiceId]);
        } else {
            $invoiceId = UuidHelper::v4();
            DB::insert('rent_invoices', [
                'id'                  => $invoiceId,
                'tenancy_id'          => $tenancyId,
                'period_month'        => $period,
                'amount_due'          => $totalDue,
                'amount_paid'         => 0,
                'overpayment'         => 0,
                'due_date'            => $dueDate,
                'status'              => $newStatus,
                'eb_units'            => $ebUnits,
                'eb_amount'           => $ebAmount,
                'rent_gst'            => $rentGst,
                'other_charges'       => $otherCharges,
                'other_charges_desc'  => $otherDesc ?: null,
                'created_at'          => date('Y-m-d H:i:s'),
            ]);
        }

        // FIX B-flow-8: don't let a PDF rendering failure leave the user on a
        // bare 500 with no way back — the invoice row above is already
        // correct; surface the PDF error but still redirect to the invoice
        // so they can retry the PDF (or proceed with payment) instead of
        // losing the page entirely.
        try {
            $pdfPath = $this->generatePdf($invoiceId, $tenancy, $property, $month, [
                'rent'          => $rent,
                'eb_units'      => $ebUnits,
                'eb_amount'     => $ebAmount,
                'rent_gst'      => $rentGst,
                'gst_rate'      => $gstRate,
                'other_charges' => $otherCharges,
                'other_desc'    => $otherDesc,
                'total'         => $totalDue,
                'due_date'      => $dueDate,
            ]);
            DB::update('rent_invoices', ['pdf_path' => $pdfPath], 'id = ?', [$invoiceId]);
            $pdfNote = '';
        } catch (\Throwable $e) {
            error_log('[RentOps] PDF generation failed for invoice ' . $invoiceId . ': ' . $e->getMessage());
            $pdfNote = ' (PDF generation failed — invoice saved, you can retry the download from the invoice page.)';
        }

        // FIX B-flow-6: distinguish create vs regenerate in the audit trail,
        // and record what changed when an existing invoice is corrected.
        AuditLog::record($isRegenerate ? 'invoice_regenerated' : 'invoice_generated', 'rent_invoices', $invoiceId, [
            'month'          => $month,
            'total'          => $totalDue,
            'eb_units'       => $ebUnits,
            'other_charges'  => $otherCharges,
            'previous_total' => $existing['amount_due'] ?? null,
            'previous_paid'  => $previousPaid,
        ]);

        $msg = $isRegenerate
            ? 'Invoice updated successfully.' . $pdfNote
            : 'Invoice generated successfully.' . $pdfNote;
        $this->redirect("/invoices/{$invoiceId}", $msg);
    }

    // ─── Invoice detail + actions ─────────────────────────────────────────────

    public function show(array $params = []): void
    {
        $invoice = DB::row("
            SELECT ri.*,
                   t.full_name, t.phone, t.email,
                   r.room_number, r.room_type,
                   te.agreed_rent, te.security_deposit
            FROM rent_invoices ri
            JOIN tenancies te ON te.id = ri.tenancy_id
            JOIN tenants t    ON t.id  = te.tenant_id
            JOIN rooms r      ON r.id  = te.room_id
            WHERE ri.id = ?
        ", [$params['id']]);

        if (!$invoice) {
            http_response_code(404);
            $this->render('partials/404', ['pageTitle' => '404', 'user' => $this->currentUser(), 'flash' => null, 'csrf' => $this->csrfToken()]);
            return;
        }

        $property = DB::row('SELECT * FROM properties LIMIT 1');

        $this->render('invoices/show', [
            'pageTitle' => 'Invoice — ' . date('M Y', strtotime($invoice['period_month'])),
            'invoice'   => $invoice,
            'property'  => $property,
            'csrf'      => $this->csrfToken(),
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
        ]);
    }

    // ─── Download PDF ─────────────────────────────────────────────────────────

    public function pdf(array $params = []): void
    {
        $invoice = DB::row('SELECT pdf_path, id FROM rent_invoices WHERE id = ?', [$params['id']]);
        if (!$invoice || !$invoice['pdf_path'] || !file_exists($invoice['pdf_path'])) {
            http_response_code(404); echo 'PDF not found.'; return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="invoice-' . $params['id'] . '.pdf"');
        header('Content-Length: ' . filesize($invoice['pdf_path']));
        readfile($invoice['pdf_path']);
        exit;
    }

    // ─── Razorpay payment link ────────────────────────────────────────────────

    public function razorpayLink(array $params = []): void
    {
        $this->verifyCsrf();

        $invoice  = DB::row('SELECT * FROM rent_invoices WHERE id = ?', [$params['id']]);
        if (!$invoice) { $this->json(['ok' => false, 'error' => 'Invoice not found'], 404); return; }

        $property = DB::row('SELECT * FROM properties LIMIT 1');
        $keyId    = $property['razorpay_key_id']     ?? '';
        $secret   = $property['razorpay_key_secret'] ?? '';

        // Decrypt secret
        if ($secret && defined('ENCRYPT_KEY')) {
            $secret = $this->decryptSecret($secret);
        }

        if (!$keyId || !$secret) {
            $this->json(['ok' => false, 'error' => 'Razorpay keys not configured in Settings.'], 422);
            return;
        }

        $tenancy = DB::row("
            SELECT t.full_name, t.phone, t.email
            FROM tenancies te
            JOIN tenants t ON t.id = te.tenant_id
            WHERE te.id = ?
        ", [$invoice['tenancy_id']]);

        $balance    = (float)$invoice['amount_due'] - (float)$invoice['amount_paid'];
        $amountPaise = (int)round($balance * 100); // Razorpay uses paise

        $payload = [
            'amount'      => $amountPaise,
            'currency'    => 'INR',
            'accept_partial' => false,
            'description' => 'Rent for ' . date('M Y', strtotime($invoice['period_month'])),
            'customer'    => [
                'name'  => $tenancy['full_name']  ?? 'Tenant',
                'email' => $tenancy['email']       ?? '',
                'contact' => preg_replace('/\D/', '', $tenancy['phone'] ?? ''),
            ],
            'notify'      => ['sms' => true, 'email' => (bool)($tenancy['email'] ?? false)],
            'reminder_enable' => true,
            'notes'       => ['invoice_id' => $params['id']],
            'callback_url'=> rtrim($_ENV['APP_URL'] ?? '', '/') . '/payments/razorpay/callback',
            'callback_method' => 'get',
        ];

        $ch = curl_init('https://api.razorpay.com/v1/payment_links');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => "{$keyId}:{$secret}",
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            $err = json_decode($resp, true)['error']['description'] ?? 'Razorpay API error';
            $this->json(['ok' => false, 'error' => $err], 502);
            return;
        }

        $data = json_decode($resp, true);
        $link = $data['short_url'] ?? $data['id'] ?? '';

        if ($link) {
            DB::update('rent_invoices', ['razorpay_link' => $link], 'id = ?', [$params['id']]);
            AuditLog::record('razorpay_link_created', 'rent_invoices', $params['id'], ['link' => $link]);
        }

        $this->json(['ok' => true, 'url' => $link, 'amount' => $balance]);
    }

    // ─── PDF generator ────────────────────────────────────────────────────────

    private function generatePdf(
        string $invoiceId,
        array  $tenancy,
        array  $property,
        string $month,
        array  $amounts
    ): string {
        require_once ROOT . '/vendor/autoload.php';

        $dir = ROOT . '/storage/invoices';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $path = "{$dir}/invoice-{$invoiceId}.pdf";

        $fmt = fn(float $v): string => 'Rs.' . number_format($v, 2);
        $monthLabel = date('F Y', strtotime($month . '-01'));
        $propName   = htmlspecialchars($property['name'] ?? 'RentOps Property');
        $propAddr   = nl2br(htmlspecialchars($property['address'] ?? ''));
        $tenantName = htmlspecialchars($tenancy['full_name']);
        $room       = htmlspecialchars($tenancy['room_number']);
        $phone      = htmlspecialchars($tenancy['phone']);
        $dueDate    = date('d M Y', strtotime($amounts['due_date']));
        $ebUnitPrice = (float)($property['eb_unit_price'] ?? 0);
        $gstRate    = $amounts['gst_rate'];

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size:12px; color:#1a1a1a; }
  .page { padding:40px 44px; }
  .header { border-bottom:3px solid #0F6E56; padding-bottom:18px; margin-bottom:24px; display:table; width:100%; }
  .header-left { display:table-cell; vertical-align:top; width:60%; }
  .header-right { display:table-cell; vertical-align:top; text-align:right; }
  .prop-name { font-size:20px; font-weight:bold; color:#0F6E56; }
  .prop-addr { font-size:11px; color:#666; margin-top:4px; line-height:1.5; }
  .invoice-label { font-size:22px; font-weight:bold; color:#1a1a1a; }
  .invoice-meta { font-size:11px; color:#555; margin-top:6px; }
  .invoice-no { font-size:12px; color:#0F6E56; font-weight:bold; }
  .section { margin-bottom:20px; }
  .section-title { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#888; margin-bottom:8px; font-weight:bold; border-bottom:1px solid #e8e8e8; padding-bottom:4px; }
  .info-table { width:100%; }
  .info-table td { padding:5px 0; font-size:12px; vertical-align:top; }
  .info-table td:first-child { color:#555; width:130px; }
  .info-table td:last-child { font-weight:600; }
  table.line-items { width:100%; border-collapse:collapse; margin-bottom:0; }
  table.line-items th { background:#0F6E56; color:#fff; padding:9px 12px; text-align:left; font-size:11px; font-weight:600; }
  table.line-items th.right { text-align:right; }
  table.line-items td { padding:9px 12px; border-bottom:1px solid #f0f0f0; font-size:12px; }
  table.line-items td.right { text-align:right; }
  table.line-items tr:last-child td { border-bottom:none; }
  table.line-items .subtotal td { background:#f9f9f9; }
  .total-box { background:#0F6E56; color:#fff; padding:14px 16px; margin-top:0; display:table; width:100%; }
  .total-box .label { display:table-cell; font-size:14px; font-weight:bold; }
  .total-box .value { display:table-cell; text-align:right; font-size:18px; font-weight:bold; }
  .due-box { background:#FFF8E7; border:1px solid #F59E0B; border-radius:4px; padding:10px 14px; margin-top:14px; font-size:12px; color:#92400E; }
  .footer { margin-top:32px; border-top:1px solid #e8e8e8; padding-top:14px; font-size:10px; color:#aaa; text-align:center; }
  .badge { display:inline-block; background:#FEE2E2; color:#991B1B; border-radius:3px; padding:2px 7px; font-size:10px; font-weight:bold; }
  .badge-green { background:#DCFCE7; color:#166534; }
</style>
</head>
<body>
<div class="page">

  <!-- Header -->
  <div class="header">
    <div class="header-left">
      <div class="prop-name">{$propName}</div>
      <div class="prop-addr">{$propAddr}</div>
    </div>
    <div class="header-right">
      <div class="invoice-label">INVOICE</div>
      <div class="invoice-no">#{$invoiceId}</div>
      <div class="invoice-meta">Period: <strong>{$monthLabel}</strong></div>
      <div class="invoice-meta">Generated: <strong>{$dueDate}</strong></div>
    </div>
  </div>

  <!-- Tenant info -->
  <div class="section">
    <div class="section-title">Bill To</div>
    <table class="info-table">
      <tr><td>Tenant</td><td>{$tenantName}</td></tr>
      <tr><td>Room</td><td>{$room}</td></tr>
      <tr><td>Phone</td><td>{$phone}</td></tr>
      <tr><td>Due Date</td><td>{$dueDate}</td></tr>
    </table>
  </div>

  <!-- Line items -->
  <div class="section">
    <div class="section-title">Charges</div>
    <table class="line-items">
      <thead>
        <tr>
          <th>Description</th>
          <th class="right">Qty / Units</th>
          <th class="right">Rate</th>
          <th class="right">Amount</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Rent — {$monthLabel}</td>
          <td class="right">1 month</td>
          <td class="right">{$fmt($amounts['rent'])}</td>
          <td class="right">{$fmt($amounts['rent'])}</td>
        </tr>
        <tr class="subtotal">
          <td style="color:#555;font-size:11px">  GST @ {$gstRate}% on Rent</td>
          <td class="right" style="color:#555;font-size:11px">—</td>
          <td class="right" style="color:#555;font-size:11px">{$gstRate}%</td>
          <td class="right" style="color:#0F6E56;font-weight:600">{$fmt($amounts['rent_gst'])}</td>
        </tr>
HTML;

        if ($amounts['eb_units'] > 0) {
            $html .= <<<HTML
        <tr>
          <td>Electricity (EB)</td>
          <td class="right">{$amounts['eb_units']} units</td>
          <td class="right">{$fmt($ebUnitPrice)} / unit</td>
          <td class="right">{$fmt($amounts['eb_amount'])}</td>
        </tr>
HTML;
        }

        if ($amounts['other_charges'] > 0) {
            $desc = htmlspecialchars($amounts['other_desc'] ?: 'Other charges');
            $html .= <<<HTML
        <tr>
          <td>{$desc}</td>
          <td class="right">—</td>
          <td class="right">—</td>
          <td class="right">{$fmt($amounts['other_charges'])}</td>
        </tr>
HTML;
        }

        $html .= <<<HTML
      </tbody>
    </table>
    <div class="total-box">
      <div class="label">Total Amount Due</div>
      <div class="value">{$fmt($amounts['total'])}</div>
    </div>
    <div class="due-box">
      Payment due by <strong>{$dueDate}</strong>. Please pay on time to avoid late fees.
    </div>
  </div>

  <div class="footer">
    This is a computer-generated invoice. No signature required. &nbsp;|&nbsp; {$propName}
  </div>
</div>
</body>
</html>
HTML;

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 150);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($path, $dompdf->output());

        return $path;
    }

    private function decryptSecret(string $encrypted): string
    {
        $key = base64_decode($_ENV['ENCRYPT_KEY'] ?? '');
        if (!$key) return $encrypted;
        $data   = base64_decode($encrypted);
        $iv     = substr($data, 0, 16);
        $cipher = substr($data, 16);
        return openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv) ?: $encrypted;
    }
}
