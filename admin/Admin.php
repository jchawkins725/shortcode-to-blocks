<?php
namespace STB\admin;

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
        return apply_filters('stb_allowed_post_types', $types, $opts);
    }

    public function register_menu() {
        $cap = Settings::required_capability();

        add_menu_page(
            __('Shortcode → Blocks', 'shortcode-to-blocks'),
            __('Shortcode → Blocks', 'shortcode-to-blocks'),
            $cap,
            STB_SLUG,
            [$this, 'render_dashboard'],
            'data:image/svg+xml;base64,' . base64_encode( file_get_contents( STB_PATH . 'assets/icon.svg' ) ),
            58
        );

        add_submenu_page(STB_SLUG, __('Dashboard', 'shortcode-to-blocks'), __('Dashboard', 'shortcode-to-blocks'), $cap, STB_SLUG, [$this, 'render_dashboard']);
        add_submenu_page(STB_SLUG, __('Settings', 'shortcode-to-blocks'), __('Settings', 'shortcode-to-blocks'), 'manage_options', STB_SLUG . '-settings', [Settings::class, 'render_settings_page']);

        // Allow Pro to register additional menu pages
        do_action('stb_register_admin_menus', STB_SLUG, $cap);
    }

    public function enqueue($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
        $screen = get_current_screen();
        if (! $screen || ! in_array($screen->post_type, self::allowed_post_types(), true)) return;
        if (! current_user_can(Settings::required_capability())) return;
        $is_block = method_exists($screen, 'is_block_editor') && $screen->is_block_editor();

        if ($is_block) {
            $rel_ui = 'assets/js/editor-ui.js';
            $ver_ui = file_exists(STB_PATH . $rel_ui) ? filemtime(STB_PATH . $rel_ui) : STB_VERSION;
            wp_enqueue_script(
                'stb-editor-ui',
                STB_URL . $rel_ui,
                ['wp-plugins','wp-edit-post','wp-element','wp-components','wp-data','wp-api-fetch','wp-notices','wp-core-data','wp-block-editor', 'wp-i18n'],
                $ver_ui,
                true
            );
            wp_set_script_translations(
                'stb-editor-ui',
                'shortcode-to-blocks',
                STB_PATH . 'languages'
            );
            $boot_handle = 'stb-editor-ui';
        } else {
            $rel = 'assets/js/converter.js';
            $ver = file_exists(STB_PATH . $rel) ? filemtime(STB_PATH . $rel) : STB_VERSION;
            wp_register_script('stb-admin', STB_URL . $rel, ['jquery', 'wp-i18n'], $ver, true);
            wp_localize_script('stb-admin', 'stbConvert', [
                'ajaxUrl'     => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('stb_convert_nonce'),
                'editUrlBase' => admin_url('post.php?post='),
            ]);
            wp_enqueue_script('stb-admin');
            wp_set_script_translations(
                'stb-admin',
                'shortcode-to-blocks',
                STB_PATH . 'languages'
            );
            $boot_handle = 'stb-admin';
        }

        global $post;
        $has_backup = $post ? (bool) get_post_meta($post->ID, '_stbp_original_content', true) : false;
        $has_vc     = $post ? \STB\core\Detector::has_vc((string) $post->post_content) : false;
        $is_pro     = defined('STBP_VERSION');

        wp_add_inline_script(
            $boot_handle,
            'window.STB_BOOT = ' . wp_json_encode([
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('stb_convert_nonce'),
                'hasBackup' => $has_backup,
                'hasVC'     => $has_vc,
                'isPro'     => $is_pro,
            ]) . ';',
            'before'
        );

        $css = 'assets/css/admin.css';
        if (file_exists(STB_PATH . $css)) {
            wp_enqueue_style('stb-admin', STB_URL . $css, [], filemtime(STB_PATH . $css));
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
            add_meta_box('stb_box', __('Convert Shortcodes to Blocks', 'shortcode-to-blocks'), [$this, 'render_metabox'], $type, 'side');
        }
    }

    public function render_metabox(\WP_Post $post) {
        if (! current_user_can(Settings::required_capability())) return;
        $edit_url = admin_url('post.php?post=' . (int) $post->ID . '&action=edit&classic-editor__forget');
        echo '<button type="button" class="button button-primary" id="stb-convert-button" data-post-id="' . esc_attr($post->ID) . '" data-edit-url="' . esc_url($edit_url) . '">' . esc_html__('Convert content', 'shortcode-to-blocks') . '</button>';
        if (get_post_meta($post->ID, '_stbp_original_content', true)) {
            echo ' <button type="button" class="button" id="stb-revert-button" data-post-id="' . esc_attr($post->ID) . '">' . esc_html__('Revert', 'shortcode-to-blocks') . '</button>';
        }
        wp_nonce_field('stb_convert_nonce', 'stb_convert_nonce_field');
    }

    public function render_dashboard() {
        self::render_tabs(STB_SLUG);
        $view = apply_filters('stb_dashboard_view', STB_PATH . 'admin/views/dashboard.php');
        include $view;
    }

    /**
     * Render tabbed navigation. Pro hooks in via filter to add more tabs.
     */
    public static function render_tabs(string $active = ''): void {
        $base_slug = defined('STB_SLUG') ? STB_SLUG : 'shortcode-to-blocks';

        $pages = [
            $base_slug              => [__('Dashboard', 'shortcode-to-blocks'), admin_url('admin.php?page=' . $base_slug)],
            $base_slug . '-settings' => [__('Settings', 'shortcode-to-blocks'), admin_url('admin.php?page=' . $base_slug . '-settings')],
        ];

        // Allow Pro to add tabs
        $pages = apply_filters('stb_admin_tabs', $pages, $base_slug);

        if ($active === '' && isset($_GET['page'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- menu page slug, no data processing
            $active = sanitize_key((string) $_GET['page']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        echo '<div class="wrap">';
        echo '<h1 class="screen-reader-text">' . esc_html__('Shortcode to Blocks Converter', 'shortcode-to-blocks') . '</h1>';
        echo '<h2 class="nav-tab-wrapper stb-nav-tabs" style="margin-top:12px;">';

        foreach ($pages as $slug => [$label, $url]) {
            $cssClass = ($slug === $active) ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a class="' . esc_attr($cssClass) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }

        echo '</h2>';
        echo '</div>';
    }
}
