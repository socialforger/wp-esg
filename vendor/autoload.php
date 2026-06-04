<?php
/**
 * Custom Autoloader per Symfony ExpressionLanguage v8.2
 * Sostituisce l'autoloader di Composer per caricamento manuale.
 */

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function (string $class) {
    $prefix = 'Symfony\\Component\\ExpressionLanguage\\';
    $base_dir = __DIR__ . '/symfony/expression-language/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
