<?php
/**
 * Core class: enqueues assets and renders the consent banner.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mavo_Cookie_Consent {

	const COOKIE_NAME = 'mavo_cookie_consent';

	/** @var self|null */
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer',          [ $this, 'render_banner' ] );
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets(): void {
		wp_enqueue_style(
			'mavo-cookie-consent',
			MAVO_CC_URL . 'assets/css/cookie-consent.css',
			[],
			MAVO_CC_VERSION
		);

		wp_enqueue_script(
			'mavo-cookie-consent',
			MAVO_CC_URL . 'assets/js/cookie-consent.js',
			[],
			MAVO_CC_VERSION,
			true // footer
		);

		$tracking = Mavo_Cookie_Consent_Settings::get_tracking_config();

		// Build pending cookies list (empty for returning visitors).
		$pending = [];
		foreach ( Mavo_Cookie_Consent_Suppression::get_pending_cookies() as $cookie ) {
			$pending[] = [
				'name'  => $cookie['name'],
				'value' => $cookie['value'],
			];
		}

		// Jetpack Stats config, populated by the Jetpack class if the handle
		// was intercepted (null = Jetpack Stats not active or not enqueued).
		$jetpack_stats = apply_filters( 'mavo_cc_jetpack_stats_config', null );

		wp_localize_script(
			'mavo-cookie-consent',
			'mavoCookieConsent',
			[
				'cookieName'      => self::COOKIE_NAME,
				'scrollThreshold' => 300,
				'ga4Id'           => $tracking['ga4_id'],
				'scProject'       => $tracking['sc_project'],
				'scSecurity'      => $tracking['sc_security'],
				'pendingCookies'  => $pending,
				'jetpackStats'    => $jetpack_stats,
			]
		);
	}

	// -------------------------------------------------------------------------
	// Banner
	// -------------------------------------------------------------------------

	/**
	 * Outputs the banner HTML in wp_footer for first-time visitors only.
	 * The --hidden class keeps it invisible; JS removes it on DOMContentLoaded.
	 * Returning visitors served a cached page receive this HTML but JS never
	 * removes --hidden when the consent cookie is already present.
	 */
	public function render_banner(): void {
		/* render the hidden banner for everyone - due to page caching everyone should see the same html
		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return;
		} */
		?>
		<div id="mavo-cookie-banner"
		     class="mavo-cookie-banner mavo-cookie-banner--hidden"
		     role="region"
		     aria-label="<?php echo esc_attr( __( 'Cookie notice', 'mavo-cookie-consent' ) ); ?>">
			<p class="mavo-cookie-banner__text">
				<?php esc_html_e( 'By using this site you accept the use of cookies and anonymous analytics.', 'mavo-cookie-consent' ); ?>
			</p>
			<button type="button" class="mavo-cookie-banner__ok">
				<?php esc_html_e( 'OK', 'mavo-cookie-consent' ); ?>
			</button>
		</div>
		<?php
	}
}
