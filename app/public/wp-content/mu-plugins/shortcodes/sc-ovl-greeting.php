<?php
// OVL: `[ovl_greeting]` shortcode renders login-aware copy without redirects.

if ( ! function_exists( 'ovl_shortcode_greeting' ) ) {
	/**
	 * Greeting message that changes per auth state.
	 *
	 * @param array $atts Shortcode attributes (unused for now).
	 *
	 * @return string
	 */
	function ovl_shortcode_greeting( array $atts = [] ): string {
		// OVL: Demonstrate conditional rendering without side effects.
		$context = ovl_get_template_context();
		$user    = wp_get_current_user();

		if ( $context['is_user_logged_in'] && $user instanceof WP_User && $user->exists() ) {
			$message = sprintf(
				/* translators: %s is the display name. */
				__( 'Welcome back, %s!', 'ovl' ),
				$user->display_name ? $user->display_name : $user->user_login
			);
		} else {
			$message = __( 'Hello! Please log in to see member content.', 'ovl' );
		}

		return sprintf(
			'<div class="ovl-greeting">%s</div>',
			ovl_escape_html( $message )
		);
	}
}

add_shortcode( 'ovl_greeting', 'ovl_shortcode_greeting' ); // OVL: Register greeting shortcode with WP.
