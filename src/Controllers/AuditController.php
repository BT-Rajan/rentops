<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\Helpers\RentEngine;

class AuditController extends BaseController
{
    public function index(array $params = []): void
    {
        $engine    = new RentEngine();
        $tenancies = DB::rows("
            SELECT te.id, te.move_in_date, te.move_out_date, te.agreed_rent, te.status,
                   t.full_name, r.room_number
            FROM tenancies te
            JOIN tenants t ON t.id = te.tenant_id
            JOIN rooms r   ON r.id = te.room_id
            ORDER BY te.status DESC, t.full_name
        ");

        $results = [];
        foreach ($tenancies as $te) {
            $issues = $engine->auditTenancy($te['id']);
            $results[] = [
                'tenancy'   => $te,
                'issues'    => $issues,
                'has_error' => !empty($issues),
            ];
        }

        // Global stats
        $stats = [
            'total'        => count($tenancies),
            'with_issues'  => count(array_filter($results, fn($r) => $r['has_error'])),
            'clean'        => count(array_filter($results, fn($r) => !$r['has_error'])),
            'missing_inv'  => 0,
            'overpayments' => 0,
        ];
        foreach ($results as $r) {
            foreach ($r['issues'] as $issue) {
                if ($issue['type'] === 'missing_invoice') $stats['missing_inv']++;
                if ($issue['type'] === 'overpayment')     $stats['overpayments']++;
            }
        }

        $this->render('audit/index', [
            'pageTitle' => 'Data Audit',
            'results'   => $results,
            'stats'     => $stats,
            'user'      => $this->currentUser(),
            'csrf'      => $this->csrfToken(),
        ]);
    }

    /**
     * Auto-fix: generate missing invoices found during audit.
     *
     * FIX B11: The previous query only fetched active tenancies, but index() audits
     * ALL tenancies (including closed ones). This meant the audit dashboard could
     * permanently report missing invoices on closed tenancies that fix() would
     * never touch. Fix: use the same tenancy scope as index() — all statuses.
     * generateMonthlyInvoicesForTenancy() already guards against inserting invoices
     * on closed tenancies via its own status check, so it is safe to pass closed
     * tenancy IDs — they will simply return false and be skipped cleanly.
     */
    public function fix(array $params = []): void
    {
        $this->verifyCsrf();
        $engine = new RentEngine();
        $fixed  = 0;

        // Audit ALL tenancies (active + closed) — matches the scope used in index()
        $tenancies = DB::rows("SELECT id FROM tenancies");
        foreach ($tenancies as $te) {
            $issues = $engine->auditTenancy($te['id']);
            foreach ($issues as $issue) {
                if ($issue['type'] === 'missing_invoice' && isset($issue['month'])) {
                    if ($engine->generateMonthlyInvoicesForTenancy($te['id'], $issue['month'])) {
                        $fixed++;
                    }
                }
            }
        }

        // Also refresh overdue status
        $overdueFixed = $engine->refreshOverdueStatus();

        $this->redirect('/audit', "Fixed {$fixed} missing invoice(s), {$overdueFixed} overdue status update(s).");
    }

    /**
     * Audit log viewer.
     */
    public function log(array $params = []): void
    {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset= ($page - 1) * $limit;

        $entries = DB::rows(
            "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $total = (int)DB::scalar('SELECT COUNT(*) FROM audit_log');

        $this->render('audit/log', [
            'pageTitle' => 'Audit Log',
            'entries'   => $entries,
            'total'     => $total,
            'page'      => $page,
            'pages'     => (int)ceil($total / $limit),
            'user'      => $this->currentUser(),
        ]);
    }
}
