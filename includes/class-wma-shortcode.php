<?php
defined( 'ABSPATH' ) || exit;

class WMA_Shortcode {

	public static function init(): void {
		add_shortcode( 'wma-sendy', [ self::class, 'render' ] );
		add_action( 'wp_ajax_wma_subscribe',        [ self::class, 'ajax_subscribe' ] );
		add_action( 'wp_ajax_nopriv_wma_subscribe', [ self::class, 'ajax_subscribe' ] );
		add_action( 'wp_enqueue_scripts',           [ self::class, 'register_assets' ] );
	}

	public static function register_assets(): void {
		wp_register_script(
			'wma-signup',
			WMA_PLUGIN_URL . 'assets/js/wma-signup.js',
			[ 'jquery' ],
			WMA_VERSION,
			true
		);
		wp_register_style(
			'wma-frontend',
			WMA_PLUGIN_URL . 'assets/css/wma-frontend.css',
			[],
			WMA_VERSION
		);
	}

	public static function render( array $atts ): string {
		$atts = shortcode_atts( [
			'id'             => 'wma-form',
			'list'           => '',
			'redirect'       => '',
			'coupon_percent' => '0',
			'coupon_expiry'  => '30',
		], $atts, 'wma-sendy' );

		if ( empty( $atts['list'] ) ) {
			return '';
		}

		wp_enqueue_script( 'wma-signup' );
		wp_enqueue_style( 'wma-frontend' );

		$form_id  = esc_attr( sanitize_html_class( $atts['id'] ) );
		$js_key   = 'wma_cfg_' . str_replace( '-', '_', sanitize_key( $atts['id'] ) );
		$cf_site  = WMA_Settings::get( 'sendy.cf_site_key' ) ?? '';

		wp_localize_script( 'wma-signup', $js_key, [
			'ajax_url'       => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'wma_subscribe' ),
			'list'           => $atts['list'],
			'redirect'       => $atts['redirect'],
			'coupon_percent' => $atts['coupon_percent'],
			'coupon_expiry'  => $atts['coupon_expiry'],
			'form_id'        => $form_id,
			'error_message'  => __( 'An error occurred. Please try again.', 'woo-marketing-automation' ),
		] );

		if ( $cf_site ) {
			wp_enqueue_script(
				'cf-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js',
				[],
				null,
				true
			);
		}

		ob_start();
		?>
		<div class="wma-form-wrap" id="<?php echo esc_attr( $form_id ); ?>-wrap">
			<form id="<?php echo esc_attr( $form_id ); ?>-form" class="wma-sendy-form" data-config="<?php echo esc_attr( $js_key ); ?>" novalidate>
				<div class="wma-field">
					<label for="<?php echo esc_attr( $form_id ); ?>-name"><?php esc_html_e( 'Name', 'woo-marketing-automation' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $form_id ); ?>-name" name="name" placeholder="<?php esc_attr_e( 'Your name', 'woo-marketing-automation' ); ?>">
				</div>
				<div class="wma-field">
					<label for="<?php echo esc_attr( $form_id ); ?>-email"><?php esc_html_e( 'Email address', 'woo-marketing-automation' ); ?> *</label>
					<input type="email" id="<?php echo esc_attr( $form_id ); ?>-email" name="email" required placeholder="<?php esc_attr_e( 'your@email.com', 'woo-marketing-automation' ); ?>">
				</div>
				<?php if ( $cf_site ) : ?>
				<div class="wma-field">
					<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $cf_site ); ?>"></div>
				</div>
				<?php endif; ?>
				<div class="wma-field">
					<button type="submit" class="wma-submit"><?php esc_html_e( 'Subscribe', 'woo-marketing-automation' ); ?></button>
				</div>
				<div id="<?php echo esc_attr( $form_id ); ?>-status" class="wma-status" role="status" aria-live="polite"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function ajax_subscribe(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ?? '' ) ), 'wma_subscribe' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'woo-marketing-automation' ) ] );
		}

		$email          = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$name           = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$list_id        = sanitize_text_field( wp_unslash( $_POST['list'] ?? '' ) );
		$redirect       = esc_url_raw( wp_unslash( $_POST['redirect'] ?? '' ) );
		$coupon_percent = absint( $_POST['coupon_percent'] ?? 0 );
		$coupon_expiry  = absint( $_POST['coupon_expiry'] ?? 30 );

		if ( ! is_email( $email ) || ! $list_id ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'woo-marketing-automation' ) ] );
		}

		$cf_secret = WMA_Settings::get( 'sendy.cf_secret_key' ) ?? '';
		if ( $cf_secret ) {
			$token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ?? '' ) );
			if ( ! self::verify_turnstile( $cf_secret, $token ) ) {
				wp_send_json_error( [ 'message' => __( 'CAPTCHA verification failed. Please try again.', 'woo-marketing-automation' ) ] );
			}
		}

		WMA_Sendy::subscribe_async( $email, $name, $list_id );

		$welcome_subject = WMA_Settings::get( 'welcome_email.subject' ) ?? '';
		$welcome_message = WMA_Settings::get( 'welcome_email.message' ) ?? '';

		if ( $coupon_percent > 0 ) {
			$code = WMA_Coupon::create_percent( $email, $coupon_percent, $coupon_expiry );
			if ( $code && $welcome_subject ) {
				WMA_Email::send( $email, $name, $welcome_subject, [
					'message'            => $welcome_message,
					'coupon_percent_code' => $code,
					'list_id'            => $list_id,
				] );
			}
		} elseif ( $welcome_subject ) {
			WMA_Email::send( $email, $name, $welcome_subject, [
				'message' => $welcome_message,
				'list_id' => $list_id,
			] );
		}

		wp_send_json_success( [
			'message'  => __( 'Thank you! You are now subscribed.', 'woo-marketing-automation' ),
			'redirect' => $redirect,
		] );
	}

	private static function verify_turnstile( string $secret, string $token ): bool {
		if ( ! $token ) {
			return false;
		}
		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
			'timeout' => 15,
			'body'    => [ 'secret' => $secret, 'response' => $token ],
		] );
		if ( is_wp_error( $response ) ) {
			WMA_Logger::log( 'Turnstile verify error: ' . $response->get_error_message(), 'ERROR' );
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $data['success'] ) && $data['success'] === true;
	}
}
