<?php
/**
 * Plugin Name: WP ESG 
 * Plugin URI:   https://github.com/socialforger/wp-esg
 * Description: Modular ESG Assessment Engine. Integrates OpenESEA, SDG Mapping, PGS Evaluation, and Vertical Product Self-Certifications.
 * Version:     1.0.0
 * Author:      SocialForger
 * Author URI:   https://socialforger.com
 * License:     GPLv2 or later
 * Text Domain: wp-esg
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'WP_ESG_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_ESG_FRAMEWORKS_PATH', WP_ESG_PATH . 'frameworks/' );

// Include the manual PSR-4 Ecosystem Autoloader
if ( file_exists( WP_ESG_PATH . 'vendor/autoload.php' ) ) {
    require_once WP_ESG_PATH . 'vendor/autoload.php';
}

// Global activation hook registered with the correct nested namespace
if ( class_exists( 'WpEsg\Storage\DatabaseSetup' ) ) {
    register_activation_hook( __FILE__, array( 'WpEsg\Storage\DatabaseSetup', 'activate' ) );
}

// Set activation redirect transient so WizardController routes to the settings page on first load
register_activation_hook( __FILE__, function() {
    set_transient( 'wp_esg_activation_redirect_flag', true, 30 );
} );

/**
 * Core initialization routine triggered on plugins_loaded
 */
add_action( 'plugins_loaded', 'wp_esg_initialize_core' );
function wp_esg_initialize_core() {
    // Resolved through Autoloader via the real Core namespace
    if ( class_exists( 'WpEsg\Core\WorkflowManager' ) ) {
        new WpEsg\Core\WorkflowManager();
    }

    // Initialize Admin UI panels (settings page, workflow monitor, activation wizard redirect)
    if ( is_admin() ) {
        if ( class_exists( 'WpEsg\Admin\AdminSettingsView' ) ) {
            new WpEsg\Admin\AdminSettingsView();
        }
        if ( class_exists( 'WpEsg\Admin\AdminWorkflowView' ) ) {
            new WpEsg\Admin\AdminWorkflowView();
        }
        if ( class_exists( 'WpEsg\Admin\WizardController' ) ) {
            new WpEsg\Admin\WizardController();
        }
    }
}

/**
 * Class WP_ESG_Engine_Test
 * Diagnostic monitoring suite for Frameworks Auto-Discovery.
 */
class WP_ESG_Engine_Test {

    public function __construct() {
        add_action( 'admin_notices', array( $this, 'render_diagnostic_notice' ) );
    }

    public static function run_diagnostics() {
        $results = array();
        $directories_to_check = array(
            'openesea' => WP_ESG_FRAMEWORKS_PATH . 'openesea/',
            'sdg'      => WP_ESG_FRAMEWORKS_PATH . 'sdg/',
            'pgs'      => WP_ESG_FRAMEWORKS_PATH . 'pgs/',
            'products' => WP_ESG_FRAMEWORKS_PATH . 'products/food/'
        );

        foreach ( $directories_to_check as $key => $path ) {
            if ( ! is_dir( $path ) ) {
                $results[$key] = array( 'status' => 'ERROR', 'message' => 'Directory missing' );
                continue;
            }

            $files = glob( $path . '*.json' );
            if ( empty( $files ) ) {
                $results[$key] = array( 'status' => 'WARNING', 'message' => 'No JSON blueprints found' );
            } else {
                $results[$key] = array( 
                    'status' => 'OK', 
                    'message' => sprintf( 'Found %d schema configuration file(s)', count( $files ) ) 
                );
            }
        }

        return $results;
    }

    public function render_diagnostic_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        $diagnostics = self::run_diagnostics();
        
        // Renderizza l'avviso solo se c'è un errore critico nei framework
        $has_error = false;
        foreach ( $diagnostics as $info ) {
            if ( $info['status'] === 'ERROR' ) {
                $has_error = true;
                break;
            }
        }

        if ( ! $has_error ) {
            return;
        }
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>⚠️ Errore Critico WP-ESG Frameworks:</strong> Mancano delle cartelle nella directory frameworks.</p>
        </div>
        <?php
    }
}

new WP_ESG_Engine_Test();
