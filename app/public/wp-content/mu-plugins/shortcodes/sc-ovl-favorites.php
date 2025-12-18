<?php
// OVL: Favorite properties list shortcode.

add_shortcode(
	'ovl_favorites',
	static function (): string {
		$current_url  = function_exists( 'ovl_get_current_url' ) ? ovl_get_current_url() : home_url( add_query_arg( [], $_SERVER['REQUEST_URI'] ?? '' ) );
		$login_url    = add_query_arg( 'redirect_to', rawurlencode( $current_url ), home_url( '/members/' ) );
		$register_url = home_url( '/member-register/' );

		if ( ! is_user_logged_in() ) {
			ob_start();
			?>
			<div class="ovl-favorites ovl-favorites--guest">
				<p>お気に入りはログイン後にご利用いただけます。</p>
				<p style="display:flex;gap:.5rem;flex-wrap:wrap;">
					<a class="button" href="<?php echo esc_url( $login_url ); ?>">ログインページへ</a>
					<a class="button button-outline" href="<?php echo esc_url( $register_url ); ?>">新規会員登録</a>
				</p>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		wp_enqueue_script( 'ovl-favorites' );

		$user_id      = get_current_user_id();
		$favorite_ids = ovl_favorites_get_ids( $user_id );
		$paged        = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;
		if ( $paged < 1 && get_query_var( 'page' ) ) {
			$paged = (int) get_query_var( 'page' );
		}
		if ( $paged < 1 && isset( $_GET['paged'] ) ) {
			$paged = max( 1, (int) $_GET['paged'] );
		}

		wp_enqueue_script( 'ovl-favorites' );

		if ( empty( $favorite_ids ) ) {
			ob_start();
			?>
			<div class="ovl-favorites ovl-favorites--empty">
				<p>お気に入りに登録された物件はまだありません。</p>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		$query = new WP_Query(
			[
				'post_type'      => 'property',
				'post_status'    => 'publish',
				'post__in'       => $favorite_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => 12,
				'paged'          => max( 1, $paged ),
			]
		);

		$favorited_set = array_flip( $favorite_ids );
		$format_number = static function ( $value, int $decimals = 0, string $suffix = '' ) {
			if ( '' === $value || null === $value ) {
				return '';
			}
			$value = is_numeric( $value ) ? (float) $value : (float) preg_replace( '/[^\d.]/', '', (string) $value );
			if ( ! is_finite( $value ) ) {
				return '';
			}
			return number_format_i18n( $value, $decimals ) . $suffix;
		};
		$get_field = static function ( $key, int $post_id ) {
			if ( function_exists( 'get_field' ) ) {
				return get_field( $key, $post_id );
			}
			return get_post_meta( $post_id, $key, true );
		};

		ob_start();
		?>
		<div class="ovl-favorites property-archive">
			<?php if ( $query->have_posts() ) : ?>
				<div class="property-archive__grid">
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						$post_id      = get_the_ID();
						$permalink    = get_permalink( $post_id );
						$link         = add_query_arg( 'redirect_to', rawurlencode( $permalink ), home_url( '/members/' ) );
						$is_new       = ( time() - get_post_time( 'U', true ) ) < 14 * DAY_IN_SECONDS;
						$price        = $format_number( $get_field( 'price', $post_id ) );
						$yield_raw    = $get_field( 'yield_gross', $post_id );
						if ( '' === $yield_raw || null === $yield_raw ) {
							$yield_raw = $get_field( 'yield_surface', $post_id );
						}
						$yield_s      = $format_number( $yield_raw, 1, '%' );
						$building_age = $get_field( 'building_age', $post_id );
						$age_label    = $building_age === '' || $building_age === null ? '—' : ( is_numeric( $building_age ) ? '築' . number_format_i18n( (float) $building_age, 0 ) . '年' : wp_strip_all_tags( (string) $building_age ) );
						$floor_area   = $format_number( $get_field( 'floor_area', $post_id ), 1, '㎡' );
						$land_area    = $format_number( $get_field( 'land_area', $post_id ), 1, '㎡' );
						$structure    = $get_field( 'structure', $post_id ) ?: '構造未登録';
						$address      = $get_field( 'address_full', $post_id ) ?: $get_field( 'address_city', $post_id );
						$address_txt  = $address ? wp_strip_all_tags( $address ) : '所在地非公開';
						$city_value   = $get_field( 'address_city', $post_id );
						$city_label   = $city_value ? wp_strip_all_tags( $city_value ) : '地域非公開';
						$station      = $get_field( 'station', $post_id );
						$access       = $get_field( 'access', $post_id );
						$transport    = '';
						if ( $station ) {
							$transport = wp_strip_all_tags( (string) $station );
						}
						if ( $access ) {
							$transport .= $transport ? ' ' : '';
							$transport .= wp_strip_all_tags( (string) $access );
						}
						$terms              = get_the_terms( $post_id, 'property_cat' );
						$status_label       = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0]->name : '公開中';
						$structure_age_parts = [];
						if ( $structure && '構造未登録' !== $structure ) {
							$structure_age_parts[] = wp_strip_all_tags( (string) $structure );
						}
						if ( $age_label && '—' !== $age_label ) {
							$structure_age_parts[] = wp_strip_all_tags( (string) $age_label );
						}
						$structure_age = $structure_age_parts ? implode( ' / ', $structure_age_parts ) : '—';
						$price_value   = $price ? esc_html( $price ) . '万円' : '—';
						$is_fav        = isset( $favorited_set[ $post_id ] );
						$button_classes = 'ovl-favorite-button';
						$button_label   = 'お気に入りに追加';
						if ( $is_fav ) {
							$button_classes .= ' is-active';
							$button_label    = 'お気に入り済み';
						}
						$specs = [
							[
								'label'    => '価格',
								'value'    => $price_value,
								'modifier' => 'price',
							],
							[
								'label'    => '表面利回り',
								'value'    => esc_html( $yield_s ),
								'modifier' => 'yield',
							],
							[
								'label'    => '市町村',
								'value'    => esc_html( $city_label ),
								'modifier' => 'city',
							],
							[
								'label'    => '構造 / 築年月',
								'value'    => esc_html( $structure_age ),
								'modifier' => 'structure',
							],
							[
								'label'    => '延床面積',
								'value'    => esc_html( $floor_area ?: '—' ),
								'modifier' => 'floor',
							],
							[
								'label'    => '土地面積',
								'value'    => esc_html( $land_area ?: '—' ),
								'modifier' => 'land',
							],
						];
						?>
						<article <?php post_class( 'ovl-property-card un-card__item top_page_property_ad_top slick-slide' ); ?>>
							<div class="ovl-property-card__media">
								<a class="ovl-property-card__thumb" href="<?php echo esc_url( $permalink ); ?>">
									<?php if ( has_post_thumbnail( $post_id ) ) : ?>
										<?php echo get_the_post_thumbnail( $post_id, 'property_card', [ 'class' => 'ovl-property-card__image', 'loading' => 'lazy' ] ); ?>
									<?php else : ?>
										<span class="ovl-property-card__noimage">No Image</span>
									<?php endif; ?>

									<?php if ( $is_new ) : ?>
										<span class="ovl-property-card__badge is-new">NEW</span>
									<?php endif; ?>
								</a>
								<div class="ovl-property-card__footer ovl-property-card__footer--media">
									<a class="ovl-property-card__cta" href="<?php echo esc_url( $permalink ); ?>">
										物件詳細
									</a>
								</div>
							</div>

							<div class="ovl-property-card__body">
								<div class="ovl-property-card__head">
									<div class="ovl-property-card__labels">
										<span class="ovl-property-card__badge-pill"><?php echo esc_html( $status_label ); ?></span>
										<?php if ( $is_new ) : ?>
											<span class="ovl-property-card__badge-pill is-accent">新着</span>
										<?php endif; ?>
									</div>
									<h3 class="ovl-property-card__title">
										<a href="<?php echo esc_url( $permalink ); ?>">
											<?php the_title(); ?>
										</a>
									</h3>
									<p class="ovl-property-card__station">
										<?php echo $transport ? esc_html( $transport ) : esc_html( $city_label ); ?>
									</p>
								</div>

								<div class="ovl-property-card__specs">
									<?php foreach ( array_chunk( $specs, 3 ) as $spec_row ) : ?>
										<div class="ovl-property-card__spec-row">
											<?php foreach ( $spec_row as $spec ) : ?>
												<div class="ovl-property-card__spec <?php echo 'price' === $spec['modifier'] ? 'ovl-property-card__spec--price' : ''; ?>">
													<span class="ovl-property-card__spec-label"><?php echo esc_html( $spec['label'] ); ?></span>
													<strong class="ovl-property-card__spec-value"><?php echo esc_html( $spec['value'] ); ?></strong>
												</div>
											<?php endforeach; ?>
										</div>
									<?php endforeach; ?>
								</div>

								<div class="ovl-favorite-control">
									<button
										type="button"
										class="<?php echo esc_attr( $button_classes ); ?>"
										data-ovl-fav="1"
										data-property-id="<?php echo esc_attr( $post_id ); ?>"
										aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>"
									>
										<span class="ovl-favorite-button__icon" aria-hidden="true">♡</span>
										<span class="ovl-favorite-button__label"><?php echo esc_html( $button_label ); ?></span>
									</button>
								</div>
							</div>
						</article>
					<?php endwhile; ?>
				</div>
			<?php else : ?>
				<p>お気に入りに登録された物件は見つかりませんでした。</p>
			<?php endif; ?>

			<?php
			$big        = 999999999;
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

		$html = (string) ob_get_clean();
		// 空の段落（wpautopやブロック由来）を除去して余白を抑える。
		$html = preg_replace( '#<p[^>]*>(?:\s|&nbsp;|<br\s*/?>)*</p>#i', '', $html );
		// wpautop などが誤挿入しにくいよう、タグ間の改行/空白を削除する。
		$html = preg_replace( '/>\s+</', '><', $html );

		return $html;
	}
);
