<?php
/**
 * OminiFlow onboarding auth gate (UI layer only).
 *
 * Gates the Meta Commerce Partner Hub iframe behind OminiFlow-branded
 * signup/login screens. Does not replace WordPress admin auth, Meta OAuth,
 * or existing token storage.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\OminiFlow;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/Auth_Config.php';
require_once __DIR__ . '/Integrations_Config.php';

/**
 * Handles OminiFlow signup/login gate state for the Shops onboarding screen.
 */
class Auth_Gate {

	/** @var string User meta key for gate completion (new key — does not replace existing options). */
	const USER_META_AUTHENTICATED = 'wc_ominiflow_onboarding_authenticated';

	/** @var string AJAX action for signup. */
	const AJAX_SIGNUP = 'wc_ominiflow_signup';

	/** @var string AJAX action for login. */
	const AJAX_LOGIN = 'wc_ominiflow_login';

	/**
	 * Registers AJAX handlers.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_' . self::AJAX_SIGNUP, array( __CLASS__, 'handle_signup' ) );
		add_action( 'wp_ajax_' . self::AJAX_LOGIN, array( __CLASS__, 'handle_login' ) );
	}

	/**
	 * Whether the OminiFlow auth gate should appear before the Meta iframe.
	 *
	 * @param bool $is_connected           Whether Meta is connected.
	 * @param bool $connection_invalid     Whether the connection token is invalid.
	 * @param bool $has_merchant_token     Whether a merchant access token exists.
	 * @return bool
	 */
	public static function should_show_auth_gate( bool $is_connected, bool $connection_invalid, bool $has_merchant_token ): bool {
		if ( ! self::should_gate_by_connection_state( $is_connected, $connection_invalid, $has_merchant_token ) ) {
			return false;
		}

		/**
		 * Filter whether the OminiFlow auth gate is shown on the Shops screen.
		 *
		 * @param bool $show_gate            Default visibility.
		 * @param bool $is_connected         Meta connection state.
		 * @param bool $connection_invalid   Invalid token flag.
		 * @param bool $has_merchant_token   Merchant token presence.
		 */
		$show_gate = (bool) apply_filters(
			'wc_ominiflow_show_auth_gate',
			true,
			$is_connected,
			$connection_invalid,
			$has_merchant_token
		);

		if ( ! $show_gate ) {
			return false;
		}

		return ! self::is_authenticated();
	}

	/**
	 * Whether the OminiFlow auth gate should appear on the WhatsApp screen.
	 *
	 * @param bool $is_connected WhatsApp connection state.
	 * @return bool
	 */
	public static function should_show_whatsapp_auth_gate( bool $is_connected ): bool {
		if ( $is_connected ) {
			return false;
		}

		if ( ! Integrations_Config::is_credential_sync_enabled() ) {
			return false;
		}

		return ! self::is_authenticated();
	}

	/**
	 * Determines whether connection state requires the OminiFlow gate.
	 *
	 * @param bool $is_connected       Meta connection state.
	 * @param bool $connection_invalid Invalid token flag.
	 * @param bool $has_merchant_token Merchant token presence.
	 * @return bool
	 */
	private static function should_gate_by_connection_state( bool $is_connected, bool $connection_invalid, bool $has_merchant_token ): bool {
		// Reconnect after invalid token: preserve existing Meta reconnect flow.
		if ( $connection_invalid && $has_merchant_token ) {
			return false;
		}

		// Already connected stores must never be gated (management iframe path).
		if ( $is_connected && $has_merchant_token && ! $connection_invalid ) {
			return false;
		}

		// New / unconnected stores only.
		return ! $is_connected && ! $has_merchant_token;
	}

	/**
	 * Whether the current admin user has passed the OminiFlow gate via real auth.
	 *
	 * @return bool
	 */
	public static function is_authenticated(): bool {
		if ( ! Auth_Config::is_configured() ) {
			return false;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		return (bool) get_user_meta( $user_id, self::USER_META_AUTHENTICATED, true );
	}

	/**
	 * Marks the current user as having passed the OminiFlow gate.
	 *
	 * @param bool $remember Whether remember-me was selected.
	 */
	private static function mark_authenticated( bool $remember = false ): void {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		update_user_meta( $user_id, self::USER_META_AUTHENTICATED, '1' );

		if ( $remember ) {
			update_user_meta( $user_id, 'wc_ominiflow_remember_me', '1' );
		} else {
			delete_user_meta( $user_id, 'wc_ominiflow_remember_me' );
		}
	}

	/**
	 * Handles signup AJAX.
	 */
	public static function handle_signup(): void {
		self::verify_ajax_request();

		if ( ! Auth_Config::is_configured() ) {
			wp_send_json_error(
				array(
					'message' => Auth_Config::get_not_configured_message(),
					'code'    => 'wc_ominiflow_auth_not_configured',
				),
				503
			);
		}

		$full_name        = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone_country    = isset( $_POST['phone_country'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_country'] ) ) : '';
		$phone            = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$password         = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$confirm_password = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : '';
		$terms_accepted   = ! empty( $_POST['terms_accepted'] );

		$errors = self::validate_signup_fields( $full_name, $email, $phone, $password, $confirm_password, $terms_accepted );

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => implode( ' ', $errors ),
				),
				400
			);
		}

		$credentials = array(
			'full_name'     => $full_name,
			'email'         => $email,
			'phone_country' => $phone_country,
			'phone'         => $phone,
		);

		$remote_result = Auth_Client::signup( $credentials, $password );

		if ( is_wp_error( $remote_result ) ) {
			wp_send_json_error(
				array(
					'message' => $remote_result->get_error_message(),
					'code'    => $remote_result->get_error_code(),
				),
				400
			);
		}

		self::mark_authenticated( false );

		/**
		 * Fires after a successful OminiFlow signup.
		 *
		 * @param array $credentials   Sanitized signup fields (no password).
		 * @param array $remote_result Remote API response.
		 */
		do_action( 'wc_ominiflow_signup_complete', $credentials, $remote_result );

		wp_send_json_success(
			array(
				'message' => __( 'Account created. Connecting to Meta…', 'facebook-for-woocommerce' ),
			)
		);
	}

	/**
	 * Handles login AJAX.
	 */
	public static function handle_login(): void {
		self::verify_ajax_request();

		if ( ! Auth_Config::is_configured() ) {
			wp_send_json_error(
				array(
					'message' => Auth_Config::get_not_configured_message(),
					'code'    => 'wc_ominiflow_auth_not_configured',
				),
				503
			);
		}

		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$remember = ! empty( $_POST['remember_me'] );

		$errors = self::validate_login_fields( $email, $password );

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => implode( ' ', $errors ),
				),
				400
			);
		}

		$credentials = array(
			'email' => $email,
		);

		$remote_result = Auth_Client::login( $credentials, $password );

		if ( is_wp_error( $remote_result ) ) {
			wp_send_json_error(
				array(
					'message' => $remote_result->get_error_message(),
					'code'    => $remote_result->get_error_code(),
				),
				400
			);
		}

		self::mark_authenticated( $remember );

		/**
		 * Fires after a successful OminiFlow login.
		 *
		 * @param array $credentials   Sanitized login fields (no password).
		 * @param array $remote_result Remote API response.
		 */
		do_action( 'wc_ominiflow_login_complete', $credentials, $remote_result );

		wp_send_json_success(
			array(
				'message' => __( 'Signed in. Connecting to Meta…', 'facebook-for-woocommerce' ),
			)
		);
	}

	/**
	 * Verifies AJAX nonce and capability.
	 */
	private static function verify_ajax_request(): void {
		check_ajax_referer( 'wc_ominiflow_auth', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'facebook-for-woocommerce' ),
				),
				403
			);
		}
	}

	/**
	 * Validates signup fields.
	 *
	 * @param string $full_name        Full name.
	 * @param string $email            Email address.
	 * @param string $phone            Phone number.
	 * @param string $password         Password.
	 * @param string $confirm_password Confirm password.
	 * @param bool   $terms_accepted   Terms acceptance.
	 * @return string[] Error messages.
	 */
	private static function validate_signup_fields( string $full_name, string $email, string $phone, string $password, string $confirm_password, bool $terms_accepted ): array {
		$errors = array();

		if ( '' === $full_name ) {
			$errors[] = __( 'Full name is required.', 'facebook-for-woocommerce' );
		}

		if ( ! is_email( $email ) ) {
			$errors[] = __( 'A valid email address is required.', 'facebook-for-woocommerce' );
		}

		if ( '' === $phone ) {
			$errors[] = __( 'WhatsApp phone number is required.', 'facebook-for-woocommerce' );
		}

		if ( strlen( $password ) < 8 ) {
			$errors[] = __( 'Password must be at least 8 characters.', 'facebook-for-woocommerce' );
		}

		if ( $password !== $confirm_password ) {
			$errors[] = __( 'Passwords do not match.', 'facebook-for-woocommerce' );
		}

		if ( ! $terms_accepted ) {
			$errors[] = __( 'You must accept the Terms of Service and Privacy Policy.', 'facebook-for-woocommerce' );
		}

		/**
		 * Filter signup validation errors.
		 *
		 * @param string[] $errors     Validation errors.
		 * @param string   $full_name  Full name.
		 * @param string   $email      Email.
		 * @param string   $phone      Phone.
		 */
		return (array) apply_filters( 'wc_ominiflow_signup_validation_errors', $errors, $full_name, $email, $phone );
	}

	/**
	 * Validates login fields.
	 *
	 * @param string $email    Email address.
	 * @param string $password Password.
	 * @return string[] Error messages.
	 */
	private static function validate_login_fields( string $email, string $password ): array {
		$errors = array();

		if ( ! is_email( $email ) ) {
			$errors[] = __( 'A valid email address is required.', 'facebook-for-woocommerce' );
		}

		if ( '' === $password ) {
			$errors[] = __( 'Password is required.', 'facebook-for-woocommerce' );
		}

		/**
		 * Filter login validation errors.
		 *
		 * @param string[] $errors Validation errors.
		 * @param string   $email  Email.
		 */
		return (array) apply_filters( 'wc_ominiflow_login_validation_errors', $errors, $email );
	}
}
