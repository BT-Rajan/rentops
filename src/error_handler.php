<?php
declare(strict_types=1);

/**
 * Global error and exception handlers.
 * Loaded once in bootstrap.php — never leaks stack traces to the browser.
 */

set_exception_handler(function (\Throwable $e): void {
    $isDev = ($_ENV['APP_ENV'] ?? 'production') === 'development';

    error_log('[RentOps] Uncaught exception: ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (headers_sent()) return;
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    if ($isDev) {
        echo '<pre style="font-family:monospace;padding:20px;background:#fef2f2;color:#991b1b">';
        echo htmlspecialchars((string)$e);
        echo '</pre>';
    } else {
        require dirname(__DIR__) . '/views/partials/500.php';
    }
    exit;
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!($errno & error_reporting())) return false;

    error_log("[RentOps] PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}");

    // Convert E_USER_ERROR to exception
    if ($errno === E_USER_ERROR) {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    return false; // let PHP handle warnings/notices
});
