<?php
/**
 * Plugin Name: Interlinear
 * Plugin URI: https://github.com/mjgfoster/interlinear
 * Description: Layered content filters for WordPress. Tag inline text with author-defined categories; readers filter without losing context.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * Author: Feierwon Media LLC
 * Author URI: https://feierwon.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: interlinear
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INTERLINEAR_VERSION', '1.0.0' );
define( 'INTERLINEAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INTERLINEAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'INTERLINEAR_PLUGIN_FILE', __FILE__ );

require_once INTERLINEAR_PLUGIN_DIR . 'includes/class-meta.php';
require_once INTERLINEAR_PLUGIN_DIR . 'includes/class-settings.php';
require_once INTERLINEAR_PLUGIN_DIR . 'includes/class-frontend.php';
require_once INTERLINEAR_PLUGIN_DIR . 'includes/class-wizard.php';
require_once INTERLINEAR_PLUGIN_DIR . 'includes/class-license.php';
require_once INTERLINEAR_PLUGIN_DIR . 'includes/class-updater.php';

/**
 * Initialize plugin components.
 */
function interlinear_init() {
	Interlinear_Meta::init();
	Interlinear_Settings::init();
	Interlinear_Frontend::init();
	Interlinear_Wizard::init();
	// Licensing + updates register hooks that must also fire during WP-Cron
	// (daily re-validation, background auto-updates), so they init here on
	// 'init' rather than 'admin_init'.
	Interlinear_License::init();
	Interlinear_Updater::init();
}
add_action( 'init', 'interlinear_init' );

/**
 * Enqueue block editor assets.
 */
function interlinear_enqueue_editor_assets() {
	$asset_file = INTERLINEAR_PLUGIN_DIR . 'build/editor.asset.php';
	$asset      = file_exists( $asset_file )
		? require $asset_file
		: array(
			'dependencies' => array(),
			'version'      => INTERLINEAR_VERSION,
		);

	wp_enqueue_script(
		'interlinear-editor',
		INTERLINEAR_PLUGIN_URL . 'build/editor.js',
		$asset['dependencies'],
		$asset['version'],
		false
	);

	wp_enqueue_style(
		'interlinear-editor',
		INTERLINEAR_PLUGIN_URL . 'assets/css/editor.css',
		array( 'wp-edit-blocks' ),
		INTERLINEAR_VERSION
	);

	wp_set_script_translations( 'interlinear-editor', 'interlinear', INTERLINEAR_PLUGIN_DIR . 'languages' );

	wp_add_inline_style( 'interlinear-editor', Interlinear_Frontend::get_custom_properties_css() );
}
add_action( 'enqueue_block_editor_assets', 'interlinear_enqueue_editor_assets' );

/**
 * Load plugin text domain.
 */
function interlinear_load_textdomain() {
	load_plugin_textdomain( 'interlinear', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'interlinear_load_textdomain' );

/**
 * Plugin activation.
 */
function interlinear_activate() {
	add_option( 'interlinear_color_source', 'default' );
	add_option( 'interlinear_custom_focus_color', '' );
	add_option( 'interlinear_default_opacity', 0.35 );
	add_option( 'interlinear_persistence', true );
	add_option( 'interlinear_presets', '{}' );

	set_transient( 'interlinear_activation_redirect', true, 30 );
}
register_activation_hook( __FILE__, 'interlinear_activate' );

/**
 * Plugin deactivation. Clear the scheduled license re-validation event.
 */
function interlinear_deactivate() {
	$timestamp = wp_next_scheduled( Interlinear_License::CRON_HOOK );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, Interlinear_License::CRON_HOOK );
	}
}
register_deactivation_hook( __FILE__, 'interlinear_deactivate' );
