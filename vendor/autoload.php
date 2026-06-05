<?php
/**
 * WP-ESG Manual Ecosystem Autoloader
 */

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function ($class) {

    $plugin_prefix = 'WpEsg\\';

    $plugin_base_dir =
        dirname(__DIR__) . '/includes/';

    if (strpos($class, $plugin_prefix) !== 0) {
        return;
    }

    $relative_class = substr(
        $class,
        strlen($plugin_prefix)
    );

    $file =
        $plugin_base_dir .
        str_replace('\\', '/', $relative_class) .
        '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
