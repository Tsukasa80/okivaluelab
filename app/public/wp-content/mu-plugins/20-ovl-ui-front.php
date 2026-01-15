<?php
// OVL: Presentation-only helpers and filters for front-end UI tweaks.

require_once __DIR__ . '/10-ovl-bootstrap.php'; // OVL: Ensure shared helpers are loaded first.

if ( ! function_exists( 'ovl_filter_body_class' ) ) {
	/**
	 * Adds contextual auth classes to the body tag.
	 *
	 * @param array $classes Original classes.
	 *
	 * @return array
	 */
	function ovl_filter_body_class( array $classes ): array {
		// OVL: Allow CSS to target logged-in/logged-out states without JS.
		$state     = is_user_logged_in() ? 'logged-in' : 'logged-out';
		$classes[] = 'ovl-auth-state-' . $state;

		return array_unique( $classes );
	}
}

add_filter( 'body_class', 'ovl_filter_body_class' ); // OVL: Hook stays presentation-only.

if ( ! function_exists( 'ovl_filter_show_admin_bar' ) ) {
	/**
	 * Hides the WordPress admin bar on the front end for non-admin users.
	 *
	 * @param bool $show Whether to show the admin bar.
	 *
	 * @return bool
	 */
	function ovl_filter_show_admin_bar( bool $show ): bool {
		// OVL: Keep admin bar for site managers, hide for member users on front-end.
		if ( is_admin() ) {
			return $show;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return $show;
		}

		return false;
	}
}

add_filter( 'show_admin_bar', 'ovl_filter_show_admin_bar', 20 ); // OVL: Front-end UI polish.

if ( ! function_exists( 'ovl_get_auth_link_markup' ) ) {
	/**
	 * Returns a login/logout link that templates can place anywhere.
	 *
	 * @param array $args {
	 *     Optional. Overrides for markup.
	 *
	 *     @type string $logged_in_label  Link label when logged in.
	 *     @type string $logged_out_label Link label when logged out.
	 *     @type string $class            CSS class attribute.
	 * }
	 *
	 * @return string
	 */
	function ovl_get_auth_link_markup( array $args = [] ): string {
		// OVL: Centralize login/logout link so block templates stay clean.
		$defaults = [
			'logged_in_label'  => __( 'Log out', 'ovl' ),
			'logged_out_label' => __( 'Log in', 'ovl' ),
			'class'            => 'ovl-auth-link',
		];

		$args = wp_parse_args( $args, $defaults );

		if ( is_user_logged_in() ) {
			$url   = wp_logout_url( home_url( '/' ) );
			$label = $args['logged_in_label'];
		} else {
			$url   = wp_login_url( ovl_get_current_url() );
			$label = $args['logged_out_label'];
		}

		return sprintf(
			'<a class="%1$s" href="%2$s">%3$s</a>',
			esc_attr( $args['class'] ),
			esc_url( $url ),
			ovl_escape_html( $label )
		);
	}
}
