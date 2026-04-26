<?php
/**
 * Central registry of scripts that must never be delayed by this plugin.
 *
 * Any delay mechanism (dequeue-and-reload, type="text/plain", defer injection,
 * etc.) should call Mavo_Cookie_Consent_Exclusions::is_excluded() before
 * touching a script. Scripts that match are always left to load normally.
 *
 * Default exclusions
 * ------------------
 *  - Geo Mashup: any script whose handle or src URL contains 'geo-mashup'.
 *    All Geo Mashup handles follow the pattern 'geo-mashup*' and the plugin
 *    serves its scripts from a path that includes 'geo-mashup', so one
 *    substring check covers both the plugin's own handles and any script it
 *    inlines under its own directory.
 *
 * Extending via filters
 * ---------------------
 *  add_filter( 'mavo_cc_excluded_handle_patterns', function( $list ) {
 *      $list[] = 'my-plugin-handle';   // exact handle or substring
 *      return $list;
 *  } );
 *
 *  add_filter( 'mavo_cc_excluded_src_patterns', function( $list ) {
 *      $list[] = 'example.com/my-script'; // substring of src URL
 *      return $list;
 *  } );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mavo_Cookie_Consent_Exclusions {

	/** @var array<string>|null Lazily initialised on first is_excluded() call. */
	private static ?array $handle_patterns = null;

	/** @var array<string>|null */
	private static ?array $src_patterns = null;

	/**
	 * Returns true when the script must not be delayed.
	 *
	 * @param string $handle WordPress script handle.
	 * @param string $src    Registered src URL (may be empty for inline-only scripts).
	 */
	public static function is_excluded( string $handle, string $src = '' ): bool {
		self::maybe_init();

		foreach ( self::$handle_patterns as $pattern ) {
			if ( '' !== $pattern && str_contains( $handle, $pattern ) ) {
				return true;
			}
		}

		if ( '' !== $src ) {
			foreach ( self::$src_patterns as $pattern ) {
				if ( '' !== $pattern && str_contains( $src, $pattern ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function maybe_init(): void {
		if ( null !== self::$handle_patterns ) {
			return;
		}

		self::$handle_patterns = (array) apply_filters(
			'mavo_cc_excluded_handle_patterns',
			[ 'geo-mashup' ]
		);

		self::$src_patterns = (array) apply_filters(
			'mavo_cc_excluded_src_patterns',
			[ 'geo-mashup' ]
		);
	}
}
