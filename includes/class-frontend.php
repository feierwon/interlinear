<?php
/**
 * Front-end rendering: sidebar, scripts, styles, and editor inline styles.
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
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'inject_editor_inline_styles' ) );
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

	/**
	 * Inject inline editor styles for tagged span colors.
	 */
	public static function inject_editor_inline_styles() {
		global $post;

		if ( ! $post ) {
			return;
		}

		$categories = Interlinear_Meta::get_categories( $post->ID );

		if ( empty( $categories ) ) {
			return;
		}

		$css = '';
		foreach ( $categories as $cat ) {
			$hex   = $cat['color'];
			$alpha = $hex . '26';
			$css  .= sprintf(
				'[data-il-category="%s"] { background-color: %s; border-bottom: 2px solid %s; }' . "\n",
				esc_attr( $cat['slug'] ),
				$alpha,
				$hex
			);
		}

		wp_add_inline_style( 'interlinear-editor', $css );
	}
}
