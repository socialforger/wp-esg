<?php
namespace WpEsg\Storage\Registry\It;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Registry
 * Algorithmic normalization and resolution engine for Italian corporate metadata.
 * Strictly decoupled from static data maps; consumes sibling JSON configuration sheets.
 *
 * @package WpEsg\Storage\Registry\It
 */
class Registry {

    /**
     * Normalizes and resolves an Italian ATECO 2025 code into a system sector token.
     * Executes a punctuation-agnostic inverse tree search over the local JSON file.
     *
     * @param string $businessCode Raw user code string from form submission (e.g., "01.13.11", "132000").
     * @return string              Normalized sector classification identifier token.
     */
    public function getSector(string $businessCode): string {
        // 1. Normalizzazione simmetrica dell'input dell'utente (solo numeri)
        $cleanInput = preg_replace('/[^0-9]/', '', $businessCode);
        if (empty($cleanInput)) {
            return 'UNIVERSAL_SERVICES';
        }

        $jsonPath = __DIR__ . '/ateco.json';
        if (!file_exists($jsonPath)) {
            return $this->executeMacroDivisionFallback($cleanInput);
        }

        $rawRows = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($rawRows)) {
            return $this->executeMacroDivisionFallback($cleanInput);
        }

        // 2. Indicizzazione dinamica speculare delle chiavi del JSON a runtime (rimozione punti)
        $optimizedIndex = [];
        foreach ($rawRows as $row) {
            if (!isset($row['codice'])) {
                continue;
            }
            $cleanKey = preg_replace('/[^0-9]/', '', $row['codice']);
            if (!empty($cleanKey)) {
                $optimizedIndex[$cleanKey] = $row['sector_token'] ?? 'UNIVERSAL_SERVICES';
            }
        }

        // 3. Ricerca algoritmica ad albero rovesciato (Sottocategoria [6] -> Divisione [2])
        for ($length = strlen($cleanInput); $length >= 2; $length--) {
            $sliceKey = substr($cleanInput, 0, $length);
            if (isset($optimizedIndex[$sliceKey])) {
                return sanitize_text_field($optimizedIndex[$sliceKey]);
            }
        }

        return $this->executeMacroDivisionFallback($cleanInput);
    }

    /**
     * Normalizes and resolves an Italian legal entity string into standardized governance metadata parameters.
     * Strips punctuation, spacing, and case formatting to match clean token keys.
     *
     * @param string $legalEntity Raw text input string from user choice (e.g., "S.r.l.", "Soc. Coop.").
     * @return array              Resolved governance configuration map or fallback defaults.
     */
    public function getLegalEntityMeta(string $legalEntity): array {
        $fallback = [
            'acronimo'        => 'N/A',
            'descrizione'     => 'Unknown Legal Structure',
            'governance_tier' => 'GENERIC'
        ];

        // 1. Normalizzazione simmetrica dell'input dell'utente (solo lettere maiuscole)
        $cleanEntityKey = strtoupper(preg_replace('/[^A-Za-z]/', '', trim($legalEntity)));
        if (empty($cleanEntityKey)) {
            return $fallback;
        }

        $jsonPath = __DIR__ . '/legal_entities.json';
        if (!file_exists($jsonPath)) {
            return $fallback;
        }

        $rawMatrix = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($rawMatrix)) {
            return $fallback;
        }

        // 2. Indicizzazione dinamica speculare delle chiavi del dizionario (rimozione punti)
        $optimizedMatrix = [];
        foreach ($rawMatrix as $key => $data) {
            $cleanKey = strtoupper(preg_replace('/[^A-Za-z]/', '', $key));
            $optimizedMatrix[$cleanKey] = $data;
        }

        // 3. Abbinamento diretto O(1) sul tracciato normalizzato simmetrico
        if (isset($optimizedMatrix[$cleanEntityKey])) {
            return array_map('sanitize_text_field', $optimizedMatrix[$cleanEntityKey]);
        }

        return $fallback;
    }

  <?php
namespace WpEsg\Storage\Registry\It;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Registry
 * Symmetrical algorithmic normalizer for Italian enterprise business metadata.
 * Strips all formatting anomalies from both user inputs and JSON dictionary keys at runtime.
 *
 * @package WpEsg\Storage\Registry\It
 */
class Registry {

    /**
     * Normalizes an Italian ATECO code and searches the local configuration registry map.
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

        $jsonPath = __DIR__ . '/ateco.json';
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
