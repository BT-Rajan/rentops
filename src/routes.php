<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\RoomController;
use App\Controllers\TenantController;
use App\Controllers\PaymentController;
use App\Controllers\DuesController;
use App\Controllers\ReminderController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Middleware\AuthMiddleware;

$auth = [AuthMiddleware::class];

// Auth
$router->get('/login',          [AuthController::class, 'showLogin']);
$router->post('/login',         [AuthController::class, 'login']);
$router->post('/logout',        [AuthController::class, 'logout']);

// Dashboard
$router->get('/',               [DashboardController::class, 'index'],   $auth);
$router->get('/dashboard',      [DashboardController::class, 'index'],   $auth);
$router->get('/api/dashboard',  [DashboardController::class, 'api'],     $auth);

// Rooms
$router->get('/rooms',                      [RoomController::class, 'index'],   $auth);
$router->get('/rooms/{id}',                 [RoomController::class, 'show'],    $auth);
$router->post('/rooms/{id}',                [RoomController::class, 'update'],  $auth);

// Tenants
$router->get('/tenants',                    [TenantController::class, 'index'],      $auth);
$router->get('/tenants/new',                [TenantController::class, 'create'],     $auth);
$router->post('/tenants/new',               [TenantController::class, 'store'],      $auth);
$router->get('/tenants/{id}',               [TenantController::class, 'show'],       $auth);
$router->post('/tenants/{id}',              [TenantController::class, 'update'],     $auth);
$router->get('/tenants/{id}/movein',        [TenantController::class, 'moveInForm'], $auth);
$router->post('/tenants/{id}/movein',       [TenantController::class, 'moveIn'],     $auth);
$router->get('/tenants/{id}/moveout',       [TenantController::class, 'moveOutForm'],$auth);
$router->post('/tenants/{id}/moveout',      [TenantController::class, 'moveOut'],    $auth);

// Payments
$router->get('/payments/new',               [PaymentController::class, 'create'],    $auth);
$router->post('/payments/new',              [PaymentController::class, 'store'],     $auth);
$router->get('/payments/{id}',              [PaymentController::class, 'show'],      $auth);

// Dues
$router->get('/dues',                       [DuesController::class, 'index'],        $auth);
$router->post('/api/invoices/generate',     [DuesController::class, 'generate'],     $auth);

// Reminders
$router->get('/reminders',                  [ReminderController::class, 'index'],    $auth);
$router->post('/reminders/preview',         [ReminderController::class, 'preview'],  $auth);

// Reports
$router->get('/reports',                    [ReportController::class, 'index'],      $auth);
$router->get('/reports/export',             [ReportController::class, 'export'],     $auth);

// Settings
$router->get('/settings',                   [SettingsController::class, 'index'],    $auth);
$router->post('/settings',                  [SettingsController::class, 'update'],   $auth);
