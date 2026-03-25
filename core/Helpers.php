<?php
namespace STB\core;

defined('ABSPATH') || exit;

class Helpers {
    public static function log($message, $level = 'info') {
        if (defined('STB_LOG') && STB_LOG) {
            error_log('[stb][' . $level . '] ' . (is_string($message) ? $message : wp_json_encode($message)));
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
