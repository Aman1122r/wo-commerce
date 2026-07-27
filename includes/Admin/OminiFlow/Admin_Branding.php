<?php
/**
 * OminiFlow admin-wide branding (headers on settings screens).
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\OminiFlow;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/Branding.php';
require_once __DIR__ . '/Onboarding_Shell.php';

/**
 * Enqueues shared admin branding assets and renders compact page headers.
 */
class Admin_Branding {

	/** @var bool */
	private static $bootstrapped = false;

	public static function init(): void {
		if ( self::$bootstrapped ) {
			return;
		}

		self::$bootstrapped = true;
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function is_branded_admin_page(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $page, array( 'wc-facebook', 'wc-whatsapp' ), true );
	}

	public static function enqueue_assets(): void {
		if ( ! self::is_branded_admin_page() ) {
			return;
		}

		wp_enqueue_style(
			'wc-ominiflow-admin-branding',
			facebook_for_woocommerce()->get_plugin_url() . '/assets/css/admin/ominiflow-admin-branding.css',
			array(),
			\WC_Facebookcommerce::VERSION
		);
	}

	public static function render_settings_header(): void {
		$logo_url = Onboarding_Shell::get_logo_url();

		if ( '' === $logo_url ) {
			?>
	<div class="ominiflow-admin-header ominiflow-admin-header--text-only" role="banner">
		<div class="ominiflow-admin-header__text">
			<p class="ominiflow-admin-header__name"><?php echo esc_html( Branding::plugin_name() ); ?></p>
			<p class="ominiflow-admin-header__tagline"><?php echo esc_html( Branding::tagline() ); ?></p>
		</div>
	</div>
			<?php
			return;
		}
		?>
	<div class="ominiflow-admin-header" role="banner">
		<img
			class="ominiflow-admin-header__logo"
			src="<?php echo esc_url( $logo_url ); ?>"
			alt="<?php echo esc_attr( Branding::short_name() ); ?>"
		/>
		<div class="ominiflow-admin-header__text">
			<p class="ominiflow-admin-header__name"><?php echo esc_html( Branding::plugin_name() ); ?></p>
			<p class="ominiflow-admin-header__tagline"><?php echo esc_html( Branding::tagline() ); ?></p>
		</div>
	</div>
		<?php
	}
}
