<?php
namespace WpEsg\Core;

if (!defined('ABSPATH')) {
    exit;
}

use WpEsg\Core\FormulaEngine;

/**
 * Class WorkflowManager
 * Enforces transactional progression limits across the assessment lifecycle matrix.
 *
 * @package WpEsg\Core
 */
class WorkflowManager {

    private FormulaEngine $formulaEngine;
    private string $languagesPath;

    public function __construct() {
        $this->formulaEngine = new FormulaEngine();
        $this->languagesPath = WP_ESG_PATH . 'languages/';
    }

    /**
     * Moves an active record context from Draft status to Pending Review, capturing a language configuration snapshot.
     *
     * @param int $assessmentId Core primary key locator inside index tracking matrix.
     * @return bool             True on successful verification updates.
     */
    public function submitToReview(int $assessmentId): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'esg_assessments';
        
        $assessment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $assessmentId), ARRAY_A);

        if (!$assessment || $assessment['workflow_status'] !== 'Draft') {
            return false;
        }

        $locale = determine_locale();
        $languageSnapshotHashes = $this->generateLanguageSnapshot($locale);

        $updated = $wpdb->update(
            $table,
            [
                'workflow_status' => 'Pending Review',
                'raw_answers'     => $this->injectLanguageMeta($assessment['raw_answers'], $languageSnapshotHashes)
            ],
            ['id' => $assessmentId],
            ['%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * Certifies a pending audit, fires mathematical score translations, and builds immutable calculation logs.
     */
    public function completeAssessment(int $assessmentId, string $frameworkId, string $version): bool {
        global $wpdb;

        $tableAssessments = $wpdb->prefix . 'esg_assessments';
        $tableResults     = $wpdb->prefix . 'esg_assessment_results';

        $assessment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $tableAssessments WHERE id = %d AND workflow_status = 'Pending Review'", $assessmentId),
            ARRAY_A
        );

        if (!$assessment) {
            return false;
        }

        $outputPayload = $this->formulaEngine->computeAssessment($assessment);

        $manifestPath = WP_ESG_PATH . 'frameworks/openesea/manifest.json';
        $manifestData = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];
        $manifestHash = hash('sha256', json_encode($manifestData));

        $inserted = $wpdb->insert(
            $tableResults,
            [
                'assessment_id'           => $assessmentId,
                'company_tax_id'          => $assessment['company_tax_id'],
                'balance_year'            => $assessment['balance_year'],
                'country_code'            => $assessment['country_code'],
                'framework_id'            => sanitize_text_field($frameworkId),
                'framework_version'       => sanitize_text_field($version),
                'framework_manifest_hash' => $manifestHash,
                'assessment_payload'      => json_encode($outputPayload),
                'completed_at'            => gmdate('Y-m-d H:i:s')
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted) {
            $wpdb->update($tableAssessments, ['workflow_status' => 'Completed'], ['id' => $assessmentId]);
            return true;
        }

        return false;
    }

    private function generateLanguageSnapshot(string $locale): array {
        $files = [
            'framework'  => "frameworks/openesea-{$locale}.json",
            'pgs'        => "frameworks/pgs-{$locale}.json",
            'categories' => "categories/textile_clothing-{$locale}.json" // Fallback reference target
        ];

        $hashes = [];
        foreach ($files as $key => $subPath) {
            $fullPath = $this->languagesPath . $subPath;
            $hashes[$key] = file_exists($fullPath) ? hash_file('sha256', $fullPath) : 'file_absent';
        }
        return $hashes;
    }

    private function injectLanguageMeta(string $rawAnswersJson, array $hashes): string {
        $answers = json_decode($rawAnswersJson, true) ?: [];
        $answers['_meta_language_snapshot'] = $hashes;
        return json_encode($answers);
    }
}
