<?php
namespace WpEsg\Core;

if (!defined('ABSPATH')) {
    exit;
}

use WpEsg\Frameworks\FrameworkRegistry;

/**
 * Class RuleEvaluator
 * Evaluates operational metrics thresholds to assert visibility maps across client form instances.
 *
 * @package WpEsg\Core
 */
class RuleEvaluator {

    /**
     * Determines which questionnaire sections are unlocked based on capacity indicators.
     *
     * @param string $macroSector   The normalized evaluation sector code.
     * @param int    $employeeCount Corporate head count parsed from initial threshold.
     * @return array                Conditional display matrix configurations.
     */
    public function evaluateVisibilityMap(string $macroSector, int $employeeCount): array {
        // Evaluate employee volume rules: companies with 10 or fewer workers trigger the trimmed Short profile pipeline
        $sizeScope = ($employeeCount <= 10) ? 'Short' : 'Long';
        
        $resolvedCategory = FrameworkRegistry::mapSectorToCategory($macroSector);

        return [
            'company_size_scope' => $sizeScope,
            'product_category'   => $resolvedCategory,
            'active_pgs_pact'    => ($resolvedCategory !== 'none') // Unlocked for solidary network market participants
        ];
    }
}
