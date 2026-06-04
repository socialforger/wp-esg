<?php
namespace WpEsg\Cli;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BadgeCliCommand
 * Orchestrates batch validation operations and automates mass certificate PDF generation via WP-CLI.
 */
class BadgeCliCommand {

    /**
     * Regulates mass batch processing parameters for analytical PDF generation runs.
     * * ## OPTIONS
     * * <year>
     * : Target fiscal accounting period to query.
     * * [--force]
     * : Overwrite existing digital certificates files inside storage layers.
     * * ## EXAMPLES
     * * wp esg badge batch-compile 2026 --force
     *
     * @param array $args      Positional command line arguments.
     * @param array $assocArgs Flag/associative option arguments mapping boundaries.
     */
    public function batchCompile(array $args, array $assocArgs): void {
        if (!class_exists('WP_CLI')) {
            return;
        }

        if (empty($args[0])) {
            \WP_CLI::error('Missing target year parameter index focus.');
        }

        $targetYear = (int)$args[0];
        $overwrite  = isset($assocArgs['force']);

        \WP_CLI::log(sprintf('Beginning systematic batch file compilation sequence for block parameters: %d.', $targetYear));

        global $wpdb;
        $table = $wpdb->prefix . 'esg_assessment_results';
        
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT id, company_tax_id FROM $table WHERE balance_year = %d",
            $targetYear
        ), ARRAY_A);

        if (empty($entries)) {
            \WP_CLI::error('Target sequence empty. Zero completed assessments encountered inside tracking limits.');
        }

        $successCount = 0;
        $pdfEngine = new \WpEsg\Output\Adapters\BadgePdfEngine();

        foreach ($entries as $row) {
            \WP_CLI::line(sprintf('-> Processing validation objects layout for entity: %s', esc_html($row['company_tax_id'])));
            
            try {
                $fileUrl = $pdfEngine->generateFormalCertificatePdf((int)$row['id']);
                if (!empty($fileUrl)) {
                    $successCount++;
                } else {
                    \WP_CLI::warning(sprintf('Skipped compilation processing run for entity identifier: %s', esc_html($row['company_tax_id'])));
                }
            } catch (\Exception $e) {
                \WP_CLI::warning(sprintf('Exception caught during batch processing stream: %s', esc_html($e->getMessage())));
            }
        }

        \WP_CLI::success(sprintf('Batch system run finalized. Processed %d legal verification objects successfully.', $successCount));
    }
}

// Intercept execution path to mount terminal sub-commands trees to WP-CLI registry maps
if (defined('WP_CLI') && \WP_CLI) {
    \WP_CLI::add_command('esg badge batch-compile', [new BadgeCliCommand(), 'batchCompile']);
}
