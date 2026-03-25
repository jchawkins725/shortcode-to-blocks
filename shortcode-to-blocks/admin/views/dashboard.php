<?php
defined('ABSPATH') || exit;

global $wpdb;

$opts       = \STB\admin\Settings::get();
$post_types = $opts['post_types'] ?? ['post', 'page'];

// Count VC posts
$types_sql    = implode(',', array_fill(0, count($post_types), '%s'));
$total_vc     = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT p.ID)
     FROM {$wpdb->posts} p
     INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
     WHERE p.post_type IN ($types_sql)
       AND p.post_status = 'publish'
       AND pm.meta_key = '_stbp_has_vc'
       AND pm.meta_value = '1'",
    ...$post_types
));

$total_backed = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT p.ID)
     FROM {$wpdb->posts} p
     INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
     WHERE p.post_type IN ($types_sql)
       AND p.post_status = 'publish'
       AND pm.meta_key = '_stbp_original_content'
       AND pm.meta_value != ''",
    ...$post_types
));

$is_pro = defined('STBP_VERSION');
?>
<div class="wrap" style="max-width:800px;">
    <h1><?php esc_html_e('Shortcode to Blocks — Dashboard', 'shortcode-to-blocks'); ?></h1>

    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin:24px 0;">
        <div style="background:#fff; padding:24px 16px; border:1px solid #ddd; border-radius:4px; text-align:center;">
            <div style="font-size:36px; line-height:1; font-weight:bold; color:#0073aa; margin-bottom:8px;"><?php echo esc_html($total_vc); ?></div>
            <div style="font-size:13px; color:#666;"><?php esc_html_e('Posts with WPBakery', 'shortcode-to-blocks'); ?></div>
        </div>
        <div style="background:#fff; padding:24px 16px; border:1px solid #ddd; border-radius:4px; text-align:center;">
            <div style="font-size:36px; line-height:1; font-weight:bold; color:#46b450; margin-bottom:8px;"><?php echo esc_html($total_backed); ?></div>
            <div style="font-size:13px; color:#666;"><?php esc_html_e('Converted (with backup)', 'shortcode-to-blocks'); ?></div>
        </div>
        <div style="background:#fff; padding:24px 16px; border:1px solid #ddd; border-radius:4px; text-align:center;">
            <div style="font-size:14px; line-height:1.4; font-weight:600; color:#444; margin-bottom:8px;"><?php
                foreach ($post_types as $i => $pt) {
                    $obj = get_post_type_object($pt);
                    echo esc_html($obj ? $obj->labels->singular_name : $pt);
                    if ($i < count($post_types) - 1) echo '<br>';
                }
            ?></div>
            <div style="font-size:13px; color:#666;"><?php esc_html_e('Selected Post Types', 'shortcode-to-blocks'); ?></div>
        </div>
    </div>

    <div style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:4px; margin-bottom:24px;">
        <h2 style="margin-top:0;"><?php esc_html_e('How to Convert', 'shortcode-to-blocks'); ?></h2>
        <ol>
            <li><?php esc_html_e('Open any post or page in the editor.', 'shortcode-to-blocks'); ?></li>
            <li><?php esc_html_e('In the sidebar, find the "Shortcode → Blocks" panel.', 'shortcode-to-blocks'); ?></li>
            <li><?php esc_html_e('Click "Convert content" to transform WPBakery shortcodes into Gutenberg blocks.', 'shortcode-to-blocks'); ?></li>
            <li><?php esc_html_e('If the result isn\'t right, click "Revert" to restore the original content.', 'shortcode-to-blocks'); ?></li>
        </ol>
    </div>

    <?php if (! $is_pro) : ?>
    <div style="background:#f0f6fc; padding:20px; border:1px solid #c3c4c7; border-left:4px solid #0073aa; border-radius:4px;">
        <h3 style="margin-top:0;"><?php esc_html_e('Upgrade to Pro', 'shortcode-to-blocks'); ?></h3>
        <p><?php esc_html_e('The free version converts basic shortcodes one page at a time. Upgrade to Pro for:', 'shortcode-to-blocks'); ?></p>
        <ul style="list-style:disc; margin-left:20px;">
            <li><?php esc_html_e('Batch conversion — convert hundreds of pages at once', 'shortcode-to-blocks'); ?></li>
            <li><?php esc_html_e('All 27+ WPBakery shortcode converters (galleries, tabs, accordions, CTAs, icons, video, and more)', 'shortcode-to-blocks'); ?></li>
            <li><?php esc_html_e('Bulk actions in the Posts/Pages list', 'shortcode-to-blocks'); ?></li>
            <li><?php esc_html_e('Activity logs and conversion history', 'shortcode-to-blocks'); ?></li>
            <li><?php esc_html_e('Tools page with scanning, exporting, and backup management', 'shortcode-to-blocks'); ?></li>
        </ul>
        <p>
            <a href="https://www.jonathanchawkins.com/shortcode-to-blocks-pro/" class="button button-primary" target="_blank">
                <?php esc_html_e('Learn More About Pro', 'shortcode-to-blocks'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
</div>
