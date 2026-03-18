<?php
/**
 * Defers Jetpack Stats to JavaScript so it respects consent
 * and does not affect page-cache integrity.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mavo_Cookie_Consent_Jetpack {

	/** @var self|null */
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// If Jetpack Stats is not active, do nothing.
		if ( ! has_action( 'wp_footer', 'stats_footer' ) ) {
			return;
		}

		// Prevent Jetpack's automatic footer output.
		remove_action( 'wp_footer', 'stats_footer', 101 );

		// Capture and defer it ourselves.
		add_action( 'wp_footer', [ $this, 'capture_and_defer_stats' ], 102 );
	}

	/**
	 * Buffers Jetpack's stats_footer() output and passes the HTML to JS
	 * as mavoCookieConsent.jetpackStatsMarkup so cookie-consent.js can
	 * inject it after the visitor gives consent.
	 */
	public function capture_and_defer_stats(): void {
		ob_start();
		stats_footer();
		$markup = ob_get_clean();

		if ( '' === trim( (string) $markup ) ) {
			return;
		}

		$json = wp_json_encode( $markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		echo '<script>window.mavoCookieConsent=window.mavoCookieConsent||{};window.mavoCookieConsent.jetpackStatsMarkup=' . $json . ';</script>' . "\n";
	}
}
