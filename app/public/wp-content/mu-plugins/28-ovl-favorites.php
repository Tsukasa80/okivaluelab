<?php
// OVL: Favorites feature for property posts.

if ( ! defined( 'OVL_FAVORITES_META_KEY' ) ) {
	// OVL: User meta key to store property IDs.
	define( 'OVL_FAVORITES_META_KEY', 'ovl_favorite_property_ids' );
}

if ( ! function_exists( 'ovl_favorites_get_ids' ) ) {
	/**
	 * Returns sanitized favorite property IDs for a user.
	 *
	 * @param int|null $user_id User ID. Defaults to current user.
	 * @return array<int>
	 */
	function ovl_favorites_get_ids( ?int $user_id = null ): array {
		$user_id = $user_id ?: get_current_user_id();
		if ( $user_id < 1 ) {
			return [];
		}

		$raw_ids = get_user_meta( $user_id, OVL_FAVORITES_META_KEY, true );
		if ( ! is_array( $raw_ids ) ) {
			return [];
		}

		$ids = array_values(
			array_filter(
				array_unique( array_map( 'absint', $raw_ids ) ),
				static function ( $id ) {
					return $id > 0;
				}
			)
		);

		return $ids;
	}
}

if ( ! function_exists( 'ovl_favorites_update_ids' ) ) {
	/**
	 * Updates the favorite property IDs for a user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $ids     Property IDs.
	 *
	 * @return array<int> Saved IDs.
	 */
	function ovl_favorites_update_ids( int $user_id, array $ids ): array {
		$clean_ids = array_values(
			array_filter(
				array_unique( array_map( 'absint', $ids ) ),
				static function ( $id ) {
					return $id > 0;
				}
			)
		);

		update_user_meta( $user_id, OVL_FAVORITES_META_KEY, $clean_ids );

		return $clean_ids;
	}
}

if ( ! function_exists( 'ovl_favorites_validate_property' ) ) {
	/**
	 * Validates a property ID before toggling.
	 *
	 * @param int $property_id Property post ID.
	 * @return true|\WP_Error
	 */
	function ovl_favorites_validate_property( int $property_id ) {
		if ( $property_id < 1 ) {
			return new WP_Error( 'ovl_invalid_property', __( 'Invalid property ID.', 'ovl' ), [ 'status' => 400 ] );
		}

		$post = get_post( $property_id );
		if ( ! $post || 'property' !== $post->post_type ) {
			return new WP_Error( 'ovl_invalid_property', __( 'Property not found.', 'ovl' ), [ 'status' => 404 ] );
		}

		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'ovl_unpublished_property', __( 'This property is not available.', 'ovl' ), [ 'status' => 403 ] );
		}

		return true;
	}
}

if ( ! function_exists( 'ovl_favorites_toggle' ) ) {
	/**
	 * Toggles a property ID for the given user.
	 *
	 * @param int $user_id     User ID.
	 * @param int $property_id Property ID.
	 *
	 * @return array{favorited: bool, favorites: array<int>}
	 */
	function ovl_favorites_toggle( int $user_id, int $property_id ): array {
		$current = ovl_favorites_get_ids( $user_id );
		$in_list = in_array( $property_id, $current, true );

		if ( $in_list ) {
			$current = array_values(
				array_filter(
					$current,
					static function ( $id ) use ( $property_id ) {
						return $id !== $property_id;
					}
				)
			);
		} else {
			$current[] = $property_id;
		}

		$updated = ovl_favorites_update_ids( $user_id, $current );

		return [
			'favorited' => ! $in_list,
			'favorites' => $updated,
		];
	}
}

if ( ! function_exists( 'ovl_favorites_rest_get' ) ) {
	/**
	 * REST: Returns favorite IDs for the current user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	function ovl_favorites_rest_get( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'ovl_not_logged_in', __( 'Authentication required.', 'ovl' ), [ 'status' => 401 ] );
		}

		$user_id = get_current_user_id();
		$ids     = ovl_favorites_get_ids( $user_id );

		return rest_ensure_response(
			[
				'favorites' => $ids,
			]
		);
	}
}

if ( ! function_exists( 'ovl_favorites_rest_toggle' ) ) {
	/**
	 * REST: Toggles a property favorite for the current user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	function ovl_favorites_rest_toggle( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'ovl_not_logged_in', __( 'Authentication required.', 'ovl' ), [ 'status' => 401 ] );
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'ovl_invalid_nonce', __( 'Invalid security token.', 'ovl' ), [ 'status' => 403 ] );
		}

		$property_id = absint( $request->get_param( 'property_id' ) );
		$valid       = ovl_favorites_validate_property( $property_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$user_id = get_current_user_id();
		$result  = ovl_favorites_toggle( $user_id, $property_id );

		return rest_ensure_response(
			[
				'property_id' => $property_id,
				'favorited'   => $result['favorited'],
				'favorites'   => $result['favorites'],
			]
		);
	}
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'ovl/v1',
			'/favorites',
			[
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
				'callback'            => 'ovl_favorites_rest_get',
			]
		);

		register_rest_route(
			'ovl/v1',
			'/favorites/toggle',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
				'callback'            => 'ovl_favorites_rest_toggle',
				'args'                => [
					'property_id' => [
						'type'     => 'integer',
						'required' => true,
					],
				],
			]
		);
	}
);

add_filter(
	'the_content',
	static function ( string $content ): string {
		// OVL: Gutenberg/WP-Members may inject empty paragraphs around shortcodes.
		if ( ! is_page( 'members' ) ) {
			return $content;
		}

		// Note: At this stage the shortcode may already be expanded, so also detect by rendered markers.
		if (
			false === strpos( $content, 'ovl_favorites' )
			&& false === strpos( $content, 'ovl-favorites' )
			&& false === strpos( $content, 'ovl-property-card__thumb' )
		) {
			return $content;
		}

		// Remove empty <p> nodes that contain only whitespace, non-breaking spaces, or <br>.
		$content = preg_replace( '#<p[^>]*>(?:\s|&nbsp;|&#160;|<br\s*/?>)*</p>#i', '', $content );

		// Unwrap <p> that incorrectly wraps our thumb link markup.
		$content = preg_replace(
			'#<p[^>]*>\s*(<a\b(?:(?!</a>).)*\bclass=(["\'])(?:(?!\2).)*\bovl-property-card__thumb\b(?:(?!\2).)*\2(?:(?!</a>).)*</a>)\s*</p>#is',
			'$1',
			$content
		);

		// Remove wpautop-inserted <br> right before closing the thumb link.
		$content = preg_replace(
			'#(<a\b(?:(?!</a>).)*\bclass=(["\'])(?:(?!\2).)*\bovl-property-card__thumb\b(?:(?!\2).)*\2(?:(?!</a>).)*)<br\s*/?>\s*(</a>)#is',
			'$1$2',
			$content
		);

		// Remove wpautop-inserted <br> right after opening the thumb link.
		$content = preg_replace(
			'#(<a\b(?:(?!</a>).)*\bclass=(["\'])(?:(?!\2).)*\bovl-property-card__thumb\b(?:(?!\2).)*\2(?:(?!</a>).)*?>)\s*<br\s*/?>#is',
			'$1',
			$content
		);

		// Remove stray <br> immediately after the thumb link.
		$content = preg_replace(
			'#(</a>)\s*<br\s*/?>#i',
			'$1',
			$content
		);

		return $content;
	},
	99
);
