<?php
/**
 * Suppresses all Set-Cookie response headers for first-time visitors
 * and captures their values so JavaScript can restore them after consent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mavo_Cookie_Consent_Suppression {

	/** @var self|null */
	private static $instance = null;

	/** @var array<int, array{name: string, value: string, attributes: string}> */
	private static array $pending_cookies = [];

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Returning visitor — consent cookie present, nothing to suppress.
		if ( isset( $_COOKIE['mavo_cookie_consent'] ) ) {
			return;
		}

		// First-time visitor: suppress all Set-Cookie headers after every plugin
		// has had a chance to call setcookie() during init (PHP_INT_MAX priority).
		add_action( 'send_headers', [ $this, 'suppress_all_cookies' ], PHP_INT_MAX );
	}

	/**
	 * Reads all pending Set-Cookie headers, captures non-HttpOnly ones,
	 * then removes every Set-Cookie header from the response.
	 */
	public function suppress_all_cookies(): void {
		$headers = headers_list();

		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Set-Cookie:' ) !== 0 ) {
				continue;
			}

			// Extract the raw cookie string after "Set-Cookie:".
			$raw = trim( substr( $header, strlen( 'Set-Cookie:' ) ) );

			// Skip HttpOnly cookies — JS cannot write these.
			if ( preg_match( '/;\s*HttpOnly/i', $raw ) ) {
				continue;
			}

			// Split into name=value and attribute parts.
			$parts      = explode( ';', $raw, 2 );
			$name_value = trim( $parts[0] );
			$attributes = isset( $parts[1] ) ? trim( $parts[1] ) : '';

			$eq_pos = strpos( $name_value, '=' );
			if ( false === $eq_pos ) {
				continue;
			}

			$name  = urldecode( substr( $name_value, 0, $eq_pos ) );
			$value = urldecode( substr( $name_value, $eq_pos + 1 ) );

			self::$pending_cookies[] = [
				'name'       => $name,
				'value'      => $value,
				'attributes' => $attributes,
			];
		}

		header_remove( 'Set-Cookie' );
	}

	/**
	 * Returns captured cookies; called during asset enqueueing.
	 *
	 * @return array<int, array{name: string, value: string, attributes: string}>
	 */
	public static function get_pending_cookies(): array {
		return self::$pending_cookies;
	}
}
