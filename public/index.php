<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

require ROOT . '/src/bootstrap.php';

$router->dispatch();
