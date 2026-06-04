<?php
namespace WpEsg\Storage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DatabaseSetup
 * Handles custom relational table schemas and activation routines using the native dbDelta engine.
 *
 * @package WpEsg\Storage
 */
class DatabaseSetup {

    /**
     * Creates or updates the custom plugin database tables under the standard WordPress schema layout.
     * Evaluates state maps for active assessment sessions, immutable footprints, and authentication keys.
     *
     * @return void
     */
    public static function activate(): void {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Table 1: In-progress assessment sessions tracking parameters and dynamic raw drafts answers
        $tableAssessments = $wpdb->prefix . 'esg_assessments';
        $sqlAssessments = "CREATE TABLE $tableAssessments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            company_tax_id varchar(50) NOT NULL,
            business_code varchar(20) NOT NULL, 
            legal_entity_code varchar(20) NOT NULL,
            company_size varchar(20) NOT NULL,   
            balance_year smallint(6) NOT NULL,
            country_code varchar(2) NOT NULL,    
            workflow_status varchar(30) DEFAULT 'Draft' NOT NULL,
            raw_answers longtext NOT NULL,       
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY company_year (company_tax_id, balance_year)
        ) $charsetCollate;";

        // Table 2: Immutable mathematical registry freezing final validated metric structures and fingerprints
        $tableResults = $wpdb->prefix . 'esg_assessment_results';
        $sqlResults = "CREATE TABLE $tableResults (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            assessment_id bigint(20) NOT NULL,
            company_tax_id varchar(50) NOT NULL,
            balance_year smallint(6) NOT NULL,
            country_code varchar(2) NOT NULL,
            framework_id varchar(50) NOT NULL,   
            framework_version varchar(20) NOT NULL,
            framework_manifest_hash varchar(64) NOT NULL, 
            assessment_payload longtext NOT NULL, 
            completed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_run (company_tax_id, balance_year)
        ) $charsetCollate;";

        // Table 3: Single-use link authentication tokens layer preventing link-replay exploits
        $tableTokens = $wpdb->prefix . 'esg_activation_tokens';
        $sqlTokens = "CREATE TABLE $tableTokens (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token_key varchar(64) NOT NULL,
            company_tax_id varchar(50) NOT NULL,
            balance_year smallint(6) NOT NULL,
            is_used tinyint(1) DEFAULT 0 NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_key (token_key)
        ) $charsetCollate;";

        dbDelta($sqlAssessments);
        dbDelta($sqlResults);
        dbDelta($sqlTokens);
    }
}
