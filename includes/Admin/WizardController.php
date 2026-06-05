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
     * FIX BUG #4: Registrazione fogli di stile e script per l'interfaccia WP ESG
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
     * FIX BUG #3: Configurazione sotto-pagine gerarchiche sotto un'unica radice
     */
    public function register_custom_wp_esg_menu() {
        
        add_menu_page(
            'WP ESG Platform',
            'WP ESG',
            'manage_options',
            'wp-esg', 
            array( $this, 'render_dashboard_view' ),
            'dashicons-analytics', 
            25
        );

        add_submenu_page(
            'wp-esg',
            'Workflow Monitor',
            'Workflow Monitor',
            'manage_options',
            'wp-esg',
            array( $this, 'render_dashboard_view' )
        );

        add_submenu_page(
            'wp-esg',
            'Setup Wizard',
            'Setup Wizard',
            'manage_options',
            'wp-esg-wizard',
            array( $this, 'render_wizard_view' )
        );

        add_submenu_page(
            'wp-esg',
            'Historical Archive',
            'Historical Archive',
            'manage_options',
            'wp-esg-archive',
            array( $this, 'render_archive_view' )
        );

        add_submenu_page(
            'wp-esg',
            'System Settings',
            'System Settings',
            'manage_options',
            'wp-esg-settings',
            array( $this, 'render_settings_view' )
        );
    }

    public function render_dashboard_view() {
        if ( class_exists( 'WpEsg\Admin\AdminWorkflowView' ) ) {
            $view = new \WpEsg\Admin\AdminWorkflowView();
            $view->render(); 
        } else {
            echo '<div class="wrap"><h2>Workflow Monitor</h2><p>Core dashboard view class missing.</p></div>';
        }
    }

    public function render_wizard_view() {
        echo '<div class="wrap">
            <h2>⚙️ WP ESG Setup Wizard</h2>
            <p class="description">Procedura guidata di calibrazione delle matriche ambientali, sociali e di governance.</p>
            <hr>
            <div class="welcome-panel" style="padding:20px; margin-top:20px;"><h3>Configurazione Guidata Matrix</h3><p>Il motore è calibrato per mapparne dinamicamente i flussi OpenESEA e i relativi moduli PGS.</p></div>
        </div>';
    }

    public function render_archive_view() {
        echo '<div class="wrap">
            <h2>🏛️ Centralized Historical Archive (Auditor Panel)</h2>
            <p class="description">Pannello di controllo del revisore per analizzare i JSON strutturati ed emettere i giudizi formali di validazione.</p>
            <hr>';
        
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
            $view->render(); 
        } else {
            echo '<div class="wrap"><h2>System Settings</h2><p>Settings view class missing.</p></div>';
        }
    }
}
