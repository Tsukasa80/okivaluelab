<?php
add_action('template_redirect', function () {
  if ( is_admin() ) return;

  $uri  = $_SERVER['REQUEST_URI'];
  $ipa  = is_post_type_archive('property') ? 1 : 0;
  $fp   = is_front_page() ? 1 : 0;
  $home = is_home() ? 1 : 0;
  $arch = is_archive() ? 1 : 0;

  // 参考：Reading設定のID（どのページが割当か確認）
  $page_on_front  = get_option('page_on_front');   // 0 なら未設定
  $page_for_posts = get_option('page_for_posts');  // 0 なら未設定

  // error_log("[WHEREAMI:template_redirect] uri={$uri} ipa(property)={$ipa} is_front={$fp} is_home={$home} is_archive={$arch} page_on_front={$page_on_front} page_for_posts={$page_for_posts}");
}, 11);
