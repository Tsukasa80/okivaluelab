<?php
/**
 * 日本語等を安全なASCII名へ変換（今後のアップロードのみ）
 * 例: "写真 2025-11-02.jpg" → "syasin-2025-11-02.jpg"（実際はスラッグ化）
 */
add_filter('sanitize_file_name', function( $filename ){
  // pathinfo は multibyte でもOK
  $info = pathinfo( $filename );
  $ext  = isset($info['extension']) ? ('.' . strtolower($info['extension'])) : '';
  $name = $info['filename'] ?? 'media';

  // アクセント/全角を落としてスラッグ化
  $name = sanitize_title( remove_accents( $name ) );

  // すべて消えてしまった場合の保険名（8桁ランダム）
  if ( $name === '' ) {
    if ( function_exists('wp_generate_password') ) {
      $rand = wp_generate_password(8, false, false);
    } else {
      try { $rand = bin2hex(random_bytes(4)); } catch (Throwable $e) { $rand = '0000aaaa'; }
    }
    $name = 'media-' . $rand;
  }

  return $name . $ext;
}, 10);
