<?php
namespace WpEsg\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TrendEngine
 * Models multi-year development velocities and improvement trajectories 
 * by compiling historical sequences of corporate performance.
 *
 * @package WpEsg\Analytics
 */
class TrendEngine {

    /**
     * Processes historical performance metrics to extrapolate development trajectory vectors.
     *
     * @param string $companyTaxId Core organization identifier registry target string.
     * @param string $indicatorId  Target performance indicator field index key.
     * @return array               Trajectory analysis dictionary containing directional vectors and net deltas.
     */
    public function calculateTrajectory(string $companyTaxId, string $indicatorId): array {
        global $wpdb;

        $table = $wpdb->prefix . 'esg_assessment_results';

        // Extract ordered historical performance runs for the targeted legal entity
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT balance_year, CAST(JSON_EXTRACT(assessment_payload, %s) AS DECIMAL(10,2)) as score
             FROM $table 
             WHERE company_tax_id = %s 
             ORDER BY balance_year ASC",
            "$.indicators.{$indicatorId}",
            sanitize_text_field($companyTaxId)
        ), ARRAY_A);

        // We require at least two separate fiscal points to extract a vector trend direction
        if (count($records) < 2) {
            return [
                'direction'  => 'STABLE',
                'net_delta'  => 0.0,
                'trajectory' => 'INSIGNIFICANT_DATA_HISTORICAL_POOL'
            ];
        }

        $latestRun   = end($records);
        $previousRun = prev($records) ?: reset($records);

        $deltaValue = (float)($latestRun['score'] - $previousRun['score']);
        
        // Define standard trajectory thresholds boundaries
        $direction = 'STABLE';
        if ($deltaValue > 1.5) {
            $direction = 'UPWARD';
        } elseif ($deltaValue < -1.5) {
            $direction = 'DOWNWARD';
        }

        return [
            'direction'  => $direction,
            'net_delta'  => $deltaValue,
            'trajectory' => sprintf('Computed variation from fiscal year %d to %d.', (int)$previousRun['balance_year'], (int)$latestRun['balance_year'])
        ];
    }
}
