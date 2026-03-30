<?php
namespace STB\core;

defined('ABSPATH') || exit;

class Converter {

    /**
     * Basic shortcodes handled by the free version.
     * Pro extends this via get_supported_shortcodes().
     */
    private $basic_shortcodes = [
        'vc_row', 'vc_column', 'vc_column_text', 'vc_btn',
        'vc_raw_html', 'vc_empty_space', 'vc_single_image',
        'vc_custom_heading', 'vc_row_inner', 'vc_column_inner',
        'vc_separator',
    ];

    /**
     * Return the list of VC shortcodes this converter supports.
     * Pro overrides this to add more shortcodes.
     *
     * @return string[]
     */
    public function get_supported_shortcodes(): array {
        return apply_filters('stb_supported_shortcodes', $this->basic_shortcodes);
    }

    /**
     * Full recursive conversion entry point.
     */
    public function convert_vc_shortcodes_recursive(string $content): string {
        $content = $this->clean_shortcode_wrappers($content);

        $supported = $this->get_supported_shortcodes();
        $pattern   = get_shortcode_regex($supported);

        // Match Gutenberg blocks
        $gutenberg_block_regex = '/(<!--\s*wp:.*?-->.*?<!--\s*\/wp:[^>]*?-->)/s';

        // Split content into Gutenberg and non-Gutenberg parts
        $parts  = preg_split($gutenberg_block_regex, $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $output = '';

        foreach ($parts as $part) {
            if (preg_match($gutenberg_block_regex, $part)) {
                $output .= $part;
                continue;
            }

            $converted = preg_replace_callback('/' . $pattern . '/s', function ($matches) {
                $shortcode     = $matches[2];
                $attr_string   = $matches[3];
                $inner_content = $matches[5] ?? '';

                $attrs           = shortcode_parse_atts($attr_string);
                $converted_inner = $this->convert_vc_shortcodes_recursive($inner_content);
                $method          = 'convert_' . $shortcode;

                if (method_exists($this, $method)) {
                    return $this->$method($attrs, $converted_inner);
                }

                // Fallback: Gutenberg shortcode block
                $attr_str = '';
                if (is_array($attrs)) {
                    foreach ($attrs as $key => $value) {
                        $attr_str .= ' ' . $key . '="' . esc_attr($value) . '"';
                    }
                }
                $shortcode_str = '[' . $shortcode . $attr_str . ']' . $converted_inner . '[/' . $shortcode . ']';
                return "<!-- wp:shortcode -->\n$shortcode_str\n<!-- /wp:shortcode -->\n";
            }, $part);

            $output .= $this->wrap_non_vc_shortcodes($converted);
        }

        return $this->clean_up_converted_gutenberg_output($output);
    }

    /* ========= CLEANUP / NORMALIZATION ========= */

    public function clean_shortcode_wrappers(string $content): string {
        $content = preg_replace('/<p>\s*(\[\/?.*?\])\s*<\/p>/s', '$1', $content);
        $content = preg_replace('/<br ?\/?>\s*(\[\/?.*?\])/s', '$1', $content);
        $content = preg_replace('/<p>\s*(<!--\s*\/?wp:[^>]*?-->)\s*<\/p>/s', '$1', $content);
        $content = preg_replace('/>\s*<p>\s*</', '><', $content);
        $content = preg_replace('/>\s*<\/p>\s*</', '><', $content);
        return $content;
    }

    public function clean_up_converted_gutenberg_output(string $content): string {
        $supported = $this->get_supported_shortcodes();

        $parts = preg_split(
            '/(<!-- wp:shortcode -->.*?<!-- \/wp:shortcode -->)/s',
            $content, -1, PREG_SPLIT_DELIM_CAPTURE
        );

        $cleaned = '';
        foreach ($parts as $part) {
            if (preg_match('/^<!-- wp:shortcode -->/', $part)) {
                $cleaned .= $part;
            } else {
                $pattern = '/\[\/?(?:' . implode('|', array_map('preg_quote', $supported)) . ')[^\]]*\]/';
                $cleaned .= preg_replace($pattern, '', $part);
            }
        }
        $content = $cleaned;

        $content = preg_replace('/<p>(\s|&nbsp;|&#160;|&#xA0;)*<\/p>/', '', $content);
        $content = preg_replace('/<p>(\s*<!-- wp:.*?-->\s*)<\/p>/', '$1', $content);
        $content = preg_replace('/<p>(\s*<!-- \/wp:.*?-->\s*)<\/p>/', '$1', $content);
        $content = preg_replace('/(<!-- wp:[^>]+-->)[\s\r\n]*<\/p>/', '$1', $content);
        $content = preg_replace('/(<!-- \/wp:[^>]+-->)\s*<\/p>/i', '$1', $content);
        $content = preg_replace('/<p>\s*(<(section|div|script|h[1-6]|ul|ol|li|hr)[^>]*>)\s*<\/p>/i', '$1', $content);
        $content = preg_replace('/<p>\s*(<\/(section|div|script|h[1-6]|ul|ol|li)>)\s*<\/p>/i', '$1', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = preg_replace('/^\s+|\s+$/m', '', $content);

        $content = preg_replace_callback(
            '/(<!-- wp:paragraph[^>]*-->)(.*?)(<!-- \/wp:paragraph -->)/is',
            function ($m) {
                $open = $m[1]; $inner = $m[2]; $close = $m[3];
                if (preg_match('/<p[^>]*>/', $inner) && !preg_match('/<\/p>/', $inner)) {
                    $inner .= '</p>';
                }
                return $open . $inner . $close;
            },
            $content
        );

        $content = preg_replace(
            '/(?:\s|&nbsp;|&#160;|&#xA0;|\xC2\xA0)*<\/p>(?:\s|&nbsp;|&#160;|&#xA0;|\xC2\xA0)*(?=<!-- (?!\/wp:paragraph))/iu',
            '',
            $content
        );

        return trim($content);
    }

    /* ========= BASIC CONVERTERS ========= */

    // [vc_row] → group + optional columns
    public function convert_vc_row($attrs, string $inner_content): string {
        $class_parts = ['alignwide'];
        $id_attr     = '';

        if (!empty($attrs['full_width']) && $attrs['full_width'] !== 'default') {
            $class_parts = ['alignfull'];
        }

        $class_parts = array_merge($class_parts, $this->classes_from_el_class($attrs['el_class'] ?? null));

        if (!empty($attrs['el_id'])) {
            $id = preg_replace('/[^A-Za-z0-9\-_:.]/', '', $attrs['el_id']);
            if ($id !== '') $id_attr = ' id="' . esc_attr($id) . '"';
        }

        $group_class     = $this->compile_class_attr($class_parts);
        $converted_inner = $this->convert_vc_shortcodes_recursive($inner_content);

        preg_match_all('/<!-- wp:column\s*(\{.*?\})?\s*-->(.*?)<!-- \/wp:column -->/s', $converted_inner, $column_matches);
        $column_count = count($column_matches[0]);

        if (empty(trim($converted_inner))) return '';

        // Single full-width column heuristic
        if ($column_count === 1) {
            $meta_json    = trim($column_matches[1][0] ?? '');
            $column_inner = trim($column_matches[2][0]);
            $is_full_width = false;

            if ($meta_json === '') {
                $is_full_width = true;
            } else {
                $meta = json_decode($meta_json, true);
                if (!empty($meta['width']) && $meta['width'] === '100%') $is_full_width = true;
            }

            if ($is_full_width) {
                if (preg_match('/<div class="wp-block-column[^>]*>(.*?)<\/div>/s', $column_inner, $m)) {
                    $column_inner_stripped = trim($m[1]);
                } else {
                    $column_inner_stripped = $column_inner;
                }

                $align = (strpos($group_class, 'alignfull') !== false) ? 'full' : 'wide';
                return "<!-- wp:group {\"align\":\"$align\"} -->\n" .
                    '<div class="wp-block-group' . $group_class . '"' . $id_attr . ">\n" .
                    $this->convert_vc_column([], $column_inner_stripped, false) . "\n" .
                    "</div>\n" .
                    "<!-- /wp:group -->";
            }
        }

        $align = (strpos($group_class, 'alignfull') !== false) ? 'full' : 'wide';
        $out  = "<!-- wp:group {\"align\":\"$align\"} -->\n";
        $out .= '<div class="wp-block-group' . $group_class . '"' . $id_attr . ">\n";

        if ($column_count > 0) {
            $out .= "<!-- wp:columns -->\n";
            $out .= '<div class="wp-block-columns">' . "\n";
            $out .= $converted_inner . "\n";
            $out .= "</div>\n";
            $out .= "<!-- /wp:columns -->\n";
        } else {
            $out .= $converted_inner . "\n";
        }

        $out .= "</div>\n";
        $out .= "<!-- /wp:group -->";
        return $out;
    }

    // [vc_column]
    public function convert_vc_column($attrs, string $inner_content, bool $wrap = true): string {
        $extra_class  = [];
        $inline_style = '';
        $block_meta   = '';

        $width_map = [
            '1/1' => '100%', '1/2' => '50%', '1/3' => '33.3333%', '2/3' => '66.6666%',
            '1/4' => '25%',  '3/4' => '75%', '1/6' => '16.6666%', '5/6' => '83.3333%',
            '1/5' => '20%',  '2/5' => '40%',  '3/5' => '60%',  '4/5' => '80%',
            '1/8' => '12.5%','3/8' => '37.5%','5/8' => '62.5%','7/8' => '87.5%',
            '1/12'=> '8.3333%','5/12'=>'41.6666%','7/12'=>'58.3333%','11/12'=>'91.6666%',
        ];

        if (!empty($attrs['width']) && isset($width_map[$attrs['width']])) {
            $width_value  = $width_map[$attrs['width']];
            $inline_style = ' style="flex-basis: ' . $width_value . ';"';
            $block_meta   = '{"width":"' . esc_attr($width_value) . '"}';
        }

        $extra_class = array_merge($extra_class, $this->classes_from_el_class($attrs['el_class'] ?? null));
        $class_attr  = $this->compile_class_attr(array_merge(['wp-block-column'], $extra_class));

        $id_attr = '';
        if (!empty($attrs['el_id'])) {
            $id = preg_replace('/[^A-Za-z0-9\-_:.]/', '', $attrs['el_id']);
            if ($id !== '') $id_attr = ' id="' . esc_attr($id) . '"';
        }

        if (empty(trim($inner_content))) return '';
        if (!$wrap) return $inner_content;

        $out  = "<!-- wp:column " . $block_meta . " -->\n";
        $out .= '<div class="' . substr($class_attr, 1) . '"' . $inline_style . $id_attr . ">\n";
        $out .= $inner_content . "\n";
        $out .= "</div>\n";
        $out .= "<!-- /wp:column -->";
        return $out;
    }

    // [vc_row_inner]
    public function convert_vc_row_inner($attrs, string $inner_content): string {
        $classes     = $this->classes_from_el_class($attrs['el_class'] ?? null);
        $group_class = $classes ? ' ' . esc_attr(implode(' ', $classes)) : '';

        $converted_inner = $this->convert_vc_shortcodes_recursive($inner_content);

        preg_match_all('/<!-- wp:column\s*(\{.*?\})?\s*-->(.*?)<!-- \/wp:column -->/s', $converted_inner, $column_matches);
        $column_count = count($column_matches[0]);

        if (empty(trim($converted_inner))) return '';

        if ($column_count === 1) {
            $meta_json = trim($column_matches[1][0] ?? '');
            $is_full   = false;
            if (empty($meta_json)) {
                $is_full = true;
            } else {
                $meta = json_decode($meta_json, true);
                if (!empty($meta['width']) && $meta['width'] === '100%') $is_full = true;
            }
            if ($is_full) return trim($column_matches[2][0]);
        }

        if ($column_count > 0) {
            $out  = "<!-- wp:columns -->\n";
            $out .= '<div class="wp-block-columns' . ($group_class ? ' ' . esc_attr($group_class) : '') . '">' . "\n";
            $out .= $converted_inner . "\n";
            $out .= "</div>\n";
            $out .= "<!-- /wp:columns -->";
        } else {
            $out  = "<!-- wp:group -->\n";
            $out .= '<div class="wp-block-group' . ($group_class ? ' ' . esc_attr($group_class) : '') . '">' . "\n";
            $out .= $converted_inner . "\n";
            $out .= "</div>\n";
            $out .= "<!-- /wp:group -->";
        }

        return $out;
    }

    // [vc_column_inner]
    public function convert_vc_column_inner($attrs, string $inner_content): string {
        $inner_content = $this->wrap_non_vc_shortcodes($inner_content);

        $extra_class  = [];
        $inline_style = '';
        $block_meta   = '';

        $width_map = [
            '1/1' => '100%', '1/2' => '50%', '1/3' => '33.3333%', '2/3' => '66.6666%',
            '1/4' => '25%',  '3/4' => '75%', '1/6' => '16.6666%', '5/6' => '83.3333%',
            '1/5' => '20%',  '2/5' => '40%',  '3/5' => '60%',  '4/5' => '80%',
            '1/8' => '12.5%','3/8' => '37.5%','5/8' => '62.5%','7/8' => '87.5%',
            '1/12'=> '8.3333%','5/12'=>'41.6666%','7/12'=>'58.3333%','11/12'=>'91.6666%',
        ];

        if (!empty($attrs['width']) && isset($width_map[$attrs['width']])) {
            $width_value  = $width_map[$attrs['width']];
            $inline_style = ' style="flex-basis: ' . $width_value . ';"';
            $block_meta   = '{"width":"' . esc_attr($width_value) . '"}';
        }

        $extra_class = array_merge($extra_class, $this->classes_from_el_class($attrs['el_class'] ?? null));
        $class_attr  = $this->compile_class_attr(array_merge(['wp-block-column'], $extra_class));

        $id_attr = '';
        if (!empty($attrs['el_id'])) {
            $id = preg_replace('/[^A-Za-z0-9\-_:.]/', '', $attrs['el_id']);
            if ($id !== '') $id_attr = ' id="' . esc_attr($id) . '"';
        }

        if (empty(trim($inner_content))) return '';

        if (empty($attrs['width']) || $attrs['width'] === '1/1' || !isset($width_map[$attrs['width']])) {
            return $inner_content;
        }

        $out  = "<!-- wp:column " . $block_meta . " -->\n";
        $out .= '<div class="' . substr($class_attr, 1) . '"' . $inline_style . $id_attr . ">\n";
        $out .= $inner_content . "\n";
        $out .= "</div>\n";
        $out .= "<!-- /wp:column -->";
        return $out;
    }

    // Helper for lists
    public function convert_list_node(\DOMNode $node, \DOMDocument $dom): string {
        $output = '';
        foreach ($node->childNodes as $li) {
            if ($li->nodeName !== 'li') continue;
            $li_content = '';
            foreach ($li->childNodes as $child) {
                if ($child->nodeName === 'ul' || $child->nodeName === 'ol') {
                    $nested = $this->convert_list_node($child, $dom);
                    $li_content .= ($child->nodeName === 'ul')
                        ? "<ul>\n{$nested}</ul>\n"
                        : "<ol>\n{$nested}</ol>\n";
                } else {
                    $li_content .= $dom->saveHTML($child);
                }
            }
            $output .= "<li>" . trim($li_content) . "</li>\n";
        }
        return $output;
    }

    // [vc_column_text]
    public function convert_vc_column_text($attrs, string $inner_content): string {
        $output  = '';
        $content = trim($inner_content);
        if ($content === '') return '';

        $parts = preg_split(
            '/(<!--\s*wp:.*?-->.*?<!--\s*\/wp:.*?-->)/s',
            $content, -1, PREG_SPLIT_DELIM_CAPTURE
        );

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;

            if (strpos($part, '<!-- wp:') === 0) {
                $output .= $part . "\n";
                continue;
            }

            $subparts = preg_split(
                '/(\[(?!vc_)[a-z0-9_-]+[^\]]*\](?:.*?\[\/[a-z0-9_-]+\])?)/s',
                $part, -1, PREG_SPLIT_DELIM_CAPTURE
            );

            foreach ($subparts as $subpart) {
                $subpart = trim($subpart);
                if ($subpart === '') continue;

                if ($subpart[0] === '[' && strpos($subpart, '[vc_') === false) {
                    $output .= "<!-- wp:shortcode -->\n{$subpart}\n<!-- /wp:shortcode -->\n";
                    continue;
                } else {
                    $subpart   = $this->smart_autop($subpart);
                    $converted = $this->blocks_from_html($subpart);
                    $output   .= $converted;
                }
            }
        }

        return trim($output);
    }

    // [vc_btn]
    public function convert_vc_btn($attrs, $inner_content = ''): string {
        $text      = $attrs['title'] ?? 'Click';
        $href      = '';
        $link_attr = $attrs['link'] ?? '';
        $align     = $attrs['align'] ?? 'left';
        $color     = $attrs['color'] ?? '';

        if (preg_match('/url:([^|"]+)/', $link_attr, $m)) {
            $href = esc_url($m[1]);
        }

        $color_hex_map = [
            'success'   => '#28a745', 'danger'  => '#dc3545',
            'warning'   => '#ffc107', 'info'    => '#17a2b8',
            'primary'   => '#007bff', 'secondary' => '#6c757d',
            'dark'      => '#343a40', 'light'   => '#f8f9fa',
        ];

        $align_map = ['left' => 'flex-start', 'right' => 'flex-end', 'center' => 'center'];
        $justify   = $align_map[$align] ?? 'flex-start';
        $layout    = wp_json_encode(["type" => "flex", "justifyContent" => $justify]);

        $button_block_attrs = [];
        $button_style_attr  = '';
        $button_class       = 'wp-element-button wp-block-button__link';

        if (!empty($color) && isset($color_hex_map[$color])) {
            $hex_color = $color_hex_map[$color];
            $button_block_attrs['style'] = ['color' => ['background' => $hex_color]];
            $button_style_attr = ' style="background-color:' . $hex_color . '"';
            $button_class     .= ' has-background';
        } elseif (!empty($attrs['custom_background'])) {
            $custom_bg = preg_replace('/[^#a-zA-Z0-9(),.% -]/', '', $attrs['custom_background']);
            $button_block_attrs['style'] = ['color' => ['background' => $custom_bg]];
            $button_style_attr = ' style="background-color:' . $custom_bg . '"';
            $button_class     .= ' has-background';
        }

        $button_attrs_json = !empty($button_block_attrs) ? wp_json_encode($button_block_attrs) : '';
        $button_comment    = !empty($button_attrs_json) ? "<!-- wp:button {$button_attrs_json} -->" : "<!-- wp:button -->";

        $out  = "<!-- wp:buttons {\"layout\":$layout} -->\n";
        $out .= "<div class=\"wp-block-buttons\" style=\"justify-content:$justify\">\n";
        $out .= "{$button_comment}\n";
        $out .= "<div class=\"wp-block-button\"><a class=\"{$button_class}\" href=\"$href\"{$button_style_attr}>" . esc_html($text) . "</a></div>\n";
        $out .= "<!-- /wp:button -->\n";
        $out .= "</div>\n";
        $out .= "<!-- /wp:buttons -->";
        return $out;
    }

    // [vc_raw_html]
    public function convert_vc_raw_html($attrs, $inner_content = ''): string {
        $content = trim($inner_content);
        $decoded = base64_decode($content);
        if ($decoded === false) return '';
        $decoded = urldecode($decoded);

        return "<!-- wp:html -->\n" . $decoded . "\n<!-- /wp:html -->";
    }

    // [vc_empty_space]
    public function convert_vc_empty_space($attrs, $inner_content = ''): string {
        $height = trim($attrs['height'] ?? '20px');
        $px     = 20;
        if (preg_match('/^(\d+)(px)?$/i', $height, $m)) {
            $px = max(0, (int)$m[1]);
        } elseif (preg_match('/^(\d+)(em|rem)$/i', $height, $m)) {
            $px = max(0, (int)$m[1]) * 16;
        } elseif (preg_match('/^(\d+)%$/', $height)) {
            $px = 40;
        }
        return '<!-- wp:spacer {"height":' . $px . '} -->' . "\n"
            . '<div style="height:' . $px . 'px" aria-hidden="true" class="wp-block-spacer"></div>' . "\n"
            . '<!-- /wp:spacer -->';
    }

    // [vc_single_image]
    public function convert_vc_single_image($attrs, $inner_content = ''): string {
        $img_id    = 0;
        $img_url   = '';
        $size_slug = 'full';
        $link_href = '';

        if (!empty($attrs['image']) && ctype_digit((string)$attrs['image'])) {
            $img_id = (int) $attrs['image'];
        }
        if (!empty($attrs['img_link']) && preg_match('/url:([^|"]+)/', $attrs['img_link'], $m)) {
            $link_href = esc_url($m[1]);
        }

        $requested = !empty($attrs['img_size']) && is_string($attrs['img_size'])
            ? strtolower(trim($attrs['img_size'])) : '';

        $registered       = wp_get_registered_image_subsizes();
        $valid_size_names = array_merge(['thumbnail','medium','large','full'], array_keys($registered));

        if ($requested && in_array($requested, $valid_size_names, true)) {
            $size_slug = $requested;
        } elseif ($requested && preg_match('/^(\d+)\s*x\s*(\d+)$/', $requested, $mm)) {
            $tw   = (int) $mm[1];
            $th   = (int) $mm[2];
            $best = ['slug' => 'full', 'score' => PHP_INT_MAX, 'wdelta' => PHP_INT_MAX];
            foreach ($registered as $slug => $def) {
                $w = (int) ($def['width'] ?? 0);
                $h = (int) ($def['height'] ?? 0);
                if ($w <= 0 || $h <= 0) continue;
                $score  = abs(($w * $h) - ($tw * $th));
                $wdelta = abs($w - $tw);
                if ($score < $best['score'] || ($score === $best['score'] && $wdelta < $best['wdelta'])) {
                    $best = ['slug' => $slug, 'score' => $score, 'wdelta' => $wdelta];
                }
            }
            $size_slug = $best['slug'] ?: 'full';
        }

        if ($img_id) {
            $src = wp_get_attachment_image_url($img_id, $size_slug);
            if ($src) $img_url = $src;
        } else {
            if (!empty($attrs['img_size']) && filter_var($attrs['img_size'], FILTER_VALIDATE_URL)) {
                $img_url = esc_url($attrs['img_size']);
            }
        }

        if ($img_url === '' && $img_id === 0) return '';

        if ($img_id) {
            $block = [
                'id'              => $img_id,
                'sizeSlug'        => $size_slug,
                'linkDestination' => $link_href ? 'custom' : 'none',
            ];
            if ($link_href) $block['url'] = $link_href;
            $json = wp_json_encode($block);

            $alt          = get_post_meta($img_id, '_wp_attachment_image_alt', true);
            $alt          = is_string($alt) ? $alt : '';
            $figure_class = 'wp-block-image size-' . sanitize_html_class($size_slug);
            $img_tag      = '<img src="' . esc_url($img_url) . '" alt="' . esc_attr($alt) . '" class="wp-image-' . $img_id . '"/>';

            if ($link_href) {
                $img_tag = '<a href="' . esc_url($link_href) . '">' . $img_tag . '</a>';
            }

            return "<!-- wp:image {$json} -->\n"
                . '<figure class="' . $figure_class . '">' . $img_tag . '</figure>' . "\n"
                . '<!-- /wp:image -->';
        } else {
            $block = [
                'url'             => $img_url,
                'linkDestination' => $link_href ? 'custom' : 'none',
            ];
            $json    = wp_json_encode($block);
            $img_tag = '<img src="' . esc_url($img_url) . '" alt=""/>';
            if ($link_href) {
                $img_tag = '<a href="' . esc_url($link_href) . '">' . $img_tag . '</a>';
            }

            return "<!-- wp:image {$json} -->\n"
                . '<figure class="wp-block-image">' . $img_tag . '</figure>' . "\n"
                . '<!-- /wp:image -->';
        }
    }

    // [vc_custom_heading]
    public function convert_vc_custom_heading($attrs, $inner_content = ''): string {
        $text  = $inner_content ?: ($attrs['text'] ?? '');
        $level = isset($attrs['level']) ? (int) $attrs['level'] : 2;
        $level = in_array($level, [1,2,3,4,5,6], true) ? $level : 2;
        $text  = esc_html(wp_strip_all_tags($text));
        if ($text === '') return '';
        return '<!-- wp:heading {"level":' . $level . '} -->' . "\n"
            . '<h' . $level . '>' . $text . '</h' . $level . '>' . "\n"
            . '<!-- /wp:heading -->';
    }

    // [vc_separator]
    public function convert_vc_separator($attrs, $inner_content = ''): string {
        $class = 'wp-block-separator';
        $style = '';

        if (!empty($attrs['style']) && in_array($attrs['style'], ['dashed','dotted','double'], true)) {
            $class .= ' is-style-' . sanitize_html_class($attrs['style']);
        }
        if (!empty($attrs['color'])) {
            $color  = preg_replace('/[^#a-zA-Z0-9(),.% -]/', '', $attrs['color']);
            $style .= "border-color:$color;";
        }
        if (!empty($attrs['el_width']) && preg_match('/^\d+%$/', $attrs['el_width'])) {
            $style .= "width:{$attrs['el_width']};margin-left:auto;margin-right:auto;";
        }

        $style_attr = $style ? ' style="' . esc_attr($style) . '"' : '';
        return "<!-- wp:separator -->\n<hr class=\"$class\"$style_attr />\n<!-- /wp:separator -->\n";
    }

    /* ========= UTILITIES (shared with Pro) ========= */

    /**
     * Smart paragraph wrapper for WPBakery content.
     */
    protected function smart_autop(string $content): string {
        $placeholders = [];
        $counter      = 0;

        $block_pattern = '/<(h[1-6]|div|ul|ol|blockquote|pre|table|figure)\b[^>]*>.*?<\/\1>|<hr\s*\/?>/is';

        $content = preg_replace_callback($block_pattern, function ($matches) use (&$placeholders, &$counter) {
            $placeholder                = "___BLOCK_PLACEHOLDER_{$counter}___";
            $placeholders[$placeholder] = $matches[0];
            $counter++;
            return "\n" . $placeholder . "\n";
        }, $content);

        $lines  = explode("\n", $content);
        $output = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === '&nbsp;') continue;
            if (strpos($line, '___BLOCK_PLACEHOLDER_') !== false) {
                $output .= $line . "\n";
                continue;
            }
            $output .= '<p>' . $line . '</p>' . "\n";
        }

        foreach ($placeholders as $placeholder => $original) {
            $output = str_replace($placeholder . "\n", $original . "\n", $output);
            $output = str_replace($placeholder, $original, $output);
        }

        return $output;
    }

    /**
     * Wrap non-VC shortcodes in Gutenberg shortcode blocks.
     */
    public function wrap_non_vc_shortcodes(string $content): string {
        if (strpos($content, '<!-- wp:shortcode -->') !== false) return $content;

        return preg_replace_callback(
            '/\[((?!vc_)[a-z0-9_-]+)([^\]]*?)(?:\]([^\[]*?)\[\/\1\]|\/?])/s',
            function ($m) {
                if (isset($m[3])) {
                    $shortcode = "[{$m[1]}{$m[2]}]{$m[3]}[/{$m[1]}]";
                } else {
                    $shortcode = "[{$m[1]}{$m[2]}]";
                }
                return "<!-- wp:shortcode -->\n{$shortcode}\n<!-- /wp:shortcode -->\n";
            },
            $content
        );
    }

    public function classes_from_el_class(?string $val): array {
        if (empty($val)) return [];
        $parts = preg_split('/\s+/', trim($val));
        $parts = array_filter(array_map(static function ($c) {
            return sanitize_html_class($c);
        }, $parts));
        return array_values(array_unique($parts));
    }

    public function compile_class_attr(array $parts): string {
        $parts = array_filter(array_map('trim', $parts));
        $parts = array_values(array_unique($parts));
        return $parts ? ' ' . esc_attr(implode(' ', $parts)) : '';
    }

    protected function vc_bool($v): bool {
        $v = strtolower(trim((string) $v));
        return in_array($v, ['1','true','yes','on'], true);
    }

    public function normalize_spans_html(\DOMElement $element): string {
        $doc = new \DOMDocument();
        $doc->loadHTML(
            mb_convert_encoding('<div>' . $element->C14N() . '</div>', 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        $root = $doc->getElementsByTagName('div')->item(0);
        if (!$root) {
            return $element->ownerDocument->saveHTML($element);
        }

        $spans = iterator_to_array($root->getElementsByTagName('span'));
        foreach ($spans as $span) {
            $style     = strtolower((string) $span->getAttribute('style'));
            $is_bold   = (bool) preg_match('/font-weight\s*:\s*(bold|[6-9]00)/', $style);
            $is_italic = (bool) preg_match('/font-style\s*:\s*(italic|oblique)/', $style);

            $wrapper = null;
            if ($is_bold && $is_italic) {
                $wrapper = $doc->createElement('strong');
                $em      = $doc->createElement('em');
                while ($span->firstChild) $em->appendChild($span->firstChild);
                $wrapper->appendChild($em);
            } elseif ($is_bold) {
                $wrapper = $doc->createElement('strong');
                while ($span->firstChild) $wrapper->appendChild($span->firstChild);
            } elseif ($is_italic) {
                $wrapper = $doc->createElement('em');
                while ($span->firstChild) $wrapper->appendChild($span->firstChild);
            } else {
                $wrapper = $doc->createDocumentFragment();
                while ($span->firstChild) $wrapper->appendChild($span->firstChild);
            }

            $span->parentNode->replaceChild($wrapper, $span);
        }

        $html = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $html .= $doc->saveHTML($child);
        }
        return $html;
    }

    protected function anchor_is_button_like(\DOMElement $a): bool {
        $class = strtolower((string) $a->getAttribute('class'));
        return (bool) preg_match('/\b(vc_btn|btn|button|wpb_button|wp-block-button__link)\b/', $class);
    }

    /**
     * Convert HTML fragment into Gutenberg blocks.
     */
    public function blocks_from_html(string $html): string {
        $out = '';

        $prev = libxml_use_internal_errors(true);
        $dom  = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = false;
        $dom->loadHTML(mb_convert_encoding('<div>' . $html . '</div>', 'HTML-ENTITIES', 'UTF-8'));
        $body = $dom->getElementsByTagName('div')->item(0);

        if (!$body) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return $out;
        }
        $para = '';

        $flush_para = function () use (&$out, &$para) {
            if ($para !== '') {
                $para = preg_replace('/\s{2,}/', ' ', $para);
                $out .= "<!-- wp:paragraph -->\n<p>{$para}</p>\n<!-- /wp:paragraph -->\n";
                $para = '';
            }
        };

        foreach ($body->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = preg_replace('/\s+/u', ' ', $node->textContent);
                if (trim($text) !== '') $para .= esc_html($text);
                continue;
            }
            if ($node->nodeType !== XML_ELEMENT_NODE) continue;

            $tag = strtolower($node->nodeName);

            if (in_array($tag, ['p','h1','h2','h3','h4','h5','h6','ul','ol','hr'], true)) {
                $flush_para();

                switch ($tag) {
                    case 'p': {
                        $normalized = $this->normalize_spans_html($node);
                        $style = strtolower((string) $node->getAttribute('style'));
                        $align = '';
                        if (preg_match('/text-align:\s*(left|right|center|justify)/', $style, $m)) {
                            $align = $m[1];
                        }
                        if ($normalized !== '') {
                            $attrs = $align ? wp_json_encode(['align' => $align]) : '';
                            $open  = $attrs ? "<!-- wp:paragraph {$attrs} -->" : "<!-- wp:paragraph -->";
                            $out  .= $open . "\n<p>{$normalized}</p>\n<!-- /wp:paragraph -->\n";
                        }
                        break;
                    }
                    case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6': {
                        $level = (int) substr($tag, 1);
                        $inner = trim($node->textContent);
                        if ($inner !== '') {
                            $out .= "<!-- wp:heading {\"level\":$level} -->\n<h$level>" . esc_html($inner) . "</h$level>\n<!-- /wp:heading -->\n";
                        }
                        break;
                    }
                    case 'ul': {
                        $items = $this->convert_list_node($node, $dom);
                        if ($items) $out .= "<!-- wp:list -->\n<ul>\n$items</ul>\n<!-- /wp:list -->\n";
                        break;
                    }
                    case 'ol': {
                        $items = $this->convert_list_node($node, $dom);
                        if ($items) $out .= "<!-- wp:list {\"ordered\":true} -->\n<ol>\n$items</ol>\n<!-- /wp:list -->\n";
                        break;
                    }
                    case 'hr':
                        $out .= "<!-- wp:separator -->\n<hr class=\"wp-block-separator\"/>\n<!-- /wp:separator -->\n";
                        break;
                }
                continue;
            }

            switch ($tag) {
                case 'a': {
                    if ($this->anchor_is_button_like($node)) {
                        $flush_para();
                        $href = $node->getAttribute('href');
                        $text = trim($node->textContent);
                        $out .= "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">\n";
                        $out .= "<!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link\" href=\""
                            . esc_url($href) . "\">" . esc_html($text) . "</a></div>\n<!-- /wp:button -->\n";
                        $out .= "</div>\n<!-- /wp:buttons -->\n";
                    } else {
                        $para .= $this->normalize_spans_html($node);
                    }
                    break;
                }
                case 'strong':
                case 'em':
                case 'span': {
                    $para .= $this->normalize_spans_html($node);
                    break;
                }
                case 'br':
                    $para .= '<br />';
                    break;
                default:
                    $para .= $dom->saveHTML($node);
                    break;
            }
        }

        $flush_para();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $out;
    }

    /**
     * Parse VC link format: "url:https%3A%2F%2Fexample.com|target:_blank|title:Link Title"
     */
    public function parse_vc_link(string $link_attr): string {
        if (empty($link_attr)) return '';

        $link_parts = explode('|', $link_attr);
        foreach ($link_parts as $part) {
            if (strpos($part, 'url:') === 0) {
                $raw_url = substr($part, 4);
                return esc_url(urldecode($raw_url));
            }
        }

        if (filter_var($link_attr, FILTER_VALIDATE_URL)) {
            return esc_url($link_attr);
        }

        return '';
    }

    /**
     * Generate Gutenberg button block markup.
     */
    public function generate_button_block(string $text, string $url, string $color, array $color_map, string $custom_bg): string {
        $button_block_attrs = [];
        $button_style_attr  = '';
        $button_class       = 'wp-block-button__link has-background wp-element-button';

        if (!empty($color) && isset($color_map[$color])) {
            $hex_color = $color_map[$color];
            $button_block_attrs['style'] = ['color' => ['background' => $hex_color]];
            $button_style_attr = ' style="background-color:' . $hex_color . '"';
        } elseif (!empty($custom_bg)) {
            $custom_bg = preg_replace('/[^#a-zA-Z0-9(),.% -]/', '', $custom_bg);
            $button_block_attrs['style'] = ['color' => ['background' => $custom_bg]];
            $button_style_attr = ' style="background-color:' . $custom_bg . '"';
        } else {
            $button_class = 'wp-block-button__link wp-element-button';
        }

        $button_attrs_json = !empty($button_block_attrs) ? ' ' . wp_json_encode($button_block_attrs) : '';

        return "<!-- wp:buttons -->\n" .
            "<div class=\"wp-block-buttons\">\n" .
            "<!-- wp:button{$button_attrs_json} -->\n" .
            "<div class=\"wp-block-button\"><a class=\"{$button_class}\" href=\"{$url}\"{$button_style_attr}>" . esc_html($text) . "</a></div>\n" .
            "<!-- /wp:button -->\n" .
            "</div>\n" .
            "<!-- /wp:buttons -->\n";
    }

    /**
     * Preserve complex grid shortcodes as wp:shortcode blocks.
     */
    public function preserve_grid_shortcode(string $shortcode_name, $attrs, string $note = ''): string {
        $attr_str = '';
        if (is_array($attrs)) {
            foreach ($attrs as $key => $value) {
                if (is_numeric($key)) continue;
                if (is_array($value)) $value = json_encode($value);
                $value     = (string) $value;
                $attr_str .= ' ' . $key . '="' . esc_attr($value) . '"';
            }
        }

        $shortcode = '[' . $shortcode_name . $attr_str . ']';

        $note_html = '';
        if ($note) {
            $note_html = "\n\n<!-- wp:paragraph -->\n<p><em>" . esc_html($note) . "</em></p>\n<!-- /wp:paragraph -->";
        }

        return "<!-- wp:shortcode -->\n"
            . $shortcode . "\n"
            . '<!-- /wp:shortcode -->' . $note_html;
    }
}
