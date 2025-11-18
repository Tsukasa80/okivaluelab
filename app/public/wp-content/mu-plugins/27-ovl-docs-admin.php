<?php
// OVL: Admin helpers for managing property document uploads/deletions.

require_once __DIR__ . '/25-ovl-docs.php'; // OVL: Reuse document helper functions.

if ( ! function_exists( 'ovl_docs_add_metabox' ) ) {
	/**
	 * Adds the document status metabox to property edit screens.
	 *
	 * @return void
	 */
	function ovl_docs_add_metabox(): void {
		add_meta_box(
			'ovl-docs-status',
			'資料ダウンロード',
			'ovl_render_docs_metabox',
			'property',
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'ovl_docs_add_metabox' ); // OVL: Surface doc status on property edit screens.

if ( ! function_exists( 'ovl_render_docs_metabox' ) ) {
	/**
	 * Outputs current document info + delete button.
	 *
	 * @param WP_Post $post Current property post.
	 *
	 * @return void
	 */
	function ovl_render_docs_metabox( WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			echo '<p>権限がありません。</p>';
			return;
		}

		$basename = ovl_get_doc_basename( $post->ID );
		if ( $basename ) {
			echo '<p><strong>現在のファイル:</strong><br>' . esc_html( $basename ) . '</p>';
		} else {
			echo '<p>登録済みの資料はありません。</p>';
		}

		$form_id = 'ovl-delete-doc-form-' . (int) $post->ID;

		submit_button(
			'資料を削除',
			'delete',
			'submit',
			false,
			[
				'form'    => $form_id,
				'onclick' => "return confirm('関連する資料ファイルを削除します。よろしいですか？');",
			]
		);
	}
}

if ( ! function_exists( 'ovl_render_docs_hidden_form' ) ) {
	/**
	 * Outputs the hidden admin-post form so nested forms are avoided.
	 *
	 * @return void
	 */
	function ovl_render_docs_hidden_form(): void {
		global $post;

		if ( ! $post instanceof WP_Post || 'property' !== $post->post_type ) {
			return;
		}

		$form_id = 'ovl-delete-doc-form-' . (int) $post->ID;
		?>
		<form id="<?php echo esc_attr( $form_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
			<?php wp_nonce_field( 'ovl_delete_doc_' . $post->ID, 'ovl_delete_doc_nonce' ); ?>
			<input type="hidden" name="action" value="ovl_delete_doc">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
		</form>
		<?php
	}
}
add_action( 'admin_footer-post.php', 'ovl_render_docs_hidden_form' );
add_action( 'admin_footer-post-new.php', 'ovl_render_docs_hidden_form' );

if ( ! function_exists( 'ovl_handle_doc_delete_request' ) ) {
	/**
	 * Processes delete button submissions.
	 *
	 * @return void
	 */
	function ovl_handle_doc_delete_request(): void {
		$raw_post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		ovl_docs_log(
			'doc delete request received',
			[
				'post_id'  => $raw_post_id,
				'user_id'  => get_current_user_id(),
				'referer'  => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				'has_post' => isset( $_POST['post_id'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			]
		);

		if ( empty( $raw_post_id ) ) {
			ovl_docs_log( 'doc delete missing post_id', [] );
			wp_die( 'Invalid request.' );
		}

		$post_id = absint( $raw_post_id );
		check_admin_referer( 'ovl_delete_doc_' . $post_id, 'ovl_delete_doc_nonce' );
		ovl_docs_log(
			'doc delete nonce verified',
			[
				'post_id' => $post_id,
			]
		);

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			ovl_docs_log(
				'doc delete capability failure',
				[
					'post_id' => $post_id,
					'user_id' => get_current_user_id(),
				]
			);
			wp_die( '権限がありません。' );
		}

		ovl_clear_post_docs( $post_id );
		delete_post_meta( $post_id, 'doc_url' );
		ovl_docs_log(
			'doc delete cleared storage',
			[
				'post_id' => $post_id,
			]
		);

		if ( function_exists( 'update_field' ) ) {
			ovl_docs_set_force_clear_mode( true );
			update_field( 'doc_url', null, $post_id ); // OVL: Force ACF UI to clear the field.
			ovl_docs_set_force_clear_mode( false );
			ovl_docs_log(
				'doc delete update_field executed',
				[
					'post_id' => $post_id,
				]
			);
		} elseif ( function_exists( 'get_field_object' ) ) {
			$field = get_field_object( 'doc_url', $post_id, false, false );
			if ( $field && isset( $field['key'] ) ) {
				delete_post_meta( $post_id, $field['key'] );
			}
		}

		$redirect = add_query_arg(
			[
				'post'            => $post_id,
				'action'          => 'edit',
				'ovl-doc-deleted' => 1,
			],
			admin_url( 'post.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
add_action( 'admin_post_ovl_delete_doc', 'ovl_handle_doc_delete_request' ); // OVL: Process delete submissions.

if ( ! function_exists( 'ovl_docs_admin_notice' ) ) {
	/**
	 * Displays success notice after deletion.
	 *
	 * @return void
	 */
	function ovl_docs_admin_notice(): void {
		if ( ! isset( $_GET['ovl-doc-deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>資料ファイルを削除しました。</p></div>';
	}
}
add_action( 'admin_notices', 'ovl_docs_admin_notice' ); // OVL: Show confirmation after deletion.
