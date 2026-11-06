<?php
/**
 * Plugin Name: firefly-distill
 * Plugin URI: https://quatroventos.com.br
 * Description: Ferramenta interna Firefly: HTML estático por host, mapeamento de LPs e fila para a nuvem.
 * Version: 0.1.0
 * Author: Gabriel Roloff / Quatro Ventos
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: firefly-distill
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('FFD_VERSION', '0.1.0');
define('FFD_FILE', __FILE__);
define('FFD_PATH', plugin_dir_path(__FILE__));
define('FFD_URL', plugin_dir_url(__FILE__));
define('FFD_SCHEMA_VERSION', '1');
define('FFD_REST_NS', 'firefly-distill/v1');

$ffdAutoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($ffdAutoload)) {
    require_once $ffdAutoload;
}

register_activation_hook(__FILE__, static function (): void {
    \FireflyDistill\Install\Activator::activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    \FireflyDistill\Install\Activator::deactivate();
});

add_action('plugins_loaded', static function (): void {
    (new \FireflyDistill\Plugin())->boot();
});
