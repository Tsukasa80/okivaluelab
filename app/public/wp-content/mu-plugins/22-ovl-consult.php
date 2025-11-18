<?php
// OVL: Consultation CPT + admin UI used by `[member_consult_form]`.

require_once __DIR__ . '/10-ovl-bootstrap.php';

if ( ! function_exists( 'ovl_register_consult_post_type' ) ) {
	/**
	 * Registers the consult custom post type.
	 *
	 * @return void
	 */
	function ovl_register_consult_post_type(): void {
		$caps = [
			'edit_post'          => 'edit_consult',
			'read_post'          => 'read_consult',
			'delete_post'        => 'delete_consult',
			'edit_posts'         => 'edit_consults',
			'edit_others_posts'  => 'edit_others_consults',
			'publish_posts'      => 'publish_consults',
			'read_private_posts' => 'read_private_consults',
			'create_posts'       => 'create_consults',
			'delete_posts'       => 'delete_consults',
			'delete_private_posts'   => 'delete_private_consults',
			'delete_published_posts' => 'delete_published_consults',
			'delete_others_posts'    => 'delete_others_consults',
			'edit_private_posts'     => 'edit_private_consults',
			'edit_published_posts'   => 'edit_published_consults',
		];

		register_post_type(
			'consult',
			[
				'label'        => '会員相談',
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'supports'     => [ 'title', 'editor', 'author' ],
				'menu_icon'    => 'dashicons-feedback',
				'menu_position'=> 25,
				'map_meta_cap' => true,
				'capability_type' => [ 'consult', 'consults' ],
				'capabilities'   => $caps,
			]
		);
	}
}
add_action( 'init', 'ovl_register_consult_post_type', 5 );

if ( ! function_exists( 'ovl_assign_consult_capabilities' ) ) {
	/**
	 * Ensures roles have the expected consult capabilities.
	 *
	 * @return void
	 */
	function ovl_assign_consult_capabilities(): void {
		$caps = [
			'edit_consult',
			'read_consult',
			'delete_consult',
			'edit_consults',
			'edit_others_consults',
			'publish_consults',
			'read_private_consults',
			'delete_consults',
			'delete_private_consults',
			'delete_published_consults',
			'delete_others_consults',
			'edit_private_consults',
			'edit_published_consults',
			'create_consults',
		];

		if ( $admin = get_role( 'administrator' ) ) {
			foreach ( $caps as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		$member_roles = array_filter(
			[
				get_role( 'member' ),
				get_role( 'subscriber' ),
			]
		);

		foreach ( $member_roles as $role ) {
			$role->add_cap( 'create_consults' );
			$role->add_cap( 'read' );
		}
	}
}
add_action( 'init', 'ovl_assign_consult_capabilities', 6 );

if ( ! function_exists( 'ovl_register_consult_meta' ) ) {
	/**
	 * Registers consult meta fields for REST/editing.
	 *
	 * @return void
	 */
	function ovl_register_consult_meta(): void {
		$meta_args = static function ( string $type = 'string' ): array {
			return [
				'type'         => $type,
				'single'       => true,
				'show_in_rest' => true,
				'auth_callback'=> static function () {
					return current_user_can( 'edit_consults' );
				},
			];
		};

		register_post_meta( 'consult', 'applicant_name', $meta_args() );
		register_post_meta( 'consult', 'applicant_email', $meta_args() );
		register_post_meta( 'consult', 'property_url', $meta_args() );
		register_post_meta( 'consult', 'contact_method', $meta_args() );
		register_post_meta( 'consult', 'phone', $meta_args() );
		register_post_meta( 'consult', 'preferred_time', $meta_args() );
		register_post_meta( 'consult', 'privacy_agreed', $meta_args( 'boolean' ) );
	}
}
add_action( 'init', 'ovl_register_consult_meta', 9 );

if ( ! function_exists( 'ovl_consult_metabox_callback' ) ) {
	/**
	 * Renders meta box fields.
	 *
	 * @param WP_Post $post Current post.
	 *
	 * @return void
	 */
	function ovl_consult_metabox_callback( WP_Post $post ): void {
		wp_nonce_field( 'ovl_consult_meta', 'ovl_consult_meta_nonce' );

		$get_meta = static function ( string $key ) use ( $post ): string {
			return (string) get_post_meta( $post->ID, $key, true );
		};
		?>
		<table class="form-table">
			<tr>
				<th><label for="ovl_applicant_name">お名前</label></th>
				<td><input type="text" id="ovl_applicant_name" name="ovl_applicant_name" class="regular-text" value="<?php echo esc_attr( $get_meta( 'applicant_name' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ovl_applicant_email">メール</label></th>
				<td><input type="email" id="ovl_applicant_email" name="ovl_applicant_email" class="regular-text" value="<?php echo esc_attr( $get_meta( 'applicant_email' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ovl_phone">電話</label></th>
				<td><input type="text" id="ovl_phone" name="ovl_phone" class="regular-text" value="<?php echo esc_attr( $get_meta( 'phone' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ovl_contact_method">希望連絡方法</label></th>
				<td><input type="text" id="ovl_contact_method" name="ovl_contact_method" class="regular-text" value="<?php echo esc_attr( $get_meta( 'contact_method' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ovl_preferred_time">都合の良い時間帯</label></th>
				<td><input type="text" id="ovl_preferred_time" name="ovl_preferred_time" class="regular-text" value="<?php echo esc_attr( $get_meta( 'preferred_time' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ovl_property_url">物件URL</label></th>
				<td><input type="url" id="ovl_property_url" name="ovl_property_url" class="regular-text" value="<?php echo esc_attr( $get_meta( 'property_url' ) ); ?>"></td>
			</tr>
			<tr>
				<th>プライバシー同意</th>
				<td>
					<label>
						<input type="checkbox" name="ovl_privacy_agreed" value="1" <?php checked( (int) get_post_meta( $post->ID, 'privacy_agreed', true ), 1 ); ?>>
						同意済み
					</label>
				</td>
			</tr>
		</table>
		<p class="description">本文（相談内容）は通常のエディターに格納されています。</p>
		<?php
	}
}

if ( ! function_exists( 'ovl_add_consult_metabox' ) ) {
	/**
	 * Adds consult meta box.
	 *
	 * @return void
	 */
	function ovl_add_consult_metabox(): void {
		add_meta_box(
			'ovl-consult-details',
			'相談フォーム入力',
			'ovl_consult_metabox_callback',
			'consult',
			'normal',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'ovl_add_consult_metabox' );

if ( ! function_exists( 'ovl_save_consult_meta' ) ) {
	/**
	 * Persists consult meta fields.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	function ovl_save_consult_meta( int $post_id ): void {
		if ( ! isset( $_POST['ovl_consult_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ovl_consult_meta_nonce'] ), 'ovl_consult_meta' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$get = static function ( string $key, callable $sanitize ) {
			return isset( $_POST[ $key ] ) ? $sanitize( wp_unslash( $_POST[ $key ] ) ) : '';
		};

		update_post_meta( $post_id, 'applicant_name', $get( 'ovl_applicant_name', 'sanitize_text_field' ) );
		update_post_meta( $post_id, 'applicant_email', $get( 'ovl_applicant_email', 'sanitize_email' ) );
		update_post_meta( $post_id, 'phone', $get( 'ovl_phone', 'sanitize_text_field' ) );
		update_post_meta( $post_id, 'contact_method', $get( 'ovl_contact_method', 'sanitize_text_field' ) );
		update_post_meta( $post_id, 'preferred_time', $get( 'ovl_preferred_time', 'sanitize_text_field' ) );
		update_post_meta( $post_id, 'property_url', $get( 'ovl_property_url', 'esc_url_raw' ) );
		update_post_meta( $post_id, 'privacy_agreed', isset( $_POST['ovl_privacy_agreed'] ) ? 1 : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
}
add_action( 'save_post_consult', 'ovl_save_consult_meta' );

if ( ! function_exists( 'ovl_manage_consult_columns' ) ) {
	/**
	 * Adjusts consult list table columns.
	 *
	 * @param array $columns Columns.
	 *
	 * @return array
	 */
	function ovl_manage_consult_columns( array $columns ): array {
		$new = [
			'cb'               => $columns['cb'],
			'title'            => 'タイトル',
			'applicant_name'   => 'お名前',
			'applicant_email'  => 'メール',
			'contact_method'   => '連絡方法',
			'phone'            => '電話番号',
			'property_url'     => '物件URL',
			'date'             => $columns['date'] ?? '日時',
		];

		return $new;
	}
}
add_filter( 'manage_edit-consult_columns', 'ovl_manage_consult_columns' );

if ( ! function_exists( 'ovl_render_consult_column' ) ) {
	/**
	 * Outputs custom column values.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 *
	 * @return void
	 */
	function ovl_render_consult_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'applicant_name':
			case 'applicant_email':
			case 'contact_method':
			case 'phone':
				echo esc_html( (string) get_post_meta( $post_id, $column, true ) );
				break;
			case 'property_url':
				$url = esc_url( (string) get_post_meta( $post_id, 'property_url', true ) );
				if ( $url ) {
					echo '<a href="' . $url . '" target="_blank" rel="noopener">リンク</a>'; // phpcs:ignore
				}
				break;
		}
	}
}
add_action( 'manage_consult_posts_custom_column', 'ovl_render_consult_column', 10, 2 );

if ( ! function_exists( 'ovl_sortable_consult_columns' ) ) {
	/**
	 * Marks consult columns sortable.
	 *
	 * @param array $columns Columns.
	 *
	 * @return array
	 */
	function ovl_sortable_consult_columns( array $columns ): array {
		$columns['applicant_name']  = 'applicant_name';
		$columns['applicant_email'] = 'applicant_email';

		return $columns;
	}
}
add_filter( 'manage_edit-consult_sortable_columns', 'ovl_sortable_consult_columns' );

if ( ! function_exists( 'ovl_consult_orderby_meta' ) ) {
	/**
	 * Applies meta ordering for custom columns.
	 *
	 * @param WP_Query $query Query.
	 *
	 * @return void
	 */
	function ovl_consult_orderby_meta( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || 'consult' !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( 'applicant_name' === $orderby ) {
			$query->set( 'meta_key', 'applicant_name' );
			$query->set( 'orderby', 'meta_value' );
		} elseif ( 'applicant_email' === $orderby ) {
			$query->set( 'meta_key', 'applicant_email' );
			$query->set( 'orderby', 'meta_value' );
		}
	}
}
add_action( 'pre_get_posts', 'ovl_consult_orderby_meta' );
