<?php
declare(strict_types=1);

namespace App\Helpers;

class UuidHelper
{
    /**
     * Generate a RFC 4122 v4 UUID using cryptographically secure random bytes.
     *
     * FIX B14: The previous implementation used mt_rand() (Mersenne Twister),
     * which is a predictable PRNG. Since UUIDs are used as primary keys for
     * tenants, tenancies, invoices, and payments, predictable IDs are an
     * enumeration/IDOR risk. random_bytes() uses the OS CSPRNG (/dev/urandom
     * on Linux), making the output unpredictable.
     */
    public static function v4(): string
    {
        $bytes = random_bytes(16);

        // Set version bits to 0100 (v4)
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        // Set variant bits to 10xx (RFC 4122)
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
