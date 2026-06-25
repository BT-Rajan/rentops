<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\UuidHelper;

class RentChangeController extends BaseController
{
    public function store(array $params = []): void
    {
        $this->verifyCsrf();

        $tenancyId = $params['tenancy_id'] ?? '';
        $tenancy   = DB::row("SELECT * FROM tenancies WHERE id = ? AND status = 'active'", [$tenancyId]);
        if (!$tenancy) {
            $this->json(['error' => 'Active tenancy not found.'], 404);
            return;
        }

        $newRent       = (float)($_POST['new_rent'] ?? 0);
        $effectiveFrom = $_POST['effective_from'] ?? date('Y-m-01');
        $note          = trim($_POST['note'] ?? '');

        if ($newRent <= 0) {
            $this->json(['error' => 'New rent must be greater than zero.'], 400);
            return;
        }

        DB::beginTransaction();
        try {
            // Log the change
            DB::insert('rent_changes', [
                'id'             => UuidHelper::v4(),
                'tenancy_id'     => $tenancyId,
                'old_rent'       => (float)$tenancy['agreed_rent'],
                'new_rent'       => $newRent,
                'effective_from' => $effectiveFrom,
                'note'           => $note,
                'created_by'     => $this->currentUser()['name'],
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            // Update tenancy agreed_rent
            DB::update('tenancies', ['agreed_rent' => $newRent], 'id = ?', [$tenancyId]);

            // Update any UNPAID future invoices for this tenancy
            DB::query("
                UPDATE rent_invoices
                SET amount_due = ?
                WHERE tenancy_id = ?
                  AND status IN ('unpaid', 'partial')
                  AND period_month >= ?
            ", [$newRent, $tenancyId, date('Y-m-d', strtotime($effectiveFrom))]);

            DB::commit();
            $this->json(['success' => true, 'new_rent' => $newRent]);
        } catch (\Throwable $e) {
            DB::rollback();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
