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
            __('Assessment Workflow Monitor', 'wp-esg'),
            __('Workflow Monitor', 'wp-esg'),
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
            <h1 class="wp-heading-inline"><?php echo esc_html(__('Corporate Assessment Workflow Tracking Monitor', 'wp-esg')); ?></h1>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped table-view-list entries" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th><?php echo esc_html(__('Tax Identifier', 'wp-esg')); ?></th>
                        <th><?php echo esc_html(__('Activity Code', 'wp-esg')); ?></th>
                        <th><?php echo esc_html(__('Capacity Profile', 'wp-esg')); ?></th>
                        <th><?php echo esc_html(__('Accounting Year', 'wp-esg')); ?></th>
                        <th><?php echo esc_html(__('Workflow Phase', 'wp-esg')); ?></th>
                        <th><?php echo esc_html(__('System Controls', 'wp-esg')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)) : ?>
                        <tr><td colspan="6"><?php echo esc_html(__('No organization assessment workflows registered within this tracking block.', 'wp-esg')); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($records as $row) : 
                            $status = esc_attr($row['workflow_status']);
                            $badgeClass = 'wp-esg-status-' . strtolower(str_replace(' ', '-', $status));
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($row['company_tax_id']); ?></strong></td>
                                <td><?php echo esc_html($row['business_code']); ?></td>
                                <td><?php echo esc_html($row['company_size']); ?></td>
                                <td><?php echo esc_html($row['balance_year']); ?></td>
                                <td><span class="wp-esg-status-badge <?php echo $badgeClass; ?>"><?php echo esc_html($status); ?></span></td>
                                <td>
                                    <?php if ($status === 'Pending Review') : ?>
                                        <button type="button" class="button button-primary button-small js-wp-esg-transition-trigger" data-assessment-id="<?php echo (int)$row['id']; ?>" data-action-target="complete">
                                            <?php echo esc_html(__('Audit & Certify', 'wp-esg')); ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="description"><?php echo esc_html(__('No controls available', 'wp-esg')); ?></span>
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
