<?php
if ( ! defined('ABSPATH') ) exit;

// まず安全に $post_id を決める
$post_id = isset($post_id) ? $post_id : 0;

// そのあとで関数を呼ぶ
echo get_the_post_thumbnail(
  $post_id,
  'ovl_card',
  [
    'class'   => 'ovl-card-thumb',
    'loading' => 'lazy',
    'sizes'   => '(max-width: 900px) 100vw, 33vw',
    'style'   => 'width:100%;height:100%;object-fit:cover;display:block;aspect-ratio:16/9;'
  ]
);



// テーマ全体でアイキャッチを有効化＋16:9サイズ定義
add_action('after_setup_theme', function () {
  add_theme_support('post-thumbnails');
  add_image_size('ovl_card', 768, 432, true); // 一覧カード用
});

// CPT「property」に後付けで thumbnail サポート
add_action('init', function () {
  if ( post_type_exists('property') ) {
    add_post_type_support('property', 'thumbnail');
  }
}, 20);
