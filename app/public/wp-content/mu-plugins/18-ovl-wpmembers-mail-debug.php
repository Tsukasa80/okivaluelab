<?php
// OVL: Debug WP-Members outgoing emails on local environments.

if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) || WP_ENVIRONMENT_TYPE !== 'local' ) {
  return;
}

if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
  return;
}

if ( ! function_exists( 'ovl_log_wpmem_email_send_args' ) ) {
  /**
   * Logs outgoing WP-Members email details just before wp_mail().
   *
   * @param array  $args     wp_mail args (to/subject/message/headers/attachments).
   * @param string $to       "user" or "admin".
   * @param array  $settings WP-Members email settings.
   * @return array
   */
  function ovl_log_wpmem_email_send_args( array $args, string $to, array $settings ): array {
    $recipient = isset( $args['to'] ) ? (string) $args['to'] : '';
    $subject   = isset( $args['subject'] ) ? (string) $args['subject'] : '';
    $message   = isset( $args['message'] ) ? (string) $args['message'] : '';

    error_log(
      sprintf(
        '[OVL:mail-debug] wpmem_email_send_args to=%s recipient=%s subject=%s',
        $to,
        $recipient,
        $subject
      )
    );

    // OVL: If Local has no email inbox UI, log the confirmation link so registration can proceed.
    if ( $to === 'user' && $message !== '' ) {
      $urls = [];
      if ( preg_match_all( '#https?://[^\\s"<>]+#i', $message, $matches ) ) {
        foreach ( $matches[0] as $url ) {
          if ( strpos( $url, 'key=' ) !== false ) {
            $urls[] = $url;
          }
        }
      }

      if ( $urls ) {
        error_log( '[OVL:mail-debug] confirm_link ' . implode( ' ', array_values( array_unique( $urls ) ) ) );
      }
    }

    return $args;
  }
}

add_filter( 'wpmem_email_send_args', 'ovl_log_wpmem_email_send_args', 999, 3 );

if ( ! function_exists( 'ovl_log_wp_mail_failed' ) ) {
  /**
   * Logs wp_mail failures (WP_Error).
   *
   * @param WP_Error $wp_error Error object from wp_mail.
   */
  function ovl_log_wp_mail_failed( $wp_error ): void {
    if ( ! is_wp_error( $wp_error ) ) {
      return;
    }

    $data = $wp_error->get_error_data();
    $to   = '';
    if ( is_array( $data ) && isset( $data['to'] ) ) {
      $to = is_array( $data['to'] ) ? implode( ',', $data['to'] ) : (string) $data['to'];
    }

    error_log(
      sprintf(
        '[OVL:mail-debug] wp_mail_failed message=%s to=%s',
        $wp_error->get_error_message(),
        $to
      )
    );
  }
}

add_action( 'wp_mail_failed', 'ovl_log_wp_mail_failed', 10, 1 );

if ( ! function_exists( 'ovl_log_wp_mail_succeeded' ) ) {
  /**
   * Logs wp_mail success (note: does not guarantee delivery).
   *
   * @param array $mail_data wp_mail args after normalization.
   */
  function ovl_log_wp_mail_succeeded( array $mail_data ): void {
    $to      = isset( $mail_data['to'] ) ? $mail_data['to'] : '';
    $subject = isset( $mail_data['subject'] ) ? (string) $mail_data['subject'] : '';

    if ( is_array( $to ) ) {
      $to = implode( ',', $to );
    }

    error_log(
      sprintf(
        '[OVL:mail-debug] wp_mail_succeeded to=%s subject=%s',
        (string) $to,
        $subject
      )
    );
  }
}

add_action( 'wp_mail_succeeded', 'ovl_log_wp_mail_succeeded', 10, 1 );
