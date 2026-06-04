<?php
namespace WpEsg\Input;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BaseInputAdapter
 * Abstract blueprint model enforcing input standardizations and structural mappings 
 * across variable third-party data collection mechanisms.
 *
 * @package WpEsg\Input
 */
abstract class BaseInputAdapter {

    /**
     * Translates custom field layouts into standardized core repository record keys.
     *
     * @param array $rawPayload Context elements parsed straight from the active intake data channel.
     * @return array            Normalized key-value dataset map matching platform storage parameters.
     */
    abstract protected function normalizeFormFields(array $rawPayload): array;

    /**
     * Validates data structure mappings and commits clean entries to raw tracking layers.
     *
     * @param array $cleanData Standardized array mapping matching the prefix_esg_assessments columns.
     * @return bool            True on successful table persistence execution.
     */
    protected function persistAssessmentAnswers(array $cleanData): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'esg_assessments';
        $taxId = sanitize_text_field($cleanData['company_tax_id'] ?? '');
        $year  = (int)($cleanData['balance_year'] ?? gmdate('Y'));

        if (empty($taxId)) {
            return false;
        }

        // Check if an entry already exists for the company matching this specific accounting year
        $existingId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE company_tax_id = %s AND balance_year = %d",
            $taxId, $year
        ));

        if ($existingId) {
            $updated = $wpdb->update($table, $cleanData, ['id' => $existingId]);
            return $updated !== false;
        } else {
            $inserted = $wpdb->insert($table, $cleanData);
            return $inserted !== false;
        }
    }
}
