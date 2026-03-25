<?php
/**
 * Uninstall cleanup for Shortcode to Blocks Converter (Free).
 */

if (! defined('WP_UNINSTALL_PLUGIN')) exit;

$stb_meta_keys = [
    '_stbp_original_content',
    '_stbp_original_content_ts',
    '_stbp_converted',
    '_stbp_converted_ts',
    '_stbp_has_vc',
];

function stb_should_retain_data() {
    return (bool) get_option('stb_retain_data_on_uninstall', true);
}

function stb_delete_options_and_transients() {
    global $wpdb;
    $like  = $wpdb->esc_like('stb_') . '%';
    $table = $wpdb->options;
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE option_name LIKE %s", $like));
    $t_like = '%' . $wpdb->esc_like('stb_') . '%';
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE option_name LIKE %s", '_transient_' . $t_like));
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE option_name LIKE %s", '_transient_timeout_' . $t_like));
}

function stb_delete_post_meta_keys(array $keys) {
    foreach ($keys as $key) {
        delete_post_meta_by_key($key);
    }
}

function stb_purge_blog(array $meta_keys) {
    stb_delete_options_and_transients();
    stb_delete_post_meta_keys($meta_keys);
}

if (stb_should_retain_data()) return;

if (is_multisite()) {
    $site_ids = get_sites(['fields' => 'ids']);
    foreach ($site_ids as $site_id) {
        switch_to_blog((int) $site_id);
        stb_purge_blog($stb_meta_keys);
        restore_current_blog();
    }
} else {
    stb_purge_blog($stb_meta_keys);
}
