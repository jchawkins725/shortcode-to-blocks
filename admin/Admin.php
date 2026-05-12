<?php
namespace STBC\admin;

defined('ABSPATH') || exit;

class Admin {

    public function init() {
        add_action('admin_menu',            [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('add_meta_boxes',        [$this, 'add_metabox']);
    }

    /** Use saved post types from Settings; fallback to post/page */
    public static function allowed_post_types(): array {
        $opts  = Settings::get();
        $types = array_values(array_filter(array_map('sanitize_key', (array) ($opts['post_types'] ?? []))));
        if (empty($types)) {
            $types = ['post', 'page'];
        }
        return apply_filters('stbc_allowed_post_types', $types, $opts);
    }

    public function register_menu() {
        $cap = Settings::required_capability();

        add_menu_page(
            __('Shortcode → Blocks', 'shortcode-to-blocks'),
            __('Shortcode → Blocks', 'shortcode-to-blocks'),
            $cap,
            STBC_SLUG,
            [$this, 'render_dashboard'],
            'data:image/svg+xml;base64,' . base64_encode( file_get_contents( STBC_PATH . 'assets/icon.svg' ) ),
            58
        );

        add_submenu_page(STBC_SLUG, __('Dashboard', 'shortcode-to-blocks'), __('Dashboard', 'shortcode-to-blocks'), $cap, STBC_SLUG, [$this, 'render_dashboard']);
        add_submenu_page(STBC_SLUG, __('Settings', 'shortcode-to-blocks'), __('Settings', 'shortcode-to-blocks'), 'manage_options', STBC_SLUG . '-settings', [Settings::class, 'render_settings_page']);

        // Allow Pro to register additional menu pages
        do_action('stbc_register_admin_menus', STBC_SLUG, $cap);
    }

    public function enqueue($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
        $screen = get_current_screen();
        if (! $screen || ! in_array($screen->post_type, self::allowed_post_types(), true)) return;
        if (! current_user_can(Settings::required_capability())) return;
        $is_block = method_exists($screen, 'is_block_editor') && $screen->is_block_editor();

        if ($is_block) {
            $rel_ui = 'assets/js/editor-ui.js';
            $ver_ui = file_exists(STBC_PATH . $rel_ui) ? filemtime(STBC_PATH . $rel_ui) : STBC_VERSION;
            wp_enqueue_script(
                'stbc-editor-ui',
                STBC_URL . $rel_ui,
                ['wp-plugins','wp-edit-post','wp-element','wp-components','wp-data','wp-api-fetch','wp-notices','wp-core-data','wp-block-editor', 'wp-i18n'],
                $ver_ui,
                true
            );
            wp_set_script_translations(
                'stbc-editor-ui',
                'shortcode-to-blocks',
                STBC_PATH . 'languages'
            );
            $boot_handle = 'stbc-editor-ui';
        } else {
            $rel = 'assets/js/converter.js';
            $ver = file_exists(STBC_PATH . $rel) ? filemtime(STBC_PATH . $rel) : STBC_VERSION;
            wp_register_script('stbc-admin', STBC_URL . $rel, ['jquery', 'wp-i18n'], $ver, true);
            wp_localize_script('stbc-admin', 'stbcConvert', [
                'ajaxUrl'     => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('stbc_convert_nonce'),
                'editUrlBase' => admin_url('post.php?post='),
            ]);
            wp_enqueue_script('stbc-admin');
            wp_set_script_translations(
                'stbc-admin',
                'shortcode-to-blocks',
                STBC_PATH . 'languages'
            );
            $boot_handle = 'stbc-admin';
        }

        global $post;
        $has_backup = $post ? (bool) get_post_meta($post->ID, '_stbp_original_content', true) : false;
        $has_vc     = $post ? \STBC\core\Detector::has_vc((string) $post->post_content) : false;
        $is_pro     = defined('STBP_VERSION');

        wp_add_inline_script(
            $boot_handle,
            'window.STBC_BOOT = ' . wp_json_encode([
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('stbc_convert_nonce'),
                'hasBackup' => $has_backup,
                'hasVC'     => $has_vc,
                'isPro'     => $is_pro,
            ]) . ';',
            'before'
        );

        $css = 'assets/css/admin.css';
        if (file_exists(STBC_PATH . $css)) {
            wp_enqueue_style('stbc-admin', STBC_URL . $css, [], filemtime(STBC_PATH . $css));
        }
    }

    private static function is_block_editor_screen(): bool {
        if (! function_exists('get_current_screen')) return false;
        $screen = get_current_screen();
        return $screen && method_exists($screen, 'is_block_editor') && $screen->is_block_editor();
    }

    public function add_metabox() {
        if (! current_user_can(Settings::required_capability())) return;
        if (self::is_block_editor_screen()) return;
        foreach (self::allowed_post_types() as $type) {
            add_meta_box('stbc_box', __('Convert Shortcodes to Blocks', 'shortcode-to-blocks'), [$this, 'render_metabox'], $type, 'side');
        }
    }

    public function render_metabox(\WP_Post $post) {
        if (! current_user_can(Settings::required_capability())) return;
        $has_backup = (bool) get_post_meta($post->ID, '_stbp_original_content', true);
        $has_vc     = \STBC\core\Detector::has_vc((string) $post->post_content);
        $mode       = $has_vc ? 'convert' : ($has_backup ? 'revert' : null);
        $edit_url = admin_url('post.php?post=' . (int) $post->ID . '&action=edit&classic-editor__forget');
        if ($mode === 'convert') {
            echo '<button type="button" class="button button-primary" id="stbc-convert-button" data-post-id="' . esc_attr($post->ID) . '" data-edit-url="' . esc_url($edit_url) . '">' . esc_html__('Convert to blocks', 'shortcode-to-blocks') . '</button>';
        } elseif ($mode === 'revert') {
            echo ' <button type="button" class="button" id="stbc-revert-button" data-post-id="' . esc_attr($post->ID) . '">' . esc_html__('Revert to backup', 'shortcode-to-blocks') . '</button>';
        }
        wp_nonce_field('stbc_convert_nonce', 'stbc_convert_nonce_field');
    }

    public function render_dashboard() {
        self::render_tabs(STBC_SLUG);
        $view = apply_filters('stbc_dashboard_view', STBC_PATH . 'admin/views/dashboard.php');
        include $view;
    }

    /**
     * Render tabbed navigation. Pro hooks in via filter to add more tabs.
     */
    public static function render_tabs(string $active = ''): void {
        $base_slug = defined('STBC_SLUG') ? STBC_SLUG : 'shortcode-to-blocks';

        $pages = [
            $base_slug              => [__('Dashboard', 'shortcode-to-blocks'), admin_url('admin.php?page=' . $base_slug)],
            $base_slug . '-settings' => [__('Settings', 'shortcode-to-blocks'), admin_url('admin.php?page=' . $base_slug . '-settings')],
        ];

        // Allow Pro to add tabs
        $pages = apply_filters('stbc_admin_tabs', $pages, $base_slug);

        if ($active === '' && isset($_GET['page'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- menu page slug, no data processing
            $active = sanitize_key((string) $_GET['page']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        echo '<div class="wrap">';
        echo '<h1 class="screen-reader-text">' . esc_html__('Shortcode to Blocks', 'shortcode-to-blocks') . '</h1>';
        echo '<h2 class="nav-tab-wrapper stbc-nav-tabs" style="margin-top:12px;">';

        foreach ($pages as $slug => [$label, $url]) {
            $cssClass = ($slug === $active) ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a class="' . esc_attr($cssClass) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }

        echo '</h2>';
        echo '</div>';
    }
}
