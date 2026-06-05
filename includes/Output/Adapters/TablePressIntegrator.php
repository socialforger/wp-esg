<?php
namespace WpEsg\Output\Adapters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TablePressIntegrator
 * Bridges dynamic unbundled assessment datasets into tabular matrix formats suitable for TablePress rendering.
 *
 * @package WpEsg\Output\Adapters
 */
class TablePressIntegrator {

    /**
     * Compiles raw assessment records into a flat tabular multidimensional array for TablePress tables.
     *
     * @return array multidimensional array where each row represents a company dataset.
     */
    public function compileDatasetForTablePress(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'esg_assessments';
        
        // Estraiamo i record ordinati per anno di bilancio più recente
        $records = $wpdb->get_results("SELECT id, company_tax_id, business_code, balance_year, company_size, workflow_status, raw_answers FROM $table ORDER BY balance_year DESC");

        if (empty($records)) {
            return [];
        }

        $tabularData = [];

        // Definizione dell'intestazione della tabella (Header Row)
        $tabularData[] = [
            __('Company Tax ID', 'wp-esg'),
            __('ATECO Code', 'wp-esg'),
            __('Reporting Year', 'wp-esg'),
            __('Company Size', 'wp-esg'),
            __('OpenESEA Status', 'wp-esg'),
            __('PGS Local Guidelines', 'wp-esg'),
            __('Eco-Compatible Products', 'wp-esg')
        ];

        foreach ($records as $row) {
            // Decodifichiamo il nuovo payload strutturato a cassetti provenienti dallo Shortcode
            $payload = json_decode($row->raw_answers, true) ?: [];

            // Isola i cassetti specifici del nuovo schema sequenziale
            $metadata = $payload['company_metadata'] ?? [];
            $openesea = $payload['openesea_framework'] ?? [];
            $pgs      = $payload['pgs_framework'] ?? [];
            $products = $payload['products_framework'] ?? [];

            // Risoluzione delle variabili anagrafiche con fallback
            $taxId = !empty($row->company_tax_id) ? $row->company_tax_id : ($metadata['company_tax_id'] ?? 'N/A');
            $ateco = !empty($row->business_code) ? $row->business_code : ($metadata['business_code'] ?? 'N/A');
            $year  = !empty($row->balance_year) ? $row->balance_year : ($metadata['balance_year'] ?? '2026');
            $size  = !empty($row->company_size) ? $row->company_size : ($metadata['company_size_scope'] ?? 'Standard');

            // Traduzione dello stato del Workflow per la conformità OpenESEA
            $statusLabel = '';
            if ('Completed' === $row->workflow_status) {
                $statusLabel = __('✓ Approved & Certified', 'wp-esg');
            } elseif ('Pending Review' === $row->workflow_status) {
                $statusLabel = __('🔒 Under Review', 'wp-esg');
            } else {
                $statusLabel = __('📝 In Progress (Draft)', 'wp-esg');
            }

            // Estrazione delle risposte specifiche dai cassetti PGS e Prodotti
            $pgsResponse      = isset($pgs['pgs_q1']) ? strtoupper($pgs['pgs_q1']) : __('N/A', 'wp-esg');
            $productsResponse = isset($products['products_q1']) ? strtoupper($products['products_q1']) : __('N/A', 'wp-esg');

            // Mappiamo la riga piatta destinata alla griglia TablePress
            $tabularData[] = [
                esc_html($taxId),
                esc_html($ateco),
                esc_html($year),
                esc_html($size),
                esc_html($statusLabel),
                esc_html($pgsResponse),
                esc_html($productsResponse)
            ];
        }

        return $tabularData;
    }
}
