<?php
// OVL: `[member_consult_form]` members-only consultation form + submission handler.

require_once __DIR__ . '/../10-ovl-bootstrap.php';

if ( ! function_exists( 'ovl_shortcode_member_consult_form' ) ) {
	/**
	 * Renders the consultation form for logged-in members.
	 *
	 * @return string
	 */
	function ovl_shortcode_member_consult_form(): string {
		if ( ! is_user_logged_in() ) {
			$login_url = esc_url( home_url( '/members/' ) );

			return '<p>このフォームは会員限定です。<a href="' . $login_url . '">ログイン／登録</a>してください。</p>';
		}

		$user         = wp_get_current_user();
		$default_name = $user->display_name ?: $user->user_login;
		$action       = esc_url( admin_url( 'admin-post.php' ) );
		$status       = isset( $_GET['consult'] ) ? sanitize_text_field( wp_unslash( $_GET['consult'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice       = '';

		if ( 'ok' === $status ) {
			$notice = '<div class="mc-alert mc-ok">送信しました。担当からご連絡します。</div>';
		} elseif ( 'ng' === $status ) {
			$reason = isset( $_GET['consult_reason'] ) ? sanitize_text_field( wp_unslash( $_GET['consult_reason'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tail   = $reason ? '（' . esc_html( $reason ) . '）' : '';
			$notice = '<div class="mc-alert mc-ng">送信に失敗しました' . $tail . '。もう一度お試しください。</div>';
		}

		ob_start();
		?>
		<div class="mc-wrap">
			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<form class="mc-form" method="post" action="<?php echo $action; ?>">
				<?php wp_nonce_field( 'member_consult', 'mc_nonce' ); ?>
				<input type="hidden" name="action" value="member_consult">

				<div class="mc-row">
					<label>お名前</label>
					<input type="text" name="mc_name" value="<?php echo esc_attr( $default_name ); ?>" required>
				</div>

				<div class="mc-row">
					<label>メール</label>
					<input type="email" name="mc_email" value="<?php echo esc_attr( $user->user_email ); ?>" required>
				</div>

				<div class="mc-row">
					<label>物件URL（任意）</label>
					<input type="url" name="mc_property_url" placeholder="https://example.com/xxxx">
				</div>

				<div class="mc-row">
					<label>相談内容</label>
					<textarea name="mc_details" rows="6" placeholder="例：収益性の目安、融資の方向性、売却可否など" required></textarea>
				</div>

				<div class="mc-row">
					<label>希望連絡方法</label>
					<label><input type="radio" name="mc_contact" value="メール" checked> メール</label>
					<label><input type="radio" name="mc_contact" value="電話"> 電話</label>
					<label><input type="radio" name="mc_contact" value="オンライン面談"> オンライン面談</label>
				</div>

				<div class="mc-row">
					<label>電話番号（電話希望時）</label>
					<input type="tel" name="mc_phone" placeholder="090-xxxx-xxxx">
				</div>

				<div class="mc-row">
					<label>都合の良い時間帯（任意）</label>
					<input type="text" name="mc_time" placeholder="平日18-20時など">
				</div>

				<div class="mc-row">
					<label><input type="checkbox" name="mc_privacy" value="1" required> プライバシーポリシーに同意します</label>
				</div>

				<div class="mc-actions">
					<button type="submit">相談内容を送信</button>
				</div>
			</form>
		</div>
		<style>
			.mc-wrap{max-width:720px;margin:16px 0}
			.mc-row{margin-bottom:12px;display:grid;gap:6px}
			.mc-row input[type=text],
			.mc-row input[type=email],
			.mc-row input[type=url],
			.mc-row input[type=tel],
			.mc-row textarea{padding:10px;border:1px solid #ddd;border-radius:8px;width:100%}
			.mc-actions button{padding:12px 16px;border:0;border-radius:10px;background:#1e73be;color:#fff;font-weight:600;cursor:pointer}
			.mc-actions button:hover{opacity:.9}
			.mc-alert{padding:12px 14px;border-radius:10px;margin-bottom:12px}
			.mc-ok{background:#ecf9f1}
			.mc-ng{background:#fdecec}
		</style>
		<?php

		return (string) ob_get_clean();
	}
}

add_shortcode( 'member_consult_form', 'ovl_shortcode_member_consult_form' );

if ( ! function_exists( 'ovl_member_consult_redirect_url' ) ) {
	/**
	 * Builds the redirect URL after submission attempts.
	 *
	 * @param string $status ok|ng.
	 * @param array  $extra  Extra query vars.
	 *
	 * @return string
	 */
	function ovl_member_consult_redirect_url( string $status, array $extra = [] ): string {
		$query = array_merge(
			[
				'consult' => $status,
			],
			$extra
		);

		$default = add_query_arg( $query, home_url( '/members/' ) );

		/**
		 * Filters the redirect URL used after submitting the consult form.
		 *
		 * @param string $default Default URL.
		 * @param string $status  Submission status.
		 * @param array  $extra   Extra query vars.
		 */
		return apply_filters( 'ovl/member_consult_redirect', $default, $status, $extra );
	}
}

if ( ! function_exists( 'ovl_handle_member_consult_submission' ) ) {
	/**
	 * Handles consultation form submissions.
	 *
	 * @return void
	 */
	function ovl_handle_member_consult_submission(): void {
		$log = static function ( string $message, array $context = [] ): void {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				$line = '[OVL:consult] ' . $message;
				if ( ! empty( $context ) ) {
					$line .= ' ' . wp_json_encode( $context );
				}

				 error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		};

		$current_user = wp_get_current_user();
		$log(
			'submission received',
			[
				'user_id' => $current_user->ID,
				'roles'   => $current_user->roles,
			]
		);

		$required_redirect = static function( string $reason ) use ( $log ) {
			$url = ovl_member_consult_redirect_url(
				'ng',
				[
					'consult_reason' => $reason,
				]
			);
			$log( 'redirecting failure', [ 'reason' => $reason, 'url' => $url ] );
			wp_safe_redirect( $url );
			exit;
		};

		if ( ! post_type_exists( 'consult' ) ) {
			$log( 'missing post type' );
			$required_redirect( 'no_cpt' );
		}

		if ( ! is_user_logged_in() ) {
			$log( 'not logged in' );
			$required_redirect( 'no_login' );
		}

		if ( ! current_user_can( 'create_consults' ) ) {
			$log( 'missing capability', [ 'roles' => $current_user->roles ] );
			$required_redirect( 'no_cap_create_consults' );
		}

		$nonce = isset( $_POST['mc_nonce'] ) ? wp_unslash( $_POST['mc_nonce'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! wp_verify_nonce( $nonce, 'member_consult' ) ) {
			$log( 'nonce failure' );
			wp_safe_redirect( home_url( '/members/?consult=ng&consult_reason=bad_nonce' ) );
			exit;
		}

		$uid   = get_current_user_id();
		$name  = isset( $_POST['mc_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email = isset( $_POST['mc_email'] ) ? sanitize_email( wp_unslash( $_POST['mc_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$tel   = isset( $_POST['mc_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$url   = isset( $_POST['mc_property_url'] ) ? esc_url_raw( wp_unslash( $_POST['mc_property_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$pref  = isset( $_POST['mc_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_contact'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$time  = isset( $_POST['mc_time'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_time'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$agree = isset( $_POST['mc_privacy'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$msg   = isset( $_POST['mc_details'] ) ? wp_kses_post( wp_unslash( $_POST['mc_details'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === trim( wp_strip_all_tags( $msg ) ) ) {
			$log( 'empty message' );
			$required_redirect( 'empty_message' );
		}

		$name_for_title = $name ?: wp_get_current_user()->user_login;
		$peek           = wp_trim_words( wp_strip_all_tags( $msg ), 10, '…' );
		$title          = '[会員相談] ' . $name_for_title . ' — ' . ( $peek ?: '内容あり' );

		$post_id = wp_insert_post(
			[
				'post_type'    => 'consult',
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => $msg,
				'post_author'  => $uid,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$log( 'insert error', [ 'message' => $post_id->get_error_message() ] );
			$required_redirect( 'insert_error' );
		}

		if ( $name ) {
			update_post_meta( $post_id, 'applicant_name', $name );
		}
		if ( $email ) {
			update_post_meta( $post_id, 'applicant_email', $email );
		}
		if ( $tel ) {
			update_post_meta( $post_id, 'phone', $tel );
		}
		if ( $url ) {
			update_post_meta( $post_id, 'property_url', $url );
		}
		if ( $pref ) {
			update_post_meta( $post_id, 'contact_method', $pref );
		}
		if ( $time ) {
			update_post_meta( $post_id, 'preferred_time', $time );
		}

		update_post_meta( $post_id, 'privacy_agreed', $agree );

		$success_url = ovl_member_consult_redirect_url(
			'ok',
			[
				'post_id' => (string) intval( $post_id ),
			]
		);
		wp_safe_redirect( $success_url );
		$log( 'submission stored', [ 'post_id' => $post_id ] );
		exit;
	}
}

if ( ! function_exists( 'ovl_member_consult_require_login' ) ) {
	/**
	 * Redirects unauthenticated submissions back to login.
	 *
	 * @return void
	 */
	function ovl_member_consult_require_login(): void {
		$url = ovl_member_consult_redirect_url(
			'ng',
			[
				'consult_reason' => 'login_required',
			]
		);
		wp_safe_redirect( $url );
		exit;
	}
}

add_action( 'admin_post_member_consult', 'ovl_handle_member_consult_submission' );
add_action( 'admin_post_nopriv_member_consult', 'ovl_member_consult_require_login' );
