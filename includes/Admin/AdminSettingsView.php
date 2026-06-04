<?php
namespace WpEsg\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AdminSettingsView
 * Renders the global platform configuration Wizard panel inside the WordPress backend.
 *
 * @package WpEsg\Admin
 */
class AdminSettingsView {

    public function __construct() {
        add_action('admin_menu', [$this, 'registerSettingsMenuPage']);
        add_action('admin_init', [$this, 'registerPlatformSettings']);
    }

    public function registerSettingsMenuPage(): void {
        add_menu_page(
            __('wp-esg Configuration Wizard', 'wp-esg'),
            __('wp-esg Wizard', 'wp-esg'),
            'manage_options',
            'wp-esg-settings',
            [$this, 'renderWizardLayout'],
            'dashicons-admin-generic',
            81
        );
    }

    public function registerPlatformSettings(): void {
        register_setting('wp_esg_settings_group', 'wp_esg_active_country');
        register_setting('wp_esg_settings_group', 'wp_esg_balance_year');
        register_setting('wp_esg_settings_group', 'wp_esg_no_auth_mode');
    }

    public function renderWizardLayout(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $activeCountry = get_option('wp_esg_active_country', 'IT');
        $balanceYear   = get_option('wp_esg_balance_year', '2026');
        $noAuthMode    = get_option('wp_esg_no_auth_mode', '1');
        ?>
        <div class="wrap wp-esg-admin-container">
            <h1><?php echo esc_html(__('wp-esg Platform Initialization Wizard', 'wp-esg')); ?></h1>
            <p><?php echo esc_html(__('Establish your cooperative network governance limits below.', 'wp-esg')); ?></p>
            
            <form method="post" action="options.php" class="wp-esg-wizard-card">
                <?php settings_fields('wp_esg_settings_group'); ?>
                <?php do_settings_sections('wp_esg_settings_group'); ?>
                
                <table class="form-table" role="presentation">
                    <tr style="vertical-align: top;">
                        <th scope="row"><label for="wp_esg_active_country"><?php echo esc_html(__('Active Region Jurisdiction', 'wp-esg')); ?></label></th>
                        <td>
                            <select id="wp_esg_active_country" name="wp_esg_active_country">
                                <option value="IT" <?php selected($activeCountry, 'IT'); ?>>Italy (ATECO Registry Mapping)</option>
                                <option value="FR" <?php selected($activeCountry, 'FR'); ?>>France (APE/NAF Registry Mapping)</option>
                                <option value="ES" <?php selected($activeCountry, 'ES'); ?>>Spain (CNAE Registry Mapping)</option>
                            </select>
                            <p class="description"><?php echo esc_html(__('Determines which tax-classification mapping matrix is invoked during structural context audits.', 'wp-esg')); ?></p>
                        </td>
                    </tr>
                    
                    <tr style="vertical-align: top;">
                        <th scope="row"><label for="wp_esg_balance_year"><?php echo esc_html(__('Active Balance Accounting Year', 'wp-esg')); ?></label></th>
                        <td>
                            <input type="number" id="wp_esg_balance_year" name="wp_esg_balance_year" value="<?php echo esc_attr($balanceYear); ?>" min="2020" max="2050" class="regular-text" />
                            <p class="description"><?php echo esc_html(__('Establishes the targeted evaluation time boundary window context.', 'wp-esg')); ?></p>
                        </td>
                    </tr>

                    <tr style="vertical-align: top;">
                        <th scope="row"><?php echo esc_html(__('No-Auth Token Lifecycle Access', 'wp-esg')); ?></th>
                        <td>
                            <fieldset>
                                <label for="wp_esg_no_auth_mode">
                                    <input type="checkbox" id="wp_esg_no_auth_mode" name="wp_esg_no_auth_mode" value="1" <?php checked($noAuthMode, '1'); ?> />
                                    <?php echo esc_html(__('Enable anonymous single-use validation access links for remote producers.', 'wp-esg')); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Lock Platform Configurations', 'wp-esg')); ?>
            </form>
        </div>
        <?php
    }
}
