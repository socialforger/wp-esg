<?php
/**
 * WP-ESG Manual Ecosystem Autoloader
 * * Gestisce il caricamento automatico PSR-4 sia per le dipendenze di Symfony 
 * (rispettando il typo della cartella 'symphony/'), sia per le classi native del plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

spl_autoload_register(function ($class) {
    
    // 1. NAMESPACE PRINCIPALE PLUGIN: WpEsg\ -> /includes/
    $plugin_prefix = 'WpEsg\\';
    $plugin_base_dir = dirname(__DIR__) . '/includes/';

    if (strpos($class, $plugin_prefix) === 0) {
        $relative_class = substr($class, strlen($plugin_prefix));
        $file = $plugin_base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // 2. DIPENDENZA SYMFONY: Symfony\Component\ExpressionLanguage\ -> /vendor/symphony/...
    $symfony_prefix = 'Symfony\\Component\\ExpressionLanguage\\';
    // Bloccato su 'symphony' con la 'h' come da tua struttura attuale su disco
    $symfony_base_dir = __DIR__ . '/symphony/expression-language/';

    if (strpos($class, $symfony_prefix) === 0) {
        $relative_class = substr($class, strlen($symfony_prefix));
        $file = $symfony_base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
