<?php
namespace WpEsg\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WizardController {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_custom_wp_esg_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Caricamento nativo di fogli di stile e script per l'interfaccia WP ESG
     */
    public function enqueue_admin_assets($hook) {
        if ( strpos($hook, 'wp-esg') === false ) {
            return;
        }

        $css_url = plugin_dir_url(dirname(__DIR__)) . 'assets/css/admin-style.css'; 
        $js_url  = plugin_dir_url(dirname(__DIR__)) . 'assets/js/admin-script.js';

        if ( file_exists( WP_ESG_PATH . 'assets/css/admin-style.css' ) ) {
            wp_enqueue_style( 'wp-esg-admin-css', $css_url, array(), '1.0.0' );
        }
        if ( file_exists( WP_ESG_PATH . 'assets/js/admin-script.js' ) ) {
            wp_enqueue_script( 'wp-esg-admin-js', $js_url, array('jquery'), '1.0.0', true );
        }
    }

    /**
     * Configurazione dell'albero dei sotto-menu sotto l'unica radice coerente 'wp-esg'
     */
    public function register_custom_wp_esg_menu() {
        
        // Menu Principale Top-Level
        add_menu_page(
            'WP ESG Platform',
            'WP ESG',
            'manage_options',
            'wp-esg', 
            array( $this, 'render_dashboard_view' ),
            'dashicons-analytics', 
            25
        );

        // Submenu 1: Workflow Monitor (Coincide con la radice)
        add_submenu_page(
            'wp-esg',
            'Workflow Monitor',
            'Workflow Monitor',
            'manage_options',
            'wp-esg',
            array( $this, 'render_dashboard_view' )
        );

        // Submenu 2: Setup Wizard
        add_submenu_page(
            'wp-esg',
            'Setup Wizard',
            'Setup Wizard',
            'manage_options',
            'wp-esg-wizard',
            array( $this, 'render_wizard_view' )
        );

        // Submenu 3: Historical Archive
        add_submenu_page(
            'wp-esg',
            'Historical Archive',
            'Historical Archive',
            'manage_options',
            'wp-esg-archive',
            array( $this, 'render_archive_view' )
        );

        // Submenu 4: System Settings
        add_submenu_page(
            'wp-esg',
            'System Settings',
            'System Settings',
            'manage_options',
            'wp-esg-settings',
            array( $this, 'render_settings_view' )
        );
    }

    /**
     * Gestione della prima pagina di visualizzazione: Workflow Monitor
     */
    public function render_dashboard_view() {
        echo '<div class="wrap">';
        echo '<h1>📊 WP ESG — Workflow Monitor</h1>';
        echo '<p class="description">' . esc_html__('Monitoraggio in tempo reale degli stati di avanzamento dei report di sostenibilità della rete aziendale.', 'wp-esg') . '</p>';
        echo '<hr class="wp-header-end">';

        if ( class_exists( 'WpEsg\Admin\AdminWorkflowView' ) ) {
            $view = new \WpEsg\Admin\AdminWorkflowView();
            $view->render(); 
        } else {
            echo '<div class="notice notice-error inline" style="margin-top:20px;"><p>Classe AdminWorkflowView non trovata.</p></div>';
        }

        echo '</div>';
    }

    public function render_wizard_view() {
        echo '<div class="wrap">
            <h2>⚙️ WP ESG Setup Wizard</h2>
            <p class="description">Procedura guidata di calibrazione delle matriche ambientali, sociali e di governance.</p>
            <hr>
            <div class="welcome-panel" style="padding:20px; margin-top:20px;"><h3>Configurazione Guidata Matrix</h3><p>Il motore è calibrato per mapparne dinamicamente i flussi OpenESEA e i relativi moduli PGS.</p></div>
        </div>';
    }

    /**
     * Archivio Storico Centralizzato per il Revisore
     */
    public function render_archive_view() {
        echo '<div class="wrap">
            <h2>🏛️ Centralized Historical Archive (Auditor Panel)</h2>
            <p class="description">Pannello di controllo del revisore per analizzare i JSON strutturati ed emettere i giudizi formali di validazione.</p>
            <hr>';
        
        if ( class_exists('WpEsg\Output\Adapters\TablePressIntegrator') ) {
            $integrator = new \WpEsg\Output\Adapters\TablePressIntegrator();
            $data = $integrator->compileDatasetForTablePress();
            
            if ( ! empty($data) ) {
                echo '<table class="wp-list-table widefat fixed striped" style="margin-top:20px; border:1px solid #ccd0d4; box-shadow:0 1px 3px rgba(0,0,0,0.05);">';
                foreach($data as $index => $row) {
                    $tag = ($index === 0) ? 'th' : 'td';
                    $style = ($index === 0) ? 'background:#f6f7f7; padding:12px; font-weight:bold; border-bottom:2px solid #dcdcde;' : 'padding:12px;';
                    echo '<tr>';
                    foreach($row as $cell) { echo "<$tag style='{$style}'>$cell</$tag>"; }
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="notice notice-info inline" style="margin-top:20px;"><p>Nessun record presente nell\'archivio storico.</p></div>';
            }
        }
        echo '</div>';
    }

    /**
     * Gestione delle impostazioni generali del plugin
     */
    public function render_settings_view() {
        echo '<div class="wrap">';
        echo '<h1>⚙️ WP ESG — System Settings</h1>';
        echo '<hr class="wp-header-end">';

        if ( class_exists( 'WpEsg\Admin\AdminSettingsView' ) ) {
            $view = new \WpEsg\Admin\AdminSettingsView();
            $view->render(); 
        } else {
            echo '<div class="notice notice-error inline" style="margin-top:20px;"><p>Classe AdminSettingsView non trovata.</p></div>';
        }
        echo '</div>';
    }
}
