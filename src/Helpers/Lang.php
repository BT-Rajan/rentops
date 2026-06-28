<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Lang — lightweight i18n helper.
 *
 * Usage:  Lang::t('nav.dashboard')
 *         Lang::t('dues.invoices_generated', ['month' => 'Jun 2025'])
 *         Lang::setLocale('ta')
 *         Lang::current()   → 'en' | 'ta'
 */
class Lang
{
    private static string $locale   = 'en';
    private static array  $strings  = [];
    private static array  $fallback = [];
    private static bool   $loaded   = false;

    public static function init(): void
    {
        if (self::$loaded) return;

        // Resolve locale from session, default en
        $locale = $_SESSION['locale'] ?? 'en';
        self::setLocale($locale);
        self::$loaded = true;
    }

    public static function setLocale(string $locale): void
    {
        $allowed = ['en', 'ta'];
        $locale  = in_array($locale, $allowed, true) ? $locale : 'en';

        self::$locale  = $locale;
        self::$strings = self::load($locale);

        // Always keep English as fallback
        if ($locale !== 'en') {
            self::$fallback = self::load('en');
        } else {
            self::$fallback = [];
        }
    }

    public static function current(): string
    {
        return self::$locale;
    }

    /**
     * Translate a dot-notated key with optional placeholder substitution.
     * Falls back to English, then to the key itself.
     *
     * @param array<string,string> $replace e.g. ['month' => 'Jun 2025']
     */
    public static function t(string $key, array $replace = []): string
    {
        $value = self::resolve($key, self::$strings)
              ?? self::resolve($key, self::$fallback)
              ?? $key;

        foreach ($replace as $k => $v) {
            $value = str_replace('{' . $k . '}', $v, $value);
        }

        return $value;
    }

    private static function resolve(string $key, array $strings): ?string
    {
        $parts  = explode('.', $key);
        $cursor = $strings;
        foreach ($parts as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) return null;
            $cursor = $cursor[$part];
        }
        return is_string($cursor) ? $cursor : null;
    }

    private static function load(string $locale): array
    {
        $file = ROOT . "/lang/{$locale}.json";
        if (!file_exists($file)) return [];
        $json = file_get_contents($file);
        return json_decode($json, true) ?? [];
    }
}
