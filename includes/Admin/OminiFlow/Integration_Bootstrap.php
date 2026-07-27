<?php
/**
 * Bootstraps OminiFlow credential sync hooks and AJAX handlers.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\OminiFlow;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/Integrations_Config.php';
require_once __DIR__ . '/Token_Store.php';
require_once __DIR__ . '/Integrations_Client.php';
require_once __DIR__ . '/Credential_Sync.php';

/**
 * Registers integration hooks for the OminiFlow plugin layer.
 */
class Integration_Bootstrap {

	const AJAX_SYNC_CREDENTIALS = 'wc_ominiflow_sync_credentials';

	/** @var bool */
	private static $initialized = false;

	/**
	 * Initializes integration hooks.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		Auth_Gate::init();

		add_action( 'wc_ominiflow_login_complete', array( __CLASS__, 'handle_auth_complete' ), 10, 2 );
		add_action( 'wc_ominiflow_signup_complete', array( __CLASS__, 'handle_auth_complete' ), 10, 2 );
		add_action( 'wp_ajax_' . self::AJAX_SYNC_CREDENTIALS, array( __CLASS__, 'handle_sync_credentials' ) );
		add_action( 'wc_facebook_disconnected', array( __CLASS__, 'handle_disconnect' ), 10, 0 );
	}

	/**
	 * Persists tokens after successful auth.
	 *
	 * @param array<string,string> $credentials   Sanitized credentials.
	 * @param array<string,mixed>  $remote_result Remote auth response.
	 */
	public static function handle_auth_complete( array $credentials, array $remote_result ): void {
		if ( empty( $remote_result['access_token'] ) ) {
		 return;
		}

		Token_Store::save_from_response( $remote_result );
	}

	/**
	 * AJAX handler for credential sync after auth gate success.
	 */
	public static function handle_sync_credentials(): void {
		check_ajax_referer( 'wc_ominiflow_auth', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'facebook-for-woocommerce' ),
				),
				403
			);
		}

		if ( ! Integrations_Config::is_credential_sync_enabled() ) {
			wp_send_json_success(
				array(
					'sync_enabled'         => false,
					'credentials_complete' => false,
					'use_meta_iframe'      => true,
				)
			);
		}

		$result = Credential_Sync::fetch_and_apply();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Notifies OminiFlow when the store disconnects locally.
	 */
	public static function handle_disconnect(): void {
		if ( ! Integrations_Config::is_credential_sync_enabled() ) {
			Token_Store::clear();
			return;
		}

		Integrations_Client::disconnect();
		Token_Store::clear();
	}
}
