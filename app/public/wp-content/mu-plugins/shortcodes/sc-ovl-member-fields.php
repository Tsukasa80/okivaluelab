<?php
// OVL: Display WP-Members user fields (checked for "register") as read-only rows.

if ( ! function_exists( 'ovl_member_fields_flatten_value' ) ) {
	function ovl_member_fields_flatten_value( $value ): array {
		$flattened = [];
		foreach ( (array) $value as $item ) {
			if ( is_array( $item ) ) {
				if ( isset( $item['label'] ) && is_scalar( $item['label'] ) ) {
					$flattened[] = sanitize_text_field( (string) $item['label'] );
					continue;
				}
				if ( isset( $item['value'] ) && is_scalar( $item['value'] ) ) {
					$flattened[] = sanitize_text_field( (string) $item['value'] );
					continue;
				}
				$flattened = array_merge( $flattened, ovl_member_fields_flatten_value( $item ) );
				continue;
			}
			if ( is_object( $item ) ) {
				if ( method_exists( $item, '__toString' ) ) {
					$flattened[] = sanitize_text_field( (string) $item );
				} else {
					$flattened[] = sanitize_text_field( wp_json_encode( $item ) );
				}
				continue;
			}
			if ( null === $item ) {
				continue;
			}
			$flattened[] = sanitize_text_field( (string) $item );
		}
		return array_values( array_filter( $flattened, static function( $text ) {
			return '' !== $text;
		} ) );
	}
}

if ( ! function_exists( 'ovl_shortcode_member_fields' ) ) {
	/**
	 * Renders a read-only list of WP-Members fields for the current user.
	 *
	 * Usage: [ovl_member_fields] (defaults to fields with "register" enabled)
	 * Options:
	 * - tag: wpmem_fields tag (register|profile|all) default: register
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Enclosed content (unused).
	 * @param string $tag     Shortcode tag.
	 * @return string
	 */
	function ovl_shortcode_member_fields( array $atts = [], string $content = '', string $tag = '' ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		if ( ! function_exists( 'wpmem_fields' ) ) {
			return '';
		}

		$context = isset( $GLOBALS['ovl_member_fields_context'] ) ? (string) $GLOBALS['ovl_member_fields_context'] : '';

		$action = '';
		if ( isset( $_REQUEST['a'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['a'] ) );
		}

		// OVL: When used on the WP-Members profile page, prefer rendering under the profile buttons.
		if ( '' === $context && function_exists( 'wpmem_is_profile' ) && wpmem_is_profile() && in_array( $action, [ '', 'update' ], true ) ) {
			return '';
		}

		// OVL: Avoid duplicate output when WP-Members is rendering edit/password forms via ?a=...
		if ( in_array( $action, [ 'edit', 'pwdchange', 'pwdreset', 'renew', 'set_password_from_key' ], true ) ) {
			return '';
		}

		// OVL: On update, WP-Members may re-render the edit form when there are errors.
		if ( 'update' === $action ) {
			$regchk = '';
			if ( isset( $GLOBALS['wpmem'] ) && is_object( $GLOBALS['wpmem'] ) && isset( $GLOBALS['wpmem']->regchk ) ) {
				$regchk = (string) $GLOBALS['wpmem']->regchk;
			}

			if ( in_array( $regchk, [ 'updaterr', 'email' ], true ) ) {
				return '';
			}
		}

		$atts = shortcode_atts(
			[
				'tag' => 'register',
				'labels' => '',
				'title' => 'ご登録情報',
				'title_level' => 2,
			],
			$atts,
			$tag
		);

		$label_overrides = [];
		if ( function_exists( 'apply_filters' ) ) {
			$label_overrides = apply_filters( 'ovl_member_fields_label_overrides', $label_overrides );
		}

		if ( ! $label_overrides ) {
			$label_overrides = [
				'first_name'        => '名',
				'last_name'         => '姓',
				'prefecture'     => '都道府県',
				'billing_city'      => '市区町村',
				'billing_address_1' => '住所（番地）',
				'billing_address_2' => '住所（建物名など）',
				'billing_postcode'  => '郵便番号',
				'billing_country'   => '国',
				'billing_phone'     => '電話番号',
				'user_email'        => 'メールアドレス',
				'user_url'          => 'Webサイト',
				'description'       => '自己紹介',
			];
		}

		if ( is_string( $atts['labels'] ) && '' !== trim( $atts['labels'] ) ) {
			$raw = trim( $atts['labels'] );

			if ( '{' === substr( $raw, 0, 1 ) ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $k => $v ) {
						$key = sanitize_key( (string) $k );
						if ( '' === $key ) {
							continue;
						}
						$label_overrides[ $key ] = sanitize_text_field( (string) $v );
					}
				}
			} else {
				$pairs = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
				foreach ( $pairs as $pair ) {
					$parts = array_map( 'trim', explode( ':', $pair, 2 ) );
					if ( 2 !== count( $parts ) ) {
						continue;
					}
					$key = sanitize_key( $parts[0] );
					if ( '' === $key ) {
						continue;
					}
					$label_overrides[ $key ] = sanitize_text_field( $parts[1] );
				}
			}
		}

		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return '';
		}

		$fields = wpmem_fields( sanitize_key( (string) $atts['tag'] ) );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return '';
		}

		// 表示上の並び順だけ調整（WP-Membersのフィールド順は触らない）
		// 例: 「姓→名」の順にしたい
		if ( isset( $fields['first_name'], $fields['last_name'] ) ) {
			$reordered_fields = [
				'last_name'  => $fields['last_name'],
				'first_name' => $fields['first_name'],
			];
			foreach ( $fields as $meta_key => $field ) {
				if ( 'first_name' === $meta_key || 'last_name' === $meta_key ) {
					continue;
				}
				$reordered_fields[ $meta_key ] = $field;
			}
			$fields = $reordered_fields;
		}

		$exclude_meta_keys = [
			'password',
			'confirm_password',
			'username',
			'user_pass',
		];

		$api = null;
		$api_fields = [];
		if ( class_exists( 'WP_Members_API' ) ) {
			$api = new WP_Members_API();
			$api_fields = wpmem_fields( 'all' );
			if ( ! is_array( $api_fields ) ) {
				$api_fields = [];
			}
		}

		$rows = [];

		foreach ( $fields as $meta_key => $field ) {
			$meta_key = (string) $meta_key;
			if ( '' === $meta_key ) {
				continue;
			}
			if ( in_array( $meta_key, $exclude_meta_keys, true ) ) {
				continue;
			}

			$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
			$label = isset( $field['label'] ) ? (string) $field['label'] : $meta_key;
			if ( isset( $label_overrides[ $meta_key ] ) && '' !== (string) $label_overrides[ $meta_key ] ) {
				$label = (string) $label_overrides[ $meta_key ];
			}

			if ( 'password' === $type ) {
				continue;
			}

			$value = get_the_author_meta( $meta_key, $user_id );

			if ( $api && isset( $api_fields[ $meta_key ] ) && in_array( $type, [ 'select', 'radio', 'multiselect', 'multicheckbox', 'image', 'file' ], true ) ) {
				$value = $api->get_field_display_value( $meta_key, $user_id, is_scalar( $value ) ? (string) $value : null );
			}

			if ( is_array( $value ) ) {
				$value = implode( '、', ovl_member_fields_flatten_value( $value ) );
			}

			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			$rows[] = '<li class="ovl-member-fields__row"><span class="ovl-member-fields__label">' . esc_html( $label ) . '</span><span class="ovl-member-fields__sep">：</span><span class="ovl-member-fields__value">' . esc_html( $value ) . '</span></li>';
		}

		if ( ! $rows ) {
			return '';
		}

		$title = is_string( $atts['title'] ) ? trim( $atts['title'] ) : '';
		$level = absint( $atts['title_level'] );
		if ( $level < 1 || $level > 6 ) {
			$level = 2;
		}

		$out = '';
		if ( '' !== $title ) {
			$out .= '<h' . $level . ' class="ovl-member-fields__title">' . esc_html( $title ) . '</h' . $level . '>';
		}

		$out .= '<ul class="ovl-member-fields">' . implode( '', $rows ) . '</ul>';
		$GLOBALS['ovl_member_fields_rendered'] = true;
		return $out;
	}
}

add_shortcode( 'ovl_member_fields', 'ovl_shortcode_member_fields' );
