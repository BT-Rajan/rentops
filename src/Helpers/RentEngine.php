<?php
declare(strict_types=1);

namespace App\Helpers;

use App\DB;

class RentEngine
{
    /**
     * Generate the first invoice for a tenancy (pro-rated if not 1st of month).
     */
    public function generateFirstInvoice(string $tenancyId): void
    {
        $tenancy = DB::row('SELECT * FROM tenancies WHERE id = ?', [$tenancyId]);
        if (!$tenancy) throw new \RuntimeException("Tenancy not found: {$tenancyId}");

        $moveIn  = new \DateTimeImmutable($tenancy['move_in_date']);
        $period  = new \DateTimeImmutable($moveIn->format('Y-m-01'));

        // Avoid duplicate
        $exists = DB::scalar(
            'SELECT COUNT(*) FROM rent_invoices WHERE tenancy_id = ? AND period_month = ?',
            [$tenancyId, $period->format('Y-m-d')]
        );
        if ($exists) return;

        $amountDue = $this->proRateMoveIn((float)$tenancy['agreed_rent'], $moveIn);
        $dueDate   = $this->dueDate($period, (int)$tenancy['rent_due_day']);

        $this->createInvoice($tenancyId, $period, $amountDue, $dueDate);
    }

    /**
     * Generate invoice for a single tenancy for a given month.
     * Used for backfill during import. Idempotent.
     */
    public function generateMonthlyInvoicesForTenancy(string $tenancyId, string $yearMonth): bool
    {
        $tenancy = DB::row('SELECT * FROM tenancies WHERE id = ?', [$tenancyId]);
        if (!$tenancy || $tenancy['status'] !== 'active') return false;

        $period = new \DateTimeImmutable("{$yearMonth}-01");
        $exists = DB::scalar(
            'SELECT COUNT(*) FROM rent_invoices WHERE tenancy_id = ? AND period_month = ?',
            [$tenancyId, $period->format('Y-m-d')]
        );
        if ($exists) return false;

        $dueDate = $this->dueDate($period, (int)$tenancy['rent_due_day']);
        $this->createInvoice($tenancyId, $period, (float)$tenancy['agreed_rent'], $dueDate);
        return true;
    }

    /**
     * Generate invoices for ALL active tenancies for a given month.
     * Idempotent — skips if invoice already exists.
     */
    public function generateMonthlyInvoices(string $yearMonth): int
    {
        $period    = new \DateTimeImmutable("{$yearMonth}-01");
        $tenancies = DB::rows("SELECT * FROM tenancies WHERE status = 'active'");
        $created   = 0;

        foreach ($tenancies as $tenancy) {
            $exists = DB::scalar(
                'SELECT COUNT(*) FROM rent_invoices WHERE tenancy_id = ? AND period_month = ?',
                [$tenancy['id'], $period->format('Y-m-d')]
            );
            if ($exists) continue;

            $amountDue = (float)$tenancy['agreed_rent'];
            $dueDate   = $this->dueDate($period, (int)$tenancy['rent_due_day']);
            $this->createInvoice($tenancy['id'], $period, $amountDue, $dueDate);
            $created++;
        }

        return $created;
    }

    /**
     * Handle move-out: pro-rate final month, settle deposit.
     */
    public function processMoveOut(string $tenancyId, string $moveOutDate, float $deduction = 0): array
    {
        $tenancy = DB::row('SELECT * FROM tenancies WHERE id = ?', [$tenancyId]);
        if (!$tenancy) throw new \RuntimeException("Tenancy not found: {$tenancyId}");

        $moveOut = new \DateTimeImmutable($moveOutDate);
        $period  = new \DateTimeImmutable($moveOut->format('Y-m-01'));

        // Find or create final month invoice
        $invoice = DB::row(
            'SELECT * FROM rent_invoices WHERE tenancy_id = ? AND period_month = ?',
            [$tenancyId, $period->format('Y-m-d')]
        );

        $finalAmount = $this->proRateMoveOut((float)$tenancy['agreed_rent'], $moveOut);

        if ($invoice) {
            // Recalculate — if already paid more than final amount, flag refund
            DB::update('rent_invoices', [
                'amount_due' => $finalAmount,
                'status'     => $this->resolveStatus($finalAmount, (float)$invoice['amount_paid']),
            ], 'id = ?', [$invoice['id']]);
        } else {
            $dueDate = $this->dueDate($period, (int)$tenancy['rent_due_day']);
            $this->createInvoice($tenancyId, $period, $finalAmount, $dueDate);
        }

        // Close tenancy
        DB::update('tenancies', [
            'move_out_date'       => $moveOutDate,
            'deposit_deduction'   => $deduction,
            'deposit_refund'      => max(0, (float)$tenancy['security_deposit'] - $deduction),
            'status'              => 'closed',
        ], 'id = ?', [$tenancyId]);

        // Update room status to vacant (unless other active tenancies exist)
        $otherActive = DB::scalar(
            "SELECT COUNT(*) FROM tenancies WHERE room_id = ? AND id != ? AND status = 'active'",
            [$tenancy['room_id'], $tenancyId]
        );
        DB::update('rooms', ['status' => $otherActive ? 'partially_occupied' : 'vacant'], 'id = ?', [$tenancy['room_id']]);

        return [
            'final_amount'    => $finalAmount,
            'deposit'         => (float)$tenancy['security_deposit'],
            'deduction'       => $deduction,
            'refund'          => max(0, (float)$tenancy['security_deposit'] - $deduction),
        ];
    }

    /**
     * Recalculate and update overdue status on all unpaid/partial invoices past due date.
     */
    public function refreshOverdueStatus(): int
    {
        $stmt = DB::query(
            "UPDATE rent_invoices
             SET status = 'overdue'
             WHERE status IN ('unpaid','partial')
               AND due_date < CURDATE()"
        );
        return $stmt->rowCount();
    }

    /**
     * Update invoice status after a payment is recorded.
     */
    public function updateInvoiceStatus(string $invoiceId, float $overpayment = 0): void
    {
        $invoice = DB::row('SELECT * FROM rent_invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) return;

        $paid   = (float)DB::scalar('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?', [$invoiceId]);
        $status = $this->resolveStatus((float)$invoice['amount_due'], $paid);

        DB::update('rent_invoices', [
            'amount_paid' => $paid,
            'overpayment' => $overpayment,
            'status'      => $status,
        ], 'id = ?', [$invoiceId]);
    }

    // ─── Pro-rata helpers ──────────────────────────────────────────────────────

    private function proRateMoveIn(float $rent, \DateTimeImmutable $moveIn): float
    {
        $daysInMonth  = (int)$moveIn->format('t');
        $dayOfMoveIn  = (int)$moveIn->format('j');
        $daysOccupied = $daysInMonth - $dayOfMoveIn + 1;

        if ($daysOccupied >= $daysInMonth) return $rent;

        $daily = $rent / $daysInMonth;
        return round($daily * $daysOccupied);
    }

    private function proRateMoveOut(float $rent, \DateTimeImmutable $moveOut): float
    {
        $daysInMonth  = (int)$moveOut->format('t');
        $dayOfMoveOut = (int)$moveOut->format('j');

        if ($dayOfMoveOut >= $daysInMonth) return $rent;

        $daily = $rent / $daysInMonth;
        return round($daily * $dayOfMoveOut);
    }

    private function dueDate(\DateTimeImmutable $period, int $dueDay): string
    {
        $daysInMonth = (int)$period->format('t');
        $day         = min($dueDay, $daysInMonth);
        return $period->format("Y-m-{$day}");
    }

    private function resolveStatus(float $due, float $paid): string
    {
        if ($paid <= 0)      return 'unpaid';
        if ($paid >= $due)   return 'paid';
        return 'partial';
    }

    private function createInvoice(string $tenancyId, \DateTimeImmutable $period, float $amount, string $dueDate): void
    {
        DB::insert('rent_invoices', [
            'id'           => $this->uuid(),
            'tenancy_id'   => $tenancyId,
            'period_month' => $period->format('Y-m-d'),
            'amount_due'   => $amount,
            'amount_paid'  => 0,
            'due_date'     => $dueDate,
            'status'       => 'unpaid',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function uuid(): string
    {
        return \App\Helpers\UuidHelper::v4();
    }
}
