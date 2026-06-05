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
    if ( class_exists( 'WpEsg\Core\WorkflowManager' ) ) {
        new WpEsg\Core\WorkflowManager();
    }

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
 * 🛠️ STRUMENTO DI MOCK TESTING - DIAGNOSTICA PROFONDA DEL REGISTRO ITALIANO
 * Si attiva visitando: https://mercatosociale.it/?run_esg_test=1
 */
add_action( 'init', function() {
    if ( ! isset( $_GET['run_esg_test'] ) ) {
        return;
    }

    header( 'Content-Type: text/html; charset=utf-8' );

    echo '<html><head><title>ESG Core Real Test</title></head><body style="font-family: sans-serif; padding: 20px; line-height: 1.6; max-width: 800px; margin: auto;">';
    echo '<div style="background: #f4f6f9; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
    echo '<h2 style="margin-top:0; color: #1d2327;">--- Test di Simulazione Reale ESG su mercatosociale.it ---</h2>';

    // 1. Payload simulato completo
    $mockAnswers = array(
        'company_tax_id'  => '12345678901',
        'business_code'   => 'A.01.13.11', // Codice ATECO specifico da testare (Coltivazione di ortaggi)
        'legal_entity'    => 'S.r.l.',     // Stringa con punteggiatura per testare l'algoritmo simmetrico
        'company_country' => 'IT',
        'balance_year'    => 2026,
        'q0102'           => '12',         // Dipendenti
    );

    echo '<h4>1. Caricamento Risposte Simulate... [OK]</h4>';

    // 2. Diagnostica Unitaria della classe Registry Italiana
    echo '<h4>2. Test di Isolamento WpEsg\Storage\Registry\It\Registry:</h4>';
    if ( class_exists( 'WpEsg\Storage\Registry\It\Registry' ) ) {
        $registry = new \WpEsg\Storage\Registry\It\Registry();
        
        // Test ATECO Resolution
        $sector = $registry->getSector($mockAnswers['business_code']);
        echo '<ul>';
        echo '<li>Input Codice ATECO: <code>' . esc_html($mockAnswers['business_code']) . '</code></li>';
        echo '<li>Settore ESG Mappato dal Registro: <strong style="color:#2271b1;">' . esc_html($sector) . '</strong></li>';
        
        // Test Legal Entity Resolution
        $legalMeta = $registry->getLegalEntityMeta($mockAnswers['legal_entity']);
        echo '<li>Input Forma Giuridica: <code>' . esc_html($mockAnswers['legal_entity']) . '</code></li>';
        echo '<li>Dati Estratti (Governance Tier): <strong style="color:#2271b1;">' . esc_html($legalMeta['governance_tier']) . '</strong> (' . esc_html($legalMeta['descrizione']) . ')</li>';
        echo '</ul>';
    } else {
        echo '<p style="color:#dc3232; font-weight:bold;">❌ Errore: Classe Registry non ancora intercettata dall\'autoloader. Verifica maiuscole/minuscole del percorso.</p>';
    }

    // 3. Chiamata reale a UserCompanyLinker
    echo '<h4>3. Esecuzione Real UserCompanyLinker::resolveContext:</h4>';
    if ( class_exists( 'WpEsg\Storage\UserCompanyLinker' ) ) {
        try {
            $context = \WpEsg\Storage\UserCompanyLinker::resolveContext(
                $mockAnswers['company_country'], 
                $mockAnswers['business_code'], 
                (int) $mockAnswers['q0102'],
                (int) $mockAnswers['balance_year']
            );
            
            echo '<ul style="color: #46b450; font-weight: bold;">';
            echo '<li>Dimensione Azienda Calcolata: ' . esc_html( $context['company_size_scope'] ) . '</li>';
            echo '<li>Modulo Qualitativo Rilevato: ' . esc_html( $context['qualitative_module'] ) . '</li>';
            echo '</ul>';
        } catch ( \Throwable $e ) {
            echo '<p style="color:#dc3232; font-weight:bold;">❌ Eccezione nel Linker: ' . esc_html( $e->getMessage() ) . '</p>';
            $context = array( 'company_size_scope' => 'Errore Runtime', 'qualitative_module' => 'none' );
        }
    } else {
        $context = array( 'company_size_scope' => 'Standard-Fallback', 'qualitative_module' => 'none' );
    }

    // 4. Scrittura o Aggiornamento sul Database
    echo '<h4>4. Persistenza Record su Database ($wpdb):</h4>';
    global $wpdb;
    $table = $wpdb->prefix . 'esg_assessments';

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
        echo '<p style="color:#dc3232;">❌ Errore critico: La tabella `' . esc_html($table) . '` non esiste.</p>';
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

        $existingId = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE company_tax_id = %s AND balance_year = %d",
            $dbRecord['company_tax_id'], $dbRecord['balance_year']
        ) );

        if ( $existingId ) {
            $wpdb->update( $table, $dbRecord, array( 'id' => $existingId ) );
            echo '<p style="color:#46b450; font-weight:bold;">✔ Database OK: Record esistente aggiornato con successo! (ID Riga: ' . $existingId . ')</p>';
        } else {
            $wpdb->insert( $table, $dbRecord );
            echo '<p style="color:#46b450; font-weight:bold;">✔ Database OK: Nuova riga inserita con successo!</p>';
        }
    }

    echo '<h3>--- Test Concluso ---</h3>';
    echo '</div></body></html>';
    
    exit;
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
