<?php
declare(strict_types=1);

use App\Helpers\Router;
use App\Helpers\UrlHelper;
use App\Helpers\EnvLoader;

// ── Autoloader ────────────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $base = ROOT . '/src/';
    $rel  = str_replace('App\\', '', $class);
    $file = $base . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($file)) require $file;
});

// ── Load .env ─────────────────────────────────────────────────────────────────
EnvLoader::load(ROOT . '/.env');

// ── Error handling ────────────────────────────────────────────────────────────
require ROOT . '/src/error_handler.php';

// ── Config ────────────────────────────────────────────────────────────────────
$config = require ROOT . '/src/config.php';

// ── URL helper (must come before any output) ──────────────────────────────────
UrlHelper::init($config['app']['url']);

// ── Global shorthand functions ────────────────────────────────────────────────
function url(string $path): string   { return \App\Helpers\UrlHelper::url($path); }
function asset(string $path): string { return \App\Helpers\UrlHelper::asset($path); }
function base(): string              { return \App\Helpers\UrlHelper::base(); }

// ── DB singleton ──────────────────────────────────────────────────────────────
require ROOT . '/src/db.php';

// ── Session ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('rentops_sess');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ]);
}

// ── Content-Security-Policy ──────────────────────────────────────────────────
// Fixes: inline JS (onclick/onchange/<script> bodies without a nonce) is a
// systemic XSS risk — any reflected/stored HTML injection becomes executable
// code. This nonce is generated fresh per-request and is the ONLY way an
// inline <script> tag will execute; every page-specific script must now live
// in an external .js file under public/assets/js/, loaded via <script src>,
// which the CSP allows from 'self' without a nonce. Inline onclick="..."
// attributes are blocked outright — there is no nonce mechanism for them,
// which is intentional: it forces the addEventListener pattern everywhere.
$cspNonce = base64_encode(random_bytes(16));
define('CSP_NONCE', $cspNonce);

header(sprintf(
    "Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-%s'; " .
    "style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; " .
    "frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://api.razorpay.com https://*.razorpay.com",
    $cspNonce
));
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ── i18n ──────────────────────────────────────────────────────────────────────
require ROOT . '/src/Helpers/Lang.php';
\App\Helpers\Lang::init();

// Shorthand global
function __( string $key, array $replace = [] ): string {
    return \App\Helpers\Lang::t($key, $replace);
}

// ── Router ────────────────────────────────────────────────────────────────────
$router = new Router();
require ROOT . '/src/routes.php';
