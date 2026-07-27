<?php
/**
 * OminiFlow authentication client (server-side only).
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\OminiFlow;

defined( 'ABSPATH' ) || exit;

/**
 * Performs OminiFlow signup/login requests when configured.
 */
class Auth_Client {

	/**
	 * Registers a new OminiFlow account.
	 *
	 * @param array<string,string> $credentials Sanitized signup fields (no password).
	 * @param string               $password    Password (never stored by this plugin).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function signup( array $credentials, string $password ) {
		return self::request( 'signup', Auth_Config::get_signup_path(), $credentials, $password );
	}

	/**
	 * Authenticates an existing OminiFlow account.
	 *
	 * @param array<string,string> $credentials Sanitized login fields (no password).
	 * @param string               $password    Password (never stored by this plugin).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function login( array $credentials, string $password ) {
		return self::request( 'login', Auth_Config::get_login_path(), $credentials, $password );
	}

	/**
	 * Executes an auth request when configuration is present.
	 *
	 * @param string               $action      signup|login.
	 * @param string               $path        Endpoint path.
	 * @param array<string,string> $credentials Sanitized credentials.
	 * @param string               $password    Password.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function request( string $action, string $path, array $credentials, string $password ) {
		if ( ! Auth_Config::is_configured() ) {
			return new \WP_Error(
				'wc_ominiflow_auth_not_configured',
				Auth_Config::get_not_configured_message()
			);
		}

		$base_url = Auth_Config::get_api_base_url();

		/**
		 * Filter the remote OminiFlow auth request before it is sent.
		 *
		 * Return an array response on success or WP_Error on failure.
		 * When this filter returns non-null, the default HTTP client is skipped.
		 *
		 * @param null|\WP_Error|array $result      Default null.
		 * @param string               $action      signup|login.
		 * @param array                $credentials Sanitized credentials.
		 * @param string               $password    Password.
		 * @param string               $base_url    API base URL (may be empty when using custom handler).
		 */
		$filtered = apply_filters( 'wc_ominiflow_auth_api_request', null, $action, $credentials, $password, $base_url );

		if ( null !== $filtered ) {
			if ( is_wp_error( $filtered ) ) {
				return $filtered;
			}

			if ( is_array( $filtered ) ) {
				return $filtered;
			}

			return new \WP_Error(
				'wc_ominiflow_auth_invalid_response',
				__( 'OminiFlow authentication returned an invalid response.', 'facebook-for-woocommerce' )
			);
		}

		if ( '' === $base_url ) {
			return new \WP_Error(
				'wc_ominiflow_auth_not_configured',
				Auth_Config::get_not_configured_message()
			);
		}

		$endpoint = trailingslashit( untrailingslashit( $base_url ) ) . $path;

		$payload = array_merge(
			$credentials,
			array(
				'password' => $password,
				'source'   => 'woocommerce',
				'site_url' => home_url( '/' ),
			)
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = is_array( $body ) && ! empty( $body['message'] )
				? (string) $body['message']
				: __( 'OminiFlow authentication failed. Please try again.', 'facebook-for-woocommerce' );

			return new \WP_Error( 'wc_ominiflow_auth_failed', $message, array( 'status' => $status_code ) );
		}

		if ( ! is_array( $body ) ) {
			return new \WP_Error(
				'wc_ominiflow_auth_invalid_response',
				__( 'OminiFlow authentication returned an invalid response.', 'facebook-for-woocommerce' )
			);
		}

		return $body;
	}
}
