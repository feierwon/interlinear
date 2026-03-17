<?php
/**
 * Post-activation setup wizard.
 *
 * @package Interlinear
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Interlinear_Wizard {

	/**
	 * Initialize wizard hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_submission' ) );
	}

	/**
	 * Register the hidden wizard page.
	 */
	public static function register_page() {
		add_submenu_page(
			'', // No parent — hidden page.
			__( 'Interlinear Setup', 'interlinear' ),
			'',
			'manage_options',
			'interlinear_setup',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Redirect to wizard on first activation.
	 */
	public static function maybe_redirect() {
		if ( ! get_transient( 'interlinear_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'interlinear_activation_redirect' );

		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=interlinear_setup' ) );
		exit;
	}

	/**
	 * Handle wizard form submission.
	 */
	public static function handle_submission() {
		if ( ! isset( $_POST['interlinear_wizard_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['interlinear_wizard_nonce'], 'interlinear_wizard' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$source = isset( $_POST['interlinear_color_source'] )
			? sanitize_text_field( $_POST['interlinear_color_source'] )
			: 'default';

		if ( ! in_array( $source, array( 'theme', 'custom', 'default' ), true ) ) {
			$source = 'default';
		}

		update_option( 'interlinear_color_source', $source );

		if ( 'custom' === $source && isset( $_POST['interlinear_custom_focus_color'] ) ) {
			$color = sanitize_text_field( $_POST['interlinear_custom_focus_color'] );
			if ( preg_match( '/^#[0-9a-f]{6}$/i', $color ) ) {
				update_option( 'interlinear_custom_focus_color', $color );
			}
		}

		update_option( 'interlinear_wizard_complete', true );

		wp_safe_redirect( admin_url( 'options-general.php?page=interlinear' ) );
		exit;
	}

	/**
	 * Render the wizard page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// If wizard already completed, redirect to settings.
		if ( get_option( 'interlinear_wizard_complete' ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=interlinear' ) );
			exit;
		}

		$source  = get_option( 'interlinear_color_source', 'default' );
		$custom  = get_option( 'interlinear_custom_focus_color', '' );
		$default = '#007cba';

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
		<style>
			.interlinear-wizard { max-width: 600px; margin: 40px auto; background: #fff; padding: 32px; border: 1px solid #ddd; border-radius: 4px; }
			.interlinear-wizard h1 { margin-top: 0; }
			.interlinear-wizard fieldset label { display: block; margin-bottom: 8px; }
			.interlinear-wizard .submit { display: flex; gap: 12px; align-items: center; }
		</style>
		<div class="wrap interlinear-wizard">
			<h1><?php esc_html_e( 'Welcome to Interlinear', 'interlinear' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Quick setup — takes under a minute.', 'interlinear' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'interlinear_wizard', 'interlinear_wizard_nonce' ); ?>

				<h3><?php esc_html_e( 'Focus color', 'interlinear' ); ?></h3>
				<fieldset>
					<label>
						<input type="radio" name="interlinear_color_source" value="theme" <?php checked( $source, 'theme' ); ?> <?php disabled( ! $theme_color ); ?> />
						<?php echo esc_html( $theme_label ); ?>
						<?php if ( $theme_color ) : ?>
							<span style="display:inline-block;width:14px;height:14px;background:<?php echo esc_attr( $theme_color ); ?>;border-radius:3px;vertical-align:middle;margin-left:4px;border:1px solid rgba(0,0,0,.15);"></span>
						<?php endif; ?>
					</label>
					<label>
						<input type="radio" name="interlinear_color_source" value="custom" <?php checked( $source, 'custom' ); ?> />
						<?php esc_html_e( 'Custom color', 'interlinear' ); ?>
						<input type="color" name="interlinear_custom_focus_color" value="<?php echo esc_attr( $custom ? $custom : $default ); ?>"
							style="vertical-align:middle;margin-left:4px;" />
					</label>
					<label>
						<input type="radio" name="interlinear_color_source" value="default" <?php checked( $source, 'default' ); ?> />
						<?php printf( esc_html__( 'Plugin default (%s)', 'interlinear' ), esc_html( $default ) ); ?>
						<span style="display:inline-block;width:14px;height:14px;background:<?php echo esc_attr( $default ); ?>;border-radius:3px;vertical-align:middle;margin-left:4px;border:1px solid rgba(0,0,0,.15);"></span>
					</label>
				</fieldset>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save & Finish', 'interlinear' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=interlinear' ) ); ?>" class="button button-link"><?php esc_html_e( 'Skip', 'interlinear' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}
}
