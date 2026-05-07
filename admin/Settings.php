<?php
namespace STB\admin;

defined('ABSPATH') || exit;

class Settings {

    public static function defaults(): array {
        $defaults = [
            'post_types'               => ['post', 'page'],
            'required_cap'             => 'edit_others_posts',
            'row_width'                => 'content',
            'retain_data_on_uninstall' => true,
        ];
        return apply_filters('stb_settings_defaults', $defaults);
    }

    public static function get(): array {
        return wp_parse_args(get_option('stb_options', []), self::defaults());
    }

    public static function required_capability(): string {
        $o   = self::get();
        $cap = $o['required_cap'] ?: 'edit_others_posts';
        return apply_filters('stb_required_capability', $cap, $o);
    }

    public static function tools_capability() {
        return apply_filters('stb_tools_capability', 'manage_options');
    }

    public function init() {
        add_action('admin_init', [$this, 'register']);
    }

    public function register() {
        register_setting('stb_settings', 'stb_options', [
            'type'              => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
        ]);

        $page = STB_SLUG . '-settings';

        add_settings_section('stb_general', __('General', 'shortcode-to-blocks'), '__return_false', $page);
        add_settings_section('stb_uninstall', __('Uninstall', 'shortcode-to-blocks'), function () {
            echo '<p>' . esc_html__('Choose what happens to plugin data when the plugin is deleted.', 'shortcode-to-blocks') . '</p>';
        }, $page);

        add_settings_field('post_types', __('Post types', 'shortcode-to-blocks'), [self::class, 'field_post_types'], $page, 'stb_general');
        add_settings_field('required_cap', __('Required capability', 'shortcode-to-blocks'), [self::class, 'field_required_cap'], $page, 'stb_general');
        add_settings_field('row_width', __('Default row width', 'shortcode-to-blocks'), [self::class, 'field_row_width'], $page, 'stb_general');

        add_settings_field('retain_data_on_uninstall', __('Keep data on uninstall', 'shortcode-to-blocks'), [self::class, 'field_retain_data'], $page, 'stb_uninstall');

        // Allow Pro to add settings sections/fields
        do_action('stb_register_settings', $page);
    }

    public static function sanitize($in) {
        $out = self::get();

        $allowed  = get_post_types(['public' => true], 'names');
        $selected = array_values(array_intersect((array) ($in['post_types'] ?? []), $allowed));
        $out['post_types'] = !empty($selected) ? $selected : ['post', 'page'];

        $caps = ['manage_options', 'edit_others_posts', 'edit_posts'];
        $out['required_cap'] = in_array(($in['required_cap'] ?? ''), $caps, true) ? $in['required_cap'] : 'edit_others_posts';

        $row_width = isset($in['row_width']) ? sanitize_key($in['row_width']) : '';
        $out['row_width'] = in_array($row_width, ['content', 'wide', 'full'], true) ? $row_width : 'content';

        $retain = !empty($in['retain_data_on_uninstall']);
        $out['retain_data_on_uninstall'] = $retain;
        update_option('stb_retain_data_on_uninstall', $retain);

        // Allow Pro to sanitize additional fields
        $out = apply_filters('stb_settings_sanitize', $out, $in);

        return $out;
    }

    public static function field_post_types() {
        $opts = self::get();
        $all  = get_post_types(['public' => true], 'objects');
        foreach ($all as $name => $obj) {
            printf(
                '<label style="display:block"><input type="checkbox" name="stb_options[post_types][]" value="%s" %s> %s</label>',
                esc_attr($name),
                checked(in_array($name, $opts['post_types'], true), true, false),
                esc_html($obj->labels->name)
            );
        }
        echo '<p class="description">' . esc_html__('Select which post types show the Convert/Revert tools.', 'shortcode-to-blocks') . '</p>';
    }

    public static function field_required_cap() {
        $opts    = self::get();
        $choices = [
            'manage_options'    => __('Administrators', 'shortcode-to-blocks'),
            'edit_others_posts' => __('Editors or above', 'shortcode-to-blocks'),
            'edit_posts'        => __('Authors or above', 'shortcode-to-blocks'),
        ];
        echo '<select name="stb_options[required_cap]">';
        foreach ($choices as $cap => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($cap), selected($opts['required_cap'], $cap, false), esc_html($label));
        }
        echo '</select>';
    }

    public static function field_row_width() {
        $opts    = self::get();
        $current = isset($opts['row_width']) ? $opts['row_width'] : 'content';
        $choices = [
            'content' => __('Content size', 'shortcode-to-blocks'),
            'wide'    => __('Wide size', 'shortcode-to-blocks'),
            'full'    => __('Full width', 'shortcode-to-blocks'),
        ];

        foreach ($choices as $value => $label) {
            printf(
                '<label style="display:block"><input type="radio" name="stb_options[row_width]" value="%s" %s> %s</label>',
                esc_attr($value),
                checked($current, $value, false),
                esc_html($label)
            );
        }

        echo '<p class="description">' . esc_html__('Choose whether converted rows default to the theme content width, Gutenberg wide width, or full width. WPBakery rows explicitly marked full width will still convert to full width.', 'shortcode-to-blocks') . '</p>';
    }

    public static function field_retain_data() {
        $opts  = self::get();
        $value = !empty($opts['retain_data_on_uninstall']);
        printf(
            '<label><input type="checkbox" name="stb_options[retain_data_on_uninstall]" value="1" %s> %s</label>',
            checked($value, true, false),
            esc_html__('Keep settings and post meta when uninstalling the plugin.', 'shortcode-to-blocks')
        );
    }

    public static function render_settings_page() {
        Admin::render_tabs((defined('STB_SLUG') ? STB_SLUG : 'shortcode-to-blocks') . '-settings');
        echo '<div class="wrap"><h1>' . esc_html__('Settings', 'shortcode-to-blocks') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('stb_settings');
        do_settings_sections(STB_SLUG . '-settings');
        submit_button();
        echo '</form></div>';
    }
}
