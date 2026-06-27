<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('TEST_MODE', true);

// ── Autoloader (mirrors public/index.php) ─────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $base = ROOT . '/src/';
    $rel  = str_replace('App\\', '', $class);
    $file = $base . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($file)) require_once $file;
});

// ── Minimal $_ENV so config.php doesn't die ───────────────────────────────────
$_ENV['APP_URL']      = 'http://localhost/rentops/public';
$_ENV['APP_ENV']      = 'testing';
$_ENV['APP_TIMEZONE'] = 'Asia/Kolkata';
$_ENV['DB_HOST']      = 'localhost';
$_ENV['DB_NAME']      = 'rentops_test';
$_ENV['DB_USER']      = 'root';
$_ENV['DB_PASS']      = '';
$_ENV['TRUSTED_PROXY']= '0';

date_default_timezone_set('Asia/Kolkata');

// ── Fake session for controllers that read $_SESSION ─────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    $_SESSION = [];
}

// ── URL helpers stub ─────────────────────────────────────────────────────────
if (!function_exists('url')) {
    function url(string $path): string { return '/rentops/public' . $path; }
}
if (!function_exists('asset')) {
    function asset(string $path): string { return '/rentops/public/assets' . $path; }
}
if (!function_exists('base')) {
    function base(): string { return '/rentops/public'; }
}

// ── Wire real DB class to SQLite in-memory ───────────────────────────────────
// We subclass nothing — instead we replace the PDO connection inside App\DB
// via reflection so all real code paths run unchanged.
require_once ROOT . '/src/db.php';   // defines App\DB but tries to connect — handle below

// Connect App\DB to SQLite
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$ref = new ReflectionClass(\App\DB::class);
$prop = $ref->getProperty('pdo');
$prop->setAccessible(true);
$prop->setValue(null, $pdo);

// ── Create schema in SQLite (MySQL-to-SQLite adapted) ────────────────────────
$pdo->exec("PRAGMA foreign_keys = ON;");

// ── MySQL-compatible UDFs for SQLite ─────────────────────────────────────────
// Production code uses MySQL functions; we shim them so real queries run unchanged.
$pdo->sqliteCreateFunction('NOW',        fn() => date('Y-m-d H:i:s'), 0);
$pdo->sqliteCreateFunction('CURDATE',    fn() => date('Y-m-d'), 0);
$pdo->sqliteCreateFunction('CURTIME',    fn() => date('H:i:s'), 0);
$pdo->sqliteCreateFunction('SYSDATE',    fn() => date('Y-m-d H:i:s'), 0);

$pdo->sqliteCreateFunction('DATE_FORMAT', function(string $date, string $fmt) {
    $ts = strtotime($date);
    $fmt = str_replace(['%Y','%m','%d','%H','%i','%s','%b','%M','%e'],
                       ['Y', 'm', 'd', 'H', 'i', 's',
                        date('M', $ts), date('F', $ts), date('j', $ts)], $fmt);
    return date($fmt, $ts);
}, 2);

$pdo->sqliteCreateFunction('DATE_SUB', function(string $date, string $interval) {
    preg_match('/INTERVAL\s+(\d+)\s+(\w+)/i', $interval, $m);
    [$n, $unit] = [(int)$m[1], strtolower($m[2])];
    return date('Y-m-d H:i:s', strtotime("-{$n} {$unit}", strtotime($date)));
}, 2);

$pdo->sqliteCreateFunction('DATE_ADD', function(string $date, string $interval) {
    preg_match('/INTERVAL\s+(\d+)\s+(\w+)/i', $interval, $m);
    [$n, $unit] = [(int)$m[1], strtolower($m[2])];
    return date('Y-m-d H:i:s', strtotime("+{$n} {$unit}", strtotime($date)));
}, 2);

$pdo->sqliteCreateFunction('LAST_DAY', function(string $date) {
    return date('Y-m-t', strtotime($date));
}, 1);

$pdo->sqliteCreateFunction('DATEDIFF', function(string $a, string $b) {
    return (int)((strtotime($a) - strtotime($b)) / 86400);
}, 2);

$pdo->sqliteCreateFunction('MONTH',  fn($d) => (int)date('m', strtotime($d)), 1);
$pdo->sqliteCreateFunction('YEAR',   fn($d) => (int)date('Y', strtotime($d)), 1);
$pdo->sqliteCreateFunction('DAY',    fn($d) => (int)date('d', strtotime($d)), 1);

$pdo->sqliteCreateFunction('IF', function($cond, $a, $b) {
    return $cond ? $a : $b;
}, 3);

$pdo->sqliteCreateFunction('IFNULL', function($a, $b) {
    return $a ?? $b;
}, 2);

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id            TEXT NOT NULL PRIMARY KEY,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    TEXT NOT NULL,
    selector   TEXT NOT NULL UNIQUE,
    token_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS properties (
    id              TEXT NOT NULL PRIMARY KEY,
    name            TEXT NOT NULL,
    address         TEXT,
    total_rooms     INTEGER NOT NULL DEFAULT 22,
    default_due_day INTEGER NOT NULL DEFAULT 5,
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS rooms (
    id          TEXT NOT NULL PRIMARY KEY,
    property_id TEXT NOT NULL,
    room_number TEXT NOT NULL,
    room_type   TEXT NOT NULL DEFAULT 'single',
    base_rent   REAL NOT NULL DEFAULT 0,
    status      TEXT NOT NULL DEFAULT 'vacant',
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS tenants (
    id                TEXT NOT NULL PRIMARY KEY,
    full_name         TEXT NOT NULL,
    phone             TEXT NOT NULL,
    email             TEXT,
    id_proof_type     TEXT NOT NULL DEFAULT 'Aadhaar',
    id_proof_number   TEXT,
    emergency_contact TEXT,
    status            TEXT NOT NULL DEFAULT 'active',
    created_at        TEXT NOT NULL DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS tenancies (
    id                TEXT NOT NULL PRIMARY KEY,
    tenant_id         TEXT NOT NULL,
    room_id           TEXT NOT NULL,
    move_in_date      TEXT NOT NULL,
    move_out_date     TEXT DEFAULT NULL,
    agreed_rent       REAL NOT NULL,
    security_deposit  REAL NOT NULL DEFAULT 0,
    deposit_deduction REAL DEFAULT NULL,
    deposit_refund    REAL DEFAULT NULL,
    rent_due_day      INTEGER NOT NULL DEFAULT 5,
    status            TEXT NOT NULL DEFAULT 'active',
    created_at        TEXT NOT NULL DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS rent_invoices (
    id           TEXT NOT NULL PRIMARY KEY,
    tenancy_id   TEXT NOT NULL,
    period_month TEXT NOT NULL,
    amount_due   REAL NOT NULL,
    amount_paid  REAL NOT NULL DEFAULT 0,
    overpayment  REAL NOT NULL DEFAULT 0,
    due_date     TEXT NOT NULL,
    status       TEXT NOT NULL DEFAULT 'unpaid',
    notes        TEXT DEFAULT NULL,
    created_at   TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (tenancy_id, period_month)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS payments (
    id           TEXT NOT NULL PRIMARY KEY,
    invoice_id   TEXT NOT NULL,
    amount       REAL NOT NULL,
    payment_date TEXT NOT NULL,
    mode         TEXT NOT NULL DEFAULT 'cash',
    note         TEXT,
    recorded_by  TEXT NOT NULL DEFAULT 'Owner',
    created_at   TEXT NOT NULL DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS rent_changes (
    id             TEXT NOT NULL PRIMARY KEY,
    tenancy_id     TEXT NOT NULL,
    old_rent       REAL NOT NULL,
    new_rent       REAL NOT NULL,
    effective_from TEXT NOT NULL,
    note           TEXT,
    created_by     TEXT NOT NULL DEFAULT 'Owner',
    created_at     TEXT NOT NULL DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    `key`      TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    actor       TEXT NOT NULL DEFAULT 'system',
    action      TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id   TEXT DEFAULT NULL,
    payload     TEXT DEFAULT NULL,
    ip          TEXT DEFAULT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    TEXT NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    used_at    TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
)");
