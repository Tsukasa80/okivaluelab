<?php
// OVL: Renderer for `[ovl_page slug="member-gate"]`.

return static function ( array $context = [] ): string {
	// OVL: Provide display-only message for member gate pages.
	$is_logged_in = ! empty( $context['is_user_logged_in'] );

	ob_start();
	?>
	<section class="ovl-page ovl-page--member-gate">
		<?php if ( $is_logged_in ) : ?>
			<p class="ovl-page__message">
				<?php esc_html_e( 'You are already signed in. Continue to the member dashboard from here.', 'ovl' ); ?>
			</p>
		<?php else : ?>
			<p class="ovl-page__message">
				<?php esc_html_e( 'Members, please sign in to access the protected content.', 'ovl' ); ?>
			</p>
			<div class="ovl-page__cta">
				<?php echo ovl_get_auth_link_markup(); // OVL: Reuse UI helper for login link. ?>
			</div>
		<?php endif; ?>
	</section>
	<?php

	return (string) ob_get_clean();
};
