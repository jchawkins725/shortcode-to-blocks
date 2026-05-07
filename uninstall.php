<?php
/**
 * Uninstall cleanup for Shortcode to Blocks (Free).
 */

if (! defined('WP_UNINSTALL_PLUGIN')) exit;

$stbc_meta_keys = [
    '_stbp_original_content',
    '_stbp_original_content_ts',
    '_stbp_converted',
    '_stbp_converted_ts',
    '_stbp_has_vc',
];

function stbc_should_retain_data() {
    return (bool) get_option('stbc_retain_data_on_uninstall', true);
}

function stbc_delete_options_and_transients() {
    global $wpdb;
    $like = $wpdb->esc_like('stbc_') . '%';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup
    $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", $like));
    $t_like = '%' . $wpdb->esc_like('stbc_') . '%';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", '_transient_' . $t_like));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", '_transient_timeout_' . $t_like));
}

function stbc_delete_post_meta_keys(array $keys) {
    foreach ($keys as $key) {
        delete_post_meta_by_key($key);
    }
}

function stbc_purge_blog(array $meta_keys) {
    stbc_delete_options_and_transients();
    stbc_delete_post_meta_keys($meta_keys);
}

if (stbc_should_retain_data()) return;

if (is_multisite()) {
    $stbc_site_ids = get_sites(['fields' => 'ids']);
    foreach ($stbc_site_ids as $stbc_site_id) {
        switch_to_blog((int) $stbc_site_id);
        stbc_purge_blog($stbc_meta_keys);
        restore_current_blog();
    }
} else {
    stbc_purge_blog($stbc_meta_keys);
}
