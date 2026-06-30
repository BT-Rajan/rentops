<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Crypto — shared AES-256-CBC encrypt/decrypt for secrets at rest
 * (Razorpay key secret, webhook secret, etc).
 *
 * Single source of truth — was previously duplicated inline in
 * SettingsController::updateRazorpay() and InvoiceController::decryptSecret().
 */
class Crypto
{
    public static function encrypt(string $plain): string
    {
        $key = base64_decode($_ENV['ENCRYPT_KEY'] ?? '');
        if (!$key) return $plain; // plain fallback if no ENCRYPT_KEY configured

        $iv     = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(?string $encrypted): string
    {
        if (!$encrypted) return '';

        $key = base64_decode($_ENV['ENCRYPT_KEY'] ?? '');
        if (!$key) return $encrypted; // wasn't encrypted to begin with

        $data   = base64_decode($encrypted);
        $iv     = substr($data, 0, 16);
        $cipher = substr($data, 16);
        $plain  = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $plain !== false ? $plain : $encrypted;
    }
}
