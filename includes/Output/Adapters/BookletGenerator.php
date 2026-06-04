<?php
namespace WpEsg\Output\Adapters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BookletGenerator
 * Generates unified editorial assessment booklets combining quantitative index radar charts and qualitative data.
 *
 * @package WpEsg\Output\Adapters
 */
class BookletGenerator {

    /**
     * Compiles an mPDF document merging metrics analysis vectors with localized community texts.
     *
     * @param int $assessmentId Core primary row locator database reference key.
     * @return string            File system destination URL path pointing to the stored asset report.
     */
    public function generateEditorialReportBooklet(int $assessmentId): string {
        global $wpdb;

        $tableAssessments = $wpdb->prefix . 'esg_assessments';
        $tableResults     = $wpdb->prefix . 'esg_assessment_results';

        $rawRow = $wpdb->get_row($wpdb->prepare("SELECT raw_answers FROM $tableAssessments WHERE id = %d", $assessmentId), ARRAY_A);
        $resultRow = $wpdb->get_row($wpdb->prepare("SELECT assessment_payload FROM $tableResults WHERE assessment_id = %d", $assessmentId), ARRAY_A);

        if (!$rawRow || !$resultRow) {
            return '';
        }

        $answers = json_decode($rawRow['raw_answers'], true);
        $calculations = json_decode($resultRow['assessment_payload'], true);

        // Build HTML content structure for the editorial report booklet layout
        $htmlReportContent = '<h1>Solidary Economy Comprehensive Assessment Booklet Report</h1>';
        $htmlReportContent .= '<p>Enterprise Tax Identifier Context: ' . esc_html($calculations['meta']['company_tax_id'] ?? '') . '</p>';
        $htmlReportContent .= '<h3>Calculated Quantitative Performance Pillars Index</h3>';
        
        foreach (($calculations['indicators'] ?? []) as $key => $score) {
            $htmlReportContent .= sprintf('<p>Pillar token component [%s]: <strong>%.2f / 100</strong></p>', esc_html($key), (float)$score);
        }

        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML($htmlReportContent);

        $uploadDir = wp_upload_dir();
        $targetDirectory = $uploadDir['basedir'] . '/wp-esg-reports/';
        
        if (!file_exists($targetDirectory)) {
            wp_mkdir_p($targetDirectory);
        }

        $outputPath = $targetDirectory . esc_attr($calculations['meta']['company_tax_id'] ?? 'orphan') . '_booklet.pdf';
        $mpdf->Output($outputPath, \Mpdf\Output\Destination::FILE);

        return $uploadDir['baseurl'] . '/wp-esg-reports/' . esc_attr($calculations['meta']['company_tax_id'] ?? 'orphan') . '_booklet.pdf';
    }
}
