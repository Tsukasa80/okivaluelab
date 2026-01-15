<?php
/**
 * Login error message overrides.
 *
 * @package okivaluelab
 */

add_filter(
  'login_errors',
  function ( $error ) {
    $target = '不明なメールアドレスです。再確認するかユーザー名による指定をお試しください。';
    $replacement = '不明なメールアドレスです。<br>メールアドレスを再確認するか、新規会員登録をお願いします。';

    if ( false !== strpos( $error, $target ) ) {
      return str_replace( $target, $replacement, $error );
    }

    return $error;
  },
  20
);

add_filter(
  'gettext',
  function ( $translation, $text, $domain ) {
    if ( 'default' !== $domain ) {
      return $translation;
    }

    $target = '不明なメールアドレスです。再確認するかユーザー名による指定をお試しください。';
    if ( $text === $target || $translation === $target ) {
      return '不明なメールアドレスです。<br>メールアドレスを再確認するか、新規会員登録をお願いします。';
    }

    return $translation;
  },
  20,
  3
);
