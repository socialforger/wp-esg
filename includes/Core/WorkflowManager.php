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
        if (class_exists('WpEsg\Core\FormulaEngine')) {
            $this->formulaEngine = new FormulaEngine();
        }
        $this->languagesPath = WP_ESG_PATH . 'languages/';
    }

    /**
     * Advances a session record from Draft status to Pending Review, capturing a cryptographic language snapshot.
     *
     * @param int $assessmentId Primary database locator row ID target.
     * @return bool              True on successful verification updates.
     */
    public function submitToReview(int $assessmentId): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'esg_assessments';
        $assessment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $assessmentId), ARRAY_A);

        // Cambiato il controllo: permettiamo il passaggio a Review se è in Draft o parzialmente modificato dall'Hub
        if (!$assessment || ($assessment['workflow_status'] !== 'Draft' && $assessment['workflow_status'] !== 'Submitted')) {
            return false; 
        }

        $locale = determine_locale();
        $languageSnapshotHashes = $this->generateLanguageSnapshot($locale);

        $updated = $wpdb->update(
            $table,
            [
                'workflow_status' => 'Pending Review', // Stato ufficiale di sblocco per il revisore
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
        // Il FormulaEngine elaborerà il payload strutturato a cassetti
        $outputPayload = [];
        if (isset($this->formulaEngine)) {
            $outputPayload = $this->formulaEngine->computeAssessment($assessment);
        } else {
            // Fallback strutturato di staging in assenza del motore matematico completo
            $outputPayload = json_decode($assessment['raw_answers'], true);
        }

        // Iniettiamo un hash di verifica sul file manifest del framework
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
                'assessment_payload'      => json_encode($outputPayload), // Questo finirà dritto dentro BadgePdfEngine!
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
            'categories' => "categories/textile_clothing-{$locale}.json" 
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
        
        // Salviamo lo snapshot crittografico dentro il suo cassetto di metadati dedicato
        if (isset($answers['company_metadata'])) {
            $answers['company_metadata']['_language_snapshot'] = $hashes;
        } else {
            $answers['_meta_language_snapshot'] = $hashes;
        }
        
        return json_encode($answers);
    }
}
