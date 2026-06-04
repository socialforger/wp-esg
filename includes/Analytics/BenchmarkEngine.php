<?php
namespace WpEsg\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BenchmarkEngine
 * Analyzes frozen multi-dimensional organizational metrics against the collective 
 * network registry to extract market peer group positionings and statistical variances.
 *
 * @package WpEsg\Analytics
 */
class BenchmarkEngine {

    /**
     * Calculates the performance distance and percentile rank for a specific quantitative indicator.
     *
     * @param string $indicatorId     The targeted indicator index key (e.g., 'ind_governance').
     * @param float  $companyScore    The calculated final score achieved by the organization (0-100).
     * @param int    $accountingYear  The targeted evaluation fiscal year window context.
     * @param string $marketCategory  The specific sector peer-group partition filter token (e.g., 'textile_clothing').
     * @return array                  Benchmark positioning metrics array including percentile ranks and distances from mean.
     */
    public function computePeerPositioning(string $indicatorId, float $companyScore, int $accountingYear, string $marketCategory): array {
        global $wpdb;

        $table = $wpdb->prefix . 'esg_assessment_results';

        // Retrieve calculations maps scores of matching category peers to build the reference baseline population pool
        // Using standard JSON_EXTRACT to drill directly into the frozen archived JSON data stream
        $peerScores = $wpdb->get_col($wpdb->prepare(
            "SELECT CAST(JSON_EXTRACT(assessment_payload, %s) AS DECIMAL(10,2)) 
             FROM $table 
             WHERE balance_year = %d 
               AND JSON_UNQUOTE(JSON_EXTRACT(assessment_payload, '$.meta.product_category')) = %s",
            "$.indicators.{$indicatorId}",
            $accountingYear,
            sanitize_text_field($marketCategory)
        ));

        if (empty($peerScores)) {
            return [
                'percentile_rank'    => 50.0, // Standard neutral fallback profile position rank
                'distance_from_mean' => 0.0
            ];
        }

        $peerScores = array_map('floatval', $peerScores);
        sort($peerScores);
        
        $totalPeers = count($peerScores);
        $lesserScoresCount = 0;
        $scoreSum = 0.0;

        foreach ($peerScores as $score) {
            if ($score < $companyScore) {
                $lesserScoresCount++;
            }
            $scoreSum += $score;
        }

        $meanValue = $scoreSum / $totalPeers;
        
        // Percentile rank calculation using the standard distribution model bounds [0, 100]
        $percentileRank = ($totalPeers > 1) ? ($lesserScoresCount / ($totalPeers - 1)) * 100.0 : 100.0;

        return [
            'percentile_rank'    => (float)max(0, min(100, $percentileRank)),
            'distance_from_mean' => (float)($companyScore - $meanValue)
        ];
    }
}
