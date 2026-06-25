<?php
declare(strict_types=1);

return [
    'app' => [
        'name'     => 'RentOps',
        'url'      => $_ENV['APP_URL'] ?? 'http://localhost',
        'timezone' => 'Asia/Kolkata',
        'locale'   => 'en_IN',
    ],
    'db' => [
        'host'    => $_ENV['DB_HOST']    ?? 'localhost',
        'name'    => $_ENV['DB_NAME']    ?? 'rentops',
        'user'    => $_ENV['DB_USER']    ?? 'root',
        'pass'    => $_ENV['DB_PASS']    ?? '',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'lifetime' => 7 * 24 * 3600, // 7 days remember-me
    ],
    'rent' => [
        'default_due_day'         => 5,
        'overdue_reminder_offset' => 3, // days after due_date before marking overdue
        'currency_symbol'         => '₹',
        'currency_locale'         => 'en_IN',
    ],
];
