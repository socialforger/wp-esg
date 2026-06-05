<?php
namespace WpEsg\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WizardController {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_custom_wp_esg_menu' ) );
    }

    /**
     * Registers the professional unified WP ESG administration menu and active satellite sub-pages.
     */
    public function register_custom_wp_esg_menu() {
        
        // 1. MENU PRINCIPALE - Titolo istituzionale "WP ESG" con icona adatta ad analisi/metriche
        add_menu_page(
            'WP ESG Platform',
            'WP ESG',
            'manage_options',
            'wp-esg-dashboard',
            array( $this, 'render_dashboard_view' ),
            'dashicons-analytics', 
            25
        );

        // 2. SOTTO-MENU: WORKFLOW MONITOR (La prima sotto-voce deve coincidere con lo slug principale)
        add_submenu_page(
            'wp-esg-dashboard',
            'Workflow Monitor',
            'Workflow Monitor',
            'manage_options',
            'wp-esg-dashboard',
            array( $this, 'render_dashboard_view' )
        );

        // 3. SOTTO-MENU: SETUP WIZARD (La procedura guidata di calibrazione)
        add_submenu_page(
            'wp-esg-dashboard',
            'Setup Wizard',
            'Setup Wizard',
            'manage_options',
            'wp-esg-wizard',
            array( $this, 'render_wizard_view' )
        );

        // 4. SOTTO-MENU: HISTORICAL ARCHIVE (Pannello revisore/auditor per il controllo dei cassetti JSON)
        add_submenu_page(
            'wp-esg-dashboard',
            'Historical Archive',
            'Historical Archive',
            'manage_options',
            'wp-esg-archive',
            array( $this, 'render_archive_view' )
        );

        // 5. SOTTO-MENU: SYSTEM SETTINGS (Configurazione parametri globali, paesi e anni di bilancio)
        add_submenu_page(
            'wp-esg-dashboard',
            'System Settings',
            'System Settings',
            'manage_options',
            'wp-esg-settings',
            array( $this, 'render_settings_view' )
        );
    }

    // ==========================================
    // CALLBACK DI RENDERING DELLE SCHERMATE ADMIN
    // ==========================================

    public function render_dashboard_view() {
        if ( class_exists( 'WpEsg\Admin\AdminWorkflowView' ) ) {
            $view = new \WpEsg\Admin\AdminWorkflowView();
            $view->render(); // Richiama la vista nativa del Workflow Monitor
        } else {
            echo '<div class="wrap"><h2>Workflow Monitor</h2><p>Core dashboard view class missing.</p></div>';
        }
    }

    public function render_wizard_view() {
        echo '<div class="wrap">
            <h2>⚙️ WP ESG Setup Wizard</h2>
            <p class="description">Procedura guidata di calibrazione delle matriche ambientali, sociali e di governance.</p>
            <hr>';
        echo '<div class="welcome-panel" style="padding:20px; margin-top:20px;"><h3>Configurazione Guidata Matrix</h3><p>Il motore è calibrato per mapparne dinamicamente i flussi OpenESEA e i relativi moduli PGS.</p></div>';
        echo '</div>';
    }

    public function render_archive_view() {
        echo '<div class="wrap">
            <h2>🏛️ Centralized Historical Archive (Auditor Panel)</h2>
            <p class="description">Pannello di controllo del revisore per analizzare i JSON strutturati ed emettere i giudizi formali di validazione.</p>
            <hr>';
        
        // Integrazione nativa con l'adattatore TablePressIntegrator per mostrare lo specchietto dei dati
        if ( class_exists('WpEsg\Output\Adapters\TablePressIntegrator') ) {
            $integrator = new \WpEsg\Output\Adapters\TablePressIntegrator();
            $data = $integrator->compileDatasetForTablePress();
            echo '<table class="wp-list-table widefat fixed striped" style="margin-top:20px; border:1px solid #ccd0d4; box-shadow:0 1px 3px rgba(0,0,0,0.05);">';
            foreach($data as $index => $row) {
                $tag = ($index === 0) ? 'th' : 'td';
                $style = ($index === 0) ? 'background:#f6f7f7; padding:12px; font-weight:bold; border-bottom:2px solid #dcdcde;' : 'padding:12px;';
                echo '<tr>';
                foreach($row as $cell) { echo "<$tag style='{$style}'>$cell</$tag>"; }
                echo '</tr>';
            }
            echo '</table>';
        }
        echo '</div>';
    }

    public function render_settings_view() {
        if ( class_exists( 'WpEsg\Admin\AdminSettingsView' ) ) {
            $view = new \WpEsg\Admin\AdminSettingsView();
            $view->render(); // Richiama la vista nativa AdminSettingsView delle impostazioni di sistema
        } else {
            echo '<div class="wrap"><h2>System Settings</h2><p>Settings view class missing.</p></div>';
        }
    }
}
