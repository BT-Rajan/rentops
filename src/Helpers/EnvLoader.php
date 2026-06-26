<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Minimal .env loader — no external dependencies.
 * Parses KEY=VALUE lines, strips quotes, skips comments.
 */
class EnvLoader
{
    public static function load(string $path): void
    {
        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key   = trim($key);
            $value = trim($value);

            // Strip surrounding quotes
            if (preg_match('/^(["\'])(.*)(\1)$/', $value, $m)) {
                $value = $m[2];
            }

            if ($key === '') continue;

            // Only set if not already in environment (allows shell override)
            if (!isset($_ENV[$key]) && !getenv($key)) {
                $_ENV[$key]   = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}
