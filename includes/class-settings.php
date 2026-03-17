<?php
/**
 * Plugin settings page and preset management.
 *
 * @package Interlinear
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Interlinear_Settings {

	/**
	 * Initialize settings hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Add settings page under Settings menu.
	 */
	public static function add_settings_page() {
		add_options_page(
			__( 'Interlinear Settings', 'interlinear' ),
			__( 'Interlinear', 'interlinear' ),
			'manage_options',
			'interlinear',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public static function register_settings() {
		register_setting( 'interlinear_settings', 'interlinear_default_opacity', array(
			'type'              => 'number',
			'sanitize_callback' => array( __CLASS__, 'sanitize_opacity' ),
			'default'           => 0.35,
			'show_in_rest'      => true,
		) );

		register_setting( 'interlinear_settings', 'interlinear_persistence', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
			'show_in_rest'      => true,
		) );

		add_settings_section(
			'interlinear_general',
			__( 'General Settings', 'interlinear' ),
			null,
			'interlinear'
		);

		add_settings_field(
			'interlinear_default_opacity',
			__( 'Default opacity for dimmed content', 'interlinear' ),
			array( __CLASS__, 'render_opacity_field' ),
			'interlinear',
			'interlinear_general'
		);

		add_settings_field(
			'interlinear_persistence',
			__( 'Reader persistence', 'interlinear' ),
			array( __CLASS__, 'render_persistence_field' ),
			'interlinear',
			'interlinear_general'
		);
	}

	/**
	 * Sanitize opacity value.
	 *
	 * @param mixed $value Raw value.
	 * @return float Clamped opacity.
	 */
	public static function sanitize_opacity( $value ) {
		$value = floatval( $value );
		return max( 0.0, min( 1.0, $value ) );
	}

	/**
	 * Render opacity field.
	 */
	public static function render_opacity_field() {
		$value = get_option( 'interlinear_default_opacity', 0.35 );
		printf(
			'<input type="number" name="interlinear_default_opacity" value="%s" min="0" max="1" step="0.05" class="small-text" />
			<p class="description">%s</p>',
			esc_attr( $value ),
			esc_html__( 'Opacity applied to non-matching content when a filter is active (0 = invisible, 1 = fully visible).', 'interlinear' )
		);
	}

	/**
	 * Render persistence field.
	 */
	public static function render_persistence_field() {
		$value = get_option( 'interlinear_persistence', true );
		printf(
			'<label><input type="checkbox" name="interlinear_persistence" value="1" %s /> %s</label>
			<p class="description">%s</p>',
			checked( $value, true, false ),
			esc_html__( 'Enable reader filter persistence sitewide', 'interlinear' ),
			esc_html__( 'When enabled, reader filter selections are saved in their browser across visits.', 'interlinear' )
		);
	}

	/**
	 * Render settings page.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'interlinear_settings' );
				do_settings_sections( 'interlinear' );
				submit_button();
				?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Preset Library', 'interlinear' ); ?></h2>
			<?php self::render_preset_table(); ?>
		</div>
		<?php
	}

	/**
	 * Render preset management table.
	 */
	private static function render_preset_table() {
		$presets = json_decode( get_option( 'interlinear_presets', '{}' ), true );

		if ( empty( $presets ) ) {
			printf( '<p>%s</p>', esc_html__( 'No presets saved yet. Create presets from the post editor.', 'interlinear' ) );
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		printf( '<th>%s</th>', esc_html__( 'Preset Name', 'interlinear' ) );
		printf( '<th>%s</th>', esc_html__( 'Categories', 'interlinear' ) );
		printf( '<th>%s</th>', esc_html__( 'Actions', 'interlinear' ) );
		echo '</tr></thead><tbody>';

		foreach ( $presets as $name => $categories ) {
			$labels = array_map( function ( $cat ) {
				return isset( $cat['label'] ) ? esc_html( $cat['label'] ) : '';
			}, $categories );

			printf(
				'<tr><td>%s</td><td>%s</td><td><button class="button il-delete-preset" data-preset="%s">%s</button></td></tr>',
				esc_html( $name ),
				implode( ', ', $labels ),
				esc_attr( $name ),
				esc_html__( 'Delete', 'interlinear' )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * Register REST routes for preset management.
	 */
	public static function register_rest_routes() {
		register_rest_route( 'interlinear/v1', '/presets', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_get_presets' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_save_preset' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'name'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'categories' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			),
		) );

		register_rest_route( 'interlinear/v1', '/presets/(?P<name>[^/]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'rest_delete_preset' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );
	}

	/**
	 * REST: Get all presets.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_get_presets() {
		$presets = json_decode( get_option( 'interlinear_presets', '{}' ), true );
		return rest_ensure_response( $presets );
	}

	/**
	 * REST: Save a preset.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function rest_save_preset( $request ) {
		$name       = $request->get_param( 'name' );
		$categories = $request->get_param( 'categories' );

		$sanitized = Interlinear_Meta::sanitize_categories( $categories );

		$presets          = json_decode( get_option( 'interlinear_presets', '{}' ), true );
		$presets[ $name ] = json_decode( $sanitized, true );
		update_option( 'interlinear_presets', wp_json_encode( $presets ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * REST: Delete a preset.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function rest_delete_preset( $request ) {
		$name    = sanitize_text_field( $request->get_param( 'name' ) );
		$presets = json_decode( get_option( 'interlinear_presets', '{}' ), true );

		if ( isset( $presets[ $name ] ) ) {
			unset( $presets[ $name ] );
			update_option( 'interlinear_presets', wp_json_encode( $presets ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}
}
