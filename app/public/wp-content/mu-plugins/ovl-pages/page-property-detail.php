<?php
// OVL: Renderer for `[ovl_page slug="property-detail"]`, replacing legacy `[ovl_property_detail]`.

if ( ! function_exists( 'ovl_property_detail_format_field' ) ) {
	/**
	 * Formats an ACF field for display in the property detail view.
	 *
	 * @param array $field Field definition with value.
	 *
	 * @return string
	 */
	function ovl_property_detail_format_field( array $field ): string {
		$label = esc_html( $field['label'] ?? '' );
		$value = $field['value'] ?? '';
		$name  = $field['name'] ?? '';

		if ( $value === '' || $value === null || $value === false || $value === [] ) {
			return '';
		}

		switch ( $field['type'] ?? '' ) {
			case 'true_false':
				$out = $value ? __( 'Yes', 'ovl' ) : __( 'No', 'ovl' );
				return "<p class=\"property-field\"><strong>{$label}：</strong> {$out}</p>";

			case 'select':
			case 'checkbox':
			case 'radio':
				$out = is_array( $value ) ? implode( '、', array_map( 'esc_html', $value ) ) : esc_html( $value );
				return "<p class=\"property-field\"><strong>{$label}：</strong> {$out}</p>";

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return "<p class=\"property-field\"><strong>{$label}：</strong> " . esc_html( $value ) . '</p>';
				}

				$num      = (float) $value;
				$decimals = 0;
				$suffix   = '';

				switch ( $name ) {
					case 'price':
						$suffix = ' 万円';
						break;
					case 'rent_monthly':
						$suffix = ' 円';
						break;
					case 'yield_gross':
					case 'yield_real':
					case 'occupancy':
						$decimals = 1;
						$suffix   = ' %';
						break;
					case 'floor_area':
					case 'land_area':
						$decimals = 2;
						$suffix   = ' ㎡';
						break;
					case 'road_width':
						$decimals = 1;
						$suffix   = ' m';
						break;
					case 'building_age':
						$suffix = '年';
						return "<p class=\"property-field\"><strong>{$label}：</strong> 築" . esc_html( number_format_i18n( $num, 0 ) ) . "{$suffix}</p>";
				}

				return "<p class=\"property-field\"><strong>{$label}：</strong> " . esc_html( number_format_i18n( $num, $decimals ) . $suffix ) . '</p>';

			case 'image':
				if ( is_array( $value ) && ! empty( $value['url'] ) ) {
					$url = esc_url( $value['url'] );
					$alt = esc_attr( $value['alt'] ?? $label );
					return "<div class=\"property-field\"><strong>{$label}：</strong><br><img src=\"{$url}\" alt=\"{$alt}\" style=\"max-width:240px;height:auto;border-radius:8px;margin-top:6px;\" /></div>";
				}
				break;

			case 'gallery':
				if ( is_array( $value ) && $value ) {
					$imgs = [];
					foreach ( $value as $img ) {
						$url = esc_url( $img['sizes']['large'] ?? ( $img['url'] ?? '' ) );
						if ( $url ) {
							$imgs[] = "<figure class=\"gallery-item\"><img src=\"{$url}\" alt=\"\" /></figure>";
						}
					}
					if ( $imgs ) {
						return "<div class=\"property-field\"><strong>{$label}：</strong><div class=\"gallery-grid\">" . implode( '', $imgs ) . '</div></div>';
					}
				}
				break;

			case 'file':
				if ( is_array( $value ) && ! empty( $value['url'] ) ) {
					$url  = esc_url( $value['url'] );
					$name = esc_html( $value['filename'] ?? __( 'File', 'ovl' ) );
					return "<p class=\"property-field\"><strong>{$label}：</strong> <a href=\"{$url}\" target=\"_blank\" rel=\"noopener\">{$name}</a></p>";
				}
				break;

			case 'relationship':
			case 'post_object':
				$items = is_array( $value ) ? $value : [ $value ];
				$links = [];
				foreach ( $items as $post_obj ) {
					if ( empty( $post_obj ) ) {
						continue;
					}
					$links[] = '<a href="' . esc_url( get_permalink( $post_obj ) ) . '">' . esc_html( get_the_title( $post_obj ) ) . '</a>';
				}
				if ( $links ) {
					return "<p class=\"property-field\"><strong>{$label}：</strong> " . implode( '、', $links ) . '</p>';
				}
				break;

			case 'repeater':
				if ( is_array( $value ) && $value ) {
					$rows = [];
					foreach ( $value as $row ) {
						$parts = [];
						foreach ( $row as $v ) {
							if ( $v === '' || $v === null ) {
								continue;
							}
							$parts[] = is_array( $v ) ? implode( '/', array_map( 'esc_html', $v ) ) : esc_html( $v );
						}
						if ( $parts ) {
							$rows[] = implode( ' | ', $parts );
						}
					}
					if ( $rows ) {
						return "<p class=\"property-field\"><strong>{$label}：</strong><br>" . implode( '<br>', $rows ) . '</p>';
					}
				}
				break;
		}

		if ( is_scalar( $value ) && $value !== '' ) {
			return "<p class=\"property-field\"><strong>{$label}：</strong> " . esc_html( (string) $value ) . '</p>';
		}

		return '';
	}
}

if ( ! function_exists( 'ovl_property_detail_plain_value' ) ) {
	/**
	 * Returns a sanitized plain string for a field value.
	 *
	 * @param array  $field    Field definition with value.
	 * @param string $fallback Text used when the field has no value.
	 *
	 * @return string
	 */
	function ovl_property_detail_plain_value( array $field, string $fallback = '—' ): string {
		$value = $field['value'] ?? '';

		if ( $value === '' || $value === null || $value === [] ) {
			return $fallback;
		}

		switch ( $field['type'] ?? '' ) {
			case 'true_false':
				return $value ? __( 'あり', 'ovl' ) : __( 'なし', 'ovl' );

			case 'select':
			case 'checkbox':
			case 'radio':
				$items = is_array( $value ) ? array_map( 'wp_strip_all_tags', $value ) : [ wp_strip_all_tags( (string) $value ) ];
				return $items ? implode( '／', $items ) : $fallback;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return wp_strip_all_tags( (string) $value );
				}

				$num      = (float) $value;
				$decimals = 0;
				$suffix   = '';

				switch ( $field['name'] ?? '' ) {
					case 'price':
						$suffix = ' 万円';
						break;
					case 'rent_monthly':
						$suffix = ' 円';
						break;
					case 'yield_gross':
					case 'yield_real':
					case 'occupancy':
						$decimals = 1;
						$suffix   = ' %';
						break;
					case 'floor_area':
					case 'land_area':
						$decimals = 2;
						$suffix   = ' ㎡';
						break;
					case 'road_width':
						$decimals = 1;
						$suffix   = ' m';
						break;
					case 'building_age':
						return '築' . number_format_i18n( $num, 0 ) . '年';
				}

				return number_format_i18n( $num, $decimals ) . $suffix;

			case 'relationship':
			case 'post_object':
				$posts = is_array( $value ) ? $value : [ $value ];
				$names = [];
				foreach ( $posts as $post_obj ) {
					if ( empty( $post_obj ) ) {
						continue;
					}
					$names[] = get_the_title( $post_obj );
				}
				return $names ? implode( '、', array_map( 'wp_strip_all_tags', $names ) ) : $fallback;

			case 'file':
				if ( is_array( $value ) && ! empty( $value['filename'] ) ) {
					return wp_strip_all_tags( (string) $value['filename'] );
				}
				break;

			case 'repeater':
				if ( is_array( $value ) && $value ) {
					$rows = [];
					foreach ( $value as $row ) {
						$row_values = [];
						foreach ( $row as $cell ) {
							if ( '' === $cell || $cell === null ) {
								continue;
							}
							$row_values[] = is_array( $cell ) ? implode( '/', array_map( 'wp_strip_all_tags', $cell ) ) : wp_strip_all_tags( (string) $cell );
						}
						if ( $row_values ) {
							$rows[] = implode( ' | ', $row_values );
						}
					}
					return $rows ? implode( ' ／ ', $rows ) : $fallback;
				}
				break;
		}

		if ( is_scalar( $value ) ) {
			return wp_strip_all_tags( (string) $value );
		}

		if ( is_array( $value ) ) {
			return implode( '／', array_map( 'wp_strip_all_tags', $value ) );
		}

		return $fallback;
	}
}

if ( ! function_exists( 'ovl_property_detail_print_gallery_script' ) ) {
	function ovl_property_detail_print_gallery_script(): void {
		static $printed = false;

		if ( $printed ) {
			return;
		}

		$printed = true;
		?>
		<script>
		(function () {
		  var initGallery = function () {
		    document.querySelectorAll('[data-property-carousel]').forEach(function (carousel) {
		      var slides = carousel.querySelectorAll('[data-carousel-slide]');
		      if (!slides.length) return;
		      var total = slides.length;
		      var current = 0;
		      var prev = carousel.querySelector('[data-carousel-prev]');
		      var next = carousel.querySelector('[data-carousel-next]');
		      var counter = carousel.querySelector('[data-carousel-current]');
		      var thumbs = carousel.querySelectorAll('[data-carousel-thumb]');

		      var setActive = function (index) {
		        current = (index + total) % total;
		        slides.forEach(function (slide) {
		          slide.classList.toggle('is-active', Number(slide.dataset.carouselSlide) === current);
		        });
		        thumbs.forEach(function (thumb) {
		          thumb.classList.toggle('is-active', Number(thumb.dataset.carouselThumb) === current);
		        });
		        if (counter) {
		          counter.textContent = current + 1;
		        }
		      };

		      if (prev) {
		        prev.addEventListener('click', function () {
		          setActive(current - 1);
		        });
		      }
		      if (next) {
		        next.addEventListener('click', function () {
		          setActive(current + 1);
		        });
		      }
		      thumbs.forEach(function (thumb) {
		        thumb.addEventListener('click', function () {
		          var target = Number(thumb.dataset.carouselThumb);
		          if (!Number.isNaN(target)) {
		            setActive(target);
		          }
		        });
		      });
		    });
		  };

		  if (document.readyState !== 'loading') {
		    initGallery();
		  } else {
		    document.addEventListener('DOMContentLoaded', initGallery);
		  }
		})();
		</script>
		<?php
	}
}

return static function ( array $context = [] ): string {
	$post = get_post();
	if ( ! $post || 'property' !== $post->post_type ) {
		error_log( '[OVL] property detail renderer aborted. Post type=' . ( $post->post_type ?? 'null' ) );
		return '';
	}

	setup_postdata( $post );

	$is_logged_in = isset( $context['is_user_logged_in'] ) ? (bool) $context['is_user_logged_in'] : is_user_logged_in();

	if ( ! $is_logged_in ) {
		add_action(
			'wp_head',
			static function () {
				echo "<meta name=\"robots\" content=\"noindex,follow\" />\n"; // OVL: Prevent guest views from being indexed.
			},
			1
		);
	}

	$permalink    = get_permalink( $post );
	$login_url = add_query_arg(
				'redirect_to',
				$permalink,
				home_url( '/members/' )
				);
	$register_url = '';

	if ( get_option( 'users_can_register' ) ) {
		$register_url = add_query_arg( 'redirect_to', rawurlencode( $permalink ), wp_registration_url() );
	}

	ob_start();
	?>
	<article <?php post_class( 'ovl-property-article property-single property-detail' ); ?>>
		<div class="ovl-property-actions">
			<?php if ( $is_logged_in ) : ?>
				<button
					type="button"
					class="ovl-favorite-button"
					data-ovl-fav
					data-property-id="<?php echo esc_attr( (string) $post->ID ); ?>"
					aria-pressed="false"
				>
					<span class="ovl-favorite-button__icon" aria-hidden="true">♡</span>
					<span class="ovl-favorite-button__label"><?php echo esc_html__( 'お気に入りに追加', 'ovl' ); ?></span>
				</button>
			<?php else : ?>
				<button type="button" class="ovl-favorite-button is-disabled" disabled>
					<span class="ovl-favorite-button__icon" aria-hidden="true">♡</span>
					<span class="ovl-favorite-button__label"><?php echo esc_html__( 'ログイン後にお気に入り追加', 'ovl' ); ?></span>
				</button>
				<p class="ovl-favorite-login">
					<a href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html__( 'ログイン', 'ovl' ); ?></a>
					<?php if ( $register_url ) : ?>
						/ <a href="<?php echo esc_url( $register_url ); ?>"><?php echo esc_html__( '新規会員登録', 'ovl' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php if ( $is_logged_in ) : ?>
			<?php
			$field_objects = [];
			if ( function_exists( 'get_field_objects' ) ) {
				$field_objects = get_field_objects( $post->ID );
				if ( ! $field_objects ) {
					error_log( '[OVL] get_field_objects empty for property ID ' . $post->ID );
				}
			}

			$field_map = [];
			if ( $field_objects ) {
				foreach ( $field_objects as $field ) {
					$name = $field['name'] ?? '';
					if ( '' === $name ) {
						continue;
					}
					$field_map[ $name ] = $field;
				}

				$legacy_aliases = [
					'yield_gross' => [
						'legacy_key' => 'yield_surface',
						'label'      => __( '表面利回り', 'ovl' ),
					],
					'yield_real'  => [
						'legacy_key' => 'yield_actual',
						'label'      => __( '実質利回り', 'ovl' ),
					],
				];

				foreach ( $legacy_aliases as $primary => $data ) {
					if ( isset( $field_map[ $primary ] ) ) {
						continue;
					}

					if ( isset( $field_map[ $data['legacy_key'] ] ) ) {
						$field_map[ $primary ]         = $field_map[ $data['legacy_key'] ];
						$field_map[ $primary ]['name'] = $primary;
						continue;
					}

					if ( ! function_exists( 'get_field' ) ) {
						continue;
					}

					$legacy_value = get_field( $data['legacy_key'], $post->ID );
					if ( $legacy_value === '' || $legacy_value === null ) {
						continue;
					}

					$field_map[ $primary ] = [
						'name'  => $primary,
						'label' => $data['label'],
						'type'  => 'number',
						'value' => $legacy_value,
					];
				}
			}

			$get_plain_value = static function ( string $key, string $fallback = '—' ) use ( $field_map ) {
				if ( ! isset( $field_map[ $key ] ) ) {
					return $fallback;
				}

				return ovl_property_detail_plain_value( $field_map[ $key ], $fallback );
			};

			$get_field_label = static function ( string $key, string $fallback = '' ) use ( $field_map ) {
				if ( isset( $field_map[ $key ]['label'] ) && $field_map[ $key ]['label'] ) {
					return $field_map[ $key ]['label'];
				}

				return $fallback;
			};

			$hero_city    = $get_plain_value( 'address_city', '' );
			$hero_address = $get_plain_value( 'address_full', '' );
			$price_label  = $get_field_label( 'price', __( '価格', 'ovl' ) );
			$price_value  = $get_plain_value( 'price', __( '価格未登録', 'ovl' ) );
			$structure    = $get_plain_value( 'structure', '' );
			$terms        = get_the_terms( $post->ID, 'property_cat' );
			$status_label = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0]->name : __( '公開中', 'ovl' );
			$is_new       = ( time() - get_post_time( 'U', true, $post ) ) < 14 * DAY_IN_SECONDS;
			$gallery_raw  = function_exists( 'get_field' ) ? get_field( 'gallery', $post->ID ) : [];
			$gallery      = is_array( $gallery_raw ) ? $gallery_raw : [];
			$summary_ai   = function_exists( 'get_field' ) ? get_field( 'summary_ai', $post->ID ) : '';
			$youtube_url  = function_exists( 'get_field' ) ? get_field( 'youtube_url', $post->ID ) : '';
			$map_lat      = function_exists( 'get_field' ) ? get_field( 'map_lat', $post->ID ) : '';
			$map_lng      = function_exists( 'get_field' ) ? get_field( 'map_lng', $post->ID ) : '';
			$download_button = do_shortcode( '[ovl_download_button]' );
			$contact_url     = home_url( '/contact/' );

			$hero_stats = [
				[
					'label' => $get_field_label( 'yield_gross', __( '表面利回り', 'ovl' ) ),
					'value' => $get_plain_value( 'yield_gross', '—' ),
				],
				[
					'label' => $get_field_label( 'yield_real', __( '実質利回り', 'ovl' ) ),
					'value' => $get_plain_value( 'yield_real', '—' ),
				],
				[
					'label' => $get_field_label( 'rent_monthly', __( '月間賃料', 'ovl' ) ),
					'value' => $get_plain_value( 'rent_monthly', '—' ),
				],
				[
					'label' => $get_field_label( 'occupancy', __( '稼働率', 'ovl' ) ),
					'value' => $get_plain_value( 'occupancy', '—' ),
				],
				[
					'label' => $get_field_label( 'building_age', __( '築年数', 'ovl' ) ),
					'value' => $get_plain_value( 'building_age', '—' ),
				],
			];

			$spec_groups = [
				__( '主要スペック', 'ovl' )   => [ 'price', 'yield_gross', 'yield_real', 'rent_monthly', 'occupancy', 'gross__income' ],
				__( '建物情報', 'ovl' )       => [ 'structure', 'building_age', 'building_type', 'floor_plan', 'units', 'floor_area', 'land_area', 'road_width', 'parking' ],
				__( '所在地・交通', 'ovl' ) => [ 'address_full', 'address_city', 'station', 'access' ],
				__( '取引条件', 'ovl' )       => [ 'land_rights', 'zoning', 'urban_planning', 'land_shape', 'setback', 'current_status', 'situation', 'delivery_date', 'transaction_type', 'site_coverage', 'floor_area_ratio' ],
				__( 'PR情報', 'ovl' )        => [ 'slogan', 'pr_text_ai', 'tag_json_ai' ],
			];

			$rendered_keys = [];
			$spec_sections = [];

			foreach ( $spec_groups as $group_label => $keys ) {
				$rows = [];
				foreach ( $keys as $key ) {
					if ( ! isset( $field_map[ $key ] ) ) {
						continue;
					}

					$value = ovl_property_detail_plain_value( $field_map[ $key ], '' );
					if ( '' === $value || '—' === $value ) {
						continue;
					}

					$rows[]         = [
						'label' => $get_field_label( $key, ucwords( str_replace( '_', ' ', $key ) ) ),
						'value' => $value,
					];
					$rendered_keys[] = $key;
				}

				if ( $rows ) {
					$spec_sections[] = [
						'title' => $group_label,
						'rows'  => $rows,
					];
				}
			}

				$exclude_keys = [
					'doc_url',
					'map_lat',
					'map_lng',
					'youtube_url',
					'og_image',
					'og_image2',
					'og_image3',
					'og_image4',
					'og_image5',
					'gallery',
					'summary_ai',
				];

			$remaining_fields = [];
			foreach ( $field_map as $key => $field ) {
				if ( in_array( $key, $exclude_keys, true ) ) {
					continue;
				}

				if ( in_array( $key, $rendered_keys, true ) ) {
					continue;
				}

				$remaining_fields[] = $field;
			}
				$media_items = [];
				$media_seen  = [];

				$resolve_image_data = static function ( $image ) {
					if ( is_array( $image ) ) {
						$full = $image['sizes']['1536x1536'] ?? ( $image['sizes']['large'] ?? ( $image['url'] ?? '' ) );
						if ( ! $full && ! empty( $image['ID'] ) ) {
							$full = wp_get_attachment_image_url( (int) $image['ID'], 'full' );
						}

						$thumb = $image['sizes']['medium_large'] ?? ( $image['sizes']['medium'] ?? ( $image['url'] ?? '' ) );
						if ( ! $thumb && ! empty( $image['ID'] ) ) {
							$thumb = wp_get_attachment_image_url( (int) $image['ID'], 'medium_large' ) ?: wp_get_attachment_image_url( (int) $image['ID'], 'medium' );
						}

						$alt = $image['alt'] ?? '';

						return $full ? [
							'full'  => $full,
							'thumb' => $thumb ?: $full,
							'alt'   => $alt,
						] : null;
					}

					if ( is_numeric( $image ) ) {
						$attachment_id = (int) $image;
						$full          = wp_get_attachment_image_url( $attachment_id, 'full' );
						if ( ! $full ) {
							return null;
						}
						$thumb = wp_get_attachment_image_url( $attachment_id, 'medium_large' ) ?: wp_get_attachment_image_url( $attachment_id, 'medium' );
						$alt   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';

						return [
							'full'  => $full,
							'thumb' => $thumb ?: $full,
							'alt'   => $alt,
						];
					}

					if ( is_string( $image ) ) {
						$url = trim( $image );
						if ( '' === $url ) {
							return null;
						}

						return [
							'full'  => esc_url_raw( $url ),
							'thumb' => esc_url_raw( $url ),
							'alt'   => '',
						];
					}

					return null;
				};

				$add_media_item = static function ( $full_url, $thumb_url = '', $alt_text = '' ) use ( &$media_items, &$media_seen, $post ) {
					$full = $full_url ? (string) $full_url : '';
					if ( '' === $full || in_array( $full, $media_seen, true ) ) {
						return;
					}

					$media_items[] = [
						'full'  => $full,
						'thumb' => $thumb_url ? (string) $thumb_url : $full,
						'alt'   => $alt_text ?: get_the_title( $post ),
					];
					$media_seen[] = $full;
				};

				if ( has_post_thumbnail( $post ) ) {
					$thumb_id  = (int) get_post_thumbnail_id( $post );
					$full_url  = wp_get_attachment_image_url( $thumb_id, 'full' ) ?: wp_get_attachment_image_url( $thumb_id, 'large' );
					$thumb_url = wp_get_attachment_image_url( $thumb_id, 'medium_large' ) ?: wp_get_attachment_image_url( $thumb_id, 'medium' );
					$alt       = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ?: get_the_title( $post );

					$add_media_item( $full_url, $thumb_url ?: $full_url, $alt );
				}

				if ( $gallery ) {
					foreach ( $gallery as $image ) {
						$image_data = $resolve_image_data( $image );
						if ( ! $image_data ) {
							continue;
						}

						$add_media_item( $image_data['full'], $image_data['thumb'], $image_data['alt'] ?? get_the_title( $post ) );
					}
				}

				$custom_media_keys = [ 'og_image', 'og_image2', 'og_image3', 'og_image4', 'og_image5' ];
				foreach ( $custom_media_keys as $media_key ) {
					$value = null;

					if ( isset( $field_map[ $media_key ] ) ) {
						$value = $field_map[ $media_key ]['value'] ?? null;
					}

					if ( ( null === $value || '' === $value ) && function_exists( 'get_field' ) ) {
						$value = get_field( $media_key, $post->ID, false );
					}

					if ( ( null === $value || '' === $value ) && metadata_exists( 'post', $post->ID, $media_key ) ) {
						$value = get_post_meta( $post->ID, $media_key, true );
					}

					if ( null === $value || '' === $value ) {
						continue;
					}

					$image_data = $resolve_image_data( $value );
					if ( ! $image_data ) {
						continue;
					}

					$add_media_item( $image_data['full'], $image_data['thumb'], $image_data['alt'] ?? get_the_title( $post ) );
				}

			$media_count = count( $media_items );
			if ( $media_count > 1 ) {
				add_action( 'wp_footer', 'ovl_property_detail_print_gallery_script', 200 );
			}

			$hero_grid_classes = 'property-detail__hero-grid';
			if ( ! $media_count ) {
				$hero_grid_classes .= ' property-detail__hero-grid--single';
			}
			?>
			<header class="property-detail__hero property-detail__hero--compact">
				<div class="<?php echo esc_attr( $hero_grid_classes ); ?>">
					<?php if ( $media_count ) : ?>
						<div class="property-detail__hero-media">
							<div class="property-detail__carousel" data-property-carousel>
								<div class="property-detail__carousel-main">
									<?php foreach ( $media_items as $index => $item ) : ?>
										<figure class="property-detail__carousel-slide<?php echo 0 === $index ? ' is-active' : ''; ?>" data-carousel-slide="<?php echo esc_attr( $index ); ?>">
											<img src="<?php echo esc_url( $item['full'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>" loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>" />
										</figure>
									<?php endforeach; ?>
									<?php if ( $media_count > 1 ) : ?>
										<button class="property-detail__carousel-nav property-detail__carousel-nav--prev" type="button" aria-label="<?php esc_attr_e( '前の画像へ', 'ovl' ); ?>" data-carousel-prev>‹</button>
										<button class="property-detail__carousel-nav property-detail__carousel-nav--next" type="button" aria-label="<?php esc_attr_e( '次の画像へ', 'ovl' ); ?>" data-carousel-next>›</button>
										<p class="property-detail__carousel-counter"><span data-carousel-current>1</span> / <?php echo esc_html( $media_count ); ?></p>
									<?php endif; ?>
								</div>
								<?php if ( $media_count > 1 ) : ?>
									<div class="property-detail__carousel-thumbs" role="tablist">
										<?php foreach ( $media_items as $index => $item ) : ?>
											<button class="property-detail__carousel-thumb<?php echo 0 === $index ? ' is-active' : ''; ?>" type="button" data-carousel-thumb="<?php echo esc_attr( $index ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '画像 %d', 'ovl' ), $index + 1 ) ); ?>">
												<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>" loading="lazy" />
											</button>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
					<div class="property-detail__hero-details">
						<div class="property-detail__hero-info">
							<div class="property-detail__badges">
								<span class="property-detail__badge"><?php echo esc_html( $status_label ); ?></span>
								<?php if ( $is_new ) : ?>
									<span class="property-detail__badge is-new"><?php esc_html_e( 'NEW', 'ovl' ); ?></span>
								<?php endif; ?>
							</div>
							<h1 class="property-detail__title"><?php the_title(); ?></h1>
							<?php if ( ( $hero_city && '—' !== $hero_city ) || ( $hero_address && '—' !== $hero_address ) ) : ?>
								<p class="property-detail__location">
									<?php if ( $hero_city && '—' !== $hero_city ) : ?>
										<span class="property-detail__location-city"><?php echo esc_html( $hero_city ); ?></span>
									<?php endif; ?>
									<?php if ( $hero_address && '—' !== $hero_address ) : ?>
										<span><?php echo esc_html( $hero_address ); ?></span>
									<?php endif; ?>
								</p>
							<?php endif; ?>
							<?php if ( $structure && '—' !== $structure ) : ?>
								<p class="property-detail__structure"><?php echo esc_html( $structure ); ?></p>
							<?php endif; ?>
						</div>
						<div class="property-detail__metrics">
							<dl class="property-detail__metric property-detail__metric--price">
								<dt><?php echo esc_html( $price_label ); ?></dt>
								<dd><?php echo esc_html( $price_value ); ?></dd>
							</dl>
							<?php foreach ( $hero_stats as $stat ) : ?>
								<dl class="property-detail__metric">
									<dt><?php echo esc_html( $stat['label'] ); ?></dt>
									<dd><?php echo esc_html( $stat['value'] ); ?></dd>
								</dl>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<div class="property-detail__hero-actions">
					<a class="property-detail__cta button" href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'この物件に問い合わせ', 'ovl' ); ?></a>
					<a class="property-detail__cta button button-outline" href="<?php echo esc_url( home_url( '/property_list/' ) ); ?>"><?php esc_html_e( '一覧に戻る', 'ovl' ); ?></a>
				</div>
			</header>

			<div class="property-detail__layout">
				<div class="property-detail__main">
					<?php if ( $summary_ai ) : ?>
						<section class="property-detail__section property-detail__section--summary">
							<h2><?php esc_html_e( 'AIサマリー', 'ovl' ); ?></h2>
							<div class="property-detail__summary">
								<?php echo wpautop( esc_html( $summary_ai ) ); ?>
							</div>
						</section>
					<?php endif; ?>

					<?php foreach ( $spec_sections as $section ) : ?>
						<section class="property-detail__section">
							<h2><?php echo esc_html( $section['title'] ); ?></h2>
							<dl class="property-detail__spec-list">
								<?php foreach ( $section['rows'] as $row ) : ?>
									<div class="property-detail__spec-item">
										<dt><?php echo esc_html( $row['label'] ); ?></dt>
										<dd><?php echo esc_html( $row['value'] ); ?></dd>
									</div>
								<?php endforeach; ?>
							</dl>
						</section>
					<?php endforeach; ?>

					<?php if ( $remaining_fields ) : ?>
						<section class="property-detail__section">
							<h2><?php esc_html_e( 'その他情報', 'ovl' ); ?></h2>
							<div class="property-detail__misc">
								<?php foreach ( $remaining_fields as $field ) : ?>
									<?php echo ovl_property_detail_format_field( $field ); ?>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>

					<?php if ( get_the_content() ) : ?>
						<section class="property-detail__section">
							<h2><?php esc_html_e( '備考・コメント', 'ovl' ); ?></h2>
							<div class="property-detail__description">
								<?php the_content(); ?>
							</div>
						</section>
					<?php endif; ?>

					<?php if ( $youtube_url ) : ?>
						<section class="property-detail__section property-detail__section--media">
							<h2><?php esc_html_e( '物件動画', 'ovl' ); ?></h2>
							<div class="property-detail__video">
								<?php echo wp_oembed_get( esc_url( $youtube_url ) ); ?>
							</div>
						</section>
					<?php endif; ?>

					<?php
					$lat = is_numeric( $map_lat ) ? (float) $map_lat : null;
					$lng = is_numeric( $map_lng ) ? (float) $map_lng : null;
					if ( $lat !== null && $lng !== null ) :
						$maps_embed = sprintf(
							'https://www.google.com/maps?q=%1$s,%2$s&output=embed',
							rawurlencode( (string) $lat ),
							rawurlencode( (string) $lng )
						);
						?>
						<section class="property-detail__section property-detail__section--media">
							<h2><?php esc_html_e( 'マップ', 'ovl' ); ?></h2>
							<div class="property-detail__map">
								<iframe src="<?php echo esc_url( $maps_embed ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
							</div>
						</section>
					<?php endif; ?>
				</div>
				<aside class="property-detail__sidebar">
					<?php if ( $download_button ) : ?>
						<section id="property-download" class="property-detail__sidebar-box">
							<h3><?php esc_html_e( '資料ダウンロード', 'ovl' ); ?></h3>
							<?php echo $download_button; ?>
						</section>
					<?php endif; ?>
					<section class="property-detail__sidebar-box">
						<h3><?php esc_html_e( 'お問い合わせ', 'ovl' ); ?></h3>
						<p><?php esc_html_e( '詳細なキャッシュフローや現地調査のご相談はお気軽にご連絡ください。', 'ovl' ); ?></p>
						<a class="property-detail__contact button" href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'この物件について相談', 'ovl' ); ?></a>
					</section>
				</aside>
			</div>
		<?php else : ?>
			<header class="property-detail__hero property-detail__hero--locked">
				<div class="property-detail__badges">
					<span class="property-detail__badge is-locked"><?php esc_html_e( '会員限定', 'ovl' ); ?></span>
				</div>
				<h1 class="property-detail__title"><?php the_title(); ?></h1>
				<p class="property-detail__lead">
					<?php esc_html_e( '価格や所在地などの詳細情報は、ログイン後にご確認いただけます。', 'ovl' ); ?>
				</p>
				<div class="property-detail__hero-actions">
					<a class="property-detail__cta button" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'ログイン', 'ovl' ); ?></a>
					<?php if ( $register_url ) : ?>
						<a class="property-detail__cta button button-outline" href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( '無料会員登録', 'ovl' ); ?></a>
					<?php endif; ?>
				</div>
			</header>
			<section class="property-detail__guest">
				<?php
				if ( function_exists( 'get_field' ) ) {
					$city    = get_field( 'address_city', $post->ID );
					$summary = get_field( 'summary_ai', $post->ID );
					if ( $city ) {
						echo '<p class="property-detail__guest-meta"><strong>' . esc_html__( 'エリア', 'ovl' ) . '：</strong>' . esc_html( $city ) . '</p>';
					}
					echo '<p class="property-detail__guest-meta"><strong>' . esc_html__( '価格', 'ovl' ) . '：</strong>' . esc_html__( 'ログイン後に表示', 'ovl' ) . '</p>';
					if ( $summary ) {
						echo '<div class="property-detail__summary">' . wpautop( esc_html( $summary ) ) . '</div>';
					}
				}
				?>
				<p><?php esc_html_e( '表面利回りや賃料情報、詳細資料のダウンロードは会員様限定です。下記よりログインまたは無料登録をお願いします。', 'ovl' ); ?></p>
				<div class="property-detail__hero-actions">
					<a class="property-detail__cta button" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'ログインして詳細を見る', 'ovl' ); ?></a>
					<?php if ( $register_url ) : ?>
						<a class="property-detail__cta button button-outline" href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( '無料会員登録', 'ovl' ); ?></a>
					<?php endif; ?>
				</div>

				<?php if ( has_excerpt() ) : ?>
					<div class="property-detail__excerpt">
						<?php the_excerpt(); ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<footer class="property-footer">
			<a class="btn btn-link" href="<?php echo esc_url( home_url( '/property_list/' ) ); ?>">← <?php esc_html_e( 'Back to listings', 'ovl' ); ?></a>
		</footer>
	</article>
	<?php

	$output = ob_get_clean();
	wp_reset_postdata();

	return (string) $output;
};
