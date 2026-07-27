<?php
/**
 * OminiFlow Integrations API configuration.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\OminiFlow;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves OminiFlow Integrations API settings.
 */
class Integrations_Config {

	/** @var string Default integrations API base URL. */
	const DEFAULT_INTEGRATIONS_API_BASE_URL = 'https://whatsapp.ominiflow.com/api/v1';

	/**
	 * Whether credential sync is enabled.
	 */
	public static function is_credential_sync_enabled(): bool {
		/**
		 * Filter whether OminiFlow credential sync is enabled.
		 *
		 * @param bool $enabled Default true when auth is configured.
		 */
		return (bool) apply_filters(
			'wc_ominiflow_credential_sync_enabled',
			Auth_Config::is_configured()
		);
	}

	/**
	 * Returns the integrations API base URL.
	 */
	public static function get_api_base_url(): string {
		/**
		 * Filter the OminiFlow integrations API base URL.
		 *
		 * @param string $base_url Default integrations base URL.
		 */
		return rtrim(
			trim( (string) apply_filters( 'wc_ominiflow_integrations_api_base_url', self::DEFAULT_INTEGRATIONS_API_BASE_URL ) ),
			'/'
		);
	}

	/**
	 * Returns the connect Meta URL shown when WhatsApp is not connected on OminiFlow.
	 */
	public static function get_connect_meta_url(): string {
		/**
		 * Filter the OminiFlow dashboard URL for connecting Meta/WhatsApp.
		 *
		 * @param string $url Default connect URL.
		 */
		return trim(
			(string) apply_filters(
				'wc_ominiflow_connect_meta_url',
				'https://whatsapp.ominiflow.com/wpbox/setup'
			)
		);
	}
}
