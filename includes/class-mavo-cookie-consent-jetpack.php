<?php
/**
 * Defers Jetpack Stats to JavaScript so it respects consent
 * and does not affect page-cache integrity.
 *
 * Modern Jetpack Stats (jetpack-stats package ≥ 0.6) enqueues the
 * `jetpack-stats` script handle on `wp_enqueue_scripts` at priority 101
 * (Tracking_Pixel::enqueue_stats_script). We dequeue it at priority 102,
 * extract the external script URL and the inline `_stq` initialisation code,
 * and hand both to cookie-consent.js via mavoCookieConsent.jetpackStats so
 * they are loaded only after the visitor gives implied consent.
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
		// Run after Jetpack has registered its script (priority 101).
		add_action( 'wp_enqueue_scripts', [ $this, 'intercept' ], 102 );
	}

	/**
	 * If Jetpack Stats enqueued its script, dequeue it and stash what we need
	 * so the core class can forward it to JavaScript.
	 */
	public function intercept(): void {
		if ( ! wp_script_is( 'jetpack-stats', 'enqueued' ) ) {
			return;
		}

		global $wp_scripts;

		// Grab the external script URL before dequeuing removes it.
		$src = $wp_scripts->registered['jetpack-stats']->src ?? '';

		if ( Mavo_Cookie_Consent_Exclusions::is_excluded( 'jetpack-stats', $src ) ) {
			return;
		}

		$inline_before = $wp_scripts->get_data( 'jetpack-stats', 'before' );

		// Dequeue + deregister so WordPress does not output either the
		// <script src="…"> tag or the inline block.
		wp_dequeue_script( 'jetpack-stats' );
		wp_deregister_script( 'jetpack-stats' );

		if ( empty( $src ) && empty( $inline_before ) ) {
			return;
		}

		// Normalise: wp_add_inline_script stores data as an array of strings.
		$inline = '';
		if ( is_array( $inline_before ) ) {
			$inline = implode( "\n", $inline_before );
		} elseif ( is_string( $inline_before ) ) {
			$inline = $inline_before;
		}

		// Stash for the core class to include in wp_localize_script output.
		add_filter( 'mavo_cc_jetpack_stats_config', function () use ( $src, $inline ) {
			return [
				'src'    => $src,
				'inline' => $inline,
			];
		} );
	}
}
