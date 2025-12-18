<?php
// OVL: Renderer for `[ovl_page slug="property-list"]` that outputs the property archive grid.

return static function ( array $context = [] ): string {
	// OVL: Price filters pulled from query vars to mirror the legacy shortcode behavior.
	$min_price = isset( $_GET['min_price'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_GET['min_price'] ) ) : '';
	$max_price = isset( $_GET['max_price'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_GET['max_price'] ) ) : '';

	// OVL: Determine the current page while being compatible with block themes.
	$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;
	if ( $paged < 1 && get_query_var( 'page' ) ) {
		$paged = (int) get_query_var( 'page' );
	}
	if ( $paged < 1 && isset( $_GET['paged'] ) ) {
		$paged = max( 1, (int) $_GET['paged'] );
	}

	$meta_query = [ 'relation' => 'AND' ];

	if ( '' !== $min_price ) {
		$meta_query[] = [
			'key'     => 'price',
			'value'   => (int) $min_price,
			'type'    => 'NUMERIC',
			'compare' => '>=',
		];
	}

	if ( '' !== $max_price ) {
		$meta_query[] = [
			'key'     => 'price',
			'value'   => (int) $max_price,
			'type'    => 'NUMERIC',
			'compare' => '<=',
		];
	}

	if ( 1 === count( $meta_query ) ) {
		$meta_query = []; // OVL: No filters were applied.
	}

	$query = new WP_Query(
		[
			'post_type'      => 'property',
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => $meta_query,
		]
	);

	// OVL: Wrapper to avoid fatal errors if ACF is disabled.
	$acf_get = static function ( $field, $post_id = null ) {
		if ( function_exists( 'get_field' ) ) {
			return get_field( $field, $post_id );
		}

		return get_post_meta( $post_id ?: get_the_ID(), $field, true );
	};

	$is_member = isset( $context['is_user_logged_in'] ) ? (bool) $context['is_user_logged_in'] : is_user_logged_in();
	$favorites = $is_member && function_exists( 'ovl_favorites_get_ids' ) ? ovl_favorites_get_ids() : [];

	wp_enqueue_script( 'ovl-favorites' );

	ob_start();
	?>
	<div class="ovl-property-list">
		<form method="get" action="<?php echo esc_url( get_permalink() ); ?>" class="ovl-property-filter" style="margin:1rem 0;display:grid;gap:.5rem;grid-template-columns:repeat(6,minmax(0,1fr));align-items:end;">
			<div style="grid-column:span 2;">
				<label for="ovl-min-price" style="display:block;font-size:.9rem;"><?php esc_html_e( 'Minimum price (JPY)', 'ovl' ); ?></label>
				<input type="number" inputmode="numeric" min="0" step="1" name="min_price" id="ovl-min-price" value="<?php echo esc_attr( $min_price ); ?>" style="width:100%;padding:.5rem;">
			</div>
			<div style="grid-column:span 2;">
				<label for="ovl-max-price" style="display:block;font-size:.9rem;"><?php esc_html_e( 'Maximum price (JPY)', 'ovl' ); ?></label>
				<input type="number" inputmode="numeric" min="0" step="1" name="max_price" id="ovl-max-price" value="<?php echo esc_attr( $max_price ); ?>" style="width:100%;padding:.5rem;">
			</div>
			<?php
			foreach ( $_GET as $key => $value ) {
				if ( in_array( $key, [ 'min_price', 'max_price', 'paged' ], true ) ) {
					continue;
				}
				if ( is_array( $value ) ) {
					continue;
				}
				printf(
					'<input type="hidden" name="%1$s" value="%2$s">',
					esc_attr( $key ),
					esc_attr( $value )
				);
			}
			?>
			<div style="grid-column:span 2;display:flex;gap:.5rem;">
				<button type="submit" class="button" style="padding:.6rem 1rem;"><?php esc_html_e( 'Filter', 'ovl' ); ?></button>
				<a class="button" style="padding:.6rem 1rem;text-decoration:none;" href="<?php echo esc_url( remove_query_arg( [ 'min_price', 'max_price', 'paged' ] ) ); ?>">
					<?php esc_html_e( 'Reset', 'ovl' ); ?>
				</a>
			</div>
		</form>

		<?php if ( $query->have_posts() ) : ?>
			<div class="ovl-grid" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$post_id     = get_the_ID();
					$title       = get_the_title();
					$detail_url  = get_permalink( $post_id );
					$price       = $acf_get( 'price', $post_id );
					$address     = $acf_get( 'address', $post_id );
					$card_class  = $is_member ? 'ovl-card' : 'ovl-card is-guest';
					$is_favorite = $is_member && in_array( $post_id, $favorites, true );
					$login_url = add_query_arg(
						'redirect_to',
						rawurlencode( $detail_url ),
						home_url( '/members/' )
					);
					$register_url = add_query_arg(
						'redirect_to',
						rawurlencode( $detail_url ),
						home_url( '/member-register/' )
					);

					$link = $is_member ? $detail_url : $login_url;
					?>
					<article <?php post_class( $card_class ); ?> style="border:1px solid #ddd;border-radius:12px;overflow:hidden;position:relative;">
						<a href="<?php echo esc_url( $link ); ?>" class="ovl-card__thumb" <?php echo $is_member ? '' : 'aria-label="Login required" rel="nofollow"'; ?> style="display:block;aspect-ratio:16/9;background:#f4f4f4;overflow:hidden;">
							<?php
							if ( has_post_thumbnail( $post_id ) ) {
								echo get_the_post_thumbnail(
									$post_id,
									'ovl_card',
									[
										'class'   => 'ovl-card-thumb',
										'loading' => 'lazy',
										'style'   => 'width:100%;height:100%;object-fit:cover;display:block;aspect-ratio:16/9;',
									]
								);
							} else {
								echo '<div style="width:100%;aspect-ratio:16/9;display:grid;place-items:center;background:#eee;color:#666;">' . esc_html__( 'NO IMAGE', 'ovl' ) . '</div>';
							}
							?>
							<?php if ( ! $is_member ) : ?>
								<span class="ovl-badge" style="position:absolute;top:.5rem;left:.5rem;background:#000;color:#fff;font-size:.8rem;padding:.25rem .5rem;border-radius:.5rem;opacity:.8;">
									<?php esc_html_e( 'Members only', 'ovl' ); ?>
								</span>
							<?php endif; ?>
						</a>
						<div style="padding:.8rem 1rem 1rem;">
							<h3 style="margin:.2rem 0 .5rem;font-size:1.05rem;line-height:1.4;">
								<a href="<?php echo esc_url( $link ); ?>" style="text-decoration:none;"><?php echo esc_html( $title ); ?></a>
							</h3>
							<?php if ( $is_member ) : ?>
								<p style="margin:.25rem 0;font-weight:600;">
									<?php
									$price_num = is_numeric( $price ) ? (float) $price : (float) preg_replace( '/[^\d.]/', '', (string) $price );
									printf( /* translators: %s is price in JPY */
										esc_html__( 'Price: %s JPY', 'ovl' ),
										$price !== '' ? esc_html( number_format( $price_num ) ) : '—'
									);
									?>
								</p>
								<p style="margin:.25rem 0;color:#555;"><?php esc_html_e( 'Address:', 'ovl' ); ?> <?php echo $address ? esc_html( $address ) : '—'; ?></p>
							<?php else : ?>
								<p style="margin:.25rem 0;font-weight:600;"><?php esc_html_e( 'Price: (members only)', 'ovl' ); ?></p>
								<p style="margin:.25rem 0;color:#555;"><?php esc_html_e( 'Address: (members only)', 'ovl' ); ?></p>
								<p style="margin:.5rem 0 0;font-size:.9rem;">
									<?php esc_html_e( 'Please log in to view full details.', 'ovl' ); ?>
									<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in', 'ovl' ); ?></a>
									<?php if ( $register_url ) : ?>
										/ <a href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Register', 'ovl' ); ?></a>
									<?php endif; ?>
								</p>
							<?php endif; ?>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'No properties matched your criteria.', 'ovl' ); ?></p>
		<?php endif; ?>

		<?php
		$big = 999999999;
		$pagination = paginate_links(
			[
				'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
				'format'    => '?paged=%#%',
				'current'   => max( 1, $paged ),
				'total'     => $query->max_num_pages,
				'type'      => 'list',
				'prev_text' => __( '« Prev', 'ovl' ),
				'next_text' => __( 'Next »', 'ovl' ),
			]
		);

		if ( $pagination ) {
			echo '<nav class="ovl-pagination">' . wp_kses_post( $pagination ) . '</nav>';
		}
		?>
	</div>
	<?php
	wp_reset_postdata();

	return (string) ob_get_clean();
};
