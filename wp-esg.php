<?php
/**
 * Plugin Name: WP ESG
 * Plugin URI:  https://github.com/socialforger/wp-esg
 * Description: Modular ESG Assessment Engine. Integrates OpenESEA, SDG Mapping, PGS Evaluation, and Vertical Product Self-Certifications.
 * Version:     1.0.0
 * Author:      SocialForger
 * Author URI:  https://socialforger.com
 * License:     GPLv2 or later
 * Text Domain: wp-esg
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'WP_ESG_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_ESG_FRAMEWORKS_PATH', WP_ESG_PATH . 'wp-esg-frameworks/' );

/**
 * Class WP_ESG_Engine_Test
 * Core test suite to verify frameworks and schemas discovery.
 */
class WP_ESG_Engine_Test {

    public function __construct() {
        // Hooks checking for activation and registering a diagnostic admin notice
        add_action( 'admin_notices', array( $this, 'render_diagnostic_notice' ) );
    }

    /**
     * Scans and verifies the existence and integrity of JSON schemas.
     */
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

            // Target key files to test scanning capabilities
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

    /**
     * Renders a clean status bar in WP Backend to visually verify the setup.
     */
    public function render_diagnostic_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        $diagnostics = self::run_diagnostics();
        ?>
        <div class="notice notice-info is-dismissible">
            <p><strong>📊 WP-ESG Frameworks Auto-Discovery Diagnostic:</strong></p>
            <ul>
                <?php foreach ( $diagnostics as $layer => $info ) : 
                    $color = ( $info['status'] === 'OK' ) ? '#46b450' : '#dc3232';
                    ?>
                    <li>
                        <span style="font-family: monospace; font-weight: bold;"><?php echo esc_html( strtoupper( $layer ) ); ?>:</span> 
                        <span style="color: <?php echo esc_html( $color ); ?>; font-weight: bold;">[<?php echo esc_html( $info['status'] ); ?>]</span> 
                        <?php echo esc_html( $info['message'] ); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
}

// Instantiate the test monitor
new WP_ESG_Engine_Test();
