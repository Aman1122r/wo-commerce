<?php
/**
 * OminiFlow authentication configuration (read-only; no secrets stored here).
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\OminiFlow;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves OminiFlow auth configuration from WordPress filters.
 */
class Auth_Config {

	/** @var string Default OminiFlow auth API base URL. */
	const DEFAULT_AUTH_API_BASE_URL = 'https://whatsapp.ominiflow.com/api/v1/auth';

	/**
	 * Whether OminiFlow authentication is configured for production use.
	 *
	 * Auth is considered configured when either:
	 * - `wc_ominiflow_auth_api_base_url` returns a non-empty URL, or
	 * - `wc_ominiflow_auth_is_configured` filter returns true (custom handler).
	 */
	public static function is_configured(): bool {
		if ( '' !== self::get_api_base_url() ) {
			return true;
		}

		/**
		 * Filter whether OminiFlow auth is fully configured.
		 *
		 * Return true when a custom auth handler is registered via
		 * `wc_ominiflow_auth_api_request` and ready for production use.
		 *
		 * @param bool $configured Default false.
		 */
		return (bool) apply_filters( 'wc_ominiflow_auth_is_configured', false );
	}

	/**
	 * Returns the OminiFlow auth API base URL.
	 */
	public static function get_api_base_url(): string {
		/**
		 * Filter the OminiFlow auth API base URL.
		 *
		 * Example: `https://app.ominiflow.com/api/v1/auth`
		 *
		 * @param string $base_url Default empty.
		 */
		return trim( (string) apply_filters( 'wc_ominiflow_auth_api_base_url', self::DEFAULT_AUTH_API_BASE_URL ) );
	}

	/**
	 * Returns the signup endpoint path appended to the base URL.
	 */
	public static function get_signup_path(): string {
		/**
		 * Filter the OminiFlow signup endpoint path.
		 *
		 * @param string $path Default `signup`.
		 */
		return trim( (string) apply_filters( 'wc_ominiflow_auth_signup_path', 'signup' ), '/' );
	}

	/**
	 * Returns the login endpoint path appended to the base URL.
	 */
	public static function get_login_path(): string {
		/**
		 * Filter the OminiFlow login endpoint path.
		 *
		 * @param string $path Default `login`.
		 */
		return trim( (string) apply_filters( 'wc_ominiflow_auth_login_path', 'login' ), '/' );
	}

	/**
	 * Returns the forgot-password URL if configured.
	 */
	public static function get_forgot_password_url(): string {
		/**
		 * Filter the OminiFlow forgot-password page URL.
		 *
		 * Must be a real, externally hosted reset page. Empty by default.
		 *
		 * @param string $url Default empty.
		 */
		return trim( (string) apply_filters( 'wc_ominiflow_forgot_password_url', '' ) );
	}

	/**
	 * Whether forgot-password navigation is available.
	 */
	public static function has_forgot_password_url(): bool {
		return '' !== self::get_forgot_password_url();
	}

	/**
	 * Message shown when auth is not configured.
	 */
	public static function get_not_configured_message(): string {
		return __( 'OminiFlow authentication is not configured. Signup and login cannot complete until the OminiFlow auth API is connected. See OMINIFLOW_AUTH_API_CONTRACT.md.', 'facebook-for-woocommerce' );
	}
}
