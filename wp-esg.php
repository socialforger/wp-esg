<?php
/**
 * Plugin Name: WP ESG 
 * Plugin URI:  https://github.com/socialforger/wp-esg
 * Description: Modular ESG Assessment Engine. Integrates OpenESEA, SDG Mapping, and Vertical Product Self-Certifications. NOTE: For Standalone mode, use the default testing access key: ESG2026
 * Version:     1.0.0
 * Author:      SocialForger
 * Author URI:  https://socialforger.com
 * License:     GPLv2 or later
 * Text Domain: wp-esg
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WP_ESG_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_ESG_FRAMEWORKS_PATH', WP_ESG_PATH . 'frameworks/' );

if ( file_exists( WP_ESG_PATH . 'vendor/autoload.php' ) ) {
    require_once WP_ESG_PATH . 'vendor/autoload.php';
}

// Caricamento del dominio di localizzazione .po/.mo
add_action( 'init', 'wp_esg_load_localization_domains' );
function wp_esg_load_localization_domains() {
    load_plugin_textdomain( 'wp-esg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Routine di attivazione database
if ( class_exists( 'WpEsg\Storage\DatabaseSetup' ) ) {
    register_activation_hook( __FILE__, array( 'WpEsg\Storage\DatabaseSetup', 'activate' ) );
}

global $wpdb;
register_activation_hook( __FILE__, 'wp_esg_activation_routine' );
function wp_esg_activation_routine() {
    set_transient( 'wp_esg_activation_redirect_flag', true, 30 );
    
    $page_title  = 'ESG Assessment';
    $shortcode   = '[wp_esg_dynamic_assessment]';
    $option_name = 'wp_esg_landing_page_id';
    
    $existing_id = get_option( $option_name );
    if ( $existing_id && 'trash' !== get_post_status( $existing_id ) ) {
        return;
    }
    
    // FIX BUG #1: Uso di get_posts invece di get_page_by_title per azzerare l'output inatteso
    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'any',
        'title'          => $page_title,
        'posts_per_page' => 1,
    ]);
    
    $page_check = ! empty( $pages ) ? $pages[0] : null;
    if ( isset( $page_check->ID ) ) {
        update_option( $option_name, $page_check->ID );
        return;
    }
    
    // FIX BUG #7: ID autore dinamico basato sul sysadmin corrente
    wp_insert_post( array(
        'post_title'     => $page_title,
        'post_name'      => 'esg-assessment',
        'post_content'   => $shortcode,
        'post_status'    => 'publish',
        'post_type'      => 'page',
        'post_author'    => get_current_user_id() ? get_current_user_id() : 1,
        'comment_status' => 'closed',
    ) );
}

// FIX BUG #5: Intercettazione del transient su admin_init per il redirect automatico pulito
add_action( 'admin_init', 'wp_esg_process_activation_redirect' );
function wp_esg_process_activation_redirect() {
    if ( get_transient( 'wp_esg_activation_redirect_flag' ) ) {
        delete_transient( 'wp_esg_activation_redirect_flag' );
        wp_redirect( admin_url( 'admin.php?page=wp-esg' ) );
        exit;
    }
}

// Bootstrap dei componenti del plugin
add_action( 'plugins_loaded', 'wp_esg_initialize_core' );
function wp_esg_initialize_core() {
    if ( class_exists( 'WpEsg\Core\WorkflowManager' ) ) {
        new WpEsg\Core\WorkflowManager();
    }

    if ( class_exists( 'WpEsg\Frontend\AssessmentShortcode' ) ) {
        new WpEsg\Frontend\AssessmentShortcode();
    }

    if ( is_admin() ) {
        if ( class_exists( 'WpEsg\Admin\WizardController' ) ) {
            new WpEsg\Admin\WizardController();
        }
    }
}
