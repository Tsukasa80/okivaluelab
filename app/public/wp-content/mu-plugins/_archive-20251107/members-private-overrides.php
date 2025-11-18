<?php
/**
 * Plugin Name: OVL Members Private Overrides
 * Description: Allows public access to property listings while Members「プライベートサイト」機能が有効な場合でもリダイレクトを抑止する。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'members_is_private_page',
	static function ( $is_private ) {
		if ( is_page( 'property_list' ) ) {
			return false;
		}

		if (
			is_singular( 'property' )
			|| is_post_type_archive( 'property' )
			|| is_tax( [ 'property_cat', 'property_tag' ] )
		) {
			return false;
		}

		return $is_private;
	},
	20
);
