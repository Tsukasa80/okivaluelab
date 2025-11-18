<?php
// OVL: Bootstrap shared helpers and feature flags for the ovl stack.

if ( ! defined( 'OVL_ENABLE_REDIRECTS' ) ) {
	// OVL: Routing remains disabled by default to follow WP-Members defaults.
	define( 'OVL_ENABLE_REDIRECTS', false );
}

if ( ! function_exists( 'ovl_get_current_url' ) ) {
	/**
	 * Returns the current request URL without triggering side effects.
	 *
	 * @return string
	 */
	function ovl_get_current_url(): string {
		// OVL: Provide sanitized current URL for UI helpers and shortcodes.
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$url    = $host ? $scheme . '://' . $host . $uri : '';

		return esc_url_raw( $url );
	}
}

if ( ! function_exists( 'ovl_escape_html' ) ) {
	/**
	 * Escapes text for safe HTML output.
	 *
	 * @param string $value Raw text.
	 *
	 * @return string
	 */
	function ovl_escape_html( $value ): string {
		// OVL: Centralized escape wrapper for shortcode/page rendering logic.
		return esc_html( (string) $value );
	}
}

if ( ! function_exists( 'ovl_get_template_context' ) ) {
	/**
	 * Builds a shared context array for templates.
	 *
	 * @param array $extra Additional context overrides.
	 *
	 * @return array
	 */
	function ovl_get_template_context( array $extra = [] ): array {
		// OVL: Provide consistent base data so templates remain simple.
		$context = [
			'current_url'       => ovl_get_current_url(),
			'is_user_logged_in' => is_user_logged_in(),
		];

		return array_merge( $context, $extra );
	}
}
