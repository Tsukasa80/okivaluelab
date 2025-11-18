<?php
// OVL: `[ovl_page slug="..."]` dispatches to per-page renderers stored in ovl-pages/.

if ( ! function_exists( 'ovl_shortcode_page_dispatch' ) ) {
	/**
	 * Loads a page renderer from ovl-pages and returns its HTML.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Enclosed content (unused).
	 * @param string $tag     Shortcode tag.
	 *
	 * @return string
	 */
	function ovl_shortcode_page_dispatch( array $atts = [], string $content = '', string $tag = '' ): string {
		// OVL: Keep page rendering isolated per file for maintainability.
		$atts = shortcode_atts(
			[
				'slug' => '',
			],
			$atts,
			$tag
		);

		$slug = sanitize_title( $atts['slug'] );

		if ( ! $slug ) {
			return '';
		}

		$pages_dir = realpath( __DIR__ . '/../ovl-pages' );
		if ( ! $pages_dir || ! is_dir( $pages_dir ) ) {
			return '';
		}

		$page_file = $pages_dir . '/page-' . $slug . '.php';

		if ( ! file_exists( $page_file ) ) {
			return '';
		}

		$renderer = include $page_file;

		if ( is_callable( $renderer ) ) {
			$context = ovl_get_template_context(
				[
					'slug' => $slug,
				]
			);

			$html = call_user_func( $renderer, $context );

			return is_string( $html ) ? $html : '';
		}

		return '';
	}
}

add_shortcode( 'ovl_page', 'ovl_shortcode_page_dispatch' ); // OVL: Register dispatcher for per-page renders.
