<?php
/**
 * OminiFlow onboarding shell markup.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\OminiFlow;

defined( 'ABSPATH' ) || exit;

/**
 * Renders OminiFlow-branded signup/login shell around the Meta iframe.
 */
class Onboarding_Shell {

	/**
	 * Returns the official OminiFlow logo URL from bundled plugin assets.
	 *
	 * @return string
	 */
	public static function get_logo_url(): string {
		$default = self::resolve_official_logo_url();

		/**
		 * Filter the OminiFlow logo URL used on onboarding screens.
		 *
		 * @param string $logo_url Logo asset URL.
		 */
		return (string) apply_filters( 'wc_ominiflow_logo_url', $default );
	}

	/**
	 * Resolves the official OminiFlow logo file shipped with the plugin.
	 *
	 * @return string
	 */
	private static function resolve_official_logo_url(): string {
		$plugin_root = dirname( __DIR__, 3 );
		$plugin_url  = facebook_for_woocommerce()->get_plugin_url();

		$candidates = array(
			'assets/images/ominiflow-logo.png'        => $plugin_url . '/assets/images/ominiflow-logo.png',
			'assets/Screenshot 2026-07-24 112059.png' => $plugin_url . '/assets/Screenshot%202026-07-24%20112059.png',
		);

		foreach ( $candidates as $relative_path => $url ) {
			if ( file_exists( $plugin_root . '/' . $relative_path ) ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Whether the bundled logo asset already includes the tagline text.
	 *
	 * @return bool
	 */
	public static function logo_includes_tagline(): bool {
		$logo_url = self::get_logo_url();

		return false !== strpos( $logo_url, 'ominiflow-logo.png' )
			|| false !== strpos( $logo_url, '112059.png' );
	}

	/**
	 * Returns localized script data for the onboarding UI.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_script_data(): array {
		return array(
			'ajax_url'            => admin_url( 'admin-ajax.php' ),
			'nonce'               => wp_create_nonce( 'wc_ominiflow_auth' ),
			'signup_action'       => Auth_Gate::AJAX_SIGNUP,
			'login_action'        => Auth_Gate::AJAX_LOGIN,
			'auth_configured'     => Auth_Config::is_configured(),
			'forgot_password_url' => Auth_Config::get_forgot_password_url(),
			'terms_url'           => (string) apply_filters( 'wc_ominiflow_terms_url', 'https://ominiflow.com/terms' ),
			'privacy_url'         => (string) apply_filters( 'wc_ominiflow_privacy_url', 'https://ominiflow.com/privacy' ),
			'i18n'                => array(
				'creating_account'      => __( 'Creating account…', 'facebook-for-woocommerce' ),
				'signing_in'            => __( 'Signing in…', 'facebook-for-woocommerce' ),
				'generic_error'         => __( 'Something went wrong. Please try again.', 'facebook-for-woocommerce' ),
				'auth_not_configured'   => Auth_Config::get_not_configured_message(),
			),
		);
	}

	/**
	 * Renders the full onboarding shell with auth panel and hidden Meta iframe.
	 *
	 * @param string $iframe_url Meta Commerce Partner Hub iframe URL.
	 */
	public static function render( string $iframe_url ): void {
		$logo_url             = self::get_logo_url();
		$show_meta_panel      = Auth_Gate::is_authenticated();
		$auth_configured      = Auth_Config::is_configured();
		$has_forgot_password  = Auth_Config::has_forgot_password_url();
		$logo_includes_tagline = self::logo_includes_tagline();
		?>
	<div class="ominiflow-onboarding" id="ominiflow-onboarding-root">
		<div class="ominiflow-onboarding__inner">
			<div class="ominiflow-onboarding__marketing">
				<div class="ominiflow-onboarding__brand">
					<?php if ( '' !== $logo_url ) : ?>
					<img
						class="ominiflow-onboarding__logo<?php echo $logo_includes_tagline ? ' ominiflow-onboarding__logo--official' : ''; ?>"
						src="<?php echo esc_url( $logo_url ); ?>"
						alt="<?php esc_attr_e( 'OminiFlow', 'facebook-for-woocommerce' ); ?>"
					/>
					<?php endif; ?>
					<?php if ( ! $logo_includes_tagline ) : ?>
					<p class="ominiflow-onboarding__tagline"><?php esc_html_e( 'easy, smarter, endless', 'facebook-for-woocommerce' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="ominiflow-onboarding__badge" aria-label="<?php esc_attr_e( 'Meta Business Partner', 'facebook-for-woocommerce' ); ?>">
					<img
						src="<?php echo esc_url( facebook_for_woocommerce()->get_plugin_url() . '/assets/images/meta-business-partner-badge.svg' ); ?>"
						alt="<?php esc_attr_e( 'Meta Business Partner', 'facebook-for-woocommerce' ); ?>"
						width="160"
						height="32"
					/>
				</div>

				<h2 class="ominiflow-onboarding__heading">
					<?php esc_html_e( 'Create your SaaS workspace in minutes.', 'facebook-for-woocommerce' ); ?>
				</h2>
				<p class="ominiflow-onboarding__description">
					<?php esc_html_e( 'Connect WooCommerce to Meta with OminiFlow — manage catalogs, ads, and WhatsApp from one streamlined workspace.', 'facebook-for-woocommerce' ); ?>
				</p>

				<ul class="ominiflow-onboarding__features">
					<li><?php esc_html_e( 'Unified Meta & WhatsApp commerce setup', 'facebook-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Secure, partner-approved onboarding flow', 'facebook-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Sync products, coupons, and shipping profiles', 'facebook-for-woocommerce' ); ?></li>
				</ul>
			</div>

			<div class="ominiflow-onboarding__panel">
				<div
					class="ominiflow-onboarding__auth<?php echo $show_meta_panel ? ' is-hidden' : ''; ?>"
					id="ominiflow-auth-panel"
					aria-hidden="<?php echo $show_meta_panel ? 'true' : 'false'; ?>"
				>
					<?php if ( ! $auth_configured ) : ?>
					<div class="ominiflow-onboarding__notice" role="status">
						<?php echo esc_html( Auth_Config::get_not_configured_message() ); ?>
					</div>
					<?php endif; ?>

					<div class="ominiflow-onboarding__tabs" role="tablist">
						<button
							type="button"
							class="ominiflow-onboarding__tab is-active"
							data-ominiflow-view="signup"
							role="tab"
							aria-selected="true"
							aria-controls="ominiflow-signup-panel"
						>
							<?php esc_html_e( 'Sign Up', 'facebook-for-woocommerce' ); ?>
						</button>
						<button
							type="button"
							class="ominiflow-onboarding__tab"
							data-ominiflow-view="login"
							role="tab"
							aria-selected="false"
							aria-controls="ominiflow-login-panel"
						>
							<?php esc_html_e( 'Sign In', 'facebook-for-woocommerce' ); ?>
						</button>
					</div>

					<div
						class="ominiflow-onboarding__form-panel is-active"
						id="ominiflow-signup-panel"
						data-ominiflow-panel="signup"
						role="tabpanel"
					>
						<h3 class="ominiflow-onboarding__form-title"><?php esc_html_e( 'Create Account', 'facebook-for-woocommerce' ); ?></h3>
						<form id="ominiflow-signup-form" class="ominiflow-onboarding__form" novalidate>
							<div class="ominiflow-field">
								<label for="ominiflow-signup-full-name"><?php esc_html_e( 'Full Name', 'facebook-for-woocommerce' ); ?></label>
								<input type="text" id="ominiflow-signup-full-name" name="full_name" autocomplete="name" required />
							</div>
							<div class="ominiflow-field">
								<label for="ominiflow-signup-email"><?php esc_html_e( 'Email Address', 'facebook-for-woocommerce' ); ?></label>
								<input type="email" id="ominiflow-signup-email" name="email" autocomplete="email" required />
							</div>
							<div class="ominiflow-field ominiflow-field--phone">
								<label for="ominiflow-signup-phone"><?php esc_html_e( 'WhatsApp Phone Number', 'facebook-for-woocommerce' ); ?></label>
								<div class="ominiflow-phone-row">
									<select id="ominiflow-signup-phone-country" name="phone_country" aria-label="<?php esc_attr_e( 'Country code', 'facebook-for-woocommerce' ); ?>">
										<option value="+1">+1</option>
										<option value="+44">+44</option>
										<option value="+91" selected>+91</option>
										<option value="+971">+971</option>
										<option value="+61">+61</option>
										<option value="+49">+49</option>
									</select>
									<input type="tel" id="ominiflow-signup-phone" name="phone" autocomplete="tel" required />
								</div>
							</div>
							<div class="ominiflow-field">
								<label for="ominiflow-signup-password"><?php esc_html_e( 'Password', 'facebook-for-woocommerce' ); ?></label>
								<input type="password" id="ominiflow-signup-password" name="password" autocomplete="new-password" minlength="8" required />
							</div>
							<div class="ominiflow-field">
								<label for="ominiflow-signup-confirm-password"><?php esc_html_e( 'Confirm Password', 'facebook-for-woocommerce' ); ?></label>
								<input type="password" id="ominiflow-signup-confirm-password" name="confirm_password" autocomplete="new-password" minlength="8" required />
							</div>
							<div class="ominiflow-field ominiflow-field--checkbox">
								<label>
									<input type="checkbox" id="ominiflow-signup-terms" name="terms_accepted" value="1" required />
									<span>
										<?php
										printf(
											/* translators: %1$s: terms link open, %2$s: terms link close, %3$s: privacy link open, %4$s: privacy link close */
											esc_html__( 'I agree to the %1$sTerms of Service%2$s and %3$sPrivacy Policy%4$s', 'facebook-for-woocommerce' ),
											'<a href="' . esc_url( (string) apply_filters( 'wc_ominiflow_terms_url', 'https://ominiflow.com/terms' ) ) . '" target="_blank" rel="noopener noreferrer">',
											'</a>',
											'<a href="' . esc_url( (string) apply_filters( 'wc_ominiflow_privacy_url', 'https://ominiflow.com/privacy' ) ) . '" target="_blank" rel="noopener noreferrer">',
											'</a>'
										);
										?>
									</span>
								</label>
							</div>
							<p class="ominiflow-onboarding__error" id="ominiflow-signup-error" role="alert" hidden></p>
							<button type="submit" class="ominiflow-onboarding__submit">
								<?php esc_html_e( 'Create Account', 'facebook-for-woocommerce' ); ?>
							</button>
							<p class="ominiflow-onboarding__switch">
								<?php esc_html_e( 'Already have an account?', 'facebook-for-woocommerce' ); ?>
								<button type="button" class="ominiflow-onboarding__link" data-ominiflow-switch="login">
									<?php esc_html_e( 'Sign In', 'facebook-for-woocommerce' ); ?>
								</button>
							</p>
						</form>
					</div>

					<div
						class="ominiflow-onboarding__form-panel"
						id="ominiflow-login-panel"
						data-ominiflow-panel="login"
						role="tabpanel"
						hidden
					>
						<h3 class="ominiflow-onboarding__form-title"><?php esc_html_e( 'Sign In', 'facebook-for-woocommerce' ); ?></h3>
						<form id="ominiflow-login-form" class="ominiflow-onboarding__form" novalidate>
							<div class="ominiflow-field">
								<label for="ominiflow-login-email"><?php esc_html_e( 'Email Address', 'facebook-for-woocommerce' ); ?></label>
								<input type="email" id="ominiflow-login-email" name="email" autocomplete="email" required />
							</div>
							<div class="ominiflow-field">
								<label for="ominiflow-login-password"><?php esc_html_e( 'Password', 'facebook-for-woocommerce' ); ?></label>
								<input type="password" id="ominiflow-login-password" name="password" autocomplete="current-password" required />
							</div>
							<div class="ominiflow-field ominiflow-field--row">
								<label class="ominiflow-field--checkbox">
									<input type="checkbox" id="ominiflow-login-remember" name="remember_me" value="1" />
									<span><?php esc_html_e( 'Remember Me', 'facebook-for-woocommerce' ); ?></span>
								</label>
								<?php if ( $has_forgot_password ) : ?>
								<a
									class="ominiflow-onboarding__link"
									id="ominiflow-forgot-password"
									href="<?php echo esc_url( Auth_Config::get_forgot_password_url() ); ?>"
									target="_blank"
									rel="noopener noreferrer"
								>
									<?php esc_html_e( 'Forgot Password?', 'facebook-for-woocommerce' ); ?>
								</a>
								<?php endif; ?>
							</div>
							<p class="ominiflow-onboarding__error" id="ominiflow-login-error" role="alert" hidden></p>
							<button type="submit" class="ominiflow-onboarding__submit">
								<?php esc_html_e( 'Login', 'facebook-for-woocommerce' ); ?>
							</button>
							<p class="ominiflow-onboarding__switch">
								<?php esc_html_e( 'Need an account?', 'facebook-for-woocommerce' ); ?>
								<button type="button" class="ominiflow-onboarding__link" data-ominiflow-switch="signup">
									<?php esc_html_e( 'Create Account', 'facebook-for-woocommerce' ); ?>
								</button>
							</p>
						</form>
					</div>
				</div>

				<div
					class="ominiflow-onboarding__meta<?php echo $show_meta_panel ? ' is-visible' : ''; ?>"
					id="ominiflow-meta-panel"
					aria-hidden="<?php echo $show_meta_panel ? 'false' : 'true'; ?>"
				>
					<?php self::render_iframe( $iframe_url, ! $show_meta_panel ); ?>
				</div>
			</div>
		</div>
	</div>
		<?php
	}

	/**
	 * Renders the Meta iframe with the existing element ID preserved.
	 *
	 * @param string $iframe_url Iframe source URL.
	 * @param bool   $hidden     Whether the iframe starts hidden.
	 */
	public static function render_iframe( string $iframe_url, bool $hidden = false ): void {
		$wrap_style = $hidden ? ' style="display:none;"' : '';
		$iframe_style = $hidden ? ' style="display:none;"' : '';
		?>
	<div class="ominiflow-onboarding__iframe-wrap"<?php echo $wrap_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<iframe
			id="facebook-commerce-iframe-enhanced"
			src="<?php echo esc_url( $iframe_url ); ?>"
			<?php echo $iframe_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		></iframe>
	</div>
		<?php
	}

	/**
	 * Renders the legacy centered iframe wrapper used when connected.
	 *
	 * @param string $iframe_url Iframe source URL.
	 */
	public static function render_connected_iframe( string $iframe_url ): void {
		?>
	<div style="display: flex; justify-content: center; max-width: 1200px; margin: 0 auto;">
		<iframe
			id="facebook-commerce-iframe-enhanced"
			src="<?php echo esc_url( $iframe_url ); ?>"
		></iframe>
	</div>
		<?php
	}
}
