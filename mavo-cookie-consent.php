<?php
/**
 * Plugin Name: MaVo Cookie Consent
 * Plugin URI:  https://example.com/mavo-cookie-consent
 * Description: Implicit cookie consent banner. Delays all tracking and third-party cookies until the visitor clicks or scrolls 300 px.
 * Version:     1.0.0
 * Author:      MaVo
 * Text Domain: mavo-cookie-consent
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAVO_CC_VERSION', '1.0.0' );
define( 'MAVO_CC_FILE',    __FILE__ );
define( 'MAVO_CC_DIR',     plugin_dir_path( __FILE__ ) );
define( 'MAVO_CC_URL',     plugin_dir_url( __FILE__ ) );

require_once MAVO_CC_DIR . 'includes/class-mavo-cookie-consent-settings.php';
require_once MAVO_CC_DIR . 'includes/class-mavo-cookie-consent-suppression.php';
require_once MAVO_CC_DIR . 'includes/class-mavo-cookie-consent-exclusions.php';
require_once MAVO_CC_DIR . 'includes/class-mavo-cookie-consent-jetpack.php';
require_once MAVO_CC_DIR . 'includes/class-mavo-cookie-consent.php';

/**
 * Registers the text domain on init (front-end only) and instantiates all singletons.
 */
function mavo_cookie_consent_init() {
	add_action( 'init', function () {
		if ( ! is_admin() ) {
			load_plugin_textdomain(
				'mavo-cookie-consent',
				false,
				dirname( plugin_basename( MAVO_CC_FILE ) ) . '/languages'
			);
		}
	} );

	Mavo_Cookie_Consent_Settings::get_instance();
	Mavo_Cookie_Consent_Suppression::get_instance();
	Mavo_Cookie_Consent_Jetpack::get_instance();
	Mavo_Cookie_Consent::get_instance();
}

mavo_cookie_consent_init();
