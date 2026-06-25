<?php
declare(strict_types=1);

use App\Helpers\Router;

// Autoloader
spl_autoload_register(function (string $class): void {
    $base = ROOT . '/src/';
    $rel  = str_replace('App\\', '', $class);
    $file = $base . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($file)) require $file;
});

// Config
$config = require ROOT . '/src/config.php';

// DB singleton
require ROOT . '/src/db.php';

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_name('rentops_sess');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ]);
}

// Router
$router = new Router();
require ROOT . '/src/routes.php';
