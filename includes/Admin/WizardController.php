<?php
namespace WpEsg\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WizardController
 * Intercepts post-activation procedures to enforce automatic configuration routing.
 *
 * @package WpEsg\Admin
 */
class WizardController {

    public function __construct() {
        add_action('admin_init', [$this, 'enforceWizardRedirectOnActivation']);
    }

    /**
     * Inspects initialization transient context to trigger initial redirection.
     */
    public function enforceWizardRedirectOnActivation(): void {
        if (!get_transient('wp_esg_activation_redirect_flag')) {
            return;
        }

        delete_transient('wp_esg_activation_redirect_flag');

        // Prevent infinite loops during bulk command line activations or silent network migrations
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        if (isset($_GET['activate-multi']) || !current_user_can('manage_options')) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=wp-esg-settings'));
        exit;
    }
}
