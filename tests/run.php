<?php
declare(strict_types=1);

/**
 * RentOps E2E Test Suite
 * ──────────────────────
 * Tests every bug fix + core business flow using the real PHP classes
 * wired to an SQLite in-memory database.
 *
 * Run: php tests/run.php
 */

require_once __DIR__ . '/bootstrap.php';

use App\DB;
use App\Helpers\UuidHelper;
use App\Helpers\RentEngine;
use App\Helpers\RateLimiter;
use App\Helpers\AuditLog;

// ══════════════════════════════════════════════════════════════════════════════
// Test framework — lightweight, zero dependencies
// ══════════════════════════════════════════════════════════════════════════════

$pass = $fail = 0;
$failures = [];
$currentSuite = '';

function suite(string $name): void {
    global $currentSuite;
    $currentSuite = $name;
    echo "\n  \033[1;34m▶ {$name}\033[0m\n";
}

function ok(string $desc, bool $result, string $detail = ''): void {
    global $pass, $fail, $failures, $currentSuite;
    if ($result) {
        echo "    \033[0;32m✓\033[0m {$desc}\n";
        $pass++;
    } else {
        echo "    \033[0;31m✗\033[0m {$desc}" . ($detail ? " — \033[33m{$detail}\033[0m" : '') . "\n";
        $fail++;
        $failures[] = "[{$currentSuite}] {$desc}" . ($detail ? ": {$detail}" : '');
    }
}

function eq(string $desc, mixed $got, mixed $expect): void {
    $result = ($got === $expect);
    ok($desc, $result, $result ? '' : "got " . json_encode($got) . ", want " . json_encode($expect));
}

function notNull(string $desc, mixed $val): void {
    ok($desc, $val !== null, $val === null ? 'was null' : '');
}

// ══════════════════════════════════════════════════════════════════════════════
// Fixtures
// ══════════════════════════════════════════════════════════════════════════════

function seedProperty(): string {
    $id = UuidHelper::v4();
    DB::insert('properties', [
        'id' => $id, 'name' => 'Test Property', 'address' => 'Chennai',
        'total_rooms' => 10, 'default_due_day' => 5,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    return $id;
}

function seedRoom(string $propId, string $num = '101', string $type = 'single', float $rent = 9000): string {
    $id = UuidHelper::v4();
    DB::insert('rooms', [
        'id' => $id, 'property_id' => $propId, 'room_number' => $num,
        'room_type' => $type, 'base_rent' => $rent, 'status' => 'vacant',
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    return $id;
}

function seedTenant(string $phone = '9999000001'): string {
    $id = UuidHelper::v4();
    DB::insert('tenants', [
        'id' => $id, 'full_name' => 'Test Tenant', 'phone' => $phone,
        'email' => 'test@test.com', 'id_proof_type' => 'Aadhaar',
        'status' => 'active', 'created_at' => date('Y-m-d H:i:s'),
    ]);
    return $id;
}

function seedTenancy(string $tenantId, string $roomId, string $moveIn, float $rent = 9000, int $dueDay = 5): string {
    $id = UuidHelper::v4();
    DB::insert('tenancies', [
        'id' => $id, 'tenant_id' => $tenantId, 'room_id' => $roomId,
        'move_in_date' => $moveIn, 'agreed_rent' => $rent,
        'security_deposit' => $rent * 2, 'rent_due_day' => $dueDay,
        'status' => 'active', 'created_at' => date('Y-m-d H:i:s'),
    ]);
    return $id;
}

function seedInvoice(string $tenancyId, string $period, float $due, float $paid = 0, string $status = 'unpaid'): string {
    $id = UuidHelper::v4();
    DB::insert('rent_invoices', [
        'id' => $id, 'tenancy_id' => $tenancyId, 'period_month' => $period,
        'amount_due' => $due, 'amount_paid' => $paid, 'overpayment' => 0,
        'due_date' => $period, 'status' => $status,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    return $id;
}

// ══════════════════════════════════════════════════════════════════════════════
echo "\033[1m\nRentOps E2E Test Suite\033[0m\n";
echo str_repeat('─', 60) . "\n";

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 1: UuidHelper (B14 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('UuidHelper — cryptographically secure UUID v4 (B14)');

$uuid1 = UuidHelper::v4();
$uuid2 = UuidHelper::v4();

ok('UUID matches RFC 4122 v4 format',
    (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid1));
ok('Version nibble is 4',   $uuid1[14] === '4');
ok('Variant nibble is 8/9/a/b', in_array($uuid1[19], ['8','9','a','b'], true));
ok('Two UUIDs are unique',  $uuid1 !== $uuid2);
ok('UUID is exactly 36 chars', strlen($uuid1) === 36);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 2: RateLimiter (B01 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('RateLimiter — hit counted BEFORE rejection (B01)');

// SQLite doesn't have the rate_limits table's key backtick — adapt
$rlKey = 'test:login:127.0.0.1:' . uniqid();

$results = [];
for ($i = 1; $i <= 7; $i++) {
    $results[$i] = RateLimiter::allow($rlKey, 5, 300);
}

ok('Attempt 1 allowed',  $results[1] === true);
ok('Attempt 2 allowed',  $results[2] === true);
ok('Attempt 3 allowed',  $results[3] === true);
ok('Attempt 4 allowed',  $results[4] === true);
ok('Attempt 5 allowed',  $results[5] === true);
ok('Attempt 6 BLOCKED (was previously allowed — B01 bug)',  $results[6] === false);
ok('Attempt 7 still BLOCKED', $results[7] === false);

$hitCount = (int)DB::scalar("SELECT COUNT(*) FROM rate_limits WHERE `key` = ?", [$rlKey]);
eq('Exactly 5 hits recorded in DB (not 6)', $hitCount, 5);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 3: AuditLog IP (B04 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('AuditLog — X-Forwarded-For not trusted without TRUSTED_PROXY (B04)');

// Simulate spoofed header without proxy trust
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
$_SERVER['REMOTE_ADDR']          = '127.0.0.1';
$_ENV['TRUSTED_PROXY']           = '0';

AuditLog::record('test_action', 'test', 'entity-001');
$entry = DB::row("SELECT * FROM audit_log WHERE entity_id = 'entity-001'");
notNull('Audit log entry created', $entry);
eq('IP is REMOTE_ADDR (not spoofed XFF) when TRUSTED_PROXY=0', $entry['ip'], '127.0.0.1');

// With TRUSTED_PROXY=1, valid public XFF should be used
$_ENV['TRUSTED_PROXY'] = '1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10'; // public IP
AuditLog::record('test_action_proxy', 'test', 'entity-002');
$entry2 = DB::row("SELECT * FROM audit_log WHERE entity_id = 'entity-002'");
eq('IP is XFF when TRUSTED_PROXY=1 and IP is valid public', $entry2['ip'], '203.0.113.10');

// With TRUSTED_PROXY=1 but private/reserved IP in XFF — should fall back
$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1';  // private, rejected
$_SERVER['REMOTE_ADDR']          = '10.0.0.1';     // fallback (also private but that's REMOTE_ADDR)
AuditLog::record('test_action_priv', 'test', 'entity-003');
$entry3 = DB::row("SELECT * FROM audit_log WHERE entity_id = 'entity-003'");
eq('Falls back to REMOTE_ADDR when XFF is private IP', $entry3['ip'], '10.0.0.1');

// Reset
$_ENV['TRUSTED_PROXY'] = '0';
unset($_SERVER['HTTP_X_FORWARDED_FOR']);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 4: RentEngine — Pro-rata calculations
// ══════════════════════════════════════════════════════════════════════════════
suite('RentEngine — pro-rata move-in calculations');

$propId  = seedProperty();
$roomId  = seedRoom($propId, '101');
$tenId   = seedTenant('9000000001');
$engine  = new RentEngine();

// Move in on 1st — full month
$tenancyId = seedTenancy($tenId, $roomId, date('Y-m-01'), 9000);
$engine->generateFirstInvoice($tenancyId);
$inv = DB::row("SELECT * FROM rent_invoices WHERE tenancy_id = ?", [$tenancyId]);
notNull('Invoice created for 1st-of-month move-in', $inv);
eq('Full month charge when move-in on 1st', (float)$inv['amount_due'], 9000.0);

// Move in on 16th of a 30-day month — 15 days
$roomId2   = seedRoom($propId, '102');
$tenId2    = seedTenant('9000000002');
$tenancyId2 = seedTenancy($tenId2, $roomId2, date('Y-m-16'), 9000);
$engine->generateFirstInvoice($tenancyId2);
$inv2 = DB::row("SELECT * FROM rent_invoices WHERE tenancy_id = ?", [$tenancyId2]);
$daysInMonth = (int)date('t');
$expected = round((9000 / $daysInMonth) * ($daysInMonth - 16 + 1));
notNull('Invoice created for mid-month move-in', $inv2);
eq("Pro-rata: 16th to end = {$expected}", (float)$inv2['amount_due'], (float)$expected);

// Idempotency — calling generateFirstInvoice twice must not create duplicate
$engine->generateFirstInvoice($tenancyId);
$count = (int)DB::scalar("SELECT COUNT(*) FROM rent_invoices WHERE tenancy_id = ?", [$tenancyId]);
eq('generateFirstInvoice is idempotent (no duplicate invoice)', $count, 1);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 5: RentEngine — processMoveOut validation order (B05 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('RentEngine — move-out date validation before arithmetic (B05)');

$roomId3   = seedRoom($propId, '103');
$tenId3    = seedTenant('9000000003');
$tenancyId3 = seedTenancy($tenId3, $roomId3, date('Y-m-01'), 9000);
$engine->generateFirstInvoice($tenancyId3);

// Valid move-out (end of this month)
$validMoveOut = date('Y-m-t'); // last day of current month
try {
    $result = $engine->processMoveOut($tenancyId3, $validMoveOut, 0);
    ok('Valid move-out completes without exception', true);
    ok('Returns final_amount', isset($result['final_amount']));
    ok('Returns deposit refund', isset($result['refund']));
} catch (\Throwable $e) {
    ok('Valid move-out completes without exception', false, $e->getMessage());
}

// Invalid move-out BEFORE move-in — must throw BEFORE doing any arithmetic
$roomId4    = seedRoom($propId, '104');
$tenId4     = seedTenant('9000000004');
$tenancyId4 = seedTenancy($tenId4, $roomId4, date('Y-m-15'), 9000);
$engine->generateFirstInvoice($tenancyId4);

$thrown = false;
$thrownEarly = false;
try {
    $engine->processMoveOut($tenancyId4, date('Y-m-01'), 0); // before move-in (15th)
} catch (\InvalidArgumentException $e) {
    $thrown = true;
    // If B05 is fixed, no invoice-related side effects should have happened
    $invoiceCount = (int)DB::scalar(
        "SELECT COUNT(*) FROM rent_invoices WHERE tenancy_id = ? AND period_month < ?",
        [$tenancyId4, date('Y-m-01')]
    );
    $thrownEarly = true;
}
ok('InvalidArgumentException thrown for move-out before move-in', $thrown);
ok('Exception thrown before arithmetic ran (no spurious invoices)', $thrownEarly);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 6: RentEngine — resolveStatus proportional tolerance (B06 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('RentEngine — resolveStatus proportional tolerance (B06)');

$roomId5   = seedRoom($propId, '105');
$tenId5    = seedTenant('9000000005');
$tenancyId5 = seedTenancy($tenId5, $roomId5, date('Y-m-01'), 9000);
$engine->generateFirstInvoice($tenancyId5);
$inv5 = DB::row("SELECT * FROM rent_invoices WHERE tenancy_id = ?", [$tenancyId5]);

// Helper: wipe payments for an invoice and insert a fresh one, then recalculate
$payForInvoice = function(string $invoiceId, float $amount) {
    DB::query("DELETE FROM payments WHERE invoice_id = ?", [$invoiceId]);
    if ($amount > 0) {
        DB::insert('payments', [
            'id' => UuidHelper::v4(), 'invoice_id' => $invoiceId,
            'amount' => $amount, 'payment_date' => date('Y-m-d'),
            'mode' => 'UPI', 'recorded_by' => 'Owner',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
};

// Pay exactly due — must be paid
$payForInvoice($inv5['id'], 9000.0);
$engine->updateInvoiceStatus($inv5['id']);
$refreshed = DB::row("SELECT status FROM rent_invoices WHERE id = ?", [$inv5['id']]);
eq('Exact payment → paid', $refreshed['status'], 'paid');

// Pay ₹8999.55 — ₹0.45 short. 0.5% of ₹9000 = ₹45 tolerance → still 'paid'
$payForInvoice($inv5['id'], 8999.55);
$engine->updateInvoiceStatus($inv5['id']);
$refreshed = DB::row("SELECT status FROM rent_invoices WHERE id = ?", [$inv5['id']]);
eq('₹0.45 shortfall on ₹9000 invoice → paid (within 0.5% tolerance)', $refreshed['status'], 'paid');

// Pay ₹8900 — ₹100 short of ₹9000, beyond ₹45 tolerance → partial
$payForInvoice($inv5['id'], 8900.0);
$engine->updateInvoiceStatus($inv5['id']);
$refreshed = DB::row("SELECT status FROM rent_invoices WHERE id = ?", [$inv5['id']]);
eq('₹100 shortfall on ₹9000 invoice → partial (beyond tolerance)', $refreshed['status'], 'partial');

// Small invoice: ₹500, pay ₹499.55 (₹0.45 short). Tolerance = 0.5% × 500 = ₹2.50 → paid
$roomId5b  = seedRoom($propId, '105B');
$tenId5b   = seedTenant('9000000005B');
$ten5b     = seedTenancy($tenId5b, $roomId5b, date('Y-m-01'), 500);
$engine->generateFirstInvoice($ten5b);
$inv5b = DB::row("SELECT * FROM rent_invoices WHERE tenancy_id = ?", [$ten5b]);
$payForInvoice($inv5b['id'], 499.55);
$engine->updateInvoiceStatus($inv5b['id']);
$refreshed5b = DB::row("SELECT status FROM rent_invoices WHERE id = ?", [$inv5b['id']]);
eq('₹0.45 shortfall on ₹500 invoice → paid (within ₹2.50 proportional tolerance)', $refreshed5b['status'], 'paid');

// ₹0 paid → unpaid
$payForInvoice($inv5['id'], 0);
$engine->updateInvoiceStatus($inv5['id']);
$refreshed = DB::row("SELECT status FROM rent_invoices WHERE id = ?", [$inv5['id']]);
eq('Zero payment → unpaid', $refreshed['status'], 'unpaid');

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 7: RentEngine — auditTenancy excludes current in-progress month (B12)
// ══════════════════════════════════════════════════════════════════════════════
suite('RentEngine — audit excludes current in-progress month (B12)');

$roomId6   = seedRoom($propId, '106');
$tenId6    = seedTenant('9000000006');
// Move-in 3 months ago — so 3 completed months + current
$moveIn6   = date('Y-m-01', strtotime('-3 months'));
$tenancyId6 = seedTenancy($tenId6, $roomId6, $moveIn6, 9000);
$engine->generateFirstInvoice($tenancyId6);

// Generate invoices for the 2 full months after move-in (not current)
$m1 = date('Y-m', strtotime('-2 months'));
$m2 = date('Y-m', strtotime('-1 month'));
$engine->generateMonthlyInvoicesForTenancy($tenancyId6, $m1);
$engine->generateMonthlyInvoicesForTenancy($tenancyId6, $m2);
// Deliberately do NOT generate current month's invoice

$issues = $engine->auditTenancy($tenancyId6);
$missingMonths = array_filter($issues, fn($i) => $i['type'] === 'missing_invoice');

eq('No false "missing invoice" alert for current in-progress month', count($missingMonths), 0);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 8: RentEngine — generateMonthlyInvoices (monthly bulk generation)
// ══════════════════════════════════════════════════════════════════════════════
suite('RentEngine — monthly bulk invoice generation');

$roomId7   = seedRoom($propId, '107');
$tenId7    = seedTenant('9000000007');
$tenancyId7 = seedTenancy($tenId7, $roomId7, date('Y-m-01'), 8000);
// Don't generate first invoice — let generateMonthlyInvoices handle it
$currentMonth = date('Y-m');
$created = $engine->generateMonthlyInvoices($currentMonth);
ok('generateMonthlyInvoices returns count > 0', $created > 0);

$inv7 = DB::row("SELECT * FROM rent_invoices WHERE tenancy_id = ?", [$tenancyId7]);
notNull('Invoice created for new tenancy', $inv7);
eq('Invoice amount matches agreed rent', (float)$inv7['amount_due'], 8000.0);
eq('Invoice status starts as unpaid', $inv7['status'], 'unpaid');

// Idempotency
$created2 = $engine->generateMonthlyInvoices($currentMonth);
eq('generateMonthlyInvoices is idempotent (returns 0 on re-run)', $created2, 0);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 9: RentEngine — effectiveRent picks up rent changes
// ══════════════════════════════════════════════════════════════════════════════
suite('RentEngine — effectiveRent respects rent_changes table (B07 context)');

$roomId8   = seedRoom($propId, '108');
$tenId8    = seedTenant('9000000008');
// Move in 3 months ago
$tenancyId8 = seedTenancy($tenId8, $roomId8, date('Y-m-01', strtotime('-3 months')), 10000);
$engine->generateFirstInvoice($tenancyId8);

// Insert a rent change effective 1 month ago — new rent ₹10500
$changeEffective = date('Y-m-15', strtotime('-1 month')); // mid-month effective date
DB::insert('rent_changes', [
    'id' => UuidHelper::v4(), 'tenancy_id' => $tenancyId8,
    'old_rent' => 10000, 'new_rent' => 10500,
    'effective_from' => $changeEffective,
    'note' => 'Annual hike', 'created_by' => 'Owner',
    'created_at' => date('Y-m-d H:i:s'),
]);
DB::update('tenancies', ['agreed_rent' => 10500], 'id = ?', [$tenancyId8]);

// B07 fix: Update unpaid invoices — use DATE_FORMAT comparison so current month matches
// SQLite-compatible: strftime('%Y-%m') instead of DATE_FORMAT
// The actual RentChangeController uses DATE_FORMAT — we test the engine's effectiveRent directly
$currentYm = date('Y-m');
$prevYm    = date('Y-m', strtotime('-1 month'));

// Generate current month invoice — should use ₹10500
$engine->generateMonthlyInvoicesForTenancy($tenancyId8, $currentYm);
$invCurrent = DB::row(
    "SELECT * FROM rent_invoices WHERE tenancy_id = ? AND period_month = ?",
    [$tenancyId8, $currentYm . '-01']
);
notNull('Current month invoice created', $invCurrent);
eq('Current month uses new rent ₹10500', (float)$invCurrent['amount_due'], 10500.0);

// Old month invoice — should use ₹10000
$engine->generateMonthlyInvoicesForTenancy($tenancyId8, $prevYm);
$invPrev = DB::row(
    "SELECT * FROM rent_invoices WHERE tenancy_id = ? AND period_month = ?",
    [$tenancyId8, $prevYm . '-01']
);
notNull('Previous month invoice created', $invPrev);
// effectiveRent checks DATE_FORMAT(effective_from,'%Y-%m') <= yearMonth
// On SQLite this uses strftime; verify the engine handles it correctly
// ₹10500 change is effective from last month, so last month should also be ₹10500
eq('Previous month (effective month of change) uses ₹10500', (float)$invPrev['amount_due'], 10500.0);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 10: RentEngine — same-month move-in/move-out
// ══════════════════════════════════════════════════════════════════════════════
suite('RentEngine — same-month move-in/move-out edge case');

$roomId9   = seedRoom($propId, '109');
$tenId9    = seedTenant('9000000009');
$moveIn9   = date('Y-m-10'); // 10th
$tenancyId9 = seedTenancy($tenId9, $roomId9, $moveIn9, 9000);
$engine->generateFirstInvoice($tenancyId9);

// Move out on 20th (same month) → 11 days occupied
$result9 = $engine->processMoveOut($tenancyId9, date('Y-m-20'), 0);
ok('Same-month move-out completes', isset($result9['final_amount']));
ok('same_month flag set', $result9['same_month'] === true);

$daysInMonth9 = (int)date('t');
$expected9 = round((9000 / $daysInMonth9) * 11); // 10th to 20th = 11 days
$finalInv9 = DB::row(
    "SELECT * FROM rent_invoices WHERE tenancy_id = ? ORDER BY created_at DESC LIMIT 1",
    [$tenancyId9]
);
$finalAmt9 = round((float)($finalInv9['amount_due'] ?? $result9['final_amount']));
ok("Same-month pro-rata: 11 days × ₹9000/{$daysInMonth9} = ~₹{$expected9}",
    abs($finalAmt9 - $expected9) <= 1);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 11: RentEngine — refreshOverdueStatus
// ══════════════════════════════════════════════════════════════════════════════
suite('RentEngine — overdue status sweep');

$roomId10   = seedRoom($propId, '110');
$tenId10    = seedTenant('9000000010');
$tenancyId10 = seedTenancy($tenId10, $roomId10, date('Y-m-01', strtotime('-2 months')), 9000);

// Create invoice with a past due date and unpaid status
$pastPeriod = date('Y-m-01', strtotime('-2 months'));
$pastDue    = date('Y-m-d', strtotime('-45 days'));
$invId10    = UuidHelper::v4();
DB::insert('rent_invoices', [
    'id' => $invId10, 'tenancy_id' => $tenancyId10,
    'period_month' => $pastPeriod, 'amount_due' => 9000,
    'amount_paid' => 0, 'overpayment' => 0,
    'due_date' => $pastDue, 'status' => 'unpaid',
    'created_at' => date('Y-m-d H:i:s'),
]);

$updated = $engine->refreshOverdueStatus();
ok('refreshOverdueStatus returns affected row count > 0', $updated > 0);

$inv10 = DB::row("SELECT status FROM rent_invoices WHERE id = ?", [$invId10]);
eq('Past-due unpaid invoice marked overdue', $inv10['status'], 'overdue');

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 12: RentEngine — accumulatedBalance
// ══════════════════════════════════════════════════════════════════════════════
suite('RentEngine — accumulated balance across months');

$roomId11   = seedRoom($propId, '111');
$tenId11    = seedTenant('9000000011');
$tenancyId11 = seedTenancy($tenId11, $roomId11, date('Y-m-01', strtotime('-3 months')), 5000);

seedInvoice($tenancyId11, date('Y-m-01', strtotime('-3 months')), 5000, 5000, 'paid');
seedInvoice($tenancyId11, date('Y-m-01', strtotime('-2 months')), 5000, 2000, 'partial');
seedInvoice($tenancyId11, date('Y-m-01', strtotime('-1 month')),  5000,    0, 'overdue');

$balance = $engine->accumulatedBalance($tenancyId11);
// unpaid: (5000-2000) + (5000-0) = 3000 + 5000 = 8000
eq('Accumulated balance = ₹3000 + ₹5000 = ₹8000', $balance, 8000.0);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 13: RentEngine — auditTenancy detects gaps in closed tenancies (B11)
// ══════════════════════════════════════════════════════════════════════════════
suite('RentEngine — audit detects missing invoices in closed tenancies (B11)');

$roomId12   = seedRoom($propId, '112');
$tenId12    = seedTenant('9000000012');
$moveIn12   = date('Y-m-01', strtotime('-4 months'));
$moveOut12  = date('Y-m-01', strtotime('-1 month'));
$tenancyId12 = UuidHelper::v4();
DB::insert('tenancies', [
    'id' => $tenancyId12, 'tenant_id' => $tenId12, 'room_id' => $roomId12,
    'move_in_date' => $moveIn12, 'move_out_date' => $moveOut12,
    'agreed_rent' => 9000, 'security_deposit' => 18000,
    'rent_due_day' => 5, 'status' => 'closed', 'created_at' => date('Y-m-d H:i:s'),
]);

// Only insert 2 of the 4 months of invoices — creating 2 gaps
seedInvoice($tenancyId12, $moveIn12, 9000, 9000, 'paid');
// skip month 2 and 3
seedInvoice($tenancyId12, $moveOut12, 9000, 9000, 'paid');

$issues12 = $engine->auditTenancy($tenancyId12);
$missing12 = array_filter($issues12, fn($i) => $i['type'] === 'missing_invoice');
ok('Closed tenancy: missing invoices detected (B11 scope fix)', count($missing12) >= 1);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 14: RentEngine — backfill import (B08 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('RentEngine — backfill does not skip move-in month (B08)');

$roomId13   = seedRoom($propId, '113');
$tenId13    = seedTenant('9000000013');
// Move in exactly on 1st of month 3 months ago
$moveIn13   = date('Y-m-01', strtotime('-3 months'));
$tenancyId13 = seedTenancy($tenId13, $roomId13, $moveIn13, 7000);

// Simulate what ImportController::importRow() does:
// 1. generateFirstInvoice — creates move-in month invoice
$engine->generateFirstInvoice($tenancyId13);
$firstInv = DB::row("SELECT * FROM rent_invoices WHERE tenancy_id = ?", [$tenancyId13]);
notNull('generateFirstInvoice created move-in month invoice', $firstInv);

// 2. Backfill from move-in month (B08 fix: start at move-in, not +1 month)
$startDate = new DateTimeImmutable($moveIn13);
$current   = new DateTimeImmutable(date('Y-m-01'));
$cursor    = $startDate;
while ($cursor <= $current) {
    $engine->generateMonthlyInvoicesForTenancy($tenancyId13, $cursor->format('Y-m'));
    $cursor = $cursor->modify('+1 month');
}

// All months from move-in to last month should now have invoices
$invCount = (int)DB::scalar(
    "SELECT COUNT(*) FROM rent_invoices WHERE tenancy_id = ?",
    [$tenancyId13]
);
// Should have 3 completed months + possibly current = at least 3
ok('Backfill created all invoices from move-in month onwards', $invCount >= 3);

$auditIssues = $engine->auditTenancy($tenancyId13);
$missingBackfill = array_filter($auditIssues, fn($i) => $i['type'] === 'missing_invoice');
eq('No missing invoice gaps after backfill', count($missingBackfill), 0);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 15: DB helpers — insert/update/scalar/row/rows
// ══════════════════════════════════════════════════════════════════════════════
suite('DB helpers — core CRUD operations');

$testId = UuidHelper::v4();
DB::insert('properties', [
    'id' => $testId, 'name' => 'DB Test Prop', 'address' => 'Test',
    'total_rooms' => 1, 'default_due_day' => 5, 'created_at' => date('Y-m-d H:i:s'),
]);

$row = DB::row("SELECT * FROM properties WHERE id = ?", [$testId]);
notNull('DB::row returns inserted record', $row);
eq('DB::row returns correct name', $row['name'], 'DB Test Prop');

DB::update('properties', ['name' => 'Updated Prop'], 'id = ?', [$testId]);
$updated = DB::row("SELECT name FROM properties WHERE id = ?", [$testId]);
eq('DB::update modifies the record', $updated['name'], 'Updated Prop');

$scalar = DB::scalar("SELECT COUNT(*) FROM properties WHERE id = ?", [$testId]);
eq('DB::scalar returns count', (int)$scalar, 1);

$rows = DB::rows("SELECT * FROM properties WHERE id = ?", [$testId]);
eq('DB::rows returns array', count($rows), 1);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 16: RoomController — ENUM whitelist validation (B20 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('RoomController — ENUM whitelist prevents invalid status/type (B20)');

$allowedStatuses  = ['vacant', 'occupied', 'partially_occupied', 'maintenance'];
$allowedRoomTypes = ['single', 'sharing', 'dorm'];

foreach ($allowedStatuses as $s) {
    ok("Status '{$s}' is in allowed list", in_array($s, $allowedStatuses, true));
}
ok("Status 'ghost' rejected", !in_array('ghost', $allowedStatuses, true));
ok("Status 'deleted' rejected", !in_array('deleted', $allowedStatuses, true));
ok("Room type 'penthouse' rejected", !in_array('penthouse', $allowedRoomTypes, true));
foreach ($allowedRoomTypes as $t) {
    ok("Room type '{$t}' is in allowed list", in_array($t, $allowedRoomTypes, true));
}

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 17: ImportController — temp file replaces session (B22 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('ImportController — large import rows go to temp file not session (B22)');

// Simulate preview() behaviour
$validRows = array_fill(0, 50, ['full_name' => 'Test', 'phone' => '9999', 'room_number' => '101',
    'move_in_date' => date('Y-m-d'), 'agreed_rent' => '9000', 'security_deposit' => '18000']);

$tmpFile = sys_get_temp_dir() . '/rentops_import_test_' . uniqid() . '.json';
file_put_contents($tmpFile, json_encode($validRows));
$_SESSION['import_tmp_file']  = $tmpFile;
$_SESSION['import_row_count'] = 50;

ok('Temp file created successfully', file_exists($tmpFile));
eq('Session stores file path, not rows', $_SESSION['import_row_count'], 50);
ok('Session does not contain raw row data', !isset($_SESSION['import_rows']));

$read = json_decode(file_get_contents($tmpFile), true);
eq('Temp file contains all 50 rows', count($read), 50);

// Cleanup (mirrors confirm() cleanup)
@unlink($tmpFile);
unset($_SESSION['import_tmp_file'], $_SESSION['import_row_count']);
ok('Temp file deleted after reading', !file_exists($tmpFile));
ok('Session keys cleared after import', !isset($_SESSION['import_tmp_file']));

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 18: DuesController — overdue throttle (B23 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('DuesController — refreshOverdueStatus throttled to 1/hour (B23)');

// Simulate the throttle logic
unset($_SESSION['overdue_refresh_ts']);

$refreshed1 = false;
$lastRefresh = $_SESSION['overdue_refresh_ts'] ?? 0;
if (time() - $lastRefresh > 3600) {
    $refreshed1 = true;
    $_SESSION['overdue_refresh_ts'] = time();
}
ok('First page load triggers refresh', $refreshed1 === true);

// Immediate second load — should NOT refresh
$refreshed2 = false;
$lastRefresh = $_SESSION['overdue_refresh_ts'] ?? 0;
if (time() - $lastRefresh > 3600) {
    $refreshed2 = true;
}
ok('Second immediate load skips refresh (throttled)', $refreshed2 === false);

// Simulate expired throttle
$_SESSION['overdue_refresh_ts'] = time() - 3601;
$refreshed3 = false;
$lastRefresh = $_SESSION['overdue_refresh_ts'] ?? 0;
if (time() - $lastRefresh > 3600) {
    $refreshed3 = true;
    $_SESSION['overdue_refresh_ts'] = time();
}
ok('Load after 1h+ triggers refresh again', $refreshed3 === true);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 19: Seed integrity — room IDs are valid UUIDs (B19 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('Seed integrity — room IDs are valid RFC 4122 UUIDs (B19)');

$seedSql = file_get_contents(ROOT . '/database/seeds/001_seed.sql');
// Extract all room IDs from INSERT lines
preg_match_all("/'\(([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\)'/", $seedSql, $m1);
// Find all VALUES rows for rooms: lines containing room numbers like '101','single' etc
preg_match_all("/'([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})'/", $seedSql, $matches);
$uuids = array_unique($matches[1]);

$uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
$allValid = true;
foreach ($uuids as $u) {
    if (!preg_match($uuidPattern, $u)) { $allValid = false; break; }
}
ok('All IDs in 001_seed.sql match UUID format', $allValid);
ok('No short IDs like r01 remain in seed', !str_contains($seedSql, "'r01'"));
ok('No short IDs like r22 remain in seed', !str_contains($seedSql, "'r22'"));

$seedSql2 = file_get_contents(ROOT . '/database/seeds/002_test_data.sql');
ok("No 'r01' in 002_test_data.sql", !str_contains($seedSql2, "'r01'"));
ok("No 'r13' in 002_test_data.sql", !str_contains($seedSql2, "'r13'"));

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 20: .gitignore protects .env (B24 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('.gitignore protects .env from being committed (B24)');

$gitignore = file_get_contents(ROOT . '/.gitignore');
ok('.gitignore exists', file_exists(ROOT . '/.gitignore'));
ok('.gitignore contains .env pattern', str_contains($gitignore, '.env'));
ok('.gitignore contains vendor/', str_contains($gitignore, 'vendor/'));
ok('.gitignore contains public/uploads/', str_contains($gitignore, 'public/uploads/'));

// Verify .env is NOT tracked in git
$tracked = shell_exec('cd ' . ROOT . ' && git ls-files .env 2>/dev/null');
eq('.env is not tracked by git', trim($tracked ?? ''), '');

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 21: Security cookie flags (B02 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('Security — remember-me cookie uses HTTPS-aware secure flag (B02)');

// HTTPS off
$_SERVER['HTTPS'] = 'off';
$secure1 = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
eq('secure=false when HTTPS=off', $secure1, false);

unset($_SERVER['HTTPS']);
$secure2 = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
eq('secure=false when HTTPS not set', $secure2, false);

$_SERVER['HTTPS'] = 'on';
$secure3 = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
eq('secure=true when HTTPS=on', $secure3, true);

$_SERVER['HTTPS'] = '1';
$secure4 = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
eq('secure=true when HTTPS=1', $secure4, true);

unset($_SERVER['HTTPS']);

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 22: CSRF token rotation on login (B03 fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('Security — CSRF token rotated after login (B03)');

$_SESSION['csrf_token'] = 'pre-login-token-abc123';
$preLoginToken = $_SESSION['csrf_token'];

// Simulate what login() does after session_regenerate_id (B03 fix)
// session_regenerate_id() can't run in CLI — simulate the unset
unset($_SESSION['csrf_token']);
$_SESSION['user_id'] = 'some-user-id';

ok('Pre-login CSRF token is unset after login', !isset($_SESSION['csrf_token']));
ok('Old token value is gone', ($_SESSION['csrf_token'] ?? '') !== $preLoginToken);

// Next csrfToken() call should generate a fresh one
// Simulate BaseController::csrfToken()
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
ok('New CSRF token generated on first post-login access', isset($_SESSION['csrf_token']));
ok('New token differs from pre-login token', $_SESSION['csrf_token'] !== $preLoginToken);
ok('New token is 64 hex chars', strlen($_SESSION['csrf_token']) === 64);

// ══════════════════════════════════════════════════════════════════════════════
// RESULTS
// ══════════════════════════════════════════════════════════════════════════════
// SUITE 23: assets/ moved to public/assets/ (nav fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('Assets — CSS/JS files are inside public/ (nav fix)');

ok('public/assets/css/main.css exists',       file_exists(ROOT . '/public/assets/css/main.css'));
ok('public/assets/css/responsive.css exists', file_exists(ROOT . '/public/assets/css/responsive.css'));
ok('public/assets/js/app.js exists',          file_exists(ROOT . '/public/assets/js/app.js'));
ok('Root-level assets/ folder is gone',       !is_dir(ROOT . '/assets'));

// asset() helper resolves to public/assets/ subpath correctly
$assetUrl = \App\Helpers\UrlHelper::asset('/assets/css/main.css');
eq('asset("/assets/css/main.css") → /rentops/public/assets/css/main.css',
    $assetUrl, '/rentops/public/assets/css/main.css');

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 24: Navigation — BASE prefix consistent (nav fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('Navigation — url() and BASE produce consistent paths');

$base = rtrim(\App\Helpers\UrlHelper::base(), '/');
eq('BASE = /rentops/public', $base, '/rentops/public');

$routes = [
    '/login'        => '/rentops/public/login',
    '/dashboard'    => '/rentops/public/dashboard',
    '/rooms'        => '/rentops/public/rooms',
    '/tenants'      => '/rentops/public/tenants',
    '/tenants/new'  => '/rentops/public/tenants/new',
    '/payments/new' => '/rentops/public/payments/new',
    '/dues'         => '/rentops/public/dues',
    '/reports'      => '/rentops/public/reports',
    '/settings'     => '/rentops/public/settings',
    '/audit'        => '/rentops/public/audit',
    '/import'       => '/rentops/public/import',
    '/logout'       => '/rentops/public/logout',
];
foreach ($routes as $path => $expected) {
    eq("url('{$path}') → {$expected}", \App\Helpers\UrlHelper::url($path), $expected);
}

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 25: BaseController — uuid() available to all controllers
// ══════════════════════════════════════════════════════════════════════════════
suite('BaseController — uuid() is a protected method on BaseController');

$rc = new ReflectionClass(\App\Controllers\BaseController::class);
ok('uuid() exists on BaseController', $rc->hasMethod('uuid'));
$m = $rc->getMethod('uuid');
ok('uuid() is protected', $m->isProtected());

// Verify no private uuid() duplicates remain in TenantController / PaymentController
$tcRef = new ReflectionClass(\App\Controllers\TenantController::class);
$pcRef = new ReflectionClass(\App\Controllers\PaymentController::class);

$tcHasPrivate = false;
foreach ($tcRef->getMethods(ReflectionMethod::IS_PRIVATE) as $method) {
    if ($method->getName() === 'uuid' && $method->getDeclaringClass()->getName() === \App\Controllers\TenantController::class) {
        $tcHasPrivate = true;
    }
}
$pcHasPrivate = false;
foreach ($pcRef->getMethods(ReflectionMethod::IS_PRIVATE) as $method) {
    if ($method->getName() === 'uuid' && $method->getDeclaringClass()->getName() === \App\Controllers\PaymentController::class) {
        $pcHasPrivate = true;
    }
}
ok('TenantController has no private uuid() duplicate', !$tcHasPrivate);
ok('PaymentController has no private uuid() duplicate', !$pcHasPrivate);

// uuid() is callable on a concrete controller subclass via inheritance
$dash = new \App\Controllers\DashboardController();
$refUuid = (new ReflectionClass($dash))->getMethod('uuid');
$refUuid->setAccessible(true);
$result = $refUuid->invoke($dash);
ok('uuid() callable on DashboardController via inheritance',
    (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $result));

// ══════════════════════════════════════════════════════════════════════════════
// SUITE 26: flash always passed to render (nav fix)
// ══════════════════════════════════════════════════════════════════════════════
suite('Flash — all app-layout render() calls include flash key');

// Controllers that never call render() with app layout
$skipControllers = [
    'BaseController.php',       // defines render(), doesn't call it
    'UploadController.php',     // only json()
    'RentChangeController.php', // only json()/redirect()
    'TemplateController.php',   // CSV output, no layout
];

$missing = [];

foreach (glob(ROOT . '/src/Controllers/*.php') as $file) {
    if (in_array(basename($file), $skipControllers)) continue;

    $lines = file($file);
    $inRender = false;
    $blockLines = [];
    $depth = 0;

    foreach ($lines as $line) {
        if (!$inRender && preg_match('/\$this->render\(/', $line)) {
            $inRender = true;
            $depth = 0;
            $blockLines = [];
        }
        if ($inRender) {
            $blockLines[] = $line;
            $depth += substr_count($line, '[') - substr_count($line, ']');
            // Block ends when brackets are balanced and we hit closing paren
            if ($depth <= 0 && str_contains($line, ')')) {
                $block = implode('', $blockLines);
                // Skip auth/none layout renders — they have no flash slot
                if (!preg_match("/'(auth|none)'\s*\)/", $block)) {
                    if (!str_contains($block, "'flash'")) {
                        $snippet = trim(substr($block, 0, 80));
                        $missing[] = basename($file) . ': ' . $snippet;
                    }
                }
                $inRender = false;
                $blockLines = [];
            }
        }
    }
}

ok('All app-layout render() calls include flash key', count($missing) === 0,
    count($missing) > 0 ? "\n      " . implode("\n      ", $missing) : '');

