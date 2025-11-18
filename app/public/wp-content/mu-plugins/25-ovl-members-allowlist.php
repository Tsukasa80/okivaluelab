<?php
// OVL: Allow selected property pages to stay public even with Members private site enabled.

require_once __DIR__ . '/10-ovl-bootstrap.php'; // OVL: Share helper stack/order.

if ( ! function_exists( 'ovl_allow_public_property_pages' ) ) {
	/**
	 * Forces property listing surfaces to remain public.
	 *
	 * @param bool $is_private Whether Members would treat current request as private.
	 *
	 * @return bool
	 */
	function ovl_allow_public_property_pages( bool $is_private ): bool {
		// OVL: Skip redirects for property listings while keeping other pages private.
		if (
			is_page( 'property_list' )
			|| is_singular( 'property' )
			|| is_post_type_archive( 'property' )
			|| is_tax( [ 'property_cat', 'property_tag' ] )
		) {
			return false;
		}

		return $is_private;
	}
}

add_filter( 'members_is_private_page', 'ovl_allow_public_property_pages', 20 ); // OVL: Inject allowlist before Members redirects fire.
