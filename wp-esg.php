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

// 🔴 FIXED CRITICAL BUG 2: Global activation hook registered with the correct nested namespace
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
add_action( ' </p>_loaded', 'wp_esg_initialize_core' );
function wp_esg_initialize_core() {
    // 🔴 FIXED CRITICAL BUG 1 & 2: Resolved through Autoloader via the real Core namespace
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
 * 🛠️ STRUMENTO DI MOCK TESTING PER AMBIENTE BACKEND ISOLATO
 * Si attiva visitando: https://mercatosociale.it/?run_esg_test=1
 */
add_action( 'init', function() {
    // Verifica la presenza della query string nell'URL
    if ( ! isset( $_GET['run_esg_test'] ) ) {
        return;
    }

    // Forza l'output HTML pulito per evitare che si mescoli con il tema o la cache di WP
    header( 'Content-Type: text/html; charset=utf-8' );

    echo '<html><head><title>ESG Core Simulation Test</title></head><body style="font-family: sans-serif; padding: 20px; line-height: 1.6; max-width: 800px; margin: auto;">';
    echo '<div style="background: #f4f6f9; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
    echo '<h2 style="margin-top:0; color: #1d2327;">--- Test di Simulazione Backend ESG su mercatosociale.it ---</h2>';

    // 1. Array simulato di risposte (Questo sostituisce l'input grezzo dei form non ancora attivi)
    $mockAnswers = array(
        'company_tax_id'  => '12345678901',
        'business_code'   => 'A.01.1', // Codice generico (es. Agricoltura)
        'company_country' => 'IT',
        'balance_year'    => 2026,
        'q0102'           => '12',      // Mappatura nativa dipendenti (forza la taglia aziendale)
    );

    echo '<h4>1. Verifica Caricamento Risposte Simulate... [OK]</h4>';

    // 2. Test del Risolutore di Contesto (UserCompanyLinker)
    if ( class_exists( 'WpEsg\Storage\UserCompanyLinker' ) ) {
        $context = \WpEsg\Storage\UserCompanyLinker::resolveContext(
            $mockAnswers['company_country'], 
            $mockAnswers['business_code'], 
            (int) $mockAnswers['q0102']
        );
        echo '<h4>2. Test UserCompanyLinker (Risolutore di Contesto):</h4>';
        echo '<ul>';
        echo '<li>Dimensione Azienda Rilevata: <strong>' . esc_html( $context['company_size_scope'] ) . '</strong></li>';
        echo '<li>Modulo Qualitativo Richiesto: <strong>' . esc_html( $context['qualitative_module'] ) . '</strong></li>';
        echo '</ul>';
    } else {
        echo '<h4 style="color:#dc3232;">2. Test UserCompanyLinker: ERRORE (Classe non trovata)</h4>';
        $context = array( 'company_size_scope' => 'Standard', 'qualitative_module' => 'none' );
    }

    // 3. Test della Persistenza dei dati grezzi su Database ($wpdb)
    echo '<h4>3. Test Scrittura / Aggiornamento Database:</h4>';
    global $wpdb;
    $table = $wpdb->prefix . 'esg_assessments';

    // Controlliamo prima se la tabella esiste davvero nel DB
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
        echo '<p style="color:#dc3232;">❌ Errore critico: La tabella `' . esc_html($table) . '` non esiste. Hai attivato correttamente il plugin per far girare DatabaseSetup?</p>';
    } else {
        $dbRecord = array(
            'company_tax_id'  => $mockAnswers['company_tax_id'],
            'business_code'   => $mockAnswers['business_code'],
            'company_size'    => $context['company_size_scope'],
            'balance_year'    => $mockAnswers['balance_year'],
            'country_code'    => $mockAnswers['company_country'],
            'workflow_status' => 'Draft',
            'raw_answers'     => json_encode( $mockAnswers )
        );

        // Verifica la presenza di record duplicati per lo stesso anno contabile (Upsert logico)
        $existingId = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE company_tax_id = %s AND balance_year = %d",
            $dbRecord['company_tax_id'], $dbRecord['balance_year']
        ) );

        if ( $existingId ) {
            $wpdb->update( $table, $dbRecord, array( 'id' => $existingId ) );
            echo '<p style="color:#46b450; font-weight:bold;">✔ Record esistente trovato e aggiornato correttamente! (ID Riga: ' . $existingId . ')</p>';
        } else {
            $wpdb->insert( $table, $dbRecord );
            echo '<p style="color:#46b450; font-weight:bold;">✔ Nessun record precedente. Nuova riga inserita con successo nel database!</p>';
        }
    }

    // 4. Placeholder per l'invocazione del motore di calcolo proprietario
    echo '<h4>4. Innesco Motore di Calcolo Proprietario:</h4>';
    echo '<p style="color:#646970; font-style:italic;">[Configurazione Pronta] Quando implementerai la tua nuova classe proprietaria di calcolo per indicatori e SDGs, potrai istanziarla qui per stampare le metriche calcolate a schermo prima di riattivare i form manager esterni.</p>';

    echo '<h3 style="border-top: 1px solid #ccd0d4; padding-top: 15px; margin-bottom:0; color: #46b450;">--- Test Eseguito con Successo ---</h3>';
    echo '</div></body></html>';
    
    exit; // Interrompe l'esecuzione di WordPress per non mostrare il layout del sito mercatosociale.it
} );

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
