<?php
namespace WpEsg\Output\Adapters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TablePressIntegrator
 * Automatically synchronizes qualitative parameters and public registry data onto TablePress tables.
 *
 * @package WpEsg\Output\Adapters
 */
class TablePressIntegrator {

    /**
     * Updates an external TablePress container grid map to expose completed network profiles.
     *
     * @param int   $tablePressId Target TablePress table instance identifier.
     * @param array $cleanRow     Normalized row elements ready for public directory visibility mapping.
     * @return bool               True on successful table persistence execution.
     */
    public function syncAssessmentToPublicGrid(int $tablePressId, array $cleanRow): bool {
        if (!class_exists('TablePress') || !class_exists('TablePress_Tables_Model')) {
            return false; // Fail gracefully if TablePress core systems are deactivated
        }

        // Load native TablePress storage interaction models
        $tableModel = \TablePress::$model_table;
        $table = $tableModel->load($tablePressId, 'json', true);

        if (is_wp_error($table)) {
            return false;
        }

        // Append corporate data payload row array maps to the bottom layer index matrix
        $table['data'][] = [
            sanitize_text_field($cleanRow['company_name'] ?? 'Anonymous Member'),
            sanitize_text_field($cleanRow['business_category'] ?? 'General Services'),
            sanitize_text_field($cleanRow['region'] ?? 'IT'),
            sprintf('Pillar Index Score: %d/100', (int)($cleanRow['global_score'] ?? 0))
        ];

        $tableModel->save($table);
        return true;
    }
}
