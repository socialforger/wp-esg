<?php

namespace WpEsg\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

use WpEsg\Core\FormulaEvaluator;

/**
 * Class SdgEngine
 *
 * Computes SDG badge eligibility
 * from calculated indicators.
 */
class SdgEngine
{
    private FormulaEvaluator $lexer;

    private string $mappingPath;

    public function __construct()
    {
        $this->lexer = new FormulaEvaluator();

        $this->mappingPath =
            WP_ESG_PATH . 'frameworks/sdg/sdg_mapping.json';
    }

    /**
     * Computes unlocked SDGs.
     */
    public function computeUnlockedGoals(
        array $indicatorsMap
    ): array {

        if (!file_exists($this->mappingPath)) {
            return [];
        }

        $mappingData = json_decode(
            file_get_contents($this->mappingPath),
            true
        );

        $rules =
            $mappingData['rules'] ?? [];

        $unlockedGoals = [];

        foreach ($rules as $goalId => $context) {

            $expression =
                $context['condition'] ?? '0';

            try {

                $isEligible =
                    $this->lexer->evaluate(
                        $expression,
                        [
                            'indicators' => $indicatorsMap
                        ]
                    );

                if ((bool)$isEligible) {

                    $unlockedGoals[$goalId] = [

                        'goal_code' =>
                            sanitize_text_field(
                                $goalId
                            ),

                        'score_link' =>
                            (float)(
                                $context['target_weight']
                                ?? 100
                            ),

                        'audited_at' =>
                            gmdate(
                                'Y-m-d H:i:s'
                            )
                    ];
                }

            } catch (\Throwable $e) {

                continue;
            }
        }

        return $unlockedGoals;
    }
}
