<?php
declare(strict_types=1);

namespace App\Controllers;

abstract class BaseController
{
    protected function render(string $view, array $data = [], string $layout = 'app'): void
    {
        extract($data);
        $viewFile   = ROOT . "/views/{$view}.php";
        $layoutFile = ROOT . "/views/layouts/{$layout}.php";

        if (!file_exists($viewFile)) {
            http_response_code(500);
            die("View not found: {$view}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === 'none') { echo $content; return; }

        require $layoutFile;
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Redirect to an app path. $path must begin with /
     * Automatically prepends APP_BASE so subfolder installs work.
     */
    protected function redirect(string $path, string $flash = '', string $flashType = 'success'): void
    {
        if ($flash) {
            $_SESSION['flash']      = $flash;
            $_SESSION['flash_type'] = $flashType;
        }
        header('Location: ' . url($path));
        exit;
    }

    protected function flash(): ?array
    {
        if (empty($_SESSION['flash'])) return null;
        $f = ['message' => $_SESSION['flash'], 'type' => $_SESSION['flash_type'] ?? 'success'];
        unset($_SESSION['flash'], $_SESSION['flash_type']);
        return $f;
    }

    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('CSRF validation failed.');
        }
    }

    protected function input(string $key, mixed $default = ''): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function requireFields(array $fields): ?string
    {
        foreach ($fields as $f) {
            if (empty($_POST[$f])) return "Field '{$f}' is required.";
        }
        return null;
    }

    protected function currentUser(): array
    {
        return [
            'id'   => $_SESSION['user_id']   ?? null,
            'name' => $_SESSION['user_name'] ?? 'Owner',
        ];
    }
}
