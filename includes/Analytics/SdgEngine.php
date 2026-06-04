<?php
namespace WpEsg\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Class SdgEngine
 * Leverages the Symfony Lexer component to analyze frozen indicator arrays
 * and automatically allocate United Nations Sustainable Development Goals badges.
 *
 * @package WpEsg\Analytics
 */
class SdgEngine {

    private ExpressionLanguage $lexer;
    private string $mappingPath;

    public function __construct() {
        $this->lexer       = new ExpressionLanguage();
        $this->mappingPath = WP_ESG_PATH . 'analytics/sdg/mapping.json';
    }

    /**
     * Cross-references computed evaluation matrices against conditional mapping expressions.
     *
     * @param array $indicatorsMap Finalized quantitative results index payload extracted from storage.
     * @return array               Array of unlocked UN goals containing target scores and verification logs.
     */
    public function computeUnlockedGoals(array $indicatorsMap): array {
        if (!file_exists($this->mappingPath)) {
            return [];
        }

        $mappingData = json_decode(file_get_contents($this->mappingPath), true);
        $rules       = $mappingData['rules'] ?? [];

        $unlockedGoals = [];

        foreach ($rules as $goalId => $context) {
            $expression = $context['condition'] ?? 'false';

            try {
                // Lexical condition processing mapping the indicators array as tokens variable block context
                $isEligible = $this->lexer->evaluate($expression, ['indicators' => $indicatorsMap]);

                if ((bool)$isEligible) {
                    $unlockedGoals[$goalId] = [
                        'goal_code'  => sanitize_text_field($goalId),
                        'score_link' => (float)($context['target_weight'] ?? 100.0),
                        'audited_at' => gmdate('Y-m-d H:i:s')
                    ];
                }
            } catch (\Exception $e) {
                // Fail-safe protection layer to catch syntax execution errors in the underlying mapping JSON files
                continue;
            }
        }

        return $unlockedGoals;
    }
}
