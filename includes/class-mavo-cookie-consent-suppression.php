<?php
/**
 * Holds back tracking cookies for first-time visitors, captures their values so
 * JavaScript can restore them after implied consent, and lets strictly
 * functional cookies through untouched.
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
		if ( isset( $_COOKIE[ Mavo_Cookie_Consent::COOKIE_NAME ] ) ) {
			return;
		}

		// First-time visitor: suppress Set-Cookie headers after every plugin
		// has had a chance to call setcookie() during init (PHP_INT_MAX priority).
		add_action( 'send_headers', [ $this, 'suppress_all_cookies' ], PHP_INT_MAX );
	}

	/**
	 * Cookies that go out even before consent.
	 *
	 * None of these track anyone: they remember a preference the visitor
	 * expressed themselves, or the site needs them to work. Holding them back
	 * degraded the site for no privacy gain — the clearest case being
	 * Polylang's language cookie, where a visitor who picked a language was
	 * not remembered until they happened to scroll far enough to consent.
	 *
	 * Matched as name prefixes, so 'comment_author_' covers the three
	 * per-commenter variants WordPress actually writes.
	 *
	 * @return string[]
	 */
	public static function functional_cookies(): array {
		return (array) apply_filters( 'mavo_cc_functional_cookies', [
			// Polylang's language preference.
			'pll_language',
			// WordPress' own: wordpress_test_cookie is what the login screen
			// probes for, wp-settings-* are admin UI preferences, and the
			// comment_author_* trio remembers what a commenter typed into a
			// form they submitted themselves.
			'wordpress_test_cookie',
			'wp-settings-',
			'comment_author_',
			// This plugin's own flag, which obviously has to survive.
			Mavo_Cookie_Consent::COOKIE_NAME,
		] );
	}

	/** Is this cookie name on the functional list? */
	private static function is_functional( string $name ): bool {
		foreach ( self::functional_cookies() as $pattern ) {
			if ( '' !== $pattern && str_starts_with( $name, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Captures the cookies JavaScript will restore, drops them from the
	 * response, and re-sends the ones that must go out regardless.
	 *
	 * header_remove() cannot remove a single cookie, only all of them, so the
	 * keepers are re-emitted afterwards — verbatim, exactly as the plugin that
	 * set them wrote them, attributes and all.
	 */
	public function suppress_all_cookies(): void {
		$headers = headers_list();
		$keep    = [];

		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Set-Cookie:' ) !== 0 ) {
				continue;
			}

			// Extract the raw cookie string after "Set-Cookie:".
			$raw = trim( substr( $header, strlen( 'Set-Cookie:' ) ) );

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

			// HttpOnly cookies are kept, never dropped. JavaScript cannot write
			// them, so suppressing one does not delay it — it destroys it, with
			// nothing able to put it back. They were already skipped for
			// capture; the blanket header_remove() below then deleted them
			// anyway, which is the half of that pair that was wrong.
			if ( preg_match( '/;\s*HttpOnly/i', $raw ) ) {
				$keep[] = $header;
				continue;
			}

			if ( self::is_functional( $name ) ) {
				$keep[] = $header;
				continue;
			}

			self::$pending_cookies[] = [
				'name'       => $name,
				'value'      => $value,
				'attributes' => $attributes,
			];
		}

		header_remove( 'Set-Cookie' );

		foreach ( $keep as $header ) {
			// false: append rather than replace, so more than one survives.
			header( $header, false );
		}
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
