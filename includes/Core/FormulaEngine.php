<?php
namespace WpEsg\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use WpEsg\Frameworks\FrameworkRegistry;

/**
 * Class FormulaEngine
 * Implements the universal two-step analytical pipeline mapping raw question answers
 * to intermediate metrics, and then translating those metrics into 0-100 indicators.
 *
 * @package WpEsg\Core
 */
class FormulaEngine {

    private ExpressionLanguage $lexer;
    private string $frameworkPath;

    public function __construct() {
        $this->lexer = new ExpressionLanguage();
        $this->frameworkPath = WP_ESG_PATH . 'frameworks/openesea/';
    }

    /**
     * Executes the absolute analytical pipeline evaluation on a target session record.
     *
     * @param array $assessmentRow Unified active row state map fetched from tracking layers.
     * @return array               Calculated quantitative data payload ready for historical freezing.
     */
    public function computeAssessment(array $assessmentRow): array {
        $rawAnswers   = json_decode($assessmentRow['raw_answers'] ?? '{}', true);
        $companySize  = $assessmentRow['company_size'] ?? 'Short';
        $businessCode = $assessmentRow['business_code'] ?? '';

        // Step 1: Compute intermediate Metrics from raw inputs
        $computedMetrics = $this->compileMetrics($rawAnswers, $companySize);

        // Step 2: Aggregate Metrics into final standard percentage Indicators (0-100)
        $computedIndicators = $this->compileIndicators($computedMetrics);

        return [
            'meta' => [
                'computed_at'        => gmdate('Y-m-d H:i:s'),
                'target_size_scope'  => $companySize,
                'business_code'      => $businessCode,
                'product_category'   => FrameworkRegistry::mapSectorToCategory($businessCode)
            ],
            'metrics'    => $computedMetrics,
            'indicators' => $computedIndicators
        ];
    }

    /**
     * Step 1: Processes raw form data into standardized structural metrics variables.
     */
    private function compileMetrics(array $rawAnswers, string $companySize): array {
        $metricsFile = $this->frameworkPath . 'metrics.json';
        if (!file_exists($metricsFile)) {
            return [];
        }

        $metricsRules = json_decode(file_get_contents($metricsFile), true) ?: [];
        $compiled = [];

        foreach ($metricsRules as $metricId => $rule) {
            // Check scope safety boundary constraints: bypass complex metrics for simplified Short runs
            $targetScope = $rule['scope'] ?? 'Universal';
            if ($targetScope === 'Long' && $companySize === 'Short') {
                $compiled[$metricId] = 0.0;
                continue;
            }

            $expression = $rule['formula'] ?? '0';
            try {
                $value = $this->lexer->evaluate($expression, ['answers' => $rawAnswers]);
                $compiled[$metricId] = (float)$value;
            } catch (\Exception $e) {
                $compiled[$metricId] = 0.0; // Fail-safe default fallback configuration anchor
            }
        }

        return $compiled;
    }

    /**
     * Step 2: Aggregates intermediate metrics into final percentage indicator dimensions.
     */
    private function compileIndicators(array $computedMetrics): array {
        $indicatorsFile = $this->frameworkPath . 'indicators.json';
        if (!file_exists($indicatorsFile)) {
            return [];
        }

        $indicatorsRules = json_decode(file_get_contents($indicatorsFile), true) ?: [];
        $compiled = [];

        foreach ($indicatorsRules as $indicatorId => $rule) {
            $expression = $rule['formula'] ?? '0';
            try {
                $score = $this->lexer->evaluate($expression, ['metrics' => $computedMetrics]);
                $compiled[$indicatorId] = (float)max(0, min(100, $score)); // Force mathematical boundaries [0, 100]
            } catch (\Exception $e) {
                $compiled[$indicatorId] = 0.0;
            }
        }

        return $compiled;
    }
}
