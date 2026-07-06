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
		register_setting( 'interlinear_settings', 'interlinear_color_source', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_color_source' ),
			'default'           => 'default',
			'show_in_rest'      => true,
		) );

		register_setting( 'interlinear_settings', 'interlinear_custom_focus_color', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_hex_color' ),
			'default'           => '',
			'show_in_rest'      => true,
		) );

		add_settings_section(
			'interlinear_color',
			__( 'Color', 'interlinear' ),
			null,
			'interlinear'
		);

		add_settings_field(
			'interlinear_color_source',
			__( 'Focus color', 'interlinear' ),
			array( __CLASS__, 'render_color_field' ),
			'interlinear',
			'interlinear_color'
		);

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
	 * Sanitize color source.
	 *
	 * @param string $value Raw value.
	 * @return string Sanitized source.
	 */
	public static function sanitize_color_source( $value ) {
		return in_array( $value, array( 'theme', 'custom', 'default' ), true ) ? $value : 'default';
	}

	/**
	 * Sanitize hex color.
	 *
	 * @param string $value Raw value.
	 * @return string Sanitized hex or empty string.
	 */
	public static function sanitize_hex_color( $value ) {
		if ( preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
			return $value;
		}
		return '';
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
	 * Render focus color compound field (radio + picker + preview).
	 */
	public static function render_color_field() {
		$source  = get_option( 'interlinear_color_source', 'default' );
		$custom  = get_option( 'interlinear_custom_focus_color', '' );
		$default = '#007cba';

		$resolved    = Interlinear_Frontend::resolve_accent_color();
		$hover       = Interlinear_Frontend::darken_hex( $resolved, 12 );
		$translucent = Interlinear_Frontend::hex_to_rgba( $resolved, 0.15 );

		$theme_color = Interlinear_Frontend::auto_pick_from_palette();
		$palette     = Interlinear_Frontend::get_theme_palette();
		$theme_name  = '';
		if ( $theme_color && ! empty( $palette ) ) {
			foreach ( $palette as $entry ) {
				if ( isset( $entry['color'] ) && strtolower( Interlinear_Frontend::normalize_hex( $entry['color'] ) ) === strtolower( $theme_color ) ) {
					$theme_name = isset( $entry['name'] ) ? $entry['name'] : '';
					break;
				}
			}
		}

		$theme_label = $theme_color
			? sprintf(
				__( 'Match active theme — %1$s (%2$s)', 'interlinear' ),
				$theme_name ? $theme_name : __( 'auto-detected', 'interlinear' ),
				$theme_color
			)
			: __( 'Match active theme (no palette detected)', 'interlinear' );

		?>
		<fieldset>
			<label>
				<input type="radio" name="interlinear_color_source" value="theme" <?php checked( $source, 'theme' ); ?> <?php disabled( ! $theme_color ); ?> />
				<?php echo esc_html( $theme_label ); ?>
				<?php if ( $theme_color ) : ?>
					<span style="display:inline-block;width:14px;height:14px;background:<?php echo esc_attr( $theme_color ); ?>;border-radius:3px;vertical-align:middle;margin-left:4px;border:1px solid rgba(0,0,0,.15);"></span>
				<?php endif; ?>
			</label><br />
			<label>
				<input type="radio" name="interlinear_color_source" value="custom" <?php checked( $source, 'custom' ); ?> />
				<?php esc_html_e( 'Custom color', 'interlinear' ); ?>
				<input type="color" name="interlinear_custom_focus_color" value="<?php echo esc_attr( $custom ? $custom : $default ); ?>"
					id="il-custom-color" style="vertical-align:middle;margin-left:4px;" />
			</label><br />
			<label>
				<input type="radio" name="interlinear_color_source" value="default" <?php checked( $source, 'default' ); ?> />
				<?php
				printf(
					esc_html__( 'Plugin default (%s)', 'interlinear' ),
					$default
				);
				?>
				<span style="display:inline-block;width:14px;height:14px;background:<?php echo esc_attr( $default ); ?>;border-radius:3px;vertical-align:middle;margin-left:4px;border:1px solid rgba(0,0,0,.15);"></span>
			</label>
		</fieldset>

		<div id="il-color-preview" style="margin-top:12px;display:flex;gap:8px;align-items:center;">
			<span style="display:inline-block;width:32px;height:32px;background:<?php echo esc_attr( $resolved ); ?>;border-radius:4px;border:1px solid rgba(0,0,0,.15);"></span>
			<span style="display:inline-block;width:32px;height:32px;background:<?php echo esc_attr( $hover ); ?>;border-radius:4px;border:1px solid rgba(0,0,0,.15);"></span>
			<span style="display:inline-block;width:32px;height:32px;background:<?php echo esc_attr( $translucent ); ?>;border-radius:4px;border:1px solid rgba(0,0,0,.15);"></span>
			<span class="description"><?php echo esc_html( $resolved ); ?> — focus, hover, translucent</span>
		</div>

		<script>
		(function() {
			var radios = document.querySelectorAll('input[name="interlinear_color_source"]');
			var picker = document.getElementById('il-custom-color');
			function toggle() {
				var val = document.querySelector('input[name="interlinear_color_source"]:checked');
				picker.disabled = !val || val.value !== 'custom';
			}
			radios.forEach(function(r) { r.addEventListener('change', toggle); });
			toggle();
		})();
		</script>
		<?php
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

			<?php Interlinear_License::render_license_section(); ?>
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
