<?php
namespace WpEsg\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AccessPolicy
 * Regulates explicit access and writing boundaries across organizational assessments 
 * depending on current state-machine context and active authentication paths.
 *
 * @package WpEsg\Security
 */
class AccessPolicy {

    /**
     * Asserts whether mutations can be committed to the target assessment session.
     *
     * @param array $assessmentRow Unified active row state map fetched from tracking layers.
     * @return bool                 True if editing is authorized, false if locked down.
     */
    public function canProducerModify(array $assessmentRow): bool {
        // Enforce strict state rule: form parameters are locked down completely 
        // the moment a draft progresses out of the initial 'Draft' phase.
        $currentPhase = $assessmentRow['workflow_status'] ?? 'Draft';
        return $currentPhase === 'Draft';
    }

    /**
     * Enforces rigid key parsing constraint policies to neutralize parameter cross-injection vectors.
     *
     * @param array  $submittedAnswers Collection of parameters fetched from submission streams.
     * @param string $allowedCategory  The contextually authorized sector identifier assigned to the active company code.
     * @return bool                    True if the payload is secure, false if corrupted.
     */
    public function enforcePayloadIntegrity(array $submittedAnswers, string $allowedCategory): bool {
        foreach ($submittedAnswers as $key => $value) {
            // Block input parameters belonging to clothing workflows if the enterprise is registered under chemical sectors
            if (str_starts_with($key, 'tex_') && $allowedCategory !== 'textile_clothing') {
                return false;
            }
            if (str_starts_with($key, 'det_') && $allowedCategory !== 'chemical_detergents') {
                return false;
            }
            if (str_starts_with($key, 'hrb_') && $allowedCategory !== 'herbalist') {
                return false;
            }
            if (str_starts_with($key, 'art_') && $allowedCategory !== 'artisan') {
                return false;
            }
        }
        return true;
    }
}
