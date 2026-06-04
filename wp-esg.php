<?php
/**
 * Plugin Name: WP ESG Market Frameworks
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

// 🔴 CORREZIONE BUG 1: Path allineato alla cartella frameworks/
define( 'WP_ESG_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_ESG_FRAMEWORKS_PATH', WP_ESG_PATH . 'frameworks/' );
define( 'WP_ESG_INCLUDES_PATH', WP_ESG_PATH . 'includes/' );

/**
 * 🔴 CORREZIONE BUG 2 & 3: Autoloading del Vendor manuale e caricamento delle classi Core.
 */
require_once WP_ESG_PATH . 'vendor/autoload.php';

// Caricamento programmatico di tutte le classi core in includes/
$core_modules = array(
    'DatabaseSetup.php',
    'FormulaEngine.php',
    'WorkflowManager.php',
    // Aggiungi qui eventuali altri file presenti in includes/
);

foreach ( $core_modules as $module ) {
    $module_path = WP_ESG_INCLUDES_PATH . $module;
    if ( file_exists( $module_path ) ) {
        require_once $module_path;
    }
}

/**
 * Inizializzazione dei componenti Core dopo il caricamento
 */
add_action( 'plugins_loaded', 'wp_esg_initialize_core' );
function wp_esg_initialize_core() {
    // Registrazione hook di installazione db
    if ( class_exists( 'WpEsg\DatabaseSetup' ) ) {
        register_activation_hook( __FILE__, array( 'WpEsg\DatabaseSetup', 'activate' ) );
    }
    
    // Inizializzazione dei manager a runtime
    if ( class_exists( 'WpEsg\WorkflowManager' ) ) {
        new WpEsg\WorkflowManager();
    }
}

/**
 * Class WP_ESG_Engine_Test
 * Monitor di diagnostica per l'Auto-Discovery dei framework JSON.
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

new WP_ESG_Engine_Test();
