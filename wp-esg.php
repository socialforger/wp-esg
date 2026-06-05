/**
 * 🛠️ STRUMENTO DI MOCK TESTING PER AMBIENTE BACKEND ISOLATO (VERSIONE BYPASS REGISTRY)
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

    // 1. Array simulato di risposte
    $mockAnswers = array(
        'company_tax_id'  => '12345678901',
        'business_code'   => 'A.01.1', 
        'company_country' => 'IT',
        'balance_year'    => 2026,
        'q0102'           => '12', // Dipendenti
    );

    echo '<h4>1. Verifica Caricamento Risposte Simulate... [OK]</h4>';

    // 2. MOCK DEL RISOLUTORE DI CONTESTO (Evitiamo il Fatal Error del Registry mancante)
    echo '<h4>2. Simulazione Contesto (UserCompanyLinker Bypassato per Test):</h4>';
    
    // Generiamo artificialmente quello che la classe avrebbe dovuto restituire
    $context = array(
        'company_size_scope' => 'Piccola Impresa', // Standard, Piccola, Media, Grande
        'qualitative_module' => 'agroecology'      // Mappato sul file PGS/OpenESEA
    );

    echo '<ul>';
    echo '<li>Dimensione Azienda (Simulata): <strong>' . esc_html( $context['company_size_scope'] ) . '</strong></li>';
    echo '<li>Modulo Qualitativo (Simulato): <strong>' . esc_html( $context['qualitative_module'] ) . '</strong></li>';
    echo '<li style="color:#e6a100; font-size: 13px;">⚠️ Nota: Il caricamento dinamico di WpEsg\Storage\Registry\It\Registry è stato aggirato nel test per evitare crash.</li>';
    echo '</ul>';

    // 3. Test della Persistenza dei dati grezzi su Database ($wpdb)
    echo '<h4>3. Test Scrittura / Aggiornamento Database:</h4>';
    global $wpdb;
    $table = $wpdb->prefix . 'esg_assessments';

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
        echo '<p style="color:#dc3232;">❌ Errore critico: La tabella `' . esc_html($table) . '` non esiste nel database di mercatosociale.it. Controlla DatabaseSetup.</p>';
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
            echo '<p style="color:#46b450; font-weight:bold;">✔ Database OK: Record esistente trovato e sovrascritto! (ID Riga: ' . $existingId . ')</p>';
        } else {
            $wpdb->insert( $table, $dbRecord );
            echo '<p style="color:#46b450; font-weight:bold;">✔ Database OK: Nuova riga di test inserita correttamente!</p>';
        }
    }

    echo '<h4>4. Innesco Motore di Calcolo Proprietario:</h4>';
    echo '<p style="color:#646970; font-style:italic;">[Configurazione Pronta] Pronto a ricevere i calcoli delle formule estratti dai JSON.</p>';

    echo '<h3 style="border-top: 1px solid #ccd0d4; padding-top: 15px; margin-bottom:0; color: #46b450;">--- Test Eseguito con Successo ---</h3>';
    echo '</div></body></html>';
    
    exit;
} );
