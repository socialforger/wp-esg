<?php
namespace WpEsg\Core;

if (!defined('ABSPATH')) {
    exit;
}

use JsonSchema\Validator;

/**
 * Class SchemaValidator
 * Enforces structural typing checks on ingestion payloads using strict JSON Schema blueprint validations.
 *
 * @package WpEsg\Core
 */
class SchemaValidator {

    private string $categoriesPath;

    public function __construct() {
        $this->categoriesPath = WP_ESG_PATH . 'market-categories/';
    }

    /**
     * Validates an entry payload dataset against its targeted category schema specification file.
     *
     * @param array  $submittedData   Associative key-value inputs array mapped out by ingestion adapters.
     * @param string $marketCategoryId Target directory category identifier key (e.g., 'textile_clothing').
     * @return array                  Verification outcome dictionary containing valid state flag and error trails log.
     */
    public function validateMarketCategoryPayload(array $submittedData, string $marketCategoryId): array {
        $schemaFile = $this->categoriesPath . $marketCategoryId . '/schema.json';

        if (!file_exists($schemaFile)) {
            return [
                'valid'  => false,
                'errors' => [sprintf('Structural blueprint configuration missing for sector reference target: %s', esc_html($marketCategoryId))]
            ];
        }

        // Deep cast associative maps to formal objects to conform with strict validation typing rules
        $dataModel   = json_decode(json_encode($submittedData));
        $schemaModel = json_decode(file_get_contents($schemaFile));

        $validator = new Validator();
        $validator->validate($dataModel, $schemaModel);

        if ($validator->isValid()) {
            return [
                'valid'  => true,
                'errors' => []
            ];
        }

        $logs = [];
        foreach ($validator->getErrors() as $error) {
            $logs[] = sprintf('[Achromatic Structural Violation: %s] %s', $error['property'], $error['message']);
        }

        return [
            'valid'  => false,
            'errors' => $logs
        ];
    }
}
