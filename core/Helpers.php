<?php
namespace STBC\core;

defined('ABSPATH') || exit;

class Helpers {
    public static function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if (function_exists('wp_trigger_error')) {
                wp_trigger_error(
                    '[stbc][' . $level . ']',
                    is_string($message) ? $message : wp_json_encode($message),
                    E_USER_NOTICE
                );
            }
        }
    }

    public static function format_admin_datetime($value, $format = ''): string {
        if ($value === '' || $value === null) return '—';

        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if (! $ts) return '—';

        if ($format === '' || $format === null) {
            $date_format = get_option('date_format') ?: 'm/d/Y';
            $time_format = get_option('time_format') ?: 'g:i a';
            $date_format = str_replace('F', 'M', $date_format);
            $format      = $date_format . ' ' . $time_format;
        }

        return wp_date($format, $ts, wp_timezone());
    }
}
