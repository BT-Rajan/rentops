<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\RentEngine;

class TenantController extends BaseController
{
    public function index(array $params = []): void
    {
        $status = $_GET['status'] ?? 'active';
        $search = trim($_GET['q'] ?? '');

        $where  = 'WHERE 1=1';
        $bind   = [];

        if (in_array($status, ['active', 'vacated'])) {
            $where .= ' AND t.status = ?';
            $bind[] = $status;
        }
        if ($search) {
            $where .= ' AND (t.full_name LIKE ? OR t.phone LIKE ? OR r.room_number LIKE ?)';
            $like   = "%{$search}%";
            array_push($bind, $like, $like, $like);
        }

        $tenants = DB::rows("
            SELECT t.*,
                   r.room_number,
                   te.agreed_rent,
                   te.move_in_date,
                   te.id AS tenancy_id,
                   COALESCE((SELECT SUM(amount_due) - SUM(amount_paid)
                              FROM rent_invoices
                              WHERE tenancy_id = te.id AND status != 'paid'), 0) AS outstanding
            FROM tenants t
            LEFT JOIN tenancies te ON te.tenant_id = t.id AND te.status = 'active'
            LEFT JOIN rooms r      ON r.id = te.room_id
            {$where}
            ORDER BY t.full_name
        ", $bind);

        $this->render('tenants/index', [
            'pageTitle' => 'Tenants',
            'tenants'   => $tenants,
            'status'    => $status,
            'search'    => $search,
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
        ]);
    }

    public function show(array $params = []): void
    {
        $tenant = DB::row('SELECT * FROM tenants WHERE id = ?', [$params['id']]);
        if (!$tenant) { http_response_code(404); return; }

        $tenancies = DB::rows("
            SELECT te.*, r.room_number, r.room_type
            FROM tenancies te
            JOIN rooms r ON r.id = te.room_id
            WHERE te.tenant_id = ?
            ORDER BY te.move_in_date DESC
        ", [$params['id']]);

        $activeTenancy = null;
        foreach ($tenancies as $te) {
            if ($te['status'] === 'active') { $activeTenancy = $te; break; }
        }

        $invoices = [];
        if ($activeTenancy) {
            $invoices = DB::rows("
                SELECT ri.*,
                       COALESCE(SUM(p.amount), 0) AS collected,
                       GROUP_CONCAT(p.mode ORDER BY p.payment_date SEPARATOR ', ') AS modes
                FROM rent_invoices ri
                LEFT JOIN payments p ON p.invoice_id = ri.id
                WHERE ri.tenancy_id = ?
                GROUP BY ri.id
                ORDER BY ri.period_month DESC
            ", [$activeTenancy['id']]);
        }

        $this->render('tenants/show', [
            'pageTitle'     => htmlspecialchars($tenant['full_name']),
            'tenant'        => $tenant,
            'tenancies'     => $tenancies,
            'activeTenancy' => $activeTenancy,
            'invoices'      => $invoices,
            'flash'         => $this->flash(),
            'user'          => $this->currentUser(),
            'csrf'          => $this->csrfToken(),
        ]);
    }

    public function create(array $params = []): void
    {
        $rooms = DB::rows("SELECT * FROM rooms WHERE status IN ('vacant','partially_occupied') ORDER BY room_number");
        $this->render('tenants/create', [
            'pageTitle' => 'Add Tenant',
            'rooms'     => $rooms,
            'csrf'      => $this->csrfToken(),
            'user'      => $this->currentUser(),
        ]);
    }

    public function store(array $params = []): void
    {
        $this->verifyCsrf();
        $err = $this->requireFields(['full_name', 'phone']);
        if ($err) { $this->redirect('/tenants/new', $err, 'error'); return; }

        $id = \Ramsey\Uuid\Uuid::uuid4()->toString();

        DB::insert('tenants', [
            'id'                => $id,
            'full_name'         => trim($_POST['full_name']),
            'phone'             => trim($_POST['phone']),
            'email'             => trim($_POST['email'] ?? ''),
            'id_proof_type'     => $_POST['id_proof_type'] ?? 'Aadhaar',
            'id_proof_number'   => trim($_POST['id_proof_number'] ?? ''),
            'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
            'status'            => 'active',
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        $this->redirect("/tenants/{$id}/movein", 'Tenant created. Now assign a room.');
    }

    public function update(array $params = []): void
    {
        $this->verifyCsrf();
        $tenant = DB::row('SELECT * FROM tenants WHERE id = ?', [$params['id']]);
        if (!$tenant) { http_response_code(404); return; }

        DB::update('tenants', [
            'full_name'         => trim($_POST['full_name'] ?? $tenant['full_name']),
            'phone'             => trim($_POST['phone']     ?? $tenant['phone']),
            'email'             => trim($_POST['email']     ?? $tenant['email']),
            'emergency_contact' => trim($_POST['emergency_contact'] ?? $tenant['emergency_contact']),
        ], 'id = ?', [$params['id']]);

        $this->redirect("/tenants/{$params['id']}", 'Tenant updated.');
    }

    public function moveInForm(array $params = []): void
    {
        $tenant = DB::row('SELECT * FROM tenants WHERE id = ?', [$params['id']]);
        if (!$tenant) { http_response_code(404); return; }

        $rooms = DB::rows("SELECT * FROM rooms WHERE status IN ('vacant','partially_occupied') ORDER BY room_number");

        $this->render('tenants/movein', [
            'pageTitle' => 'Move In — ' . htmlspecialchars($tenant['full_name']),
            'tenant'    => $tenant,
            'rooms'     => $rooms,
            'csrf'      => $this->csrfToken(),
            'user'      => $this->currentUser(),
        ]);
    }

    public function moveIn(array $params = []): void
    {
        $this->verifyCsrf();
        $tenant = DB::row('SELECT * FROM tenants WHERE id = ?', [$params['id']]);
        if (!$tenant) { http_response_code(404); return; }

        $err = $this->requireFields(['room_id', 'move_in_date', 'agreed_rent', 'security_deposit', 'rent_due_day']);
        if ($err) { $this->redirect("/tenants/{$params['id']}/movein", $err, 'error'); return; }

        $room = DB::row('SELECT * FROM rooms WHERE id = ?', [$_POST['room_id']]);
        if (!$room) { $this->redirect("/tenants/{$params['id']}/movein", 'Invalid room.', 'error'); return; }

        DB::beginTransaction();
        try {
            $tenancyId = $this->uuid();
            DB::insert('tenancies', [
                'id'               => $tenancyId,
                'tenant_id'        => $params['id'],
                'room_id'          => $_POST['room_id'],
                'move_in_date'     => $_POST['move_in_date'],
                'move_out_date'    => null,
                'agreed_rent'      => (float)$_POST['agreed_rent'],
                'security_deposit' => (float)$_POST['security_deposit'],
                'rent_due_day'     => (int)$_POST['rent_due_day'],
                'status'           => 'active',
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            // Update room status
            $newStatus = ($room['room_type'] === 'sharing' || $room['room_type'] === 'dorm')
                ? 'partially_occupied' : 'occupied';
            DB::update('rooms', ['status' => $newStatus], 'id = ?', [$_POST['room_id']]);

            // Generate first invoice (pro-rata if not 1st of month)
            $engine = new RentEngine();
            $engine->generateFirstInvoice($tenancyId);

            DB::commit();
            $this->redirect("/tenants/{$params['id']}", 'Move-in recorded. First invoice generated.');
        } catch (\Throwable $e) {
            DB::rollback();
            $this->redirect("/tenants/{$params['id']}/movein", 'Move-in failed: ' . $e->getMessage(), 'error');
        }
    }

    public function moveOutForm(array $params = []): void
    {
        $tenant = DB::row('SELECT * FROM tenants WHERE id = ?', [$params['id']]);
        if (!$tenant) { http_response_code(404); return; }

        $tenancy = DB::row("SELECT te.*, r.room_number FROM tenancies te JOIN rooms r ON r.id = te.room_id WHERE te.tenant_id = ? AND te.status = 'active'", [$params['id']]);

        $this->render('tenants/moveout', [
            'pageTitle' => 'Move Out — ' . htmlspecialchars($tenant['full_name']),
            'tenant'    => $tenant,
            'tenancy'   => $tenancy,
            'csrf'      => $this->csrfToken(),
            'user'      => $this->currentUser(),
        ]);
    }

    public function moveOut(array $params = []): void
    {
        $this->verifyCsrf();

        $err = $this->requireFields(['move_out_date']);
        if ($err) { $this->redirect("/tenants/{$params['id']}/moveout", $err, 'error'); return; }

        $tenancy = DB::row("SELECT * FROM tenancies WHERE tenant_id = ? AND status = 'active'", [$params['id']]);
        if (!$tenancy) { $this->redirect("/tenants/{$params['id']}", 'No active tenancy.', 'error'); return; }

        DB::beginTransaction();
        try {
            $engine = new RentEngine();
            $engine->processMoveOut($tenancy['id'], $_POST['move_out_date'], (float)($_POST['deposit_deduction'] ?? 0));

            DB::update('tenants', ['status' => 'vacated'], 'id = ?', [$params['id']]);

            DB::commit();
            $this->redirect("/tenants/{$params['id']}", 'Move-out processed successfully.');
        } catch (\Throwable $e) {
            DB::rollback();
            $this->redirect("/tenants/{$params['id']}/moveout", 'Move-out failed: ' . $e->getMessage(), 'error');
        }
    }

    private function uuid(): string
    {
        return \App\Helpers\UuidHelper::v4();
    }
}
