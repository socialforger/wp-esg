<?php
namespace WpEsg\Storage\Registry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ItRegistry
 * Pure algorithmic normalizer for Italian ISTAT ATECO codes.
 * Responsibility is strictly limited to input sanitization and character trimming.
 *
 * @package WpEsg\Storage\Registry
 */
class ItRegistry {

    /**
     * Sanitizes and normalizes the incoming business code string.
     * Strips away dots, spaces, dashes, leaving only a clean numeric identifier.
     *
     * @param string $businessCode Raw code string from user input (e.g., " 01.11.00 ", "13-20-00", "20.41").
     * @return string              A clean, sanitized numeric-only string payload.
     */
    public static function getSector(string $businessCode): string {
        // 1. Trim whitespace boundaries and strip everything that is not an integer digit
        $cleanCode = preg_replace('/[^0-9]/', '', trim($businessCode));

        // 2. Return the pure numeric string or a safe fallback identifier if empty
        if (empty($cleanCode)) {
            return '000000';
        }

        return $cleanCode;
    }
}
