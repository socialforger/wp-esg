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
    /**
     * Maps ATECO section letter codes to canonical sector tokens.
     * Used as a fallback when the ateco2025.json data lacks a sector_token field.
     */
    private static array $sectionTokenMap = [
        'A' => 'AGRICULTURE',
        'B' => 'EXTRACTIVE',
        'C' => 'MANUFACTURING',
        'D' => 'ENERGY',
        'E' => 'WATER_WASTE',
        'F' => 'CONSTRUCTION',
        'G' => 'TRADE',
        'H' => 'TRANSPORT',
        'I' => 'HOSPITALITY',
        'J' => 'MEDIA_PUBLISHING',
        'K' => 'ICT_SERVICES',
        'L' => 'FINANCE_INSURANCE',
        'M' => 'REAL_ESTATE',
        'N' => 'PROFESSIONAL_SERVICES',
        'O' => 'ADMIN_SUPPORT',
        'P' => 'PUBLIC_ADMINISTRATION',
        'Q' => 'EDUCATION',
        'R' => 'HEALTH_SOCIAL',
        'S' => 'ARTS_ENTERTAINMENT',
        'T' => 'OTHER_SERVICES',
        'U' => 'HOUSEHOLD',
        'V' => 'EXTRATERRITORIAL',
    ];

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

        // 2a. Build section letter -> Division numeric mapping (e.g. 'A' -> ['01','02','03'])
        //     and Numeric -> sector_token index (uses sector_token field when present, falls back to section map).
        $sectionOfDivision = []; // numeric division prefix -> section letter
        $currentSection    = '';
        foreach ($rawRows as $row) {
            if ( !isset($row['codice']) ) {
                continue;
            }
            if ($row['classificazione'] === 'Sezione') {
                $currentSection = strtoupper(trim($row['codice']));
                continue;
            }
            if ($row['classificazione'] === 'Divisione') {
                $divKey = preg_replace('/[^0-9]/', '', $row['codice']);
                if (!empty($divKey) && !empty($currentSection)) {
                    $sectionOfDivision[$divKey] = $currentSection;
                }
            }
        }

        // 2b. Build optimized numeric-key index with sector_token (or derived token via section map)
        $optimizedIndex = [];
        foreach ($rawRows as $row) {
            if (!isset($row['codice'])) {
                continue;
            }
            $cleanKey = preg_replace('/[^0-9]/', '', $row['codice']);
            if (empty($cleanKey)) {
                continue;
            }
            if (!empty($row['sector_token'])) {
                // Prefer explicit token when the JSON provides it
                $optimizedIndex[$cleanKey] = $row['sector_token'];
            } else {
                // Derive token from ATECO section letter via static map
                $divPrefix = substr($cleanKey, 0, 2);
                $section   = $sectionOfDivision[$divPrefix] ?? '';
                $optimizedIndex[$cleanKey] = self::$sectionTokenMap[$section] ?? 'UNIVERSAL_SERVICES';
            }
        }

        // 3. Inverse hierarchical tree search from maximum length down to Division level (2 digits)
        for ($length = strlen($cleanInput); $length >= 2; $length--) {
            $sliceKey = substr($cleanInput, 0, $length);
            if (isset($optimizedIndex[$sliceKey])) {
                return sanitize_text_field($optimizedIndex[$sliceKey]);
            }
        }

        return 'UNIVERSAL_SERVICES';
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
