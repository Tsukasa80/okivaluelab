<?php
/**
 * Property meta dynamic block renderer.
 *
 * Variables available from the registration closure:
 * $attributes array
 * $content    string
 * $block      WP_Block
 *
 * @package okivaluelab-child
 */

$post_id   = $block->context['postId'] ?? get_the_ID();
$post_type = $block->context['postType'] ?? ( $post_id ? get_post_type( $post_id ) : '' );

if ( ! $post_id || 'property' !== $post_type ) {
	return;
}

$variant = $attributes['variant'] ?? 'price';
$label   = $attributes['label'] ?? '';

if ( 'price' === $variant ) {
	$price_raw = ovl_property_get_meta_value( 'price', $post_id );
	$price     = ovl_property_format_numeric_meta( $price_raw, 0 );
	$value     = '—';

	if ( '' !== $price ) {
		$value = $price . '万円';
	}

	if ( ! $label ) {
		$label = '価格';
	}

	$wrapper_class = 'property-card__meta property-card__meta--price';
} else {
	if ( ! $label ) {
		$label = '表面利回り';
	}

	$fields = array_filter( array_map( 'trim', explode( ',', $attributes['yieldFields'] ?? '' ) ) );
	$fields = $fields ?: array( 'yield_gross', 'yield_surface', 'yield_real', 'yield_actual' );
	$value = '—';

	foreach ( $fields as $field_key ) {
		$raw = ovl_property_get_meta_value( $field_key, $post_id );
		if ( '' === $raw ) {
			continue;
		}

		$number = ovl_property_format_numeric_meta( $raw, 1 );
		if ( '' === $number ) {
			continue;
		}

		$value = $number . '%';
		break;
	}

	$wrapper_class = 'property-card__meta property-card__meta--yield';
}

$label_html = $label ? '<span class="property-card__meta-label">' . esc_html( $label ) . '</span>' : '';

?>
<p class="<?php echo esc_attr( $wrapper_class ); ?>">
	<?php
	if ( $label_html ) {
		echo wp_kses_post( $label_html );
	}
	?>
	<span class="property-card__meta-value"><?php echo esc_html( $value ); ?></span>
</p>
