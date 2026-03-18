<?php
/**
 * Admin settings page: Settings → Cookie Consent.
 * English-only — no translations loaded for admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mavo_Cookie_Consent_Settings {

	/** @var self|null */
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',  [ $this, 'add_settings_page' ] );
		add_action( 'admin_init',  [ $this, 'register_settings' ] );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function add_settings_page(): void {
		add_options_page(
			'Cookie Consent',
			'Cookie Consent',
			'manage_options',
			'mavo-cookie-consent',
			[ $this, 'render_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// Settings API registration
	// -------------------------------------------------------------------------

	public function register_settings(): void {
		// GA4 section
		add_settings_section(
			'mavo_cc_ga4',
			'Google Analytics 4',
			'__return_false',
			'mavo-cookie-consent'
		);

		register_setting( 'mavo_cc_options', 'mavo_cc_ga4_id', [
			'sanitize_callback' => [ $this, 'sanitize_ga4_id' ],
			'default'           => '',
		] );

		add_settings_field(
			'mavo_cc_ga4_id',
			'Measurement ID',
			[ $this, 'field_ga4_id' ],
			'mavo-cookie-consent',
			'mavo_cc_ga4'
		);

		// Statcounter section
		add_settings_section(
			'mavo_cc_statcounter',
			'Statcounter',
			'__return_false',
			'mavo-cookie-consent'
		);

		register_setting( 'mavo_cc_options', 'mavo_cc_statcounter_project', [
			'sanitize_callback' => 'absint',
			'default'           => 0,
		] );

		register_setting( 'mavo_cc_options', 'mavo_cc_statcounter_security', [
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );

		add_settings_field(
			'mavo_cc_statcounter_project',
			'Project ID',
			[ $this, 'field_sc_project' ],
			'mavo-cookie-consent',
			'mavo_cc_statcounter'
		);

		add_settings_field(
			'mavo_cc_statcounter_security',
			'Security Code',
			[ $this, 'field_sc_security' ],
			'mavo-cookie-consent',
			'mavo_cc_statcounter'
		);
	}

	// -------------------------------------------------------------------------
	// Sanitize callbacks
	// -------------------------------------------------------------------------

	public function sanitize_ga4_id( string $value ): string {
		$value = strtoupper( trim( $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^G-[A-Z0-9]+$/', $value ) ) {
			add_settings_error(
				'mavo_cc_ga4_id',
				'mavo_cc_ga4_id_invalid',
				'Measurement ID must be in the format G-XXXXXXXXXX.'
			);
			return get_option( 'mavo_cc_ga4_id', '' );
		}
		return $value;
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	public function field_ga4_id(): void {
		$value = esc_attr( get_option( 'mavo_cc_ga4_id', '' ) );
		echo '<input type="text" name="mavo_cc_ga4_id" value="' . $value . '" class="regular-text" placeholder="G-XXXXXXXXXX" />';
	}

	public function field_sc_project(): void {
		$value = absint( get_option( 'mavo_cc_statcounter_project', 0 ) );
		echo '<input type="number" name="mavo_cc_statcounter_project" value="' . $value . '" class="small-text" min="0" />';
	}

	public function field_sc_security(): void {
		$value = esc_attr( get_option( 'mavo_cc_statcounter_security', '' ) );
		echo '<input type="text" name="mavo_cc_statcounter_security" value="' . $value . '" class="regular-text" />';
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Cookie Consent</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'mavo_cc_options' );
				do_settings_sections( 'mavo-cookie-consent' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Static helper used by the core class
	// -------------------------------------------------------------------------

	/**
	 * Returns all three tracking config values as a typed array.
	 *
	 * @return array{ga4_id: string, sc_project: int, sc_security: string}
	 */
	public static function get_tracking_config(): array {
		return [
			'ga4_id'      => (string) get_option( 'mavo_cc_ga4_id', '' ),
			'sc_project'  => (int)    get_option( 'mavo_cc_statcounter_project', 0 ),
			'sc_security' => (string) get_option( 'mavo_cc_statcounter_security', '' ),
		];
	}
}
