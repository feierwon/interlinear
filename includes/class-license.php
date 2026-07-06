<?php
/**
 * License management via the Lemon Squeezy License API.
 *
 * Enforcement model: "gate updates only". Interlinear remains fully functional
 * without a license — nothing here disables features. A valid license unlocks
 * automatic updates and support (the update gate lives in class-updater.php).
 *
 * The Lemon Squeezy activate/validate/deactivate endpoints are authenticated by
 * the license key itself, so no secret API key is embedded in the plugin.
 *
 * @package Interlinear
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Interlinear_License {

	/**
	 * Lemon Squeezy product ID this license must belong to.
	 *
	 * TODO: set this to the Interlinear product ID after creating the Lemon
	 * Squeezy store. While 0, product verification is skipped (any valid key is
	 * accepted) so the flow can be exercised before the store exists.
	 */
	const PRODUCT_ID = 0;

	/** Lemon Squeezy License API base (no trailing slash). */
	const API_BASE = 'https://api.lemonsqueezy.com/v1/licenses';

	/** Option keys. */
	const OPT_KEY    = 'interlinear_license_key';
	const OPT_STATUS = 'interlinear_license_status';

	/** Daily re-validation cron hook. */
	const CRON_HOOK = 'interlinear_license_daily_check';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_interlinear_activate_license', array( __CLASS__, 'handle_activate' ) );
		add_action( 'admin_post_interlinear_deactivate_license', array( __CLASS__, 'handle_deactivate' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'scheduled_validate' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_notice' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Get the stored license key.
	 *
	 * @return string
	 */
	public static function get_key() {
		return trim( (string) get_option( self::OPT_KEY, '' ) );
	}

	/**
	 * Get the stored status array: status, instance_id, expires_at, last_check.
	 *
	 * @return array
	 */
	public static function get_status() {
		$status = get_option( self::OPT_STATUS, array() );
		return is_array( $status ) ? $status : array();
	}

	/**
	 * Whether the license is currently active (the only thing that unlocks updates).
	 *
	 * @return bool
	 */
	public static function is_valid() {
		$status = self::get_status();
		return isset( $status['status'] ) && 'active' === $status['status'];
	}

	/**
	 * Handle the activation form submission.
	 */
	public static function handle_activate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'interlinear' ) );
		}
		check_admin_referer( 'interlinear_license' );

		$key = isset( $_POST['interlinear_license_key'] )
			? sanitize_text_field( wp_unslash( $_POST['interlinear_license_key'] ) )
			: '';

		if ( '' === $key ) {
			self::redirect_with( 'empty' );
		}

		$response = self::api_request( 'activate', array(
			'license_key'   => $key,
			'instance_name' => self::instance_name(),
		) );

		if ( is_wp_error( $response ) ) {
			self::redirect_with( 'http_error' );
		}

		if ( empty( $response['activated'] ) ) {
			$detail = isset( $response['error'] ) ? $response['error'] : '';
			self::redirect_with( 'invalid', $detail );
		}

		// Confirm the license belongs to this product (once PRODUCT_ID is set).
		if ( self::PRODUCT_ID
			&& isset( $response['meta']['product_id'] )
			&& (int) $response['meta']['product_id'] !== (int) self::PRODUCT_ID ) {
			self::redirect_with( 'wrong_product' );
		}

		update_option( self::OPT_KEY, $key );
		update_option( self::OPT_STATUS, array(
			'status'      => isset( $response['license_key']['status'] ) ? sanitize_text_field( $response['license_key']['status'] ) : 'active',
			'instance_id' => isset( $response['instance']['id'] ) ? sanitize_text_field( $response['instance']['id'] ) : '',
			'expires_at'  => isset( $response['license_key']['expires_at'] ) ? sanitize_text_field( (string) $response['license_key']['expires_at'] ) : null,
			'last_check'  => time(),
		) );

		self::redirect_with( 'activated' );
	}

	/**
	 * Handle the deactivation form submission.
	 */
	public static function handle_deactivate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'interlinear' ) );
		}
		check_admin_referer( 'interlinear_license' );

		$key      = self::get_key();
		$status   = self::get_status();
		$instance = isset( $status['instance_id'] ) ? $status['instance_id'] : '';

		if ( $key && $instance ) {
			// Best effort — free up the activation seat. Ignore transport errors.
			self::api_request( 'deactivate', array(
				'license_key' => $key,
				'instance_id' => $instance,
			) );
		}

		delete_option( self::OPT_KEY );
		delete_option( self::OPT_STATUS );
		delete_transient( Interlinear_Updater::TRANSIENT );

		self::redirect_with( 'deactivated' );
	}

	/**
	 * Daily cron: re-validate so refunds/expiries/disables are reflected.
	 * On transport failure the last known status is preserved.
	 */
	public static function scheduled_validate() {
		$key      = self::get_key();
		$status   = self::get_status();
		$instance = isset( $status['instance_id'] ) ? $status['instance_id'] : '';

		if ( ! $key || ! $instance ) {
			return;
		}

		$response = self::api_request( 'validate', array(
			'license_key' => $key,
			'instance_id' => $instance,
		) );

		if ( is_wp_error( $response ) ) {
			return;
		}

		$status['status']     = ( ! empty( $response['valid'] ) && isset( $response['license_key']['status'] ) )
			? sanitize_text_field( $response['license_key']['status'] )
			: 'inactive';
		$status['expires_at'] = isset( $response['license_key']['expires_at'] )
			? sanitize_text_field( (string) $response['license_key']['expires_at'] )
			: ( isset( $status['expires_at'] ) ? $status['expires_at'] : null );
		$status['last_check'] = time();

		update_option( self::OPT_STATUS, $status );
	}

	/**
	 * POST to a Lemon Squeezy license endpoint and decode the JSON body.
	 *
	 * @param string $endpoint activate|validate|deactivate.
	 * @param array  $body     Request parameters.
	 * @return array|WP_Error Decoded body, or WP_Error on transport failure.
	 */
	private static function api_request( $endpoint, $body ) {
		$response = wp_remote_post( self::API_BASE . '/' . $endpoint, array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
			'body'    => $body,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : new WP_Error( 'interlinear_bad_response', __( 'Unexpected response from the licensing server.', 'interlinear' ) );
	}

	/**
	 * A stable per-site instance name for the activation record.
	 *
	 * @return string
	 */
	private static function instance_name() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host ? $host : 'wordpress-site';
	}

	/**
	 * Redirect back to the settings page with a result code.
	 *
	 * @param string $code   Result code.
	 * @param string $detail Optional detail message.
	 */
	private static function redirect_with( $code, $detail = '' ) {
		$args = array(
			'page'       => 'interlinear',
			'il_license' => $code,
		);
		if ( '' !== $detail ) {
			$args['il_license_msg'] = rawurlencode( $detail );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Render result feedback and a gentle "activate for updates" reminder.
	 * Per the gate-updates-only model, the reminder appears only on our own
	 * settings page and the Plugins list — never sitewide.
	 */
	public static function maybe_render_notice() {
		// Result feedback after an activate/deactivate round trip.
		if ( isset( $_GET['il_license'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$code    = sanitize_key( wp_unslash( $_GET['il_license'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$detail  = isset( $_GET['il_license_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['il_license_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$success = array( 'activated', 'deactivated' );
			$map     = array(
				'activated'     => __( 'License activated. You will now receive automatic updates.', 'interlinear' ),
				'deactivated'   => __( 'License deactivated.', 'interlinear' ),
				'empty'         => __( 'Please enter a license key.', 'interlinear' ),
				'invalid'       => __( 'That license key could not be activated.', 'interlinear' ),
				'wrong_product' => __( 'That license key is for a different product.', 'interlinear' ),
				'http_error'    => __( 'Could not reach the licensing server. Please try again.', 'interlinear' ),
			);
			if ( isset( $map[ $code ] ) ) {
				$message = $map[ $code ];
				if ( 'invalid' === $code && $detail ) {
					$message .= ' ' . $detail;
				}
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( in_array( $code, $success, true ) ? 'success' : 'error' ),
					esc_html( $message )
				);
			}
		}

		// Gentle reminder, scoped to our settings page and the plugins list.
		$screen = get_current_screen();
		if ( $screen
			&& in_array( $screen->id, array( 'settings_page_interlinear', 'plugins' ), true )
			&& ! self::is_valid() ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				wp_kses(
					sprintf(
						/* translators: %s: settings page URL */
						__( 'Interlinear is active and working. <a href="%s">Activate your license</a> to receive automatic updates and support.', 'interlinear' ),
						esc_url( admin_url( 'options-general.php?page=interlinear' ) )
					),
					array( 'a' => array( 'href' => array() ) )
				)
			);
		}
	}

	/**
	 * Render the License section on the settings page. Called from
	 * Interlinear_Settings::render_settings_page(). Posts to admin-post.php as
	 * its own form (separate from the options.php settings form).
	 */
	public static function render_license_section() {
		$key    = self::get_key();
		$status = self::get_status();
		$valid  = self::is_valid();
		$masked = $key ? str_repeat( '•', max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 ) : '';
		?>
		<hr />
		<h2><?php esc_html_e( 'License', 'interlinear' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Interlinear works fully without a license. Activate your license to receive automatic updates and support.', 'interlinear' ); ?>
		</p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'interlinear_license' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'License key', 'interlinear' ); ?></th>
					<td>
						<?php if ( $valid ) : ?>
							<input type="text" class="regular-text code" value="<?php echo esc_attr( $masked ); ?>" disabled />
							<span style="color:#2271b1;font-weight:600;margin-left:6px;">&#10003; <?php esc_html_e( 'Active', 'interlinear' ); ?></span>
							<?php if ( ! empty( $status['expires_at'] ) ) : ?>
								<p class="description">
									<?php
									/* translators: %s: date */
									printf( esc_html__( 'Renews or expires: %s', 'interlinear' ), esc_html( $status['expires_at'] ) );
									?>
								</p>
							<?php endif; ?>
							<input type="hidden" name="action" value="interlinear_deactivate_license" />
						<?php else : ?>
							<input type="text" name="interlinear_license_key" class="regular-text code" value="" placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX" autocomplete="off" />
							<input type="hidden" name="action" value="interlinear_activate_license" />
							<?php if ( ! empty( $status['status'] ) && 'active' !== $status['status'] ) : ?>
								<p class="description">
									<?php
									/* translators: %s: license status such as expired or disabled */
									printf( esc_html__( 'Current status: %s', 'interlinear' ), esc_html( $status['status'] ) );
									?>
								</p>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php
			submit_button(
				$valid ? __( 'Deactivate license', 'interlinear' ) : __( 'Activate license', 'interlinear' ),
				$valid ? 'secondary' : 'primary'
			);
			?>
		</form>
		<?php
	}
}
