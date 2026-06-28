<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Lang;

class LangController extends BaseController
{
    public function switch(array $params = []): void
    {
        $locale = $_POST['locale'] ?? $_GET['locale'] ?? 'en';
        $allowed = ['en', 'ta'];
        $locale  = in_array($locale, $allowed, true) ? $locale : 'en';

        $_SESSION['locale'] = $locale;
        Lang::setLocale($locale);

        // Redirect back
        $ref = $_SERVER['HTTP_REFERER'] ?? '/';
        header('Location: ' . $ref);
        exit;
    }
}
