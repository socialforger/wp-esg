<?php
namespace WpEsg\Cli;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ExportCliCommand
 * Extracts normalized quantitative indices from localized tables to export to central solidarity network systems.
 */
class ExportCliCommand {

    /**
     * Executes bulk archival migrations exporting raw system indicator footprints to standard file systems formats.
     * * ## OPTIONS
     * * <target_output_file>
     * : Structural target destination filepath mapping.
     * * ## EXAMPLES
     * * wp esg export network-dump /var/www/exports/openesea_2026.json
     *
     * @param array $args      Positional command line arguments.
     * @param array $assocArgs Flag/associative option arguments mapping boundaries.
     */
    public function networkDump(array $args, array $assocArgs): void {
        if (!class_exists('WP_CLI')) {
            return;
        }

        if (empty($args[0])) {
            \WP_CLI::error('Absolute system write filepath path designation is missing.');
        }

        $outputPath = sanitize_text_field($args[0]);

        \WP_CLI::log(sprintf('Executing mass tracking file dump sequences into storage context: %s', $outputPath));

        global $wpdb;
        $table = $wpdb->prefix . 'esg_assessment_results';
        
        $dataset = $wpdb->get_results("SELECT id, company_tax_id, balance_year, assessment_payload FROM $table", ARRAY_A);

        if (empty($dataset)) {
            \WP_CLI::warning('Data extraction layer returned zero active elements.');
            return;
        }

        $payloadCollection = [];
        foreach ($dataset as $row) {
            $payloadCollection[] = [
                'entity'       => $row['company_tax_id'],
                'period'       => (int)$row['balance_year'],
                'calculations' => json_decode($row['assessment_payload'], true)
            ];
        }

        $jsonOutput = json_encode($payloadCollection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Assert directory accessibility boundaries before trying to pipe system output streams
        $directoryPath = dirname($outputPath);
        if (!file_exists($directoryPath)) {
            wp_mkdir_p($directoryPath);
        }

        if (false === file_put_contents($outputPath, $jsonOutput)) {
            \WP_CLI::error('Storage output tracking channel failure. Write procedures rejected by filesystem.');
        }

        \WP_CLI::success('Extraction operation completely finalized. Compliance data ready for European cross-node analytics.');
    }
}

// Intercept execution path to mount terminal sub-commands trees to WP-CLI registry maps
if (defined('WP_CLI') && \WP_CLI) {
    \WP_CLI::add_command('esg export network-dump', [new ExportCliCommand(), 'networkDump']);
}
