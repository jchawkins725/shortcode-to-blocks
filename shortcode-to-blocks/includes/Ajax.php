<?php
namespace STB\includes;

use STB\admin\Admin;
use STB\core\Detector;

defined('ABSPATH') || exit;

class Ajax {

    public function init() {
        add_action('wp_ajax_stb_convert', [$this, 'convert']);
        add_action('wp_ajax_stb_revert',  [$this, 'revert']);
    }

    private function validate_request(): int {
        if (
            ! isset($_POST['stb_convert_nonce_field']) ||
            ! check_ajax_referer('stb_convert_nonce', 'stb_convert_nonce_field', false)
        ) {
            wp_send_json_error('invalid or missing nonce', 403);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if (! $post_id) wp_send_json_error('invalid post id', 400);

        $post = get_post($post_id);
        if (! $post) wp_send_json_error('post not found', 404);

        if (! in_array($post->post_type, Admin::allowed_post_types(), true)) {
            wp_send_json_error('disallowed post type', 403);
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            wp_send_json_error('cannot process autosave/revision', 400);
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error('insufficient permissions', 403);
        }

        return $post_id;
    }

    public function convert() {
        $post_id = $this->validate_request();
        $post    = get_post($post_id);

        Detector::flag_post($post_id, (string) $post->post_content);
        if (! Detector::has_vc((string) $post->post_content)) {
            delete_transient('stbp_dash_counts');
            wp_send_json_success(['message' => 'no WPBakery content found; nothing to convert'], 200);
        }

        // Backup original content (only once)
        if (! get_post_meta($post_id, '_stbp_original_content', true)) {
            update_post_meta($post_id, '_stbp_original_content', $post->post_content);
            update_post_meta($post_id, '_stbp_original_content_ts', time());
        }

        try {
            // Use filterable converter class so Pro can substitute its own
            $converter_class = apply_filters('stb_converter_class', '\\STB\\core\\Converter');
            $converter       = new $converter_class();
            $converted       = $converter->convert_vc_shortcodes_recursive($post->post_content);

            if ($converted !== $post->post_content) {
                $tpl  = $this->get_current_template_slug($post_id);
                $args = [
                    'ID'            => $post_id,
                    'post_content'  => $converted,
                    'page_template' => $tpl,
                    'post_type'     => get_post_type($post_id),
                ];
                $res = wp_update_post($args, true);
                if (is_wp_error($res)) {
                    wp_send_json_error('failed to update post: ' . $res->get_error_message(), 500);
                }
            }

            // Allow Pro to log the conversion
            do_action('stb_post_converted', $post_id);

            delete_transient('stbp_dash_counts');
            wp_send_json_success(['message' => 'content converted'], 200);

        } catch (\Throwable $e) {
            do_action('stb_convert_error', $post_id, $e->getMessage());
            delete_transient('stbp_dash_counts');
            wp_send_json_error('conversion error: ' . $e->getMessage(), 500);
        }
    }

    public function revert() {
        $post_id = $this->validate_request();

        $original = get_post_meta($post_id, '_stbp_original_content', true);
        if ($original === '' || $original === null) {
            wp_send_json_error('no backup found', 404);
        }

        $tpl  = $this->get_current_template_slug($post_id);
        $args = [
            'ID'            => $post_id,
            'post_content'  => $original,
            'page_template' => $tpl,
            'post_type'     => get_post_type($post_id),
        ];
        $res = wp_update_post($args, true);
        if (is_wp_error($res)) {
            wp_send_json_error('failed to update post: ' . $res->get_error_message(), 500);
        }

        delete_post_meta($post_id, '_stbp_original_content');
        delete_post_meta($post_id, '_stbp_original_content_ts');
        delete_post_meta($post_id, '_stbp_converted');
        delete_post_meta($post_id, '_stbp_converted_ts');
        delete_post_meta($post_id, '_stbp_batch_id');

        $post = get_post($post_id);
        Detector::flag_post($post_id, (string) $post->post_content);

        do_action('stb_post_reverted', $post_id);

        delete_transient('stbp_dash_counts');
        wp_send_json_success(['message' => 'content reverted'], 200);
    }

    private function get_current_template_slug(int $post_id): string {
        $tpl = get_page_template_slug($post_id);
        if (! is_string($tpl) || $tpl === '') return 'default';

        $post = get_post($post_id);
        if ($post instanceof \WP_Post) {
            $registered = wp_get_theme()->get_page_templates($post);
            if ($tpl !== 'default' && ! in_array($tpl, $registered, true)) {
                return 'default';
            }
        }
        return $tpl;
    }
}
