<?php
/**
 * Property specific shortcodes.
 *
 * @package okivaluelab-child
 */

if ( ! function_exists( 'ovl_render_property_cards_shortcode' ) ) {
	/**
	 * Renders Rakumachi inspired property cards.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	function ovl_render_property_cards_shortcode( array $atts = [] ): string {
		wp_enqueue_script( 'ovl-favorites' );

		$atts = shortcode_atts(
			[
				'per_page' => 12,
				'columns'  => 3,
			],
			$atts,
			'ovl_property_cards'
		);

		$min_price   = isset( $_GET['min_price'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_GET['min_price'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$max_price   = isset( $_GET['max_price'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_GET['max_price'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$city_filter = isset( $_GET['city'] ) ? sanitize_text_field( wp_unslash( $_GET['city'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$normalize_decimal = static function ( $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return '';
			}

			$value = preg_replace( '/[^0-9.,]/', '', $value );
			if ( '' === $value ) {
				return '';
			}

			$value = str_replace( ',', '.', $value );

			return is_numeric( $value ) ? $value : '';
		};

		$normalize_meta_decimal = static function ( $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return '';
			}

			$value = str_replace( [ ',', '，' ], '.', $value );
			$value = str_replace( [ '%', '％' ], '', $value );
			$value = preg_replace( '/[^0-9.\-]/', '', $value );

			return is_numeric( $value ) ? $value : '';
		};

		$min_yield  = isset( $_GET['min_yield'] ) ? $normalize_decimal( wp_unslash( $_GET['min_yield'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$max_yield  = isset( $_GET['max_yield'] ) ? $normalize_decimal( wp_unslash( $_GET['max_yield'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$yield_keys = [ 'yield_gross', 'yield_surface', 'yield_real', 'yield_actual' ];

		$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;
		if ( $paged < 1 && get_query_var( 'page' ) ) {
			$paged = (int) get_query_var( 'page' );
		}
		if ( $paged < 1 && isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$paged = max( 1, (int) $_GET['paged'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$meta_query = [ 'relation' => 'AND' ];
		$post_scope = null;
		$intersection = static function ( ?array $current, array $next ) {
			if ( null === $current ) {
				return $next;
			}
			if ( empty( $current ) || empty( $next ) ) {
				return [];
			}

			return array_values( array_intersect( $current, $next ) );
		};

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

		global $wpdb;

		if ( $city_filter ) {
			$city_ids = $wpdb->get_col(
				$wpdb->prepare(
					"
					SELECT pm.post_id
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = %s
					  AND pm.meta_value = %s
					  AND p.post_type = %s
					  AND p.post_status = %s
					",
					'address_city',
					$city_filter,
					'property',
					'publish'
				)
			);

			$city_ids  = array_map( 'intval', array_filter( (array) $city_ids ) );
			$post_scope = $intersection( $post_scope, $city_ids ?: [ 0 ] );
		}

		if ( '' !== $min_yield || '' !== $max_yield ) {
			if ( is_array( $post_scope ) && 1 === count( $post_scope ) && in_array( 0, $post_scope, true ) ) {
				$yield_ids = [];
			} else {
				$yield_params = $yield_keys;
				$yield_sql    = "
					SELECT pm.post_id, pm.meta_value
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key IN (" . implode( ',', array_fill( 0, count( $yield_keys ), '%s' ) ) . ")
					  AND p.post_type = %s
					  AND p.post_status = %s
					  AND pm.meta_value <> ''
				";
				$yield_params[] = 'property';
				$yield_params[] = 'publish';

				if ( is_array( $post_scope ) && ! empty( $post_scope ) && ! in_array( 0, $post_scope, true ) ) {
					$yield_sql   .= ' AND pm.post_id IN (' . implode( ',', array_fill( 0, count( $post_scope ), '%d' ) ) . ')';
					$yield_params = array_merge( $yield_params, $post_scope );
				}

				$yield_rows = $wpdb->get_results( $wpdb->prepare( $yield_sql, $yield_params ), ARRAY_A );
				$yield_ids  = [];

				foreach ( (array) $yield_rows as $row ) {
					$normalized = $normalize_meta_decimal( $row['meta_value'] ?? '' );
					if ( '' === $normalized ) {
						continue;
					}

					$value = (float) $normalized;
					if ( '' !== $min_yield && $value < (float) $min_yield ) {
						continue;
					}

					if ( '' !== $max_yield && $value > (float) $max_yield ) {
						continue;
					}

					$yield_ids[] = (int) $row['post_id'];
				}

				$yield_ids = array_values( array_unique( $yield_ids ) );
			}

			$yield_ids  = array_values( array_unique( $yield_ids ) );
			$post_scope = $intersection( $post_scope, $yield_ids ?: [ 0 ] );
		}

		if ( 1 === count( $meta_query ) ) {
			$meta_query = [];
		}

		$city_choices = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT DISTINCT pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				  AND p.post_type = %s
				  AND p.post_status = %s
				  AND pm.meta_value <> ''
				ORDER BY pm.meta_value ASC
				",
				'address_city',
				'property',
				'publish'
			)
		);
		if ( ! is_array( $city_choices ) ) {
			$city_choices = [];
		}

		$query = new WP_Query(
			array_filter(
				[
					'post_type'      => 'property',
					'post_status'    => 'publish',
					'posts_per_page' => max( 1, (int) $atts['per_page'] ),
					'paged'          => $paged,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'meta_query'     => $meta_query,
					'post__in'       => is_array( $post_scope ) ? ( $post_scope ?: [ 0 ] ) : null,
				]
			)
		);

		$get_field = static function ( string $key, int $post_id ) {
			if ( function_exists( 'get_field' ) ) {
				return get_field( $key, $post_id );
			}

			return get_post_meta( $post_id, $key, true );
		};

		$format_number = static function ( $value, int $decimals = 0, string $suffix = '' ) {
			if ( $value === '' || $value === null ) {
				return '—';
			}

			if ( is_numeric( $value ) ) {
				return number_format_i18n( (float) $value, $decimals ) . $suffix;
			}

			return wp_strip_all_tags( (string) $value );
		};

		$is_member = is_user_logged_in();

		ob_start();
		?>
		<div class="property-archive" style="--ovl-property-columns: <?php echo esc_attr( max( 1, (int) $atts['columns'] ) ); ?>;">
			<form method="get" class="property-archive__filter">
				<label for="property-min-price">
					<span>最小価格（万円）</span>
					<input type="number" id="property-min-price" name="min_price" inputmode="numeric" min="0" step="1" value="<?php echo esc_attr( $min_price ); ?>">
				</label>
					<label for="property-max-price">
						<span>最大価格（万円）</span>
						<input type="number" id="property-max-price" name="max_price" inputmode="numeric" min="0" step="1" value="<?php echo esc_attr( $max_price ); ?>">
					</label>
					<label for="property-min-yield">
						<span>最小利回り（%）</span>
						<input type="number" id="property-min-yield" name="min_yield" inputmode="decimal" min="0" step="0.1" value="<?php echo esc_attr( $min_yield ); ?>">
					</label>
					<label for="property-max-yield">
						<span>最大利回り（%）</span>
						<input type="number" id="property-max-yield" name="max_yield" inputmode="decimal" min="0" step="0.1" value="<?php echo esc_attr( $max_yield ); ?>">
					</label>
					<label for="property-city">
						<span>市町村</span>
						<select id="property-city" name="city">
							<option value="">指定なし</option>
							<?php foreach ( $city_choices as $city_option ) : ?>
								<?php
								$city_option = wp_strip_all_tags( (string) $city_option );
								if ( '' === $city_option ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( $city_option ); ?>" <?php selected( $city_filter, $city_option ); ?>><?php echo esc_html( $city_option ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<div class="property-archive__filter-actions">
						<button type="submit" class="button">絞り込み</button>
						<a class="button button-reset" href="<?php echo esc_url( remove_query_arg( [ 'min_price', 'max_price', 'city', 'min_yield', 'max_yield', 'paged' ] ) ); ?>">条件クリア</a>
					</div>
				</form>
			<?php if ( $query->have_posts() ) : ?>
				<div class="property-archive__grid">
					<?php
					$favorites = is_user_logged_in() && function_exists( 'ovl_favorites_get_ids' ) ? ovl_favorites_get_ids() : [];
					$fav_set   = array_flip( $favorites );
					while ( $query->have_posts() ) :
						$query->the_post();
						$post_id      = get_the_ID();
						$permalink    = get_permalink( $post_id );
						$link         = $is_member ? $permalink : add_query_arg( 'redirect_to', rawurlencode( $permalink ), home_url( '/members/' ) );
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
						$is_favorite   = $is_member && isset( $fav_set[ $post_id ] );
						$button_classes = 'ovl-favorite-button';
						$button_label   = 'お気に入りに追加';
						$button_attrs   = '';
						if ( $is_favorite ) {
							$button_classes .= ' is-active';
							$button_label    = 'お気に入り済み';
						}
						if ( ! $is_member ) {
							$button_classes .= ' is-disabled';
							$button_label    = 'ログイン後にお気に入り追加';
							$button_attrs    = 'disabled aria-disabled="true"';
						}
						$register_url = home_url( '/member-register/' );

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
								'value'    => $is_member ? esc_html( $structure_age ) : '—',
								'modifier' => 'structure',
							],
							[
								'label'    => '延床面積',
								'value'    => $is_member ? esc_html( $floor_area ) : '—',
								'modifier' => 'floor',
							],
							[
								'label'    => '土地面積',
								'value'    => $is_member ? esc_html( $land_area ) : '—',
								'modifier' => 'land',
							],
						];
						?>
						<article <?php post_class( 'ovl-property-card un-card__item top_page_property_ad_top slick-slide' ); ?>>
							<div class="ovl-property-card__media">
								<a class="ovl-property-card__thumb" href="<?php echo esc_url( $link ); ?>" <?php echo $is_member ? '' : 'rel="nofollow"'; ?>>
									<?php if ( has_post_thumbnail( $post_id ) ) : ?>
										<?php echo get_the_post_thumbnail( $post_id, 'property_card', [ 'class' => 'ovl-property-card__image', 'loading' => 'lazy' ] ); ?>
									<?php else : ?>
										<span class="ovl-property-card__noimage">No Image</span>
									<?php endif; ?>

									<?php if ( $is_new ) : ?>
										<span class="ovl-property-card__badge is-new">NEW</span>
									<?php endif; ?>
								<?php if ( ! $is_member ) : ?>
									<span class="ovl-property-card__badge is-locked">会員限定</span>
								<?php endif; ?>
									</a>
									<?php if ( ! $is_member ) : ?>
										<div class="ovl-property-card__auth-links">
											<a href="<?php echo esc_url( $link ); ?>" rel="nofollow">ログイン</a>
											<span class="ovl-property-card__auth-sep" aria-hidden="true">｜</span>
											<a href="<?php echo esc_url( $register_url ); ?>" rel="nofollow">新規会員登録</a>
										</div>
									<?php endif; ?>
								</div>

							<div class="ovl-property-card__body">
								<div class="ovl-property-card__head">
									<div class="ovl-property-card__labels">
											<?php if ( '公開中' !== $status_label ) : ?>
												<span class="ovl-property-card__badge-pill"><?php echo esc_html( $status_label ); ?></span>
											<?php endif; ?>
											<?php if ( $is_new ) : ?>
												<span class="ovl-property-card__badge-pill is-accent">新着</span>
											<?php endif; ?>
										</div>
									<h3 class="ovl-property-card__title">
										<a href="<?php echo esc_url( $link ); ?>" <?php echo $is_member ? '' : 'rel="nofollow"'; ?>>
											<?php the_title(); ?>
										</a>
									</h3>
									<p class="ovl-property-card__station">
										<?php
										if ( $is_member ) {
											echo $transport ? esc_html( $transport ) : esc_html( $city_label );
										} else {
											echo '住所情報は会員限定';
										}
										?>
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

								<?php $compact_favorite_label = $is_favorite ? 'お気に入り済み' : 'お気に入り'; ?>
								<div class="ovl-property-card__actions">
									<a class="ovl-property-card__cta" href="<?php echo esc_url( $link ); ?>" <?php echo $is_member ? '' : 'rel="nofollow"'; ?>><?php echo $is_member ? '物件詳細' : 'ログインして詳細'; ?></a>
									<button
										type="button"
										class="<?php echo esc_attr( $button_classes ); ?>"
										data-ovl-fav="1"
										data-property-id="<?php echo esc_attr( $post_id ); ?>"
										aria-pressed="<?php echo $is_favorite ? 'true' : 'false'; ?>"
										aria-label="<?php echo esc_attr( $button_label ); ?>"
										<?php echo $button_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									>
										<span class="ovl-favorite-button__icon" aria-hidden="true">♡</span>
										<span class="ovl-favorite-button__label"><?php echo esc_html( $compact_favorite_label ); ?></span>
									</button>
								</div>

							</div>
						</article>
						<?php
					endwhile;
					?>
				</div>
				<?php
				$add_args = [];
				foreach ( $_GET as $key => $value ) {
					if ( 'paged' === $key || is_array( $value ) ) {
						continue;
					}

					$add_args[ $key ] = wp_strip_all_tags( (string) $value );
				}

				$pagination = paginate_links(
					[
						'base'      => str_replace( 999999, '%#%', esc_url( get_pagenum_link( 999999 ) ) ),
						'format'    => get_option( 'permalink_structure' ) ? 'page/%#%/' : '?paged=%#%',
						'current'   => max( 1, $paged ),
						'total'     => max( 1, (int) $query->max_num_pages ),
						'type'      => 'list',
						'prev_text' => '«',
						'next_text' => '»',
						'add_args'  => $add_args,
					]
				);

				if ( $pagination ) :
					?>
					<nav class="property-archive__pagination"><?php echo wp_kses_post( $pagination ); ?></nav>
				<?php endif; ?>
			<?php else : ?>
				<p class="property-archive__empty">条件に一致する物件は見つかりませんでした。</p>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();

		return trim( ob_get_clean() );
	}
}

add_shortcode( 'ovl_property_cards', 'ovl_render_property_cards_shortcode' );

if ( ! function_exists( 'ovl_property_normalize_meta_value' ) ) {
	/**
	 * Normalizes ACF/meta values into strings.
	 *
	 * @param mixed $value Raw value.
	 */
	function ovl_property_normalize_meta_value( $value ): string {
		if ( is_array( $value ) ) {
			if ( array_key_exists( 'value', $value ) ) {
				$value = $value['value'];
			} elseif ( array_key_exists( 'label', $value ) ) {
				$value = $value['label'];
			} else {
				$value = reset( $value );
			}
		}

		if ( null === $value ) {
			return '';
		}

		return trim( wp_strip_all_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'ovl_property_get_meta_value' ) ) {
	/**
	 * Fetches a property meta value with ACF fallback.
	 *
	 * @param string $key     Meta key.
	 * @param int    $post_id Post ID.
	 */
	function ovl_property_get_meta_value( string $key, int $post_id ): string {
		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $key, $post_id );
		} else {
			$value = get_post_meta( $post_id, $key, true );
		}

		return ovl_property_normalize_meta_value( $value );
	}
}

if ( ! function_exists( 'ovl_property_format_numeric_meta' ) ) {
	/**
	 * Formats numeric meta values.
	 *
	 * @param string $value    Raw value.
	 * @param int    $decimals Decimals.
	 */
	function ovl_property_format_numeric_meta( string $value, int $decimals = 0 ): string {
		if ( '' === $value ) {
			return '';
		}

		$normalized = str_replace( [ ',', '，' ], '', $value );
		$normalized = preg_replace( '/[^0-9.\-]/', '', $normalized );
		if ( '' === $normalized ) {
			return '';
		}

		return number_format_i18n( (float) $normalized, $decimals );
	}
}

if ( ! function_exists( 'ovl_property_price_shortcode' ) ) {
	/**
	 * Outputs the formatted property price for loops.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	function ovl_property_price_shortcode( array $atts = [] ): string {
		$post_id = get_the_ID();
		if ( ! $post_id || 'property' !== get_post_type( $post_id ) ) {
			return '';
		}
		$atts = shortcode_atts(
			[
				'label'    => '価格',
				'class'    => '',
				'fallback' => '—',
			],
			$atts,
			'ovl_property_price'

		);

		$value = $atts['fallback'];

		$price_value = ovl_property_get_meta_value( 'price', $post_id );

		$formatted = ovl_property_format_numeric_meta( $price_value, 0 );
		if ( '' !== $formatted ) {
			$value = $formatted . '万円';
		}

		$classes    = trim( 'property-card__meta property-card__meta--price ' . $atts['class'] );
		$label_html = $atts['label'] ? '<span class="property-card__meta-label">' . esc_html( $atts['label'] ) . '</span>' : '';

		return sprintf(
			'<p class="%1$s">%2$s<span class="property-card__meta-value">%3$s</span></p>',
			esc_attr( $classes ),
			$label_html,
			esc_html( $value )
		);
	}
}
add_shortcode( 'ovl_property_price', 'ovl_property_price_shortcode' );

if ( ! function_exists( 'ovl_property_yield_shortcode' ) ) {
	/**
	 * Outputs preferred yield meta for property loops.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	function ovl_property_yield_shortcode( array $atts = [] ): string {
		$post_id = get_the_ID();
		if ( ! $post_id || 'property' !== get_post_type( $post_id ) ) {
			return '';
		}

		$atts = shortcode_atts(
			[
				'label'    => '表面利回り',
				'class'    => '',
				'fields'   => 'yield_gross,yield_surface,yield_real,yield_actual',
				'fallback' => '—',
			],
			$atts,
			'ovl_property_yield'
		);

		$value = $atts['fallback'];

		$keys = array_filter( array_map( 'trim', explode( ',', $atts['fields'] ) ) );
		foreach ( $keys as $key ) {
			$key_value = ovl_property_get_meta_value( $key, $post_id );
			if ( '' === $key_value ) {
				continue;
			}

			$formatted = ovl_property_format_numeric_meta( $key_value, 1 );
			if ( '' === $formatted ) {
				continue;
			}

			$value = $formatted . '%';
			break;
		}

		$classes    = trim( 'property-card__meta property-card__meta--yield ' . $atts['class'] );
		$label_html = $atts['label'] ? '<span class="property-card__meta-label">' . esc_html( $atts['label'] ) . '</span>' : '';

		return sprintf(
			'<p class="%1$s">%2$s<span class="property-card__meta-value">%3$s</span></p>',
			esc_attr( $classes ),
			$label_html,
			esc_html( $value )
		);
	}
}
add_shortcode( 'ovl_property_yield', 'ovl_property_yield_shortcode' );
