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
					case 'yield_surface':
					case 'yield_actual':
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
	<article <?php post_class( 'ovl-property-article' ); ?>>
		<header class="entry-header">
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="entry-thumbnail">
					<?php the_post_thumbnail( 'large', [ 'class' => 'property-hero' ] ); ?>
				</div>
			<?php endif; ?>
		</header>

		<?php if ( $is_logged_in ) : ?>
			<section class="entry-content">
				<?php
				if ( function_exists( 'get_field_objects' ) ) {
					$fields = get_field_objects( $post->ID );
					if ( $fields ) {
						$exclude = [ 'doc_url', 'map_lat', 'map_lng', 'youtube_url', 'og_image' ];
						foreach ( $exclude as $removed_key ) {
							unset( $fields[ $removed_key ] );
						}

						$priority_order = [
							'address_city',
							'address_full',
							'price',
							'yield_surface',
							'yield_actual',
							'structure',
							'building_age',
							'floor_area',
							'land_area',
							'road_width',
							'zoning',
							'rent_monthly',
							'occupancy',
						];

						echo '<div class="property-fields">';
						foreach ( $priority_order as $key ) {
							if ( isset( $fields[ $key ] ) ) {
								echo ovl_property_detail_format_field( $fields[ $key ] ); // OVL: Render prioritized field.
								unset( $fields[ $key ] );
							}
						}
						foreach ( $fields as $field ) {
							echo ovl_property_detail_format_field( $field );
						}
						echo '</div>';
					} else {
						error_log( '[OVL] get_field_objects empty for property ID ' . $post->ID );
						echo '<p class="property-field notice">' . esc_html__( 'No property data found. Please update the post and try again.', 'ovl' ) . '</p>';
					}
				}

				$download_button = do_shortcode( '[ovl_download_button]' );
				if ( $download_button ) :
					?>
					<section class="property-download">
						<h2><?php esc_html_e( 'Download documents', 'ovl' ); ?></h2>
						<?php echo $download_button; ?>
					</section>
				<?php endif; ?>

				<?php if ( get_the_content() ) : ?>
					<hr />
					<div class="property-description">
						<?php the_content(); ?>
					</div>
				<?php endif; ?>

				<?php
				$youtube_url = function_exists( 'get_field' ) ? get_field( 'youtube_url', $post->ID ) : '';
				$map_lat     = function_exists( 'get_field' ) ? get_field( 'map_lat', $post->ID ) : '';
				$map_lng     = function_exists( 'get_field' ) ? get_field( 'map_lng', $post->ID ) : '';
				?>

				<?php if ( $youtube_url ) : ?>
					<section class="property-video">
						<h2><?php esc_html_e( 'Property video', 'ovl' ); ?></h2>
						<div class="video-embed">
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
					<section class="property-map">
						<h2><?php esc_html_e( 'Map', 'ovl' ); ?></h2>
						<div class="map-embed">
							<iframe src="<?php echo esc_url( $maps_embed ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
						</div>
					</section>
				<?php endif; ?>

			</section>
		<?php else : ?>
			<section class="entry-content">
				<?php
				if ( function_exists( 'get_field' ) ) {
					$city    = get_field( 'address_city', $post->ID );
					$summary = get_field( 'summary_ai', $post->ID );
					if ( $city ) {
						echo '<p><strong>' . esc_html__( 'Area:', 'ovl' ) . '</strong>' . esc_html( $city ) . '</p>';
					}
					echo '<p><strong>' . esc_html__( 'Price:', 'ovl' ) . '</strong>' . esc_html__( 'Available after login', 'ovl' ) . '</p>';
					if ( $summary ) {
						echo '<div class="property-summary">' . wpautop( esc_html( $summary ) ) . '</div>';
					}
				}
				?>
				<p><?php esc_html_e( 'Detailed price, address, floor plans, and ROI reports are available for members only.', 'ovl' ); ?></p>

				<p style="margin-top:1rem;">
					<a class="button btn btn-primary" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in for details', 'ovl' ); ?></a>
					<?php if ( $register_url ) : ?>
						&nbsp;/&nbsp;
						<a class="button btn btn-outline" href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Register for free', 'ovl' ); ?></a>
					<?php endif; ?>
				</p>

				<?php if ( has_excerpt() ) : ?>
					<div class="property-excerpt" style="margin-top:1rem;">
						<?php the_excerpt(); ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<footer class="property-footer" style="margin-top:2rem;">
			<a class="btn btn-link" href="<?php echo esc_url( home_url( '/property_list/' ) ); ?>">← <?php esc_html_e( 'Back to listings', 'ovl' ); ?></a>
		</footer>
	</article>
	<?php

	$output = ob_get_clean();
	wp_reset_postdata();

	return (string) $output;
};
