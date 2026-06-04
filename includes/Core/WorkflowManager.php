<?php
namespace WpEsg\Core;

if (!defined('ABSPATH')) {
    exit;
}

use WpEsg\Core\FormulaEngine;

/**
 * Class WorkflowManager
 * Enforces atomic state transitions (BPM) across the corporate assessment registry lifecycle,
 * embedding localized file snapshots as permanent historical seals.
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
     * Advances a session record from Draft status to Pending Review, capturing a cryptographic language snapshot.
     *
     * @param int $assessmentId Primary database locator row ID target.
     * @return bool             True on successful verification updates.
     */
    public function submitToReview(int $assessmentId): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'esg_assessments';
        $assessment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $assessmentId), ARRAY_A);

        if (!$assessment || $assessment['workflow_status'] !== 'Draft') {
            return false; // Modifications are blocked once a session steps out of the un-submitted Draft state
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
     * Approves a pending assessment, runs quantitative scoring pipelines, and archives frozen analytical results.
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

        // Run quantitative calculations translation engine (Questions -> Metrics -> Indicators)
        $outputPayload = $this->formulaEngine->computeAssessment($assessment);

        $manifestFile = WP_ESG_PATH . 'frameworks/openesea/manifest.json';
        $manifestData = file_exists($manifestFile) ? json_decode(file_get_contents($manifestFile), true) : [];
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

    /**
     * Captures runtime file hashes of standard translations to embed into raw records for audit logging.
     */
    private function generateLanguageSnapshot(string $locale): array {
        $files = [
            'framework'  => "frameworks/openesea-{$locale}.json",
            'pgs'        => "frameworks/pgs-{$locale}.json",
            'categories' => "categories/textile_clothing-{$locale}.json" // Anchor baseline taxonomy target
        ];

        $hashes = [];
        foreach ($files as $key => $subPath) {
            $fullPath = $this->languagesPath . $subPath;
            $hashes[$key] = file_exists($fullPath) ? hash_file('sha256', $fullPath) : 'file_absent';
        }
        return $hashes;
    }

    /**
     * Serializes cryptographic language metadata tags straight into raw questionnaire string structures.
     */
    private function injectLanguageMeta(string $rawAnswersJson, array $hashes): string {
        $answers = json_decode($rawAnswersJson, true) ?: [];
        $answers['_meta_language_snapshot'] = $hashes;
        return json_encode($answers);
    }
}
