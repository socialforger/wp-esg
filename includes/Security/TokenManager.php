<?php
namespace WpEsg\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TokenManager
 * Orchestrates the lifecycle of single-use, cryptographic, unauthenticated access tokens 
 * for remote network producers.
 *
 * @package WpEsg\Security
 */
class TokenManager {

    private string $tableName;

    public function __construct() {
        global $wpdb;
        $this->tableName = $wpdb->prefix . 'esg_activation_tokens';
    }

    /**
     * Generates, hashes, and registers a single-use access link token inside the storage layers.
     *
     * @param string $companyTaxId Central organization tax identifier string.
     * @param int    $balanceYear  Target fiscal accounting period.
     * @param int    $lifespanDays Validity window duration in days.
     * @return string              The raw alfanumeric token to append to the anonymous URL query string.
     */
    public function generateSingleUseToken(string $companyTaxId, int $balanceYear, int $lifespanDays = 30): string {
        global $wpdb;

        // Generate cryptographically secure pseudorandom raw bytes
        $rawToken = bin2hex(random_bytes(32));
        
        // Save only the SHA-256 hash in the database to prevent database leakage exploits
        $secureHash = hash('sha256', $rawToken);
        
        $expiryDate = gmdate('Y-m-d H:i:s', time() + ($lifespanDays * DAY_IN_SECONDS));

        $wpdb->insert(
            $this->tableName,
            [
                'token_key'      => $secureHash,
                'company_tax_id' => sanitize_text_field($companyTaxId),
                'balance_year'   => (int)$balanceYear,
                'is_used'        => 0,
                'expires_at'     => $expiryDate
            ],
            ['%s', '%s', '%d', '%d', '%s']
        );

        return $rawToken;
    }

    /**
     * Inspects a raw token string parameter against active, unused database records.
     *
     * @param string $rawToken The raw token extracted from the request context parameters.
     * @return array|null       The metadata array on matching context, or null if invalid or expired.
     */
    public function validateTokenContext(string $rawToken): ?array {
        global $wpdb;

        if (empty($rawToken)) {
            return null;
        }

        $secureHash = hash('sha256', $rawToken);
        $currentGmt = gmdate('Y-m-d H:i:s');

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT company_tax_id, balance_year FROM {$this->tableName} 
                 WHERE token_key = %s AND is_used = 0 AND expires_at > %s",
                $secureHash,
                $currentGmt
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Burns an active token signature immediately upon entry ingestion to prevent token-replay exploits.
     *
     * @param string $rawToken The raw access token currently verified.
     * @return bool            True if consumed successfully.
     */
    public function consumeToken(string $rawToken): bool {
        global $wpdb;

        $secureHash = hash('sha256', $rawToken);

        $updated = $wpdb->update(
            $this->tableName,
            ['is_used' => 1],
            ['token_key' => $secureHash],
            ['%d'],
            ['%s']
        );

        return $updated !== false;
    }
}
