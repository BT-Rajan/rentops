<?php
declare(strict_types=1);

// Set timezone as early as possible
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');

return [
    'app' => [
        'name'     => $_ENV['APP_NAME']     ?? 'RentOps',
        'url'      => $_ENV['APP_URL']      ?? 'http://localhost',
        'env'      => $_ENV['APP_ENV']      ?? 'production',
        'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata',
        'locale'   => 'en_IN',
    ],
    'db' => [
        'host'    => $_ENV['DB_HOST']    ?? 'localhost',
        'port'    => (int)($_ENV['DB_PORT'] ?? 3306),
        'name'    => $_ENV['DB_NAME']    ?? 'rentops',
        'user'    => $_ENV['DB_USER']    ?? 'root',
        'pass'    => $_ENV['DB_PASS']    ?? '',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'lifetime' => 7 * 24 * 3600,
    ],
    'rent' => [
        'default_due_day'         => (int)($_ENV['DEFAULT_DUE_DAY'] ?? 5),
        'overdue_reminder_offset' => 3,
        'currency_symbol'         => '₹',
        'currency_locale'         => 'en_IN',
    ],
];
