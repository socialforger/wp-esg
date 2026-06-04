<?php
namespace WpEsg\Core;

if (!defined('ABSPATH')) {
    exit;
}

use WpEsg\Frameworks\FrameworkRegistry;

/**
 * Class RuleEvaluator
 * Handles UX conditioning metadata rules, employee threshold capacities, 
 * and front-end form sheet rendering boundaries.
 *
 * @package WpEsg\Core
 */
class RuleEvaluator {

    /**
     * Dynamically resolves front-end conditional configurations based on user registration choices.
     *
     * @param string $macroSector   The normalized evaluation sector code derived from national registries.
     * @param int    $employeeCount Headcount parameter checked against structural breakdown limits.
     * @return array                Interface layout active configuration map flags.
     */
    public function evaluateVisibilityMap(string $macroSector, int $employeeCount): array {
        // Enforce employee headcount rule: <= 10 workers automatically compresses the layout into a Short path
        $sizeScope = ($employeeCount <= 10) ? 'Short' : 'Long';
        
        $resolvedCategory = FrameworkRegistry::mapSectorToCategory($macroSector);

        return [
            'company_size_scope' => $sizeScope,
            'product_category'   => $resolvedCategory,
            'active_pgs_pact'    => ($resolvedCategory !== 'none') // Activated for participating solidarity market entities
        ];
    }

    /**
     * Validates if an abstract question configuration block is eligible for processing under current size limits.
     */
    public function isQuestionNodeActive(array $questionData, string $currentSizeScope): bool {
        $requiredScope = $questionData['scope'] ?? 'Universal';
        
        if ($requiredScope === 'Long' && $currentSizeScope === 'Short') {
            return false;
        }
        
        return true;
    }
}
