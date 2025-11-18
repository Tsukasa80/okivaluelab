<?php
// OVL: Routing/redirect bootstrap guarded by the OVL_ENABLE_REDIRECTS flag.

require_once __DIR__ . '/10-ovl-bootstrap.php'; // OVL: Guarantee access to the feature flag definition.

if ( ! defined( 'OVL_ENABLE_REDIRECTS' ) || ! OVL_ENABLE_REDIRECTS ) {
	// OVL: Exit early so WP-Members default redirects remain in control.
	return;
}

if ( ! function_exists( 'ovl_bootstrap_redirect_modules' ) ) {
	/**
	 * Loads routing modules when redirects are explicitly enabled.
	 *
	 * @return void
	 */
	function ovl_bootstrap_redirect_modules(): void {
		// OVL: Allow modular routing logic that can be toggled off safely.
		$directory = __DIR__ . '/routing';

		if ( ! is_dir( $directory ) ) {
			return;
		}

		$modules = glob( trailingslashit( $directory ) . '*.php' );

		if ( empty( $modules ) ) {
			return;
		}

		sort( $modules );

		foreach ( $modules as $module ) {
			require_once $module;
		}
	}
}

ovl_bootstrap_redirect_modules(); // OVL: Only executes when redirects are intentionally re-enabled.
