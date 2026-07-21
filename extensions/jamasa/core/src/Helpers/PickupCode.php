<?php

declare(strict_types=1);

namespace Jamasa\Core\Helpers;

/**
 * Pickup Code Helper
 *
 * Derives a 4-character pickup code (A000 format) from an order hash.
 * Used to display a non-sequential order identifier to customers.
 */
class PickupCode
{
    /**
     * Generate a pickup code from an order hash.
     *
     * Format: 1 letter (A-Z) + 3 digits (000-999) = 26,000 combinations
     *
     * @param string $hash The 32-character MD5 hash from the order
     * @return string The 4-character pickup code (e.g., "L899")
     */
    public static function fromHash(?string $hash): string
    {
        // Order hashes are 32-char MD5 (hex). Guard length AND hex-ness: the first
        // 5 chars feed hexdec() below, which emits a PHP deprecation on non-hex
        // input. Treat a non-hex/short hash as invalid — never happens for a real
        // order, keeps the code hex-clean and PHP-version-proof.
        if (empty($hash) || strlen($hash) < 5 || !ctype_xdigit(substr($hash, 0, 5))) {
            return '----';
        }

        // First 2 hex chars -> letter (A-Z)
        $letterIndex = hexdec(substr($hash, 0, 2)) % 26;
        $letter = chr(ord('A') + $letterIndex);

        // Next 3 hex chars -> digits (000-999)
        $digits = str_pad((string)(hexdec(substr($hash, 2, 3)) % 1000), 3, '0', STR_PAD_LEFT);

        return $letter . $digits;
    }
}
