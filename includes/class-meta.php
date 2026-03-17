<?php
/**
 * Post meta registration and sanitization.
 *
 * @package Interlinear
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Interlinear_Meta {

	/**
	 * Maximum categories per post.
	 */
	const MAX_CATEGORIES = 6;

	/**
	 * Initialize meta registration.
	 */
	public static function init() {
		self::register_meta();
	}

	/**
	 * Register post meta keys.
	 */
	private static function register_meta() {
		register_post_meta( '', '_interlinear_categories', array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'string',
			'default'           => '[]',
			'sanitize_callback' => array( __CLASS__, 'sanitize_categories' ),
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		) );

		register_post_meta( '', '_interlinear_version', array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		) );

		register_post_meta( '', '_interlinear_persistence', array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}

	/**
	 * Sanitize the categories JSON string.
	 *
	 * @param string $value Raw JSON string.
	 * @return string Sanitized JSON string.
	 */
	public static function sanitize_categories( $value ) {
		$categories = json_decode( $value, true );

		if ( ! is_array( $categories ) ) {
			return '[]';
		}

		$categories = array_slice( $categories, 0, self::MAX_CATEGORIES );
		$sanitized  = array();

		foreach ( $categories as $index => $cat ) {
			if ( ! is_array( $cat ) || empty( $cat['label'] ) ) {
				continue;
			}

			$label = sanitize_text_field( $cat['label'] );
			$slug  = sanitize_title( $label );

			if ( empty( $slug ) ) {
				$slug = 'category-' . ( $index + 1 );
			}

			$color = isset( $cat['color'] ) ? sanitize_hex_color( $cat['color'] ) : '#3A7FC9';
			if ( empty( $color ) ) {
				$color = '#3A7FC9';
			}

			$mode = isset( $cat['mode'] ) && 'exclusive' === $cat['mode'] ? 'exclusive' : 'multi';

			$sanitized[] = array(
				'slug'  => $slug,
				'label' => $label,
				'color' => $color,
				'mode'  => $mode,
			);
		}

		return wp_json_encode( $sanitized );
	}

	/**
	 * Get categories for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Decoded categories array.
	 */
	public static function get_categories( $post_id ) {
		$raw = get_post_meta( $post_id, '_interlinear_categories', true );

		if ( empty( $raw ) ) {
			return array();
		}

		$categories = json_decode( $raw, true );

		return is_array( $categories ) ? $categories : array();
	}

	/**
	 * Check whether persistence is enabled for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_persistence_enabled( $post_id ) {
		$sitewide = get_option( 'interlinear_persistence', true );

		if ( ! $sitewide ) {
			return false;
		}

		return (bool) get_post_meta( $post_id, '_interlinear_persistence', true );
	}
}
