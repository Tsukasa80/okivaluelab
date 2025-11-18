<?php
// どのフックが動き、どの条件に当たっているかをログに出す
if ( ! defined('ABSPATH') ) exit;

// 1) まずは毎リクエストで場所を出す
add_action('init', function(){
  $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '(no uri)';
  error_log('[WHEREAMI:init] URI='.$uri);
});

// 2) メインクエリが組まれる直前に、各種フラグを出す
add_action('pre_get_posts', function($q){
  if ( ! $q->is_main_query() ) return;
  $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '(no uri)';
  error_log(sprintf('[WHEREAMI:pre_get_posts] main=1 uri=%s is_front=%d is_home=%d is_archive=%d is_post_type_archive(property)=%d is_tax=%d post_type=%s',
    $uri,
    (int) $q->is_front_page(),
    (int) $q->is_home(),
    (int) $q->is_archive(),
    (int) is_post_type_archive('property'),
    (int) is_tax(),
    $q->get('post_type') ? (is_array($q->get('post_type')) ? implode(',', (array)$q->get('post_type')) : $q->get('post_type')) : '(none)'
  ));
}, 9);

// 3) テンプレ決定直前（ここで property アーカイブなら投稿一覧も吐く）
add_action('template_redirect', function(){
  $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '(no uri)';
  error_log(sprintf('[WHEREAMI:template_redirect] uri=%s is_property_archive=%d is_tax=%d',
    $uri,
    (int) is_post_type_archive('property'),
    (int) is_tax()
  ));

  if ( is_post_type_archive('property') || is_tax(array('property_cat','property_tag')) ) {
    // メインクエリの投稿を診断
    $posts = get_posts(array(
      'post_type' => 'property',
      'posts_per_page' => 12,
      'paged' => max(1, get_query_var('paged')),
      'fields' => 'ids',
    ));
    error_log('---- [WHEREAMI] property archive detected; posts='.implode(',', $posts).' ----');

    // 1件ずつ最低限の情報
    foreach ($posts as $id) {
      $perm     = get_permalink($id);
      $thumb_id = get_post_thumbnail_id($id);
      $thumb    = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '(no-thumb)';
      error_log(sprintf('[WHEREAMI:post] id=%d perm=%s thumb=%s', $id, $perm, $thumb));
    }
  }
}, 10);
