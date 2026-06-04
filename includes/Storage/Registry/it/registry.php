<?php
namespace WpEsg\Storage\Registry\It;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Registry
 * Symmetrical algorithmic normalizer for Italian enterprise business metadata.
 * Ingests the unified ATECO 2025 JSON dump co-located within the /it/ module directory.
 *
 * @package WpEsg\Storage\Registry\It
 */
class Registry {

    /**
     * Normalizes an Italian ATECO code and searches the local ateco2025.json structure.
     * Executes a punctuation-agnostic inverse tree search over the imported data rows.
     *
     * @param string $businessCode Raw code string from form fields (e.g., "01.13.11", "011100").
     * @return string              The raw sector token defined inside the JSON metadata file, or an empty string.
     */
    public function getSector(string $businessCode): string {
        // 1. Symmetrical input normalization: extract digits only
        $cleanInput = preg_replace('/[^0-9]/', '', $businessCode);
        if (empty($cleanInput)) {
            return '';
        }

        // Updated path targeting the real repository standard filename
        $jsonPath = __DIR__ . '/ateco2025.json';
        if (!file_exists($jsonPath)) {
            return '';
        }

        $rawRows = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($rawRows)) {
            return '';
        }

        // 2. Symmetrical JSON key normalization: strip dots/formatting at runtime
        $optimizedIndex = [];
        foreach ($rawRows as $row) {
            if (!isset($row['codice'])) {
                continue;
            }
            $cleanKey = preg_replace('/[^0-9]/', '', $row['codice']);
            if (!empty($cleanKey)) {
                // Ingests the custom sector_token directly mapped onto the structural taxonomy nodes
                $optimizedIndex[$cleanKey] = $row['sector_token'] ?? '';
            }
        }

        // 3. Inverse hierarchical tree search from maximum length down to Division level (2 digits)
        for ($length = strlen($cleanInput); $length >= 2; $length--) {
            $sliceKey = substr($cleanInput, 0, $length);
            if (isset($optimizedIndex[$sliceKey])) {
                return sanitize_text_field($optimizedIndex[$sliceKey]);
            }
        }

        return '';
    }

    /**
     * Normalizes an Italian legal entity string and extracts its raw parameters payload array.
     * Implements full symmetrical sanitation across input values and target JSON dictionary keys.
     *
     * @param string $legalEntity Raw legal entity format notation text string (e.g., "S.r.l.", "Soc. Coop.").
     * @return array              The matched declarative array structure from JSON sheets, or empty structure.
     */
    public function getLegalEntityMeta(string $legalEntity): array {
        $emptyFallback = [
            'acronimo'        => 'N/A',
            'descrizione'     => 'Unknown',
            'governance_tier' => 'UNKNOWN'
        ];

        // 1. Symmetrical input normalization: force uppercase alphabetical tokens only
        $cleanInputKey = strtoupper(preg_replace('/[^A-Za-z]/', '', trim($legalEntity)));
        if (empty($cleanInputKey)) {
            return $emptyFallback;
        }

        $jsonPath = __DIR__ . '/legal_entities.json';
        if (!file_exists($jsonPath)) {
            return $emptyFallback;
        }

        $rawMatrix = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($rawMatrix)) {
            return $emptyFallback;
        }

        // 2. Symmetrical JSON key normalization: strip punctuation at runtime from dictionary keys
        $optimizedMatrix = [];
        foreach ($rawMatrix as $key => $data) {
            $cleanJsonKey = strtoupper(preg_replace('/[^A-Za-z]/', '', $key));
            if (!empty($cleanJsonKey)) {
                $optimizedMatrix[$cleanJsonKey] = $data;
            }
        }

        // 3. Symmetrical atomic O(1) comparison match
        if (isset($optimizedMatrix[$cleanInputKey])) {
            return array_map('sanitize_text_field', $optimizedMatrix[$cleanInputKey]);
        }

        return $emptyFallback;
    }
}
