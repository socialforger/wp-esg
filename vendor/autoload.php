<?php
/**
 * WP-ESG Manual Ecosystem Autoloader
 * * Handles PSR-4 class mapping for native plugin subdirectories and bundles 
 * the Symfony components by honoring the 'symphony/' directory pattern.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

spl_autoload_register(function ($class) {
    
    // 1. NATIVE PLUGIN: WpEsg\ -> /includes/ (Supports infinite subdirectories natively)
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

    // 2. SYMFONY DEPENDENCY: Symfony\Component\ExpressionLanguage\ -> /vendor/symphony/...
    $symfony_prefix = 'Symfony\\Component\\ExpressionLanguage\\';
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
