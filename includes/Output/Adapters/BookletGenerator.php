<?php
namespace WpEsg\Output\Adapters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BookletGenerator
 * Generates the unified multi-framework Corporate Sustainability Report booklet.
 *
 * @package WpEsg\Output\Adapters
 */
class BookletGenerator {

    /**
     * Compiles the comprehensive ESG Booklet PDF for a given assessment row.
     *
     * @param int $assessmentId The core row identifier inside prefix_esg_assessments table.
     * @return string           Destination URL path pointing to the generated booklet asset.
     */
    public function generateSustainabilityBooklet(int $assessmentId): string {
        global $wpdb;

        $table = $wpdb->prefix . 'esg_assessments';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $assessmentId));

        if (!$row || empty($row->raw_answers)) {
            return '';
        }

        // Decodifichiamo il payload strutturato a cassetti provenienti dallo Shortcode a step
        $payload = json_decode($row->raw_answers, true);

        // 🧠 SPLIT ARCHITETTURALE DEI CASSETTI ESG
        $metadata = $payload['company_metadata'] ?? [];
        $openesea = $payload['openesea_framework'] ?? [];
        $pgs      = $payload['pgs_framework'] ?? [];
        $products = $payload['products_framework'] ?? [];

        // Risoluzione dei dati anagrafici con fallback per retrocompatibilità sandbox
        $taxId    = esc_attr($row->company_tax_id ?: ($metadata['company_tax_id'] ?? 'N/A'));
        $year     = esc_attr($row->balance_year ?: ($metadata['balance_year'] ?? '2026'));
        $ateco    = esc_attr($row->business_code ?: ($metadata['business_code'] ?? 'N/A'));
        $scope    = esc_attr($row->company_size ?: ($metadata['company_size_scope'] ?? 'Standard'));

        // Inizializzazione libreria nativa FPDF
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        
        // --- COPERTINA / INTESTAZIONE ---
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetTextColor(34, 113, 177); // Brand color #2271b1
        $pdf->Cell(0, 15, 'CORPORATE SUSTAINABILITY DISCLOSURE REPORT', 0, 1, 'C');
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->SetTextColor(100, 105, 112);
        $pdf->Cell(0, 10, 'Unified Framework Assessment Booklet', 0, 1, 'C');
        $pdf->Ln(10);

        // --- CAPITOLO 1: ANAGRAFICA AZIENDALE ---
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(29, 35, 39);
        $pdf->Cell(0, 10, '1. Company Profile & Operational Metadata', 0, 1, 'L');
        $pdf->SetDrawColor(34, 113, 177);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 50, $pdf->GetY());
        $pdf->Ln(4);
        
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(60, 8, 'Tax Identifier / VAT ID:', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, $taxId, 0, 1, 'L');
        
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(60, 8, 'Economic Activity (ATECO):', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, $ateco, 0, 1, 'L');
        
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(60, 8, 'Accounting Reporting Year:', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, $year, 0, 1, 'L');
        
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(60, 8, 'Calculated Boundary Size:', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, $scope, 0, 1, 'L');
        $pdf->Ln(10);

        // --- CAPITOLO 2: COMPLIANCE CORE OPENESEA ---
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, '2. OpenESEA Regulatory Core Framework', 0, 1, 'L');
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 50, $pdf->GetY());
        $pdf->Ln(4);
        
        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(0, 6, 'Q: Does your enterprise actively monitor circular economy protocols or resource recycling targets contextualized to the industrial vertical sector?', 0, 'L');
        $pdf->SetFont('Arial', 'B', 11);
        $ans1 = strtoupper($openesea['openesea_q1'] ?? 'Not Answered');
        $pdf->Cell(0, 8, 'Official Declaration Response: ' . $ans1, 0, 1, 'L');
        $pdf->Ln(8);

        // --- CAPITOLO 3: SOCIAL & GOVERNANCE PGS ---
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, '3. Network PGS Evaluation Indicators', 0, 1, 'L');
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 50, $pdf->GetY());
        $pdf->Ln(4);
        
        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(0, 6, 'Q: Does your enterprise run local community support guidelines or corporate code-of-conduct transparency policies?', 0, 'L');
        $pdf->SetFont('Arial', 'B', 11);
        $ans2 = strtoupper($pgs['pgs_q1'] ?? 'Not Answered');
        $pdf->Cell(0, 8, 'Network Positioning Response: ' . $ans2, 0, 1, 'L');
        $pdf->Ln(8);

        // --- CAPITOLO 4: CERTIFICAZIONE PRODOTTI ---
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, '4. Vertical Product Supply Chain Metrics', 0, 1, 'L');
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 50, $pdf->GetY());
        $pdf->Ln(4);
        
        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(0, 6, 'Q: Are your primary commercial goods produced utilizing certified eco-compatible materials?', 0, 'L');
        $pdf->SetFont('Arial', 'B', 11);
        $ans3 = strtoupper($products['products_q1'] ?? 'Not Answered');
        $pdf->Cell(0, 8, 'Supply Chain Self-Certification: ' . $ans3, 0, 1, 'L');

        // Salvataggio fisico del documento PDF nel server
        $uploadDir = wp_upload_dir();
        $targetDirectory = $uploadDir['basedir'] . '/wp-esg-booklets/';
        
        if (!file_exists($targetDirectory)) {
            wp_mkdir_p($targetDirectory);
        }

        $finalDestinationPath = $targetDirectory . $taxId . '_' . $year . '_booklet.pdf';
        $pdf->Output('F', $finalDestinationPath);

        return $uploadDir['baseurl'] . '/wp-esg-booklets/' . $taxId . '_' . $year . '_booklet.pdf';
    }
}
