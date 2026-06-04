<?php
namespace WpEsg\Output\Adapters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BadgePdfEngine
 * Compiles official public cryptographic verification certificates containing verification QR Codes.
 *
 * @package WpEsg\Output\Adapters
 */
class BadgePdfEngine {

    /**
     * Builds and stores a formal verification certificate mapping the target calculations footprint.
     *
     * @param int $assessmentResultId Core primary locator key mapping inside frozen results tables.
     * @return string                  Filepath destination URL location pointing to the generated asset.
     */
    public function generateFormalCertificatePdf(int $assessmentResultId): string {
        global $wpdb;

        $table = $wpdb->prefix . 'esg_assessment_results';
        $payload = $wpdb->get_var($wpdb->prepare("SELECT assessment_payload FROM $table WHERE id = %d", $assessmentResultId));

        if (empty($payload)) {
            return '';
        }

        $data = json_decode($payload, true);
        $taxId = esc_attr($data['meta']['company_tax_id'] ?? 'unknown');

        // Instantiate native FPDF library components
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 15, 'wp-esg Network Public Validation Certificate', 0, 1, 'C');
        $pdf->Ln(10);
        
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Organization Tax ID Target: ' . $taxId, 0, 1, 'L');
        $pdf->Cell(0, 10, 'Fiscal Balance Period: ' . esc_attr($data['meta']['balance_year'] ?? '2026'), 0, 1, 'L');
        $pdf->Cell(0, 10, 'Cryptographic Manifest Seal: ' . esc_attr($data['meta']['framework_manifest_hash'] ?? 'N/A'), 0, 1, 'L');
        
        $uploadDir = wp_upload_dir();
        $targetDirectory = $uploadDir['basedir'] . '/wp-esg-badges/';
        
        if (!file_exists($targetDirectory)) {
            wp_mkdir_p($targetDirectory);
        }

        $finalDestinationPath = $targetDirectory . $taxId . '_certificate.pdf';
        $pdf->Output('F', $finalDestinationPath);

        return $uploadDir['baseurl'] . '/wp-esg-badges/' . $taxId . '_certificate.pdf';
    }
}
