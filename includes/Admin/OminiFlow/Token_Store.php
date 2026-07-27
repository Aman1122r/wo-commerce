<?php
/**
 * Encrypted OminiFlow session token storage.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\OminiFlow;

defined( 'ABSPATH' ) || exit;

/**
 * Persists OminiFlow bearer and refresh tokens in wp_options.
 */
class Token_Store {

	const OPTION_ACCESS_TOKEN   = 'wc_ominiflow_access_token';
	const OPTION_REFRESH_TOKEN  = 'wc_ominiflow_refresh_token';
	const OPTION_USER_ID        = 'wc_ominiflow_user_id';
	const OPTION_TOKEN_EXPIRES  = 'wc_ominiflow_token_expires_at';
	const OPTION_STORE_BOUND    = 'wc_ominiflow_store_bound';

	/**
	 * Saves tokens from an auth or refresh response.
	 *
	 * @param array<string,mixed> $response Remote auth/refresh payload.
	 */
	public static function save_from_response( array $response ): void {
		if ( ! empty( $response['access_token'] ) ) {
			update_option( self::OPTION_ACCESS_TOKEN, self::encrypt( (string) $response['access_token'] ) );
		}

		if ( ! empty( $response['refresh_token'] ) ) {
			update_option( self::OPTION_REFRESH_TOKEN, self::encrypt( (string) $response['refresh_token'] ) );
		}

		if ( ! empty( $response['user_id'] ) ) {
			update_option( self::OPTION_USER_ID, sanitize_text_field( (string) $response['user_id'] ) );
		}

		$expires_in = isset( $response['expires_in'] ) ? (int) $response['expires_in'] : 3600;
		update_option( self::OPTION_TOKEN_EXPIRES, time() + max( 60, $expires_in ) );
	}

	public static function get_access_token(): string {
		$stored = get_option( self::OPTION_ACCESS_TOKEN, '' );

		return is_string( $stored ) && '' !== $stored ? self::decrypt( $stored ) : '';
	}

	public static function get_refresh_token(): string {
		$stored = get_option( self::OPTION_REFRESH_TOKEN, '' );

		return is_string( $stored ) && '' !== $stored ? self::decrypt( $stored ) : '';
	}

	public static function is_expiring_soon( int $buffer_seconds = 300 ): bool {
		$expires_at = (int) get_option( self::OPTION_TOKEN_EXPIRES, 0 );

		if ( $expires_at <= 0 ) {
			return false;
		}

		return time() >= ( $expires_at - $buffer_seconds );
	}

	public static function mark_store_bound( bool $bound = true ): void {
		update_option( self::OPTION_STORE_BOUND, $bound ? 'yes' : 'no' );
	}

	public static function is_store_bound(): bool {
		return 'yes' === get_option( self::OPTION_STORE_BOUND, 'no' );
	}

	public static function clear(): void {
		delete_option( self::OPTION_ACCESS_TOKEN );
		delete_option( self::OPTION_REFRESH_TOKEN );
		delete_option( self::OPTION_USER_ID );
		delete_option( self::OPTION_TOKEN_EXPIRES );
		delete_option( self::OPTION_STORE_BOUND );
	}

	private static function encrypt( string $value ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $value );
		}

		$key    = self::encryption_key();
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return base64_encode( $value );
		}

		return base64_encode( $iv . $cipher );
	}

	private static function decrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$decoded = base64_decode( $value, true );

		if ( false === $decoded ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) || strlen( $decoded ) <= 16 ) {
			return is_string( base64_decode( $value, true ) ) ? (string) base64_decode( $value, true ) : '';
		}

		$iv     = substr( $decoded, 0, 16 );
		$cipher = substr( $decoded, 16 );
		$key    = self::encryption_key();
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}

	private static function encryption_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . 'wc_ominiflow', true );
	}
}
