<?php
namespace WpEsg\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AdminWorkflowView
 * Controls the administrative supervision table panel monitoring corporate assessment stages.
 *
 * @package WpEsg\Admin
 */
class AdminWorkflowView {

    /**
     * Il costruttore ora è pulito. 
     * La registrazione dei menu è centralizzata in WizardController per evitare conflitti e menu sdoppiati.
     */
    public function __construct() {
        // Logica dei menu migrata nel Controller unificato
    }

    /**
     * Questo metodo ora implementa il nome standard richiesto dal WizardController
     * ed esegue il rendering della dashboard in modo pulito e sicuro.
     */
    public function render(): void {
        global $wpdb;
        
        if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
            return;
        }

        $table = $wpdb->prefix . 'esg_assessments';
        $records = $wpdb->get_results("SELECT id, company_tax_id, business_code, company_size, balance_year, workflow_status, raw_answers FROM $table ORDER BY id DESC", ARRAY_A);
        ?>
        <div class="wp-esg-admin-container" style="margin-top: 20px;">
            <table class="wp-list-table widefat fixed striped table-view-list entries" style="border:1px solid #ccd0d4; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <thead>
                    <tr>
                        <th style="padding:12px; font-weight:bold;"><?php echo esc_html(__('Partita IVA / Cod. Fiscale', 'wp-esg')); ?></th>
                        <th style="padding:12px; font-weight:bold;"><?php echo esc_html(__('Codice ATECO', 'wp-esg')); ?></th>
                        <th style="padding:12px; font-weight:bold;"><?php echo esc_html(__('Dimensione Azienda', 'wp-esg')); ?></th>
                        <th style="padding:12px; font-weight:bold;"><?php echo esc_html(__('Anno di Bilancio', 'wp-esg')); ?></th>
                        <th style="padding:12px; font-weight:bold;"><?php echo esc_html(__('Stato Avanzamento', 'wp-esg')); ?></th>
                        <th style="padding:12px; font-weight:bold; text-align:right;"><?php echo esc_html(__('Azioni di Sistema', 'wp-esg')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)) : ?>
                        <tr><td colspan="6" style="padding:15px; text-align:center; color:#646970; font-style:italic;"><?php echo esc_html(__('Nessun flusso di assessment aziendale registrato in questo blocco di tracciamento.', 'wp-esg')); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($records as $row) : 
                            $status = esc_attr($row['workflow_status']);
                            $badgeClass = 'wp-esg-status-' . strtolower(str_replace(' ', '-', $status));
                            
                            // 🧠 DECODIFICA DEI NUOVI CASSETTI CON FALLBACK FLUIDO
                            $payload = json_decode($row['raw_answers'], true) ?: [];
                            $metadata = $payload['company_metadata'] ?? [];

                            // Risoluzione delle variabili con fallback prioritario sulle risposte reali
                            $taxId = !empty($row['company_tax_id']) ? $row['company_tax_id'] : ($metadata['company_tax_id'] ?? 'N/A');
                            $ateco = !empty($row['business_code']) ? $row['business_code'] : ($metadata['business_code'] ?? 'N/A');
                            $year  = !empty($row['balance_year']) ? $row['balance_year'] : ($metadata['balance_year'] ?? '2026');
                            $size  = !empty($row['company_size']) ? $row['company_size'] : ($metadata['company_size_scope'] ?? 'Standard');

                            // Traduzione del profilo dimensionale
                            switch (strtoupper(trim($size))) {
                                case 'SMALL':
                                case 'SHORT':
                                case 'MICRO':
                                    $displaySize = __('Piccola / Micro Impresa', 'wp-esg');
                                    break;
                                case 'MEDIUM':
                                    $displaySize = __('Media Impresa', 'wp-esg');
                                    break;
                                case 'LARGE':
                                case 'LONG':
                                    $displaySize = __('Grande Impresa', 'wp-esg');
                                    break;
                                default:
                                    $displaySize = !empty($size) ? esc_html($size) : __('Non Specificata', 'wp-esg');
                                    break;
                            }

                            // Stile grafico dinamico dei badge per allinearsi all'Hub di WordPress
                            $badgeStyle = 'padding:4px 8px; font-size:12px; font-weight:bold; border-radius:3px; display:inline-block;';
                            if ($status === 'Draft' || $status === 'Submitted') {
                                $displayStatus = __('📝 In Corso (Bozza)', 'wp-esg');
                                $badgeColor = 'background:#fef3cd; color:#664d03;';
                            } elseif ($status === 'Pending Review') {
                                $displayStatus = __('🔒 In Revisione', 'wp-esg');
                                $badgeColor = 'background:#cff4fc; color:#055160;';
                            } elseif ($status === 'Completed') {
                                $displayStatus = __('✓ Certificato', 'wp-esg');
                                $badgeColor = 'background:#d1e7dd; color:#0f5132;';
                            } else {
                                $displayStatus = esc_html($status);
                                $badgeColor = 'background:#f8f9fa; color:#212529;';
                            }
                            ?>
                            <tr>
                                <td style="padding:12px;"><strong><?php echo esc_html($taxId); ?></strong></td>
                                <td style="padding:12px;"><code><?php echo esc_html($ateco); ?></code></td>
                                <td style="padding:12px; color:#50575e;"><?php echo esc_html($displaySize); ?></td>
                                <td style="padding:12px; font-weight:600; color:#1d2327;"><?php echo esc_html($year); ?></td>
                                <td style="padding:12px;"><span style="<?php echo $badgeStyle . ' ' . $badgeColor; ?>"><?php echo esc_html($displayStatus); ?></span></td>
                                <td style="padding:12px; text-align:right;">
                                    <?php if ($status === 'Pending Review') : ?>
                                        <button type="button" class="button button-primary button-small js-wp-esg-transition-trigger" data-assessment-id="<?php echo (int)$row['id']; ?>" data-action-target="complete" style="background:#2271b1; border-color:#2271b1; font-weight:bold;">
                                            <?php echo esc_html(__('Revisiona & Certifica', 'wp-esg')); ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="description" style="font-size:12px; color:#a7aaad; font-style:italic;"><?php echo esc_html(__('Nessuna azione', 'wp-esg')); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
        <?php
    }
}
