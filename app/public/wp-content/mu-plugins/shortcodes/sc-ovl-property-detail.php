<?php
// OVL: Legacy `[ovl_property_detail]` compatibility via the new ovl-page renderer.

if ( ! function_exists( 'ovl_shortcode_property_detail' ) ) {
	/**
	 * Proxies the legacy shortcode to the new ovl-page renderer.
	 *
	 * @return string
	 */
	function ovl_shortcode_property_detail(): string {
		// OVL: Reuse the generic dispatcher to keep logic in `ovl-pages/page-property-detail.php`.
		return do_shortcode( '[ovl_page slug="property-detail"]' );
	}
}

add_shortcode( 'ovl_property_detail', 'ovl_shortcode_property_detail' ); // OVL: Maintain backward compatibility with block templates.
