<?php
/**
 * Plugin Name: Shortcode to Blocks
 * Description: Convert WPBakery Page Builder content to native Gutenberg blocks. Basic single-post conversion for common shortcodes.
 * Version: 1.0.1
 * Author: Jonathan Hawkins
 * Author URI: https://shortcodetoblocks.com/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shortcode-to-blocks
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('STBC_VERSION', '1.0.1');
define('STBC_FILE', __FILE__);
define('STBC_PATH', plugin_dir_path(__FILE__));
define('STBC_URL', plugin_dir_url(__FILE__));
define('STBC_SLUG', 'shortcode-to-blocks');

// Lightweight PSR-4 autoloader
spl_autoload_register(function ($class) {
    $prefix   = 'STBC\\';
    $base_dir = STBC_PATH;
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $base_dir . $rel . '.php';
    if (file_exists($file)) require_once $file;
});

// Settings link on Plugins screen
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $url = admin_url('admin.php?page=' . STBC_SLUG . '-settings');
    array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'shortcode-to-blocks') . '</a>');
    return $links;
});

// Boot
add_action('plugins_loaded', function () {
    if (is_admin()) {
        (new STBC\admin\Admin())->init();
        (new STBC\admin\Settings())->init();
        (new STBC\core\Hooks())->init();
        (new STBC\includes\Ajax())->init();
    }
}, 9); // priority 9 so Pro (default 10) loads after

register_activation_hook(STBC_FILE, function () {
    // Nothing to install for free version (no logs table)
});

register_deactivation_hook(STBC_FILE, function () {
    // Clean up if needed
});
