<?php
/**
 * Plugin Name: Shortcode to Blocks Converter
 * Description: Convert WPBakery Page Builder content to native Gutenberg blocks. Basic single-post conversion for common shortcodes.
 * Version: 1.0.0
 * Author: Jonathan Hawkins
 * Author URI: https://www.jonathanchawkins.com/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shortcode-to-blocks
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('STB_VERSION', '1.0.0');
define('STB_FILE', __FILE__);
define('STB_PATH', plugin_dir_path(__FILE__));
define('STB_URL', plugin_dir_url(__FILE__));
define('STB_SLUG', 'shortcode-to-blocks');

// Lightweight PSR-4 autoloader
spl_autoload_register(function ($class) {
    $prefix   = 'STB\\';
    $base_dir = STB_PATH;
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $base_dir . $rel . '.php';
    if (file_exists($file)) require_once $file;
});

// Settings link on Plugins screen
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $url = admin_url('admin.php?page=' . STB_SLUG . '-settings');
    array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'shortcode-to-blocks') . '</a>');
    return $links;
});

// Boot
add_action('plugins_loaded', function () {
    load_plugin_textdomain('shortcode-to-blocks', false, dirname(plugin_basename(__FILE__)) . '/languages');

    if (is_admin()) {
        (new STB\admin\Admin())->init();
        (new STB\admin\Settings())->init();
        (new STB\core\Hooks())->init();
        (new STB\includes\Ajax())->init();
    }
}, 9); // priority 9 so Pro (default 10) loads after

register_activation_hook(STB_FILE, function () {
    // Nothing to install for free version (no logs table)
});

register_deactivation_hook(STB_FILE, function () {
    // Clean up if needed
});
