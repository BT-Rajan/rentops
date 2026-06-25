<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;

class RoomController extends BaseController
{
    public function index(array $params = []): void
    {
        $rooms = DB::rows("
            SELECT r.*,
                   t.full_name AS tenant_name,
                   t.id        AS tenant_id,
                   te.agreed_rent,
                   te.id       AS tenancy_id
            FROM rooms r
            LEFT JOIN tenancies te ON te.room_id = r.id AND te.status = 'active'
            LEFT JOIN tenants t    ON t.id = te.tenant_id
            ORDER BY r.room_number
        ");

        $this->render('rooms/index', [
            'pageTitle' => 'Rooms',
            'rooms'     => $rooms,
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
        ]);
    }

    public function show(array $params = []): void
    {
        $room = DB::row('SELECT * FROM rooms WHERE id = ?', [$params['id']]);
        if (!$room) { http_response_code(404); return; }

        $tenancies = DB::rows("
            SELECT te.*, t.full_name, t.phone,
                   te.move_in_date, te.move_out_date,
                   (SELECT COALESCE(SUM(amount_due) - SUM(amount_paid), 0)
                    FROM rent_invoices WHERE tenancy_id = te.id) AS outstanding
            FROM tenancies te
            JOIN tenants t ON t.id = te.tenant_id
            WHERE te.room_id = ?
            ORDER BY te.move_in_date DESC
        ", [$params['id']]);

        $invoices = DB::rows("
            SELECT ri.*, COALESCE(SUM(p.amount), 0) AS collected
            FROM rent_invoices ri
            JOIN tenancies te ON te.id = ri.tenancy_id
            LEFT JOIN payments p ON p.invoice_id = ri.id
            WHERE te.room_id = ?
            GROUP BY ri.id
            ORDER BY ri.period_month DESC
            LIMIT 12
        ", [$params['id']]);

        $this->render('rooms/show', [
            'pageTitle' => 'Room ' . htmlspecialchars($room['room_number']),
            'room'      => $room,
            'tenancies' => $tenancies,
            'invoices'  => $invoices,
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
        ]);
    }

    public function update(array $params = []): void
    {
        $this->verifyCsrf();
        $room = DB::row('SELECT * FROM rooms WHERE id = ?', [$params['id']]);
        if (!$room) { http_response_code(404); return; }

        DB::update('rooms', [
            'base_rent'   => (float)($_POST['base_rent'] ?? $room['base_rent']),
            'room_type'   => $_POST['room_type']   ?? $room['room_type'],
            'status'      => $_POST['status']       ?? $room['status'],
        ], 'id = ?', [$params['id']]);

        $this->redirect("/rooms/{$params['id']}", 'Room updated successfully.');
    }
}
