<?php
/**
 * WP-ESG Cleanup Engine on Uninstall
 * Deletes all custom database tables, saved options, and metadata blocks upon hard plugin removal.
 */

// 🛡️ SICUREZZA: Se il file viene chiamato direttamente dal browser e non da WordPress, esce immediatamente.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. ELIMINAZIONE DELLE TABELLE PERSONALIZZATE DAL DATABASE
// Elenchiamo le tabelle core create dal plugin
$esg_tables = array(
    $wpdb->prefix . 'esg_assessments',
    $wpdb->prefix . 'esg_assessment_results'
);

foreach ( $esg_tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS $table" );
}

// 2. ELIMINAZIONE DELLE OPZIONI DI CONFIGURAZIONE (SALVATE NELLA VIEW IMPOSTAZIONI)
$esg_options = array(
    'wp_esg_active_country',
    'wp_esg_balance_year',
    'wp_esg_no_auth_mode',
    'wp_esg_landing_page_id' // L'ID della pagina frontend creata all'attivazione
);

foreach ( $esg_options as $option ) {
    delete_option( $option );
}

// 3. PULIZIA DEI TRANSIENT RESIDUI DI SISTEMA
delete_transient( 'wp_esg_activation_redirect_flag' );

// 4. FACOLTATIVO: Rimozione della pagina frontend "ESG Assessment" creata all'attivazione
// Se vuoi lasciare la pagina creata per l'utente, puoi commentare o rimuovere questo blocco.
$landing_page_id = get_option( 'wp_esg_landing_page_id' );
if ( $landing_page_id ) {
    wp_delete_post( $landing_page_id, true ); // Impostato a true per bypassare il cestino ed eliminarla subito
}
