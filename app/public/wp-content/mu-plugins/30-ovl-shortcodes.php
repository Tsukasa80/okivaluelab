<?php
// OVL: Shortcode registrations and autoloader bootstrap.

require_once __DIR__ . '/10-ovl-bootstrap.php'; // OVL: Shared helpers are required for every shortcode.

if ( ! function_exists( 'ovl_load_shortcode_modules' ) ) {
	/**
	 * Loads one shortcode per file from the shortcodes directory.
	 *
	 * @return void
	 */
	function ovl_load_shortcode_modules(): void {
		// OVL: Keep shortcode logic isolated for readability and rollback safety.
		$directory = __DIR__ . '/shortcodes';
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$files = glob( trailingslashit( $directory ) . '*.php' );
		if ( empty( $files ) ) {
			return;
		}

		sort( $files );

		foreach ( $files as $shortcode_file ) {
			require_once $shortcode_file;
		}
	}
}

ovl_load_shortcode_modules(); // OVL: Autoload at mu-plugin bootstrap.
