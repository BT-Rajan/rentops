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

    // Return JSON for AJAX/fetch requests so the JS layer can surface the error
    $wantsJson = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
              || isset($_SERVER['HTTP_X_REQUESTED_WITH']);
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $isDev ? $e->getMessage() : 'Internal server error']);
        exit;
    }

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
