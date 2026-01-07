<?php
// OVL: Override WP-Members field labels when admin UI cannot edit labels.

if ( ! function_exists( 'ovl_filter_wpmem_field_labels' ) ) {
  /**
   * Override labels for specific WP-Members fields.
   *
   * @param array  $fields Field definitions.
   * @param string $tag    Form tag (register/profile/etc).
   * @param string $form   Form context.
   * @return array
   */
  function ovl_filter_wpmem_field_labels( array $fields, string $tag, string $form ): array {
    if ( isset( $fields['billing_address_1'] ) && is_array( $fields['billing_address_1'] ) ) {
      $fields['billing_address_1']['label'] = '住所（番地）';
    }

    return $fields;
  }
}

add_filter( 'wpmem_fields', 'ovl_filter_wpmem_field_labels', 25, 3 );
