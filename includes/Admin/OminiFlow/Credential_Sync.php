<?php
/**
 * Fetches OminiFlow credentials and applies them to WordPress options.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\OminiFlow;

use WooCommerce\Facebook\Handlers\WhatsAppConnection;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates credential fetch + mapping for WooCommerce stores.
 */
class Credential_Sync {

	/**
	 * Fetches credentials from OminiFlow and persists them locally.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function fetch_and_apply() {
		$result = Integrations_Client::get_credentials();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::apply_response( $result );
	}

	/**
	 * Applies a credentials API response to local options.
	 *
	 * @param array<string,mixed> $response Remote credentials payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function apply_response( array $response ) {
		$meta_connected      = ! empty( $response['meta_connected'] );
		$whatsapp_connected  = ! empty( $response['whatsapp_connected'] );

		if ( ! $meta_connected && ! $whatsapp_connected ) {
			return array(
				'success'              => true,
				'meta_connected'       => false,
				'whatsapp_connected'   => false,
				'credentials_complete' => false,
				'connect_url'          => ! empty( $response['connect_url'] )
					? (string) $response['connect_url']
					: Integrations_Config::get_connect_meta_url(),
				'message'              => ! empty( $response['message'] )
					? (string) $response['message']
					: __( 'Connect your WhatsApp account on OminiFlow first.', 'facebook-for-woocommerce' ),
			);
		}

		if ( ! empty( $response['meta'] ) && is_array( $response['meta'] ) ) {
			self::apply_meta_credentials( $response['meta'] );
		}

		if ( ! empty( $response['whatsapp'] ) && is_array( $response['whatsapp'] ) ) {
			self::apply_whatsapp_credentials( $response['whatsapp'] );
		}

		Token_Store::mark_store_bound( true );

		$meta_complete      = self::is_meta_complete();
		$whatsapp_complete  = self::is_whatsapp_complete();

		/**
		 * Fires after OminiFlow credentials were applied to local options.
		 *
		 * @param array $response Applied remote payload.
		 */
		do_action( 'wc_ominiflow_credentials_applied', $response );

		return array(
			'success'              => true,
			'meta_connected'       => $meta_connected,
			'whatsapp_connected'   => $whatsapp_connected,
			'meta_complete'        => $meta_complete,
			'whatsapp_complete'    => $whatsapp_complete,
			'credentials_complete' => $meta_complete || $whatsapp_complete,
		);
	}

	/**
	 * @param array<string,mixed> $params Meta credential payload.
	 */
	private static function apply_meta_credentials( array $params ): void {
		$options = array();

		if ( ! empty( $params['access_token'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_ACCESS_TOKEN ] = $params['access_token'];
		}

		if ( ! empty( $params['merchant_access_token'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_MERCHANT_ACCESS_TOKEN ] = $params['merchant_access_token'];
		}

		if ( ! empty( $params['page_access_token'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_PAGE_ACCESS_TOKEN ] = $params['page_access_token'];
		}

		if ( ! empty( $params['commerce_merchant_settings_id'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_COMMERCE_MERCHANT_SETTINGS_ID ] = $params['commerce_merchant_settings_id'];
		}

		if ( ! empty( $params['commerce_partner_integration_id'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_COMMERCE_PARTNER_INTEGRATION_ID ] = $params['commerce_partner_integration_id'];
		}

		if ( ! empty( $params['installed_features'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_INSTALLED_FEATURES ] = $params['installed_features'];
		}

		if ( ! empty( $params['product_catalog_id'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_PRODUCT_CATALOG_ID ] = $params['product_catalog_id'];
		}

		if ( ! empty( $params['profiles'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_PROFILES ] = $params['profiles'];
		}

		if ( ! empty( $params['business_manager_id'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_BUSINESS_MANAGER_ID ] = $params['business_manager_id'];
		}

		if ( ! empty( $params['ad_account_id'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_AD_ACCOUNT_ID ] = $params['ad_account_id'];
		}

		foreach ( $options as $key => $value ) {
			update_option( $key, $value );
		}

		if ( ! empty( $params['merchant_access_token'] ) || ! empty( $params['access_token'] ) ) {
			update_option( 'wc_facebook_has_connected_fbe_2', 'yes' );
			update_option( 'wc_facebook_has_authorized_pages_read_engagement', 'yes' );
			delete_transient( 'wc_facebook_connection_invalid' );
		}
	}

	/**
	 * @param array<string,mixed> $params WhatsApp credential payload.
	 */
	private static function apply_whatsapp_credentials( array $params ): void {
		$map = array(
			'access_token'           => WhatsAppConnection::OPTION_WA_UTILITY_ACCESS_TOKEN,
			'wa_installation_id'     => WhatsAppConnection::OPTION_WA_INSTALLATION_ID,
			'business_id'            => WhatsAppConnection::OPTION_WA_BUSINESS_ID,
			'waba_id'                => WhatsAppConnection::OPTION_WA_WABA_ID,
			'phone_number_id'        => WhatsAppConnection::OPTION_WA_PHONE_NUMBER_ID,
			'integration_config_id'  => WhatsAppConnection::OPTION_WA_INTEGRATION_CONFIG_ID,
			'external_business_id'   => WhatsAppConnection::OPTION_WA_EXTERNAL_BUSINESS_ID,
		);

		foreach ( $map as $remote_key => $option_key ) {
			if ( ! empty( $params[ $remote_key ] ) ) {
				update_option( $option_key, $params[ $remote_key ] );
			}
		}

		if ( ! empty( $params['integration_config_id'] ) ) {
			update_option( WhatsAppConnection::OPTION_WA_ONBOARDING_COMPLETE, WhatsAppConnection::ONBOARDING_STATE_COMPLETE );
		}
	}

	public static function is_meta_complete(): bool {
		$merchant_token = get_option( \WC_Facebookcommerce_Integration::OPTION_MERCHANT_ACCESS_TOKEN, '' );
		$access_token   = get_option( \WC_Facebookcommerce_Integration::OPTION_ACCESS_TOKEN, '' );

		return ( is_string( $merchant_token ) && '' !== $merchant_token )
			|| ( is_string( $access_token ) && '' !== $access_token );
	}

	public static function is_whatsapp_complete(): bool {
		$token     = get_option( WhatsAppConnection::OPTION_WA_UTILITY_ACCESS_TOKEN, '' );
		$waba_id   = get_option( WhatsAppConnection::OPTION_WA_WABA_ID, '' );
		$phone_id  = get_option( WhatsAppConnection::OPTION_WA_PHONE_NUMBER_ID, '' );

		return is_string( $token ) && '' !== $token
			&& is_string( $waba_id ) && '' !== $waba_id
			&& is_string( $phone_id ) && '' !== $phone_id;
	}
}
