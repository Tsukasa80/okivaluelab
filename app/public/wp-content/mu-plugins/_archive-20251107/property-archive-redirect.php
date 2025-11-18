<?php
/**
 * Redirect legacy /property/ archive URLs to the official /property_list/ page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'template_redirect', function () {
	if ( is_admin() ) {
		return;
	}

	$path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

	if ( $path === 'property' ) {
		wp_safe_redirect( home_url( '/property_list/' ), 301 );
		exit;
	}
} );
