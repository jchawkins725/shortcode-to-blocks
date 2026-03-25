=== Shortcode to Blocks Converter ===
Contributors: jchawkins725
Tags: gutenberg, block-editor, wpbakery, visual-composer, shortcode, converter, migration, blocks
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert WPBakery Page Builder shortcodes to native Gutenberg blocks — one page at a time.

== Description ==

**Migrate from WPBakery Page Builder to Gutenberg — Free**

Shortcode to Blocks Converter is a free tool that converts the most common WPBakery Page Builder (Visual Composer) shortcodes into native WordPress Gutenberg blocks. Edit each post in the block editor, click **Convert**, and your WPBakery layout is replaced with clean, modern blocks.

**Important**: This plugin is designed exclusively for WPBakery Page Builder shortcodes (vc_* elements). It does not work with Elementor, Divi, Beaver Builder, or other page builders.

= What Gets Converted =

**Layout Elements**
Rows and columns (`vc_row`, `vc_column`, `vc_row_inner`, `vc_column_inner`) become Group and Columns blocks with responsive settings and CSS classes preserved.

**Content Elements**
* `vc_column_text` → Paragraph / HTML blocks
* `vc_custom_heading` → Heading blocks with proper levels
* `vc_btn` → Button blocks with color and link preserved
* `vc_raw_html` → Custom HTML blocks
* `vc_single_image` → Image blocks
* `vc_separator` → Separator blocks
* `vc_empty_space` → Spacer blocks

**Third-party Shortcodes**
Non-WPBakery shortcodes (contact forms, plugins, etc.) are automatically wrapped in Shortcode blocks and keep working after conversion.

= Features =

* One-click convert and revert on each post/page edit screen
* Works in both the block editor (Gutenberg sidebar panel) and the classic editor (metabox)
* Automatic backup of original content — revert any time
* Dashboard with VC detection stats
* Settings for allowed post types and required capability
* Extensible architecture — the Pro add-on unlocks advanced converters, batch tools, logging, and more

= Supported Shortcodes (Free) =

| WPBakery Shortcode | Gutenberg Block |
|---|---|
| `vc_row` / `vc_row_inner` | Group |
| `vc_column` / `vc_column_inner` | Column |
| `vc_column_text` | Paragraph / HTML |
| `vc_custom_heading` | Heading |
| `vc_btn` | Button |
| `vc_single_image` | Image |
| `vc_raw_html` | Custom HTML |
| `vc_separator` | Separator |
| `vc_empty_space` | Spacer |

= Need More? =

[Shortcode to Blocks Converter Pro](https://www.jonathanchawkins.com/shortcode-to-blocks-pro/) adds:

* **17 additional converters** — CTAs, toggles, video, galleries, maps, tabs, tours, accordions, icons, grids, and more
* **Batch conversion** — convert hundreds of posts at once with dry-run preview
* **Batch revert** — undo an entire batch in one click
* **Bulk action** — convert directly from the Posts / Pages list table
* **Logging** — full history of every conversion, revert, and error with CSV export
* **Tools** — backup purge, log management, VC detection scan
* **Converted Posts** — filterable list of everything that has been converted
* **License key activation** — receive updates and support

== Installation ==

1. Upload the `shortcode-to-blocks` folder to `/wp-content/plugins/`, or install from the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Shortcode → Blocks** in the admin sidebar to see the dashboard.
4. Open any post or page that contains WPBakery content and click **Convert** in the sidebar panel (block editor) or metabox (classic editor).

== Frequently Asked Questions ==

= Does this work with other page builders? =
No. This plugin converts WPBakery Page Builder (Visual Composer) shortcodes only.

= Can I revert after converting? =
Yes. The original content is stored in post meta. Click **Revert** to restore it.

= What happens to shortcodes that aren't supported? =
They're wrapped in a Gutenberg Shortcode block and continue to render normally. No content is lost.

= Is it safe for production? =
The plugin creates automatic backups, but always test on a staging copy first and back up your database before converting many posts.

= Do I need the Pro version? =
The free version handles the most common layout and content shortcodes for single-post conversion. If you need batch tools, advanced converters (video, galleries, tabs, accordions, CTAs, maps), logging, or bulk actions, the Pro add-on is the way to go.

== Screenshots ==

1. Gutenberg sidebar panel with Convert / Revert buttons
2. Classic editor metabox
3. Dashboard with VC detection overview
4. Settings page

== Changelog ==

= 1.0.0 =
* Initial release
* Converts 11 core WPBakery shortcodes to Gutenberg blocks
* Single-post convert and revert via editor sidebar or metabox
* Automatic content backup in post meta
* Dashboard with VC post detection
* Settings for post types and capability
* Extensible architecture for Pro add-on

== Upgrade Notice ==

= 1.0.0 =
First release. Always test on a staging site before converting production content.
