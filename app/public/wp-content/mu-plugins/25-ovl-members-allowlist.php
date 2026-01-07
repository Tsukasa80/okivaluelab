<?php
// OVL: Keep most pages public; require login for property detail + member consult.

require_once __DIR__ . '/10-ovl-bootstrap.php'; // OVL: Share helper stack/order.

if ( ! function_exists( 'ovl_extend_code_snippets_allowlist' ) ) {
	/**
	 * Ensures the Code Snippets allowlist respects our login policy.
	 *
	 * @param array $allow Allowed path list from the snippet.
	 *
	 * @return array
	 */
	function ovl_extend_code_snippets_allowlist( array $allow ): array {
		if ( ovl_requires_login() ) {
			return $allow;
		}

		$path = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		}

		if ( $path !== null ) {
			$normalized = rtrim( $path, '/' ) . '/';
			$normalized = $normalized === '//' ? '/' : $normalized;
			$allow[]    = $normalized;
		}

		return array_values( array_unique( $allow ) );
	}
}

add_filter( 'my_members_allowlist_paths', 'ovl_extend_code_snippets_allowlist', 1 );

if ( ! function_exists( 'ovl_requires_login' ) ) {
	/**
	 * Returns true when the current request should require login.
	 *
	 * @return bool
	 */
	function ovl_requires_login(): bool {
		$path = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$parsed = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			if ( isset( $parsed['path'] ) ) {
				$path = trim( $parsed['path'], '/' );
			}
		}

		if ( $path === 'member-consult' ) {
			return true;
		}

		if ( $path !== '' && strpos( $path, 'property/' ) === 0 ) {
			return true;
		}

		return is_singular( 'property' ) || is_page( 'member-consult' );
	}
}

if ( ! function_exists( 'ovl_allow_public_property_pages' ) ) {
	/**
	 * Forces all non-protected pages to remain public in Members.
	 *
	 * @param bool $is_private Whether Members would treat current request as private.
	 *
	 * @return bool
	 */
	function ovl_allow_public_property_pages( bool $is_private ): bool {
		return ovl_requires_login();
	}
}

add_filter( 'members_is_private_page', 'ovl_allow_public_property_pages', 20 ); // OVL: Mark only protected pages as private.

if ( ! function_exists( 'ovl_allow_public_private_blog' ) ) {
	/**
	 * Forces Members private-site flag off for public pages.
	 *
	 * @param bool $is_private Whether Members would treat the site as private.
	 *
	 * @return bool
	 */
	function ovl_allow_public_private_blog( bool $is_private ): bool {
		return ovl_requires_login() ? $is_private : false;
	}
}

add_filter( 'members_is_private_blog', 'ovl_allow_public_private_blog', 20 ); // OVL: Skip private-site mode for public pages.

if ( ! function_exists( 'ovl_skip_members_private_redirect' ) ) {
	/**
	 * Removes Members private-site redirect on allowed public pages.
	 */
	function ovl_skip_members_private_redirect(): void {
		if ( ! function_exists( 'members_please_log_in' ) ) {
			return;
		}

		if ( ! ovl_requires_login() ) {
			remove_action( 'template_redirect', 'members_please_log_in', 0 );
		}
	}
}

add_action( 'template_redirect', 'ovl_skip_members_private_redirect', -1 ); // OVL: Remove private redirect before Members runs.

if ( ! function_exists( 'ovl_allow_public_wpmembers_pages' ) ) {
	/**
	 * Forces all non-protected pages to remain public in WP-Members.
	 *
	 * @param bool  $block Whether WP-Members would block the current request.
	 * @param array $args  Details passed by WP-Members block checks.
	 *
	 * @return bool
	 */
	function ovl_allow_public_wpmembers_pages( bool $block, array $args ): bool {
		return ovl_requires_login();
	}
}

add_filter( 'wpmem_block', 'ovl_allow_public_wpmembers_pages', 20, 2 ); // OVL: Only block protected pages under WP-Members.
