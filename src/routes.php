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
use App\Controllers\ImportController;
use App\Controllers\UploadController;
use App\Controllers\TemplateController;
use App\Middleware\AuthMiddleware;
use App\Controllers\AuditController;
use App\Controllers\RentChangeController;
use App\Controllers\LangController;

$auth = [AuthMiddleware::class];

// Auth
$router->get('/login',            [AuthController::class, 'showLogin']);
$router->post('/login',           [AuthController::class, 'login']);
$router->post('/logout',          [AuthController::class, 'logout']);
$router->get('/forgot-password',  [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('/reset-password',   [AuthController::class, 'showResetPassword']);
$router->post('/reset-password',  [AuthController::class, 'resetPassword']);

// Dashboard
$router->get('/',               [DashboardController::class, 'index'],   $auth);
$router->get('/dashboard',      [DashboardController::class, 'index'],   $auth);
$router->get('/api/dashboard',  [DashboardController::class, 'api'],     $auth);

// Rooms
$router->get('/rooms',                      [RoomController::class, 'index'],   $auth);
$router->get('/rooms/{id}',                 [RoomController::class, 'show'],    $auth);
$router->post('/rooms/{id}',                [RoomController::class, 'update'],  $auth);

// Tenants
$router->get('/tenants',                    [TenantController::class, 'index'],       $auth);
$router->get('/tenants/new',                [TenantController::class, 'create'],      $auth);
$router->post('/tenants/new',               [TenantController::class, 'store'],       $auth);
$router->get('/tenants/{id}',               [TenantController::class, 'show'],        $auth);
$router->post('/tenants/{id}',              [TenantController::class, 'update'],      $auth);
$router->get('/tenants/{id}/movein',        [TenantController::class, 'moveInForm'],  $auth);
$router->post('/tenants/{id}/movein',       [TenantController::class, 'moveIn'],      $auth);
$router->get('/tenants/{id}/moveout',       [TenantController::class, 'moveOutForm'], $auth);
$router->post('/tenants/{id}/moveout',      [TenantController::class, 'moveOut'],     $auth);

// Tenant file uploads
$router->post('/tenants/{id}/upload-proof', [UploadController::class, 'uploadIdProof'], $auth);
$router->post('/tenants/{id}/delete-proof', [UploadController::class, 'deleteIdProof'], $auth);

// Payments
$router->get('/payments/new',               [PaymentController::class, 'create'],    $auth);
$router->post('/payments/new',              [PaymentController::class, 'store'],     $auth);
$router->get('/payments/{id}',              [PaymentController::class, 'show'],      $auth);

// Dues
$router->get('/dues',                       [DuesController::class, 'index'],        $auth);
$router->post('/api/invoices/generate',     [DuesController::class, 'generate'],     $auth);

// Reminders
$router->get('/reminders',                  [ReminderController::class, 'index'],    $auth);
$router->post('/reminders/send',            [ReminderController::class, 'send'],     $auth);
$router->post('/reminders/{id}/resend',     [ReminderController::class, 'resend'],   $auth);
$router->get('/reminders/{id}/detail',      [ReminderController::class, 'detail'],   $auth);

// Reports
$router->get('/reports',                    [ReportController::class, 'index'],      $auth);
$router->get('/reports/export',             [ReportController::class, 'export'],     $auth);

// Settings
$router->get('/settings',               [SettingsController::class, 'index'],          $auth);
$router->post('/settings',              [SettingsController::class, 'update'],         $auth);
$router->post('/settings/password',     [SettingsController::class, 'changePassword'], $auth);

// Audit & QA
$router->get('/audit',          [AuditController::class, 'index'], $auth);
$router->post('/audit/fix',     [AuditController::class, 'fix'],   $auth);
$router->get('/audit/log',      [AuditController::class, 'log'],   $auth);

// Rent changes (mid-tenancy)
$router->post('/tenancies/{tenancy_id}/rent-change', [RentChangeController::class, 'store'], $auth);

// Invoices
use App\Controllers\InvoiceController;
use App\Controllers\RazorpayWebhookController;
$router->get('/invoices',                          [InvoiceController::class, 'index'],         $auth);
$router->get('/invoices/new',                      [InvoiceController::class, 'create'],        $auth);
$router->post('/invoices',                         [InvoiceController::class, 'store'],         $auth);
$router->get('/invoices/{id}',                     [InvoiceController::class, 'show'],          $auth);
$router->get('/invoices/{id}/pdf',                 [InvoiceController::class, 'pdf'],           $auth);
$router->post('/invoices/{id}/razorpay-link',      [InvoiceController::class, 'razorpayLink'],  $auth);

// Razorpay reconciliation — NOT behind $auth: these are hit by Razorpay's
// servers (webhook) and the tenant's browser (callback redirect), neither of
// which has a RentOps session. The webhook verifies its own HMAC signature
// instead — see RazorpayWebhookController::handle().
$router->post('/payments/razorpay/webhook',        [RazorpayWebhookController::class, 'handle']);
$router->get('/payments/razorpay/callback',        [RazorpayWebhookController::class, 'callback']);

// Settings — billing + razorpay
$router->post('/settings/billing',                 [SettingsController::class, 'updateBilling'],   $auth);
$router->post('/settings/razorpay',                [SettingsController::class, 'updateRazorpay'],  $auth);

// Language switch
$router->get('/lang/switch',  [LangController::class, 'switch']);
$router->post('/lang/switch', [LangController::class, 'switch']);

// Import
$router->get('/import',                     [ImportController::class, 'index'],      $auth);
$router->post('/import/preview',            [ImportController::class, 'preview'],    $auth);
$router->post('/import/confirm',            [ImportController::class, 'confirm'],    $auth);
$router->get('/import/template',            [TemplateController::class, 'csvTemplate'], $auth);
