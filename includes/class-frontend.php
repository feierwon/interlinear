<?php
/**
 * Front-end rendering: sidebar, scripts, and styles.
 *
 * @package Interlinear
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Interlinear_Frontend {

	/**
	 * Initialize front-end hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_render_sidebar' ) );
		add_action( 'wp_head', array( __CLASS__, 'output_custom_properties' ), 1 );
	}

	/**
	 * Output CSS custom properties on :root.
	 */
	public static function output_custom_properties() {
		$focus       = self::resolve_accent_color();
		$hover       = self::darken_hex( $focus, 12 );
		$translucent = self::hex_to_rgba( $focus, 0.15 );

		echo '<style id="interlinear-custom-properties">
:root {
	--il-focus: ' . esc_attr( $focus ) . ';
	--il-focus-hover: ' . esc_attr( $hover ) . ';
	--il-focus-translucent: ' . esc_attr( $translucent ) . ';
}
</style>' . "\n";
	}

	/**
	 * Build inline CSS string for custom properties.
	 *
	 * @return string CSS rule.
	 */
	public static function get_custom_properties_css() {
		$focus       = self::resolve_accent_color();
		$hover       = self::darken_hex( $focus, 12 );
		$translucent = self::hex_to_rgba( $focus, 0.15 );

		return sprintf(
			':root { --il-focus: %s; --il-focus-hover: %s; --il-focus-translucent: %s; }',
			esc_attr( $focus ),
			esc_attr( $hover ),
			esc_attr( $translucent )
		);
	}

	/**
	 * Resolve focus color using the fallback chain: theme → custom → default.
	 *
	 * @return string Hex color.
	 */
	public static function resolve_accent_color() {
		$source = get_option( 'interlinear_color_source', 'default' );

		if ( 'theme' === $source ) {
			$color = self::auto_pick_from_palette();
			if ( $color ) {
				return $color;
			}
		}

		if ( 'custom' === $source ) {
			$custom = get_option( 'interlinear_custom_focus_color', '' );
			if ( $custom && preg_match( '/^#[0-9a-f]{6}$/i', $custom ) ) {
				return $custom;
			}
		}

		return '#007cba';
	}

	/**
	 * Auto-pick the best accent color from the active theme palette.
	 *
	 * @return string|false Hex color or false if no palette.
	 */
	public static function auto_pick_from_palette() {
		$palette = self::get_theme_palette();

		if ( empty( $palette ) ) {
			return false;
		}

		$best_score = -1;
		$best_color = false;

		foreach ( $palette as $entry ) {
			$hex = isset( $entry['color'] ) ? self::normalize_hex( $entry['color'] ) : '';
			if ( ! $hex ) {
				continue;
			}

			$hsl             = self::hex_to_hsl( $hex );
			$s               = $hsl[1];
			$l               = $hsl[2];
			$lightness_score = max( 0, 1 - abs( $l - 0.45 ) * 2 );
			$score           = $s * 0.6 + $lightness_score * 0.4;

			if ( $score > $best_score ) {
				$best_score = $score;
				$best_color = $hex;
			}
		}

		return $best_color;
	}

	/**
	 * Get the active theme's color palette.
	 *
	 * @return array Array of { slug, name, color } entries.
	 */
	public static function get_theme_palette() {
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$palette = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
			if ( ! empty( $palette ) ) {
				return $palette;
			}
		}

		$support = get_theme_support( 'editor-color-palette' );
		if ( is_array( $support ) && isset( $support[0] ) && is_array( $support[0] ) ) {
			return $support[0];
		}

		return array();
	}

	/**
	 * Normalize a hex color to 6-character format.
	 *
	 * @param string $hex Hex color.
	 * @return string 6-character hex with #, or empty string on failure.
	 */
	public static function normalize_hex( $hex ) {
		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return '';
		}

		return '#' . $hex;
	}

	/**
	 * Convert a hex color to HSL.
	 *
	 * @param string $hex Hex color (6-character with #).
	 * @return array [ hue (0-360), saturation (0-1), lightness (0-1) ].
	 */
	public static function hex_to_hsl( $hex ) {
		$hex = ltrim( $hex, '#' );
		$r   = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g   = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b   = hexdec( substr( $hex, 4, 2 ) ) / 255;

		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$l   = ( $max + $min ) / 2;

		if ( $max === $min ) {
			return array( 0, 0, $l );
		}

		$d = $max - $min;
		$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );

		if ( $max === $r ) {
			$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
		} elseif ( $max === $g ) {
			$h = ( $b - $r ) / $d + 2;
		} else {
			$h = ( $r - $g ) / $d + 4;
		}

		$h = $h * 60;

		return array( $h, $s, $l );
	}

	/**
	 * Darken a hex color by a percentage.
	 *
	 * @param string $hex    Hex color.
	 * @param int    $amount Percentage to darken (0-100).
	 * @return string Darkened hex color.
	 */
	public static function darken_hex( $hex, $amount ) {
		$hex = ltrim( $hex, '#' );
		$r   = max( 0, intval( hexdec( substr( $hex, 0, 2 ) ) * ( 1 - $amount / 100 ) ) );
		$g   = max( 0, intval( hexdec( substr( $hex, 2, 2 ) ) * ( 1 - $amount / 100 ) ) );
		$b   = max( 0, intval( hexdec( substr( $hex, 4, 2 ) ) * ( 1 - $amount / 100 ) ) );
		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Convert a hex color to rgba.
	 *
	 * @param string $hex   Hex color.
	 * @param float  $alpha Alpha value (0-1).
	 * @return string RGBA string.
	 */
	public static function hex_to_rgba( $hex, $alpha ) {
		$hex = ltrim( $hex, '#' );
		$r   = hexdec( substr( $hex, 0, 2 ) );
		$g   = hexdec( substr( $hex, 2, 2 ) );
		$b   = hexdec( substr( $hex, 4, 2 ) );
		return sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, $alpha );
	}

	/**
	 * Check if the current post has Interlinear categories.
	 *
	 * @return array|false Categories array or false.
	 */
	private static function get_current_categories() {
		if ( ! is_singular() ) {
			return false;
		}

		$post_id    = get_the_ID();
		$categories = Interlinear_Meta::get_categories( $post_id );

		if ( empty( $categories ) ) {
			return false;
		}

		return $categories;
	}

	/**
	 * Enqueue front-end assets if categories are present.
	 */
	public static function maybe_enqueue_assets() {
		$categories = self::get_current_categories();

		if ( ! $categories ) {
			return;
		}

		$post_id = get_the_ID();

		wp_enqueue_style(
			'interlinear-frontend',
			INTERLINEAR_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			INTERLINEAR_VERSION
		);

		wp_enqueue_script(
			'interlinear-frontend',
			INTERLINEAR_PLUGIN_URL . 'assets/js/interlinear.js',
			array(),
			INTERLINEAR_VERSION,
			true
		);

		$opacity = get_option( 'interlinear_default_opacity', 0.35 );

		wp_localize_script( 'interlinear-frontend', 'interlinearData', array(
			'postId'      => $post_id,
			'categories'  => $categories,
			'persistence' => Interlinear_Meta::is_persistence_enabled( $post_id ),
			'opacity'     => floatval( $opacity ),
		) );
	}

	/**
	 * Render sidebar markup if categories are present.
	 */
	public static function maybe_render_sidebar() {
		$categories = self::get_current_categories();

		if ( ! $categories ) {
			return;
		}

		echo '<nav class="il-sidebar" aria-label="' . esc_attr__( 'Content filters', 'interlinear' ) . '" id="il-sidebar">';

		printf(
			'<button class="il-filter il-filter--all il-filter--active" aria-pressed="true" data-il-filter="all">%s</button>',
			esc_html__( 'All', 'interlinear' )
		);

		foreach ( $categories as $cat ) {
			printf(
				'<button class="il-filter" aria-pressed="false" data-il-filter="%s" style="--il-color: %s;">%s</button>',
				esc_attr( $cat['slug'] ),
				esc_attr( $cat['color'] ),
				esc_html( $cat['label'] )
			);
		}

		printf(
			'<div aria-live="polite" aria-atomic="true" class="il-announcer visually-hidden">%s</div>',
			esc_html__( 'Showing all content.', 'interlinear' )
		);

		echo '</nav>';
	}

}
