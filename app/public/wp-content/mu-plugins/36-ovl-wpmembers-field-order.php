<?php
// OVL: Adjust WP-Members field order for profile edit screens.

if ( ! function_exists( 'ovl_filter_wpmem_fields_order_profile_name' ) ) {
  /**
   * Reorder name fields on WP-Members profile forms (edit/update).
   *
   * WP-Members converts tags edit/update -> profile internally, so we target 'profile'.
   *
   * @param array  $fields Field definitions keyed by meta key.
   * @param string $tag    Context tag (e.g., register/profile/all).
   * @param string $form   Form variant (default/shortform/etc).
   * @return array
   */
  function ovl_filter_wpmem_fields_order_profile_name( array $fields, string $tag, string $form ): array {
    if ( 'profile' !== $tag ) {
      return $fields;
    }

    if ( ! isset( $fields['first_name'], $fields['last_name'] ) ) {
      return $fields;
    }

    $reordered = [
      'last_name'  => $fields['last_name'],
      'first_name' => $fields['first_name'],
    ];

    foreach ( $fields as $meta_key => $field ) {
      if ( 'first_name' === $meta_key || 'last_name' === $meta_key ) {
        continue;
      }
      $reordered[ $meta_key ] = $field;
    }

    return $reordered;
  }
}

add_filter( 'wpmem_fields', 'ovl_filter_wpmem_fields_order_profile_name', 20, 3 );

