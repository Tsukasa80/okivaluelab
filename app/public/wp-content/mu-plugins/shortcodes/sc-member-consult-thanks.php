<?php
// OVL: `[member_consult_thanks]` renders a confirmation view for consult submissions.

if ( ! function_exists( 'ovl_member_consult_thanks_url' ) ) {
	/**
	 * Resolves the thank-you page URL automatically from known slugs.
	 *
	 * @return string
	 */
	function ovl_member_consult_thanks_url(): string {
		$candidate_paths = [
			'member-consult/thank-you',
			'member-consult-thank-you',
			'member-consult/thanks',
			'member-consult-thanks',
			'thank-you',
		];

		$url = '';

		foreach ( $candidate_paths as $path ) {
			$page = get_page_by_path( $path );
			if ( $page instanceof WP_Post ) {
				$url = (string) get_permalink( $page );
				break;
			}
		}

		if ( '' === $url ) {
			$url = home_url( '/member-consult-thank-you/' );
		}

		return apply_filters( 'ovl/member_consult_thanks_url', $url, $candidate_paths );
	}
}

if ( ! function_exists( 'ovl_member_consult_thanks_alert' ) ) {
	/**
	 * Outputs a simple alert component.
	 *
	 * @param string $message Message HTML.
	 * @param string $type    Alert variation.
	 *
	 * @return string
	 */
	function ovl_member_consult_thanks_alert( string $message, string $type = 'info' ): string {
		return sprintf(
			'<div class="member-consult-alert member-consult-alert--%2$s">%1$s</div>',
			wp_kses(
				$message,
				[
					'a' => [
						'href'  => [],
						'class' => [],
						'rel'   => [],
					],
					'br' => [],
					'strong' => [],
				]
			),
			esc_attr( $type )
		);
	}
}

if ( ! function_exists( 'ovl_member_consult_format_field_value' ) ) {
	/**
	 * Normalizes field values for output.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return string
	 */
	function ovl_member_consult_format_field_value( $value ): string {
		if ( is_array( $value ) ) {
			$value = array_filter(
				array_map(
					static function ( $item ) {
						if ( is_scalar( $item ) || null === $item ) {
							return trim( (string) $item );
						}

						return wp_json_encode( $item, JSON_UNESCAPED_UNICODE );
					},
					$value
				)
			);

			if ( empty( $value ) ) {
				return '';
			}

			return implode( '<br>', array_map( 'esc_html', $value ) );
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		return nl2br( esc_html( $value ) );
	}
}

if ( ! function_exists( 'ovl_member_consult_guess_owner_id' ) ) {
	/**
	 * Attempts to infer ownership meta for consult entries.
	 *
	 * @param int $post_id Consult ID.
	 *
	 * @return int
	 */
	function ovl_member_consult_guess_owner_id( int $post_id ): int {
		$meta_keys = [
			'_ovl_consult_user_id',
			'ovl_consult_user_id',
			'consult_user_id',
			'member_id',
			'user_id',
		];

		foreach ( $meta_keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '' !== $value ) {
				return absint( $value );
			}
		}

		return 0;
	}
}

if ( ! function_exists( 'ovl_shortcode_member_consult_thanks' ) ) {
	/**
	 * Renders the consult summary table.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	function ovl_shortcode_member_consult_thanks( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'post_id'      => 0,
				'fallback_url' => home_url( '/member-consult/' ),
			],
			$atts,
			'member_consult_thanks'
		);

		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( get_permalink() ?: ovl_member_consult_thanks_url() );

			return ovl_member_consult_thanks_alert(
				sprintf(
					/* translators: %s: login URL */
					__( '入力内容を表示するには<a href="%s" rel="nofollow">ログイン</a>してください。', 'ovl' ),
					esc_url( $login_url )
				),
				'warning'
			);
		}

		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id && isset( $_GET['post_id'] ) ) {
			$post_id = absint( wp_unslash( $_GET['post_id'] ) );
		}

		if ( $post_id <= 0 ) {
			$message = __( '表示対象の相談IDが指定されていません。', 'ovl' );
			if ( $atts['fallback_url'] ) {
				$message .= sprintf(
					/* translators: %s: fallback URL */
					__( ' <a href="%s" rel="nofollow">フォームへ戻る</a>。', 'ovl' ),
					esc_url( $atts['fallback_url'] )
				);
			}

			return ovl_member_consult_thanks_alert( $message, 'error' );
		}

		$consult = get_post( $post_id );
		if ( ! $consult || 'consult' !== $consult->post_type ) {
			return ovl_member_consult_thanks_alert( __( '相談データが見つかりません。', 'ovl' ), 'error' );
		}

		if ( in_array( $consult->post_status, [ 'trash', 'auto-draft' ], true ) ) {
			return ovl_member_consult_thanks_alert( __( 'この相談は現在閲覧できません。', 'ovl' ), 'error' );
		}

		$current_user_id = get_current_user_id();
		$owns_entry      = (int) $consult->post_author === $current_user_id && $current_user_id > 0;

		$meta_owner_id = ovl_member_consult_guess_owner_id( $post_id );
		if ( $meta_owner_id > 0 ) {
			$owns_entry = $owns_entry || $meta_owner_id === $current_user_id;
		}

		$owns_entry = (bool) apply_filters( 'ovl/member_consult_can_view', $owns_entry, $consult, $current_user_id );

		if ( ! $owns_entry && ! current_user_can( 'read_post', $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return ovl_member_consult_thanks_alert( __( 'この入力内容を表示する権限がありません。', 'ovl' ), 'error' );
		}

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$submitted   = esc_html( get_date_from_gmt( $consult->post_date_gmt, $date_format ) );

		$field_map = [
			__( 'お名前', 'ovl' )           => 'applicant_name',
			__( 'メールアドレス', 'ovl' )   => 'applicant_email',
			__( '電話番号', 'ovl' )         => 'phone',
			__( 'ご希望の連絡方法', 'ovl' ) => 'contact_method',
			__( 'ご希望時間帯', 'ovl' )     => 'preferred_time',
			__( '物件URL', 'ovl' )          => 'property_url',
		];

		$field_map = apply_filters( 'ovl/member_consult_thanks_fields', $field_map, $consult );

		$rows = '';
		foreach ( $field_map as $label => $meta_key ) {
			if ( '' === $meta_key || null === $meta_key ) {
				continue;
			}

			$value = get_post_meta( $post_id, $meta_key, true );
			$value = ( '' === $value ) ? '' : ovl_member_consult_format_field_value( $value );

			if ( '' === $value ) {
				continue;
			}

			$rows .= sprintf(
				'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
				esc_html( $label ),
				$value // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		$content = $consult->post_content ? wpautop( wp_kses_post( $consult->post_content ) ) : '';

		ob_start();
		?>
		<div class="member-consult-thanks">
			<div class="member-consult-thanks__intro">
				<p><?php echo esc_html__( '以下の内容でご相談を受け付けました。', 'ovl' ); ?></p>
				<p class="member-consult-thanks__submitted">
					<?php
					printf(
						/* translators: %s submitted datetime */
						esc_html__( '受付日時: %s', 'ovl' ),
						$submitted
					);
					?>
				</p>
			</div>
			<?php if ( $rows ) : ?>
				<div class="member-consult-thanks__summary">
					<table class="member-consult-thanks__table">
						<tbody>
						<?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
			<?php if ( $content ) : ?>
				<div class="member-consult-thanks__content">
					<h3><?php echo esc_html__( 'ご相談内容', 'ovl' ); ?></h3>
					<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
			<?php if ( $atts['fallback_url'] ) : ?>
				<div class="member-consult-thanks__actions">
					<a class="member-consult-thanks__button" href="<?php echo esc_url( $atts['fallback_url'] ); ?>">
						<?php echo esc_html__( '相談フォームへ戻る', 'ovl' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php

		return trim( (string) ob_get_clean() );
	}
}

add_shortcode( 'member_consult_thanks', 'ovl_shortcode_member_consult_thanks' );

if ( ! function_exists( 'ovl_member_consult_thanks_redirect' ) ) {
	/**
	 * Redirects successful submissions to the confirmation page.
	 *
	 * @param string $redirect Current redirect URL.
	 * @param string $status   Submission status.
	 * @param array  $extra    Extra query vars from the handler.
	 *
	 * @return string
	 */
	function ovl_member_consult_thanks_redirect( string $redirect, string $status, array $extra ): string {
		if ( 'ok' !== strtolower( $status ) ) {
			return $redirect;
		}

		$post_id = 0;
		if ( isset( $extra['post_id'] ) ) {
			$post_id = absint( $extra['post_id'] );
		}

		if ( $post_id <= 0 ) {
			return $redirect;
		}

		return add_query_arg( 'post_id', $post_id, ovl_member_consult_thanks_url() );
	}
}

add_filter( 'ovl/member_consult_redirect', 'ovl_member_consult_thanks_redirect', 20, 3 );
