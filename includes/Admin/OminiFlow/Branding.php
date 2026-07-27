<?php
/**
 * OminiFlow white-label branding strings.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\OminiFlow;

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes OminiFlow branding copy for admin and plugin surfaces.
 */
class Branding {

	public static function plugin_name(): string {
		return (string) apply_filters(
			'wc_ominiflow_plugin_name',
			__( 'OminiFlow for WooCommerce', 'facebook-for-woocommerce' )
		);
	}

	public static function short_name(): string {
		return (string) apply_filters(
			'wc_ominiflow_short_name',
			__( 'OminiFlow', 'facebook-for-woocommerce' )
		);
	}

	public static function menu_label(): string {
		return (string) apply_filters(
			'wc_ominiflow_menu_label',
			self::short_name()
		);
	}

	public static function whatsapp_menu_label(): string {
		return (string) apply_filters(
			'wc_ominiflow_whatsapp_menu_label',
			__( 'OminiFlow WhatsApp', 'facebook-for-woocommerce' )
		);
	}

	public static function whatsapp_page_title(): string {
		return (string) apply_filters(
			'wc_ominiflow_whatsapp_page_title',
			sprintf(
				/* translators: %s: OminiFlow brand name */
				__( '%s WhatsApp', 'facebook-for-woocommerce' ),
				self::short_name()
			)
		);
	}

	public static function product_tab_label(): string {
		return (string) apply_filters(
			'wc_ominiflow_product_tab_label',
			self::short_name()
		);
	}

	public static function catalog_sync_label(): string {
		return (string) apply_filters(
			'wc_ominiflow_catalog_sync_label',
			sprintf(
				/* translators: %s: OminiFlow brand name */
				__( '%s Catalog Sync', 'facebook-for-woocommerce' ),
				self::short_name()
			)
		);
	}

	public static function integration_title(): string {
		return self::plugin_name();
	}

	public static function integration_description(): string {
		return (string) apply_filters(
			'wc_ominiflow_integration_description',
			__( 'OminiFlow commerce workspace — Meta catalog sync, pixel, and ads integration for WooCommerce.', 'facebook-for-woocommerce' )
		);
	}

	public static function tagline(): string {
		return (string) apply_filters(
			'wc_ominiflow_tagline',
			__( 'easy, smarter, endless', 'facebook-for-woocommerce' )
		);
	}
}
