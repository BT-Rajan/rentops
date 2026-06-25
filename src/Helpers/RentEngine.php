<?php
declare(strict_types=1);

namespace App\Helpers;

use App\DB;

/**
 * RentEngine — core financial logic for RentOps.
 *
 * Responsibilities:
 *   - Pro-rata invoice generation (move-in / move-out)
 *   - Monthly bulk invoice generation (idempotent)
 *   - Same-month move-in/out (zero-rent guard)
 *   - Accumulated overdue across months
 *   - Overpayment detection and status resolution
 *   - Deposit reconciliation on move-out
 */
class RentEngine
{
    // ─── Public API ────────────────────────────────────────────────────────────

    /**
     * Generate the first invoice for a tenancy.
     * Pro-rated if move-in is not the 1st of the month.
     * Edge: same-month move-out → zero invoice, flagged.
     */
    public function generateFirstInvoice(string $tenancyId): void
    {
        $tenancy = $this->requireTenancy($tenancyId);

        $moveIn = new \DateTimeImmutable($tenancy['move_in_date']);
        $period = new \DateTimeImmutable($moveIn->format('Y-m-01'));

        if ($this->invoiceExists($tenancyId, $period)) return;

        // Edge: same-month move-out (move-out recorded before cron runs)
        if (!empty($tenancy['move_out_date'])) {
            $moveOut     = new \DateTimeImmutable($tenancy['move_out_date']);
            $sameMonth   = $moveIn->format('Y-m') === $moveOut->format('Y-m');
            if ($sameMonth) {
                $amount = $this->proRateSameMonth(
                    (float)$tenancy['agreed_rent'], $moveIn, $moveOut
                );
                $dueDate = $this->dueDate($period, (int)$tenancy['rent_due_day']);
                $this->createInvoice($tenancyId, $period, $amount, $dueDate, 'same_month_exit');
                return;
            }
        }

        $amount  = $this->proRateMoveIn((float)$tenancy['agreed_rent'], $moveIn);
        $dueDate = $this->dueDate($period, (int)$tenancy['rent_due_day']);
        $this->createInvoice($tenancyId, $period, $amount, $dueDate);
    }

    /**
     * Generate invoices for ALL active tenancies for a given month.
     * Idempotent — skips if invoice already exists.
     * Picks up any accumulated balance from previous unpaid months.
     */
    public function generateMonthlyInvoices(string $yearMonth): int
    {
        $period    = new \DateTimeImmutable("{$yearMonth}-01");
        $tenancies = DB::rows("SELECT * FROM tenancies WHERE status = 'active'");
        $created   = 0;

        foreach ($tenancies as $tenancy) {
            if ($this->invoiceExists($tenancy['id'], $period)) continue;

            $rent    = $this->effectiveRent($tenancy['id'], (float)$tenancy['agreed_rent'], $yearMonth);
            $dueDate = $this->dueDate($period, (int)$tenancy['rent_due_day']);
            $this->createInvoice($tenancy['id'], $period, $rent, $dueDate);
            $created++;
        }

        return $created;
    }

    /**
     * Generate invoice for a single tenancy for a given month.
     * Used for backfill during CSV import. Idempotent.
     */
    public function generateMonthlyInvoicesForTenancy(string $tenancyId, string $yearMonth): bool
    {
        $tenancy = DB::row('SELECT * FROM tenancies WHERE id = ?', [$tenancyId]);
        if (!$tenancy || $tenancy['status'] !== 'active') return false;

        $period = new \DateTimeImmutable("{$yearMonth}-01");
        if ($this->invoiceExists($tenancyId, $period)) return false;

        $rent    = $this->effectiveRent($tenancyId, (float)$tenancy['agreed_rent'], $yearMonth);
        $dueDate = $this->dueDate($period, (int)$tenancy['rent_due_day']);
        $this->createInvoice($tenancyId, $period, $rent, $dueDate);
        return true;
    }

    /**
     * Handle move-out: pro-rate final month, reconcile deposit.
     * Edge: same-month move-in/out → one combined invoice.
     * Edge: move-out on last day of month → full month charge.
     */
    public function processMoveOut(string $tenancyId, string $moveOutDate, float $deduction = 0): array
    {
        $tenancy = $this->requireTenancy($tenancyId);
        $moveOut = new \DateTimeImmutable($moveOutDate);
        $moveIn  = new \DateTimeImmutable($tenancy['move_in_date']);
        $period  = new \DateTimeImmutable($moveOut->format('Y-m-01'));

        $sameMonth   = $moveIn->format('Y-m') === $moveOut->format('Y-m');
        $finalAmount = $sameMonth
            ? $this->proRateSameMonth((float)$tenancy['agreed_rent'], $moveIn, $moveOut)
            : $this->proRateMoveOut((float)$tenancy['agreed_rent'], $moveOut);

        // Validate: move-out cannot be before move-in
        if ($moveOut < $moveIn) {
            throw new \InvalidArgumentException('Move-out date cannot be before move-in date.');
        }

        // Find or create/update the final month invoice
        $invoice = DB::row(
            'SELECT * FROM rent_invoices WHERE tenancy_id = ? AND period_month = ?',
            [$tenancyId, $period->format('Y-m-d')]
        );

        if ($invoice) {
            $newStatus = $this->resolveStatus($finalAmount, (float)$invoice['amount_paid']);
            DB::update('rent_invoices', [
                'amount_due' => $finalAmount,
                'status'     => $newStatus,
                'notes'      => $sameMonth ? 'Same-month exit: pro-rated' : 'Move-out: pro-rated final month',
            ], 'id = ?', [$invoice['id']]);
        } else {
            $dueDate = $this->dueDate($period, (int)$tenancy['rent_due_day']);
            $this->createInvoice(
                $tenancyId, $period, $finalAmount, $dueDate,
                $sameMonth ? 'same_month_exit' : null
            );
        }

        // Carry-forward: mark all previous unpaid invoices overdue
        DB::query(
            "UPDATE rent_invoices
             SET status = 'overdue'
             WHERE tenancy_id = ?
               AND status IN ('unpaid','partial')
               AND period_month < ?",
            [$tenancyId, $period->format('Y-m-d')]
        );

        $deposit = (float)$tenancy['security_deposit'];
        $refund  = max(0, $deposit - $deduction);

        // Close tenancy
        DB::update('tenancies', [
            'move_out_date'     => $moveOutDate,
            'deposit_deduction' => $deduction,
            'deposit_refund'    => $refund,
            'status'            => 'closed',
        ], 'id = ?', [$tenancyId]);

        // Free the room (unless other active tenancies share it)
        $otherActive = (int)DB::scalar(
            "SELECT COUNT(*) FROM tenancies WHERE room_id = ? AND id != ? AND status = 'active'",
            [$tenancy['room_id'], $tenancyId]
        );
        DB::update('rooms', [
            'status' => $otherActive > 0 ? 'partially_occupied' : 'vacant',
        ], 'id = ?', [$tenancy['room_id']]);

        return [
            'final_amount' => $finalAmount,
            'same_month'   => $sameMonth,
            'deposit'      => $deposit,
            'deduction'    => $deduction,
            'refund'       => $refund,
        ];
    }

    /**
     * Sweep all unpaid/partial invoices past their due date → overdue.
     * Run daily via cron or on-demand before any dues listing.
     */
    public function refreshOverdueStatus(): int
    {
        return DB::query(
            "UPDATE rent_invoices
             SET status = 'overdue'
             WHERE status IN ('unpaid','partial')
               AND due_date < CURDATE()"
        )->rowCount();
    }

    /**
     * Recompute invoice status after any payment mutation.
     */
    public function updateInvoiceStatus(string $invoiceId, float $overpayment = 0): void
    {
        $invoice = DB::row('SELECT * FROM rent_invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) return;

        $paid   = (float)DB::scalar(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?',
            [$invoiceId]
        );
        $status = $this->resolveStatus((float)$invoice['amount_due'], $paid);

        DB::update('rent_invoices', [
            'amount_paid' => $paid,
            'overpayment' => $overpayment,
            'status'      => $status,
        ], 'id = ?', [$invoiceId]);
    }

    /**
     * Return accumulated outstanding balance across ALL months for a tenancy.
     * Used for dashboard and tenant detail page.
     */
    public function accumulatedBalance(string $tenancyId): float
    {
        return (float)DB::scalar(
            "SELECT COALESCE(SUM(amount_due) - SUM(amount_paid), 0)
             FROM rent_invoices
             WHERE tenancy_id = ? AND status != 'paid'",
            [$tenancyId]
        );
    }

    /**
     * Full integrity check for a tenancy — returns array of issues.
     * Used by the QA audit route.
     */
    public function auditTenancy(string $tenancyId): array
    {
        $issues  = [];
        $tenancy = DB::row('SELECT * FROM tenancies WHERE id = ?', [$tenancyId]);
        if (!$tenancy) return [['type' => 'error', 'msg' => 'Tenancy not found']];

        // Check for gap months (no invoice for a month within tenancy period)
        $start   = new \DateTimeImmutable(date('Y-m-01', strtotime($tenancy['move_in_date'])));
        $end     = new \DateTimeImmutable(date('Y-m-01')); // current month
        if (!empty($tenancy['move_out_date'])) {
            $end = new \DateTimeImmutable(date('Y-m-01', strtotime($tenancy['move_out_date'])));
        }

        $cursor = $start;
        while ($cursor <= $end) {
            $exists = $this->invoiceExists($tenancyId, $cursor);
            if (!$exists) {
                $issues[] = [
                    'type' => 'missing_invoice',
                    'msg'  => 'No invoice for ' . $cursor->format('M Y'),
                    'month'=> $cursor->format('Y-m'),
                ];
            }
            $cursor = $cursor->modify('+1 month');
        }

        // Check for overpayments
        $over = DB::rows(
            "SELECT id, period_month, overpayment FROM rent_invoices
             WHERE tenancy_id = ? AND overpayment > 0",
            [$tenancyId]
        );
        foreach ($over as $o) {
            $issues[] = [
                'type' => 'overpayment',
                'msg'  => 'Overpayment of ₹' . number_format((float)$o['overpayment'])
                        . ' on ' . date('M Y', strtotime($o['period_month'])),
            ];
        }

        // Check for negative balance (data inconsistency)
        $invoices = DB::rows(
            'SELECT id, period_month, amount_due, amount_paid FROM rent_invoices WHERE tenancy_id = ?',
            [$tenancyId]
        );
        foreach ($invoices as $inv) {
            if ((float)$inv['amount_paid'] > (float)$inv['amount_due'] + 0.01) {
                $issues[] = [
                    'type' => 'over_collected',
                    'msg'  => 'Collected more than due on ' . date('M Y', strtotime($inv['period_month'])),
                ];
            }
        }

        return $issues;
    }

    // ─── Pro-rata calculations ──────────────────────────────────────────────────

    /**
     * Move-in pro-rata: charge from move-in day to end of month.
     */
    private function proRateMoveIn(float $rent, \DateTimeImmutable $moveIn): float
    {
        $daysInMonth  = (int)$moveIn->format('t');
        $dayOfMoveIn  = (int)$moveIn->format('j');
        $daysOccupied = $daysInMonth - $dayOfMoveIn + 1;
        if ($daysOccupied >= $daysInMonth) return $rent;
        return round(($rent / $daysInMonth) * $daysOccupied);
    }

    /**
     * Move-out pro-rata: charge from 1st to move-out day (inclusive).
     */
    private function proRateMoveOut(float $rent, \DateTimeImmutable $moveOut): float
    {
        $daysInMonth  = (int)$moveOut->format('t');
        $dayOfMoveOut = (int)$moveOut->format('j');
        if ($dayOfMoveOut >= $daysInMonth) return $rent;
        return round(($rent / $daysInMonth) * $dayOfMoveOut);
    }

    /**
     * Same-month edge: moved in and out in the same calendar month.
     * Charge only for days actually occupied.
     */
    private function proRateSameMonth(
        float $rent,
        \DateTimeImmutable $moveIn,
        \DateTimeImmutable $moveOut
    ): float {
        $daysInMonth  = (int)$moveIn->format('t');
        $daysOccupied = (int)$moveOut->format('j') - (int)$moveIn->format('j') + 1;
        $daysOccupied = max(1, $daysOccupied); // at least 1 day
        if ($daysOccupied >= $daysInMonth) return $rent;
        return round(($rent / $daysInMonth) * $daysOccupied);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve invoice status from due vs paid amounts.
     * Handles floating-point tolerance (₹0.50 rounding).
     */
    private function resolveStatus(float $due, float $paid): string
    {
        if ($paid <= 0)              return 'unpaid';
        if ($paid >= $due - 0.50)   return 'paid';   // tolerance for rounding
        return 'partial';
    }

    /**
     * Effective rent for a month — checks rent_changes table first.
     * Falls back to tenancy agreed_rent.
     */
    private function effectiveRent(string $tenancyId, float $defaultRent, string $yearMonth): float
    {
        $change = DB::row(
            "SELECT new_rent FROM rent_changes
             WHERE tenancy_id = ?
               AND DATE_FORMAT(effective_from, '%Y-%m') <= ?
             ORDER BY effective_from DESC
             LIMIT 1",
            [$tenancyId, $yearMonth]
        );
        return $change ? (float)$change['new_rent'] : $defaultRent;
    }

    private function dueDate(\DateTimeImmutable $period, int $dueDay): string
    {
        $day = min($dueDay, (int)$period->format('t'));
        return $period->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
    }

    private function invoiceExists(string $tenancyId, \DateTimeImmutable $period): bool
    {
        return (bool)DB::scalar(
            'SELECT COUNT(*) FROM rent_invoices WHERE tenancy_id = ? AND period_month = ?',
            [$tenancyId, $period->format('Y-m-d')]
        );
    }

    private function requireTenancy(string $tenancyId): array
    {
        $t = DB::row('SELECT * FROM tenancies WHERE id = ?', [$tenancyId]);
        if (!$t) throw new \RuntimeException("Tenancy not found: {$tenancyId}");
        return $t;
    }

    private function createInvoice(
        string $tenancyId,
        \DateTimeImmutable $period,
        float $amount,
        string $dueDate,
        ?string $noteFlag = null
    ): void {
        DB::insert('rent_invoices', [
            'id'           => UuidHelper::v4(),
            'tenancy_id'   => $tenancyId,
            'period_month' => $period->format('Y-m-d'),
            'amount_due'   => max(0, $amount),   // never negative
            'amount_paid'  => 0,
            'overpayment'  => 0,
            'due_date'     => $dueDate,
            'status'       => 'unpaid',
            'notes'        => $noteFlag,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }
}
