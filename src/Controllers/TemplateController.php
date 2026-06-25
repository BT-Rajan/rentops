<?php
declare(strict_types=1);

namespace App\Controllers;

class TemplateController extends BaseController
{
    public function csvTemplate(array $params = []): void
    {
        $filename = 'rentops_import_template.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');

        // Header row
        fputcsv($out, [
            'full_name', 'phone', 'email', 'room_number',
            'move_in_date', 'agreed_rent', 'security_deposit',
            'rent_due_day', 'id_proof_type', 'id_proof_number', 'emergency_contact',
        ]);

        // Two sample rows
        fputcsv($out, ['Ravi Kumar',  '9876543210', 'ravi@email.com',  '101', date('Y-m-01', strtotime('-3 months')), '9000', '18000', '5', 'Aadhaar', '1234-5678-9012', 'Meena Kumar — 9876543211']);
        fputcsv($out, ['Priya Singh', '9123456789', 'priya@email.com', '102', date('Y-m-01', strtotime('-2 months')), '9000', '18000', '5', 'Aadhaar', '9876-5432-1098', '']);

        fclose($out);
        exit;
    }
}
