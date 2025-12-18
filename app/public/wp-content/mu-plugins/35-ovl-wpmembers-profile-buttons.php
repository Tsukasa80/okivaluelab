<?php
// OVL: Render WP-Members profile links as buttons.

if ( ! function_exists( 'ovl_filter_wpmem_member_links_args' ) ) {
  /**
   * Styles the default [wpmem_profile] link list as block buttons.
   *
   * @param array $args Default links args.
   * @return array
   */
  function ovl_filter_wpmem_member_links_args( array $args ): array {
    $edit_url = esc_url( add_query_arg( 'a', 'edit', remove_query_arg( 'key' ) ) );
    $pwd_url  = esc_url( add_query_arg( 'a', 'pwdchange', remove_query_arg( 'key' ) ) );

    $edit_label = function_exists( 'wpmem_get_text' ) ? wpmem_get_text( 'profile_edit' ) : '登録情報の変更';
    $pwd_label  = function_exists( 'wpmem_get_text' ) ? wpmem_get_text( 'profile_password' ) : 'パスワードを変更';

    $buttons = [
      '<div class="wp-block-button members-btn"><a class="wp-block-button__link wp-element-button" href="' . $edit_url . '">' . esc_html( $edit_label ) . '</a></div>',
      '<div class="wp-block-button members-btn"><a class="wp-block-button__link wp-element-button" href="' . $pwd_url . '">' . esc_html( $pwd_label ) . '</a></div>',
    ];

    $extra_rows = [];
    if ( isset( $args['rows'] ) && is_array( $args['rows'] ) && count( $args['rows'] ) > 2 ) {
      $extra_rows = array_slice( $args['rows'], 2 );
    }

    $args['wrapper_before'] = '<div class="wp-block-buttons wpmem-member-links">';
    $args['wrapper_after']  = '</div>';
    $args['rows']           = $buttons;

    if ( $extra_rows ) {
      $args['after_wrapper'] = (string) ( $args['after_wrapper'] ?? '' );
      $args['after_wrapper'] .= '<ul class="wpmem-member-links__extra">' . implode( '', $extra_rows ) . '</ul>';
    }

    // Prepend the read-only member fields above the buttons (messages appear above via WP-Members).
    if ( empty( $GLOBALS['ovl_member_fields_rendered'] ) && function_exists( 'do_shortcode' ) ) {
      $previous_context = isset( $GLOBALS['ovl_member_fields_context'] ) ? $GLOBALS['ovl_member_fields_context'] : null;
      $GLOBALS['ovl_member_fields_context'] = 'profile_links';

      $args['before_wrapper'] = (string) ( $args['before_wrapper'] ?? '' );
      $args['before_wrapper'] .= do_shortcode( '[ovl_member_fields]' );

      if ( null === $previous_context ) {
        unset( $GLOBALS['ovl_member_fields_context'] );
      } else {
        $GLOBALS['ovl_member_fields_context'] = $previous_context;
      }
    }

    return $args;
  }
}

add_filter( 'wpmem_member_links_args', 'ovl_filter_wpmem_member_links_args' );
