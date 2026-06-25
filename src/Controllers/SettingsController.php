<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;

class SettingsController extends BaseController
{
    public function index(array $params = []): void
    {
        $property = DB::row('SELECT * FROM properties LIMIT 1');
        $this->render('settings/index', [
            'pageTitle' => 'Settings',
            'property'  => $property,
            'csrf'      => $this->csrfToken(),
            'flash'     => $this->flash(),
            'user'      => $this->currentUser(),
        ]);
    }

    public function update(array $params = []): void
    {
        $this->verifyCsrf();
        $property = DB::row('SELECT * FROM properties LIMIT 1');
        if (!$property) {
            $this->redirect('/settings', 'No property configured.', 'error');
            return;
        }

        DB::update('properties', [
            'name'            => trim($_POST['name']            ?? $property['name']),
            'address'         => trim($_POST['address']         ?? $property['address']),
            'default_due_day' => (int)($_POST['default_due_day'] ?? $property['default_due_day']),
        ], 'id = ?', [$property['id']]);

        $this->redirect('/settings', 'Settings saved.');
    }
}
