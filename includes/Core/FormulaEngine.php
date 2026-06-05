<?php

namespace WpEsg\Core;

if (!defined('ABSPATH')) {
    exit;
}

use WpEsg\Frameworks\FrameworkRegistry;
use WpEsg\Core\FormulaEvaluator;

/**
 * Class FormulaEngine
 *
 * OpenESEA analytical engine.
 *
 * Step 1:
 * Raw answers -> Metrics
 *
 * Step 2:
 * Metrics -> Indicators
 */
class FormulaEngine
{
    private FormulaEvaluator $evaluator;

    private string $frameworkPath;

    public function __construct()
    {
        $this->evaluator = new FormulaEvaluator();

        $this->frameworkPath =
            WP_ESG_PATH . 'frameworks/openesea/';
    }

    /**
     * Executes a full assessment computation.
     */
    public function computeAssessment(
        array $assessmentRow
    ): array {

        $rawAnswers = json_decode(
            $assessmentRow['raw_answers'] ?? '{}',
            true
        ) ?: [];

        $companySize =
            $assessmentRow['company_size'] ?? 'Short';

        $businessCode =
            $assessmentRow['business_code'] ?? '';

        $metrics = $this->compileMetrics(
            $rawAnswers,
            $companySize
        );

        $indicators = $this->compileIndicators(
            $metrics
        );

        return [
            'meta' => [
                'computed_at' =>
                    gmdate('Y-m-d H:i:s'),

                'target_size_scope' =>
                    $companySize,

                'business_code' =>
                    $businessCode,

                'product_category' =>
                    FrameworkRegistry::mapSectorToCategory(
                        $businessCode
                    )
            ],

            'metrics' => $metrics,

            'indicators' => $indicators
        ];
    }

    /**
     * Converts raw questionnaire answers
     * into normalized metrics.
     */
    private function compileMetrics(
        array $rawAnswers,
        string $companySize
    ): array {

        $file =
            $this->frameworkPath . 'metrics.json';

        if (!file_exists($file)) {
            return [];
        }

        $config = json_decode(
            file_get_contents($file),
            true
        );

        if (
            !is_array($config) ||
            !isset($config['metrics'])
        ) {
            return [];
        }

        $rules = $config['metrics'];

        $compiled = [];

        foreach ($rules as $metricId => $rule) {

            if (!is_array($rule)) {
                continue;
            }

            $scope =
                $rule['scope'] ?? 'Universal';

            if (
                $scope === 'Long' &&
                $companySize === 'Short'
            ) {
                $compiled[$metricId] = 0;
                continue;
            }

            $source =
                $rule['source'] ?? null;

            if (!$source) {
                continue;
            }

            $rawValue =
                $rawAnswers[$source] ?? null;

            $type =
                $rule['type'] ?? 'string';

            switch ($type) {

                case 'int':

                    $compiled[$metricId] =
                        (int)(
                            $rawValue
                            ?? ($rule['fallback'] ?? 0)
                        );

                    break;

                case 'float':

                    $compiled[$metricId] =
                        (float)(
                            $rawValue
                            ?? ($rule['fallback'] ?? 0)
                        );

                    break;

                case 'bool':

                    $mapping =
                        $rule['mapping'] ?? [];

                    $compiled[$metricId] =
                        (int)(
                            $mapping[$rawValue]
                            ?? ($rule['fallback'] ?? 0)
                        );

                    break;

                default:

                    $compiled[$metricId] =
                        $rawValue;
            }
        }

        return $compiled;
    }

    /**
     * Computes indicators from metrics.
     */
    private function compileIndicators(
        array $metrics
    ): array {

        $file =
            $this->frameworkPath . 'indicators.json';

        if (!file_exists($file)) {
            return [];
        }

        $config = json_decode(
            file_get_contents($file),
            true
        );

        if (
            !is_array($config) ||
            !isset($config['indicators'])
        ) {
            return [];
        }

        $rules = $config['indicators'];

        $compiled = [];

        foreach ($rules as $indicatorId => $rule) {

            $formula =
                $rule['formula'] ?? '0';

            try {

                $value =
                    $this->evaluator->evaluate(
                        $formula,
                        [
                            'metrics' => $metrics,
                            'indicators' => $compiled
                        ]
                    );

                $compiled[$indicatorId] =
                    (float)$value;

            } catch (\Throwable $e) {

                $compiled[$indicatorId] = 0.0;
            }
        }

        return $compiled;
    }
}
