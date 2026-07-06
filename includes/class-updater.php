<?php
/**
 * Automatic updates for the paid plugin, gated by a valid license.
 *
 * Paid plugins are not hosted on wp.org, so there is no wp.org update source.
 * This checks a self-hosted JSON manifest and injects an available update into
 * WordPress only when the license is active. Updates are the ONLY thing gated
 * by licensing — see class-license.php for the enforcement model.
 *
 * Expected manifest shape:
 *   {
 *     "version":      "1.1.0",
 *     "download_url": "https://.../interlinear-1.1.0.zip",
 *     "requires":     "5.8",
 *     "tested":       "6.5",
 *     "requires_php": "7.2",
 *     "sections":     { "changelog": "<p>…</p>" }
 *   }
 * The endpoint should authorize `download_url` using the license_key and
 * instance_id query args this class appends.
 *
 * @package Interlinear
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Interlinear_Updater {

	/**
	 * URL of the update manifest endpoint.
	 *
	 * TODO: set this once the update endpoint is stood up (e.g. a REST route on
	 * getliftoff.org). While empty, no updates are offered — the plugin simply
	 * behaves as if it is up to date.
	 */
	const MANIFEST_URL = '';

	/** Cache key for the fetched manifest. */
	const TRANSIENT = 'interlinear_update_manifest';

	/** Plugin slug used in the update/info payloads. */
	const SLUG = 'interlinear';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'purge_cache' ), 10, 2 );
	}

	/**
	 * Plugin basename (folder/file.php) as WordPress keys updates by.
	 *
	 * @return string
	 */
	private static function basename() {
		return plugin_basename( INTERLINEAR_PLUGIN_FILE );
	}

	/**
	 * Fetch and cache the remote manifest. Returns null when updates are not
	 * available (no endpoint configured, no valid license, or transport error).
	 *
	 * @return array|null
	 */
	private static function get_manifest() {
		if ( '' === self::MANIFEST_URL || ! Interlinear_License::is_valid() ) {
			return null;
		}

		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached ) {
			return is_array( $cached ) && ! empty( $cached ) ? $cached : null;
		}

		$status = Interlinear_License::get_status();
		$url    = add_query_arg(
			array(
				'license_key' => rawurlencode( Interlinear_License::get_key() ),
				'instance_id' => isset( $status['instance_id'] ) ? rawurlencode( $status['instance_id'] ) : '',
				'version'     => INTERLINEAR_VERSION,
			),
			self::MANIFEST_URL
		);

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : array();

		// Cache both hits and misses briefly to avoid hammering the endpoint.
		set_transient( self::TRANSIENT, $data, 6 * HOUR_IN_SECONDS );

		return ! empty( $data ) ? $data : null;
	}

	/**
	 * Inject an available update into the plugins update transient.
	 *
	 * @param mixed $transient Update transient (object) or falsy.
	 * @return mixed
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$manifest = self::get_manifest();
		if ( ! $manifest || empty( $manifest['version'] ) ) {
			return $transient;
		}

		if ( version_compare( $manifest['version'], INTERLINEAR_VERSION, '>' ) ) {
			$item = array(
				'slug'        => self::SLUG,
				'plugin'      => self::basename(),
				'new_version' => $manifest['version'],
				'package'     => isset( $manifest['download_url'] ) ? $manifest['download_url'] : '',
				'url'         => 'https://feierwon.com',
			);
			foreach ( array( 'requires', 'tested', 'requires_php' ) as $field ) {
				if ( isset( $manifest[ $field ] ) ) {
					$item[ $field ] = $manifest[ $field ];
				}
			}
			$transient->response[ self::basename() ] = (object) $item;
		}

		return $transient;
	}

	/**
	 * Provide plugin details for the "View details" modal.
	 *
	 * @param mixed  $result Default result.
	 * @param string $action Requested action.
	 * @param object $args   Request args.
	 * @return mixed
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$manifest = self::get_manifest();
		if ( ! $manifest ) {
			return $result;
		}

		$info = array(
			'name'          => 'Interlinear',
			'slug'          => self::SLUG,
			'version'       => isset( $manifest['version'] ) ? $manifest['version'] : INTERLINEAR_VERSION,
			'author'        => '<a href="https://feierwon.com">Feierwon Media LLC</a>',
			'homepage'      => 'https://feierwon.com',
			'download_link' => isset( $manifest['download_url'] ) ? $manifest['download_url'] : '',
			'sections'      => isset( $manifest['sections'] ) ? (array) $manifest['sections'] : array(),
		);
		foreach ( array( 'requires', 'tested', 'requires_php' ) as $field ) {
			if ( isset( $manifest[ $field ] ) ) {
				$info[ $field ] = $manifest[ $field ];
			}
		}

		return (object) $info;
	}

	/**
	 * Clear the cached manifest after a plugin update completes.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $data     Upgrade context.
	 */
	public static function purge_cache( $upgrader, $data ) {
		if ( isset( $data['action'], $data['type'] )
			&& 'update' === $data['action']
			&& 'plugin' === $data['type'] ) {
			delete_transient( self::TRANSIENT );
		}
	}
}
