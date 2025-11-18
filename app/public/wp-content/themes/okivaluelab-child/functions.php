<?php
	
// 親→子の順でCSSを読み込み
add_action('wp_enqueue_scripts', function () {
  $parent = 'twenty-twenty-five-style';
  wp_enqueue_style($parent, get_template_directory_uri() . '/style.css', [], null);
  wp_enqueue_style('okivaluelab-child-style', get_stylesheet_uri(), [$parent], filemtime(get_stylesheet_directory() . '/style.css'));
});

// ブロックテーマでのサムネ等（必要に応じて）
add_action('after_setup_theme', function () {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails', ['post', 'page', 'property']);
  add_image_size('property_card', 600, 400, true); // 物件カード向けサイズ
});

// ユーザー名を出す最小ショートコード（5章で使用）
add_shortcode('ovl_username', function () {
  if (!is_user_logged_in()) return '';
  $u = wp_get_current_user();
  return esc_html($u->display_name ?: $u->user_login);
});
