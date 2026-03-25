<?php
namespace STB\core;

defined('ABSPATH') || exit;

class Detector {
    /** Fast/cheap detection: quick strpos, then a light regex */
    public static function has_vc(string $content): bool {
        if ($content === '') return false;
        if (strpos($content, '[vc_') === false) return false;
        return (bool) preg_match('/\[(vc_[a-z0-9_]+)\b/i', $content);
    }

    /** Set/refresh per-post flag `_stbp_has_vc` to '1' or '0' */
    public static function flag_post(int $post_id, ?string $content = null): void {
        if ($content === null) {
            $post = get_post($post_id);
            if (! $post) return;
            $content = (string) $post->post_content;
        }
        $has = self::has_vc($content) ? '1' : '0';
        update_post_meta($post_id, '_stbp_has_vc', $has);
    }
}
