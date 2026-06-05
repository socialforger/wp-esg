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

    public function __construct() {
        add_action('admin_menu', [$this, 'registerWorkflowMenuPage']);
    }

    public function registerWorkflowMenuPage(): void {
        add_submenu_page(
            'wp-esg-settings',
            __('Monitoraggio Avanzamento ESG', 'wp-esg'),
            __('Monitor Workflow', 'wp-esg'),
            'edit_posts',
            'wp-esg-workflow',
            [$this, 'renderWorkflowTableDashboard']
        );
    }

    public function renderWorkflowTableDashboard(): void {
        global $wpdb;
        
        if (!current_user_can('edit_posts')) {
            return;
        }

        $table = $wpdb->prefix . 'esg_assessments';
        $records = $wpdb->get_results("SELECT id, company_tax_id, business_code, company_size, balance_year, workflow_status FROM $table ORDER BY id DESC", ARRAY_A);
        ?>
        <div class="wrap wp-esg-admin-container">
            <h1 class="wp-heading-inline"><?php echo esc_html(__('Monitoraggio e Tracciamento dei Bilanci ESG Aziendali', 'wp-esg')); ?></h1>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped table-view-list entries" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th><?php echo esc_html(__('Partita IVA / Cod. Fiscale', 'wp-esg')); ?></th>
                        <th><?php echo esc_html(__('Codice ATECO', 'wp-esg')); ?></th>
                        <th><?php echo esc_html(__('Dimensione Azienda', 'wp-esg')); ?></th>
                        <th><?php echo esc_html(__('Anno di Bilancio', 'wp-esg')); ?></th>
                        <th><?php echo esc_html(__('Stato Avanzamento', 'wp-esg')); ?></th>
                        <th><?php echo esc_html(__('Azioni di Sistema', 'wp-esg')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)) : ?>
                        <tr><td colspan="6"><?php echo esc_html(__('Nessun flusso di assessment aziendale registrato in questo blocco di tracciamento.', 'wp-esg')); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($records as $row) : 
                            $status = esc_attr($row['workflow_status']);
                            $badgeClass = 'wp-esg-status-' . strtolower(str_replace(' ', '-', $status));
                            
                            // 🛠️ NORMALIZZAZIONE INTERFACCIA: Traduzione dinamica del profilo dimensionale
                            switch (strtoupper(trim($row['company_size']))) {
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
                                    $displaySize = !empty($row['company_size']) ? esc_html($row['company_size']) : __('Non Specificata', 'wp-esg');
                                    break;
                            }

                            // Traduzione estetica dello stato del workflow per il badge amministrativo
                            $displayStatus = $status;
                            if ($status === 'Draft') {
                                $displayStatus = __('Bozza', 'wp-esg');
                            } elseif ($status === 'Pending Review') {
                                $displayStatus = __('In Revisione', 'wp-esg');
                            } elseif ($status === 'Completed') {
                                $displayStatus = __('Certificato', 'wp-esg');
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($row['company_tax_id']); ?></strong></td>
                                <td><code><?php echo esc_html($row['business_code']); ?></code></td>
                                <td><?php echo esc_html($displaySize); ?></td>
                                <td><?php echo esc_html($row['balance_year']); ?></td>
                                <td><span class="wp-esg-status-badge <?php echo $badgeClass; ?>"><?php echo esc_html($displayStatus); ?></span></td>
                                <td>
                                    <?php if ($status === 'Pending Review') : ?>
                                        <button type="button" class="button button-primary button-small js-wp-esg-transition-trigger" data-assessment-id="<?php echo (int)$row['id']; ?>" data-action-target="complete">
                                            <?php echo esc_html(__('Revisiona & Certifica', 'wp-esg')); ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="description"><?php echo esc_html(__('Nessuna azione disponibile', 'wp-esg')); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
