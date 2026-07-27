<?php
/**
 * OminiFlow Integrations API client (server-side only).
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\OminiFlow;

defined( 'ABSPATH' ) || exit;

/**
 * Calls OminiFlow Integrations API endpoints.
 */
class Integrations_Client {

	/**
	 * Fetches Meta/WhatsApp credentials for the bound store.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_credentials() {
		return self::request( 'GET', '/integrations/woocommerce/credentials' );
	}

	/**
	 * Refreshes credentials from OminiFlow.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function refresh_credentials() {
		return self::request( 'POST', '/integrations/woocommerce/credentials/refresh', array(
			'site_url' => home_url( '/' ),
		) );
	}

	/**
	 * Returns integration health status.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_status() {
		return self::request( 'GET', '/integrations/woocommerce/status' );
	}

	/**
	 * Unbinds the store from OminiFlow.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function disconnect() {
		return self::request( 'DELETE', '/integrations/woocommerce/disconnect', array(
			'site_url' => home_url( '/' ),
		) );
	}

	/**
	 * Attempts to refresh the OminiFlow access token.
	 *
	 * @return bool
	 */
	public static function maybe_refresh_access_token(): bool {
		if ( ! Token_Store::is_expiring_soon() ) {
			return true;
		}

		$refresh_token = Token_Store::get_refresh_token();

		if ( '' === $refresh_token ) {
			return false;
		}

		$endpoint = trailingslashit( untrailingslashit( Auth_Config::get_api_base_url() ) ) . 'refresh';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'refresh_token' => $refresh_token,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 || ! is_array( $body ) || empty( $body['success'] ) ) {
			return false;
		}

		Token_Store::save_from_response( $body );

		return true;
	}

	/**
	 * Executes an integrations API request.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path   Endpoint path.
	 * @param array<string,mixed>  $body   Optional JSON body.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function request( string $method, string $path, array $body = array() ) {
		if ( ! Integrations_Config::is_credential_sync_enabled() ) {
			return new \WP_Error(
				'wc_ominiflow_sync_disabled',
				__( 'OminiFlow credential sync is disabled.', 'facebook-for-woocommerce' )
			);
		}

		if ( ! self::maybe_refresh_access_token() && '' === Token_Store::get_access_token() ) {
			return new \WP_Error(
				'wc_ominiflow_session_expired',
				__( 'Session expired. Please log in again.', 'facebook-for-woocommerce' )
			);
		}

		$access_token = Token_Store::get_access_token();

		if ( '' === $access_token ) {
			return new \WP_Error(
				'wc_ominiflow_not_authenticated',
				__( 'Please sign in to OminiFlow first.', 'facebook-for-woocommerce' )
			);
		}

		$endpoint = Integrations_Config::get_api_base_url() . $path;
		$args     = array(
			'timeout' => 20,
			'headers' => array(
				'Authorization'        => 'Bearer ' . $access_token,
				'Accept'               => 'application/json',
				'Content-Type'         => 'application/json',
				'X-OminiFlow-Site-Url' => home_url( '/' ),
				'X-OminiFlow-Source'   => 'woocommerce',
			),
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		if ( 'GET' === strtoupper( $method ) ) {
			$response = wp_remote_get( $endpoint, $args );
		} elseif ( 'DELETE' === strtoupper( $method ) ) {
			$args['method'] = 'DELETE';
			$response       = wp_remote_request( $endpoint, $args );
		} else {
			$args['method'] = strtoupper( $method );
			$response       = wp_remote_request( $endpoint, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$payload     = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = is_array( $payload ) && ! empty( $payload['message'] )
				? (string) $payload['message']
				: __( 'OminiFlow integration request failed.', 'facebook-for-woocommerce' );

			return new \WP_Error(
				is_array( $payload ) && ! empty( $payload['error_code'] ) ? (string) $payload['error_code'] : 'wc_ominiflow_integration_failed',
				$message,
				array( 'status' => $status_code )
			);
		}

		if ( ! is_array( $payload ) ) {
			return new \WP_Error(
				'wc_ominiflow_invalid_response',
				__( 'OminiFlow returned an invalid response.', 'facebook-for-woocommerce' )
			);
		}

		return $payload;
	}
}
