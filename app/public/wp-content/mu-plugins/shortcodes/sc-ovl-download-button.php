<?php
// OVL: `[ovl_download_button]` renders the secured document download link.

require_once __DIR__ . '/../25-ovl-docs.php'; // OVL: Ensure helper functions exist.

if ( ! function_exists( 'ovl_shortcode_download_button' ) ) {
	/**
	 * Outputs a download link (or login prompt) for the current property.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	function ovl_shortcode_download_button( array $atts = [] ): string {
		// OVL: Match legacy defaults while supporting overrides.
		$atts = shortcode_atts(
			[
				'post_id' => 0,
				'label'   => '資料をダウンロード',
			],
			$atts,
			'ovl_download_button'
		);

		$post_id = $atts['post_id'] ? absint( $atts['post_id'] ) : get_the_ID();

		if ( ! $post_id ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( get_permalink( $post_id ) );

			return sprintf(
				'<a class="ovl-download-login" rel="nofollow noopener" href="%1$s">%2$s</a>',
				esc_url( $login_url ),
				esc_html__( '会員登録すると資料DL可', 'ovl' )
			);
		}

		$download_url = ovl_get_download_url( $post_id );
		if ( '' === $download_url ) {
			return '';
		}

		$basename = ovl_get_doc_basename( $post_id );

		return sprintf(
			'<a class="ovl-download-button" rel="nofollow noopener" download="%3$s" href="%1$s">%2$s</a>',
			esc_url( $download_url ),
			esc_html( $atts['label'] ),
			esc_attr( $basename ?: 'document.pdf' )
		);
	}
}

add_shortcode( 'ovl_download_button', 'ovl_shortcode_download_button' ); // OVL: Make shortcode available to pages/templates.
