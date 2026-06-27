<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\RentEngine;
use App\Helpers\UuidHelper;

class ImportController extends BaseController
{
    private const REQUIRED_COLS = ['full_name', 'phone', 'room_number', 'move_in_date', 'agreed_rent', 'security_deposit'];

    public function index(array $params = []): void
    {
        $this->render('import/index', [
            'pageTitle' => 'Bulk Import',
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
            'csrf'      => $this->csrfToken(),
        ]);
    }

    public function preview(array $params = []): void
    {
        $this->verifyCsrf();
        $parsed = $this->parseCsv();
        if (isset($parsed['error'])) {
            $this->redirect('/import', $parsed['error'], 'error');
            return;
        }

        // Dry-run: validate each row without writing
        $rows   = $parsed['rows'];
        $errors = [];
        $valid  = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // 1-indexed + header
            $e      = $this->validateRow($row, $rowNum);
            if ($e) {
                $errors[] = $e;
            } else {
                $valid[] = $row;
            }
        }

        // FIX B22: Storing large arrays in $_SESSION can exceed PHP's session file
        // size limit (~1MB default) for imports of 100+ rows with many fields.
        // Write validated rows to a temp file keyed by session ID instead, and store
        // only the file path + row count in the session. The file is cleaned up in
        // confirm() whether or not the import succeeds.
        $tmpFile = sys_get_temp_dir() . '/rentops_import_' . session_id() . '.json';
        file_put_contents($tmpFile, json_encode($valid));

        $_SESSION['import_tmp_file']  = $tmpFile;
        $_SESSION['import_row_count'] = count($valid);
        $_SESSION['import_errors']    = $errors; // errors are small strings, safe in session

        $this->render('import/preview', [
            'pageTitle' => 'Import Preview',
            'valid'     => $valid,
            'errors'    => $errors,
            'user'      => $this->currentUser(),
            'csrf'      => $this->csrfToken(),
        ]);
    }

    public function confirm(array $params = []): void
    {
        $this->verifyCsrf();

        // FIX B22: Read rows from temp file, not session
        $tmpFile = $_SESSION['import_tmp_file'] ?? null;

        // Always clean up session keys — even on error paths below
        unset($_SESSION['import_tmp_file'], $_SESSION['import_row_count'], $_SESSION['import_errors']);

        if (!$tmpFile || !file_exists($tmpFile)) {
            $this->redirect('/import', 'No rows to import. Upload CSV again.', 'error');
            return;
        }

        $rows = json_decode(file_get_contents($tmpFile), true) ?? [];
        @unlink($tmpFile); // delete temp file immediately after reading

        if (empty($rows)) {
            $this->redirect('/import', 'No valid rows found. Upload CSV again.', 'error');
            return;
        }

        $imported = 0;
        $skipped  = 0;
        $failLog  = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $result = $this->importRow($row);
                if ($result === 'imported') $imported++;
                elseif ($result === 'skipped') $skipped++;
                else $failLog[] = $result;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            $this->redirect('/import', 'Import failed: ' . $e->getMessage(), 'error');
            return;
        }

        $msg = "Import complete — {$imported} imported, {$skipped} skipped (already exist).";
        if ($failLog) $msg .= ' ' . count($failLog) . ' rows had errors.';

        $this->redirect('/tenants', $msg);
    }

    // ─── Private helpers ───────────────────────────────────────────────────────

    private function parseCsv(): array
    {
        if (empty($_FILES['csv']['tmp_name'])) {
            return ['error' => 'No file uploaded.'];
        }

        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$fh) return ['error' => 'Could not read uploaded file.'];

        $header = fgetcsv($fh);
        if (!$header) { fclose($fh); return ['error' => 'CSV appears empty.']; }

        // Normalise header keys
        $header = array_map(fn($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);

        // Check required columns
        $missing = array_diff(self::REQUIRED_COLS, $header);
        if ($missing) {
            fclose($fh);
            return ['error' => 'Missing columns: ' . implode(', ', $missing)];
        }

        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if (count($line) === count($header)) {
                $row = array_combine($header, $line);
                if (array_filter($row)) $rows[] = array_map('trim', $row); // skip blank rows
            }
        }
        fclose($fh);

        if (empty($rows)) return ['error' => 'CSV has no data rows.'];

        return ['rows' => $rows];
    }

    private function validateRow(array $row, int $rowNum): ?string
    {
        foreach (self::REQUIRED_COLS as $col) {
            if (empty($row[$col])) {
                return "Row {$rowNum}: missing '{$col}'.";
            }
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['move_in_date'])) {
            return "Row {$rowNum}: move_in_date must be YYYY-MM-DD (got '{$row['move_in_date']}').";
        }

        if (!is_numeric($row['agreed_rent']) || (float)$row['agreed_rent'] <= 0) {
            return "Row {$rowNum}: agreed_rent must be a positive number.";
        }

        $room = DB::row('SELECT id FROM rooms WHERE room_number = ?', [$row['room_number']]);
        if (!$room) {
            return "Row {$rowNum}: room '{$row['room_number']}' not found.";
        }

        return null;
    }

    private function importRow(array $row): string
    {
        // Check if tenant with same phone already exists
        $existing = DB::row('SELECT id FROM tenants WHERE phone = ?', [$row['phone']]);
        $tenantId = $existing ? $existing['id'] : UuidHelper::v4();

        if (!$existing) {
            DB::insert('tenants', [
                'id'                => $tenantId,
                'full_name'         => $row['full_name'],
                'phone'             => $row['phone'],
                'email'             => $row['email']             ?? '',
                'id_proof_type'     => $row['id_proof_type']     ?? 'Aadhaar',
                'id_proof_number'   => $row['id_proof_number']   ?? '',
                'emergency_contact' => $row['emergency_contact'] ?? '',
                'status'            => 'active',
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
        }

        $room = DB::row('SELECT * FROM rooms WHERE room_number = ?', [$row['room_number']]);

        // Skip if active tenancy already exists for this tenant+room
        $activeTenancy = DB::row(
            "SELECT id FROM tenancies WHERE tenant_id = ? AND room_id = ? AND status = 'active'",
            [$tenantId, $room['id']]
        );
        if ($activeTenancy) return 'skipped';

        $tenancyId = UuidHelper::v4();
        $dueDay    = (int)($row['rent_due_day'] ?? 5);

        DB::insert('tenancies', [
            'id'               => $tenancyId,
            'tenant_id'        => $tenantId,
            'room_id'          => $room['id'],
            'move_in_date'     => $row['move_in_date'],
            'move_out_date'    => null,
            'agreed_rent'      => (float)$row['agreed_rent'],
            'security_deposit' => (float)($row['security_deposit'] ?? 0),
            'rent_due_day'     => $dueDay,
            'status'           => 'active',
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        // Update room status
        $newStatus = in_array($room['room_type'], ['sharing', 'dorm']) ? 'partially_occupied' : 'occupied';
        DB::update('rooms', ['status' => $newStatus], 'id = ?', [$room['id']]);

        // Generate first invoice (pro-rata)
        (new RentEngine())->generateFirstInvoice($tenancyId);

        // Generate invoices for any past months up to current month
        $this->backfillInvoices($tenancyId, $row['move_in_date'], (int)$dueDay);

        return 'imported';
    }

    /**
     * Generate invoices for every full month between move_in and now,
     * so historical data is complete. Skips if already exists (idempotent).
     *
     * FIX B08: The previous code unconditionally started backfill from move_in+1month,
     * assuming generateFirstInvoice() always handles the move-in month. That assumption
     * breaks when move-in is exactly the 1st of the month — generateFirstInvoice() may
     * still produce a full-month invoice, but the idempotency guard inside
     * generateMonthlyInvoicesForTenancy() means the backfill would harmlessly skip it
     * anyway. The real bug: if generateFirstInvoice() somehow didn't create the invoice
     * (e.g. a race condition or exception caught upstream), the move-in month had no
     * coverage and the gap was invisible.
     *
     * Fix: start backfill from the move-in month itself. generateMonthlyInvoicesForTenancy()
     * is idempotent — it skips the month if an invoice already exists — so starting
     * one month earlier costs nothing and closes the gap.
     */
    private function backfillInvoices(string $tenancyId, string $moveInDate, int $dueDay): void
    {
        $engine  = new RentEngine();
        // Start from the move-in month (not +1). generateMonthlyInvoicesForTenancy
        // is idempotent so it safely skips the first month when already created.
        $start   = new \DateTimeImmutable(date('Y-m-01', strtotime($moveInDate)));
        $current = new \DateTimeImmutable(date('Y-m-01'));

        while ($start <= $current) {
            $engine->generateMonthlyInvoicesForTenancy($tenancyId, $start->format('Y-m'));
            $start = $start->modify('+1 month');
        }
    }
}
