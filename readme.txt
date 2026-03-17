=== Interlinear ===
Contributors: feierwon
Tags: content filters, inline tagging, categories, reading experience, gutenberg
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Layered content filters for WordPress. Authors tag inline text with semantic categories; readers filter without losing context.

== Description ==

Interlinear lets authors tag inline text with up to six semantic categories per post. When a reader selects a category from the sticky sidebar, matching text is highlighted while the rest of the page dims. No content is hidden or removed from the DOM.

**For authors:**

* Define up to 6 categories per post with custom labels and colors
* Tag text directly in the Gutenberg block editor via the toolbar
* Save and load category presets across posts
* Colored dotted underlines in the editor show what's tagged

**For readers:**

* Sticky sidebar with one-click filter toggles
* Highlighted text with category-colored background and underline
* Smooth sweep-in animation when a filter is selected
* Filter state remembered across visits via localStorage
* Fully keyboard operable and screen-reader accessible

**Design principles:**

* Tagged text is invisible until a filter is activated
* No content is ever hidden — unmatched text dims, matched text highlights
* Graceful degradation: without JS or with the plugin deactivated, all text renders normally
* Self-contained front-end JS with no WordPress dependencies
* Logical CSS properties throughout for future RTL support

== Installation ==

1. Upload the `interlinear` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Define categories in the post editor sidebar under "Interlinear Categories"
4. Select text and click the Interlinear Tag button in the block toolbar to tag it

== Frequently Asked Questions ==

= Does this work with the Classic Editor? =

No. Interlinear v1 requires the Gutenberg block editor.

= What happens if I deactivate the plugin? =

Tagged text renders as plain unstyled spans. No content is lost or broken.

= Can readers select multiple categories at once? =

In v1, filtering is single-select — one category at a time. Clicking another category switches to it.

= Does this work with any theme? =

Yes. The front-end uses relative opacity and inline styles that work regardless of theme colors or typography. A minimum line-height is applied to the content area when tagged content is present to ensure highlights render cleanly.

== Screenshots ==

1. Category definition panel in the post editor sidebar
2. Tagging text with the toolbar button
3. Front-end sidebar with filter toggles
4. Highlighted text with dimmed surroundings

== Changelog ==

= 1.0.0 =
* Initial release
* Gutenberg inline format type for tagging text
* Per-post category definitions with color picker and mode toggle
* Preset save/load system
* Front-end sticky sidebar with filter toggles
* Block-level content dimming with highlighted span pop-out
* Sweep-in animation on filter activation
* localStorage-based reader state persistence
* Dynamic editor styles with colored dotted underlines
* Full accessibility: aria-pressed, aria-live announcements, keyboard support
* Settings page: default opacity, filter mode, persistence toggle, preset management
