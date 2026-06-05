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
        // La registrazione dei menu è stata centralizzata nel WizardController unificato.
        add_action('admin_init', [$this, 'registerPlatformSettings']);
    }

    public function registerPlatformSettings(): void {
        register_setting('wp_esg_settings_group', 'wp_esg_active_country');
        register_setting('wp_esg_settings_group', 'wp_esg_balance_year');
        register_setting('wp_esg_settings_group', 'wp_esg_no_auth_mode');
    }

    /**
     * Metodo di rendering ufficiale richiamato dal WizardController
     */
    public function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $activeCountry = get_option('wp_esg_active_country', 'IT');
        $balanceYear   = get_option('wp_esg_balance_year', '2026');
        $noAuthMode    = get_option('wp_esg_no_auth_mode', '1');
        ?>
        <div class="wp-esg-admin-container" style="margin-top:20px;">
            <p><?php echo esc_html(__('Establish your cooperative network governance limits below.', 'wp-esg')); ?></p>
            
            <form method="post" action="options.php" class="wp-esg-wizard-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <?php settings_fields('wp_esg_settings_group'); ?>
                <?php do_settings_sections('wp_esg_settings_group'); ?>
                
                <table class="form-table" role="presentation">
                    <tr style="vertical-align: top;">
                        <th scope="row" style="padding:20px 10px; font-weight:bold;"><label for="wp_esg_active_country"><?php echo esc_html(__('Active Region Jurisdiction', 'wp-esg')); ?></label></th>
                        <td style="padding:20px 10px;">
                            <select id="wp_esg_active_country" name="wp_esg_active_country" style="min-width:350px; padding:6px;">
                                <option value="IT" <?php selected($activeCountry, 'IT'); ?>>Italy (ATECO Registry Mapping)</option>
                                <option value="FR" <?php selected($activeCountry, 'FR'); ?>>France (APE/NAF Registry Mapping)</option>
                                <option value="ES" <?php selected($activeCountry, 'ES'); ?>>Spain (CNAE Registry Mapping)</option>
                            </select>
                            <p class="description" style="margin-top:8px; color:#646970; font-style:italic;"><?php echo esc_html(__('Determines which tax-classification mapping matrix is invoked during structural context audits.', 'wp-esg')); ?></p>
                        </td>
                    </tr>
                    
                    <tr style="vertical-align: top;">
                        <th scope="row" style="padding:20px 10px; font-weight:bold;"><label for="wp_esg_balance_year"><?php echo esc_html(__('Active Balance Accounting Year', 'wp-esg')); ?></label></th>
                        <td style="padding:20px 10px;">
                            <input type="number" id="wp_esg_balance_year" name="wp_esg_balance_year" value="<?php echo esc_attr($balanceYear); ?>" min="2020" max="2050" class="regular-text" style="min-width:350px; padding:6px;" />
                            <p class="description" style="margin-top:8px; color:#646970; font-style:italic;"><?php echo esc_html(__('Establishes the targeted evaluation time boundary window context.', 'wp-esg')); ?></p>
                        </td>
                    </tr>

                    <tr style="vertical-align: top;">
                        <th scope="row" style="padding:20px 10px; font-weight:bold Hausa-label;"><?php echo esc_html(__('No-Auth Token Lifecycle Access', 'wp-esg')); ?></th>
                        <td style="padding:20px 10px;">
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
