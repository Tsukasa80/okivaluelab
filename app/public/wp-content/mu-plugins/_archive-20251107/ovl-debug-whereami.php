<?php
/**
 * Plugin Name: OVL Debug WhereAmI
 * Description: 現在の URL やリダイレクト経路を debug.log に記録するデバッグ用 MU プラグイン
 */
if ( ! defined('ABSPATH') ) exit;

// 現在のリクエストパスを整形して取得
function ovl_dbg_path() {
  return trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
}

// wp_redirect の経路を必ずログへ出力
add_filter('wp_redirect', function ($location, $status) {
  if ( is_admin() ) return $location;

  $from = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

  // 呼び出しスタックを簡易表示
  $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
  $via = [];
  foreach ($bt as $f) {
    $fn = $f['function'] ?? 'func';
    if (!empty($f['class'])) {
      $fn = $f['class'] . '::' . $fn;
    }
    $file = '';
    if (!empty($f['file'])) {
      $file = $f['file'] . ':' . ($f['line'] ?? '?');
    }
    $via[] = $file ? $fn . ' @ ' . $file : $fn;
  }
  $via_str = implode(' <= ', $via);

  error_log('[OVL:wp_redirect] from=' . $from . ' -> ' . $location . ' status=' . $status . ' via=' . $via_str);
  return $location;
}, 98, 2);


// ---- template_redirect の早い段階で状況を記録 ----
add_action('template_redirect', function () {
  error_log('[OVL:template_redirect:early] path=' . ovl_dbg_path()
    . ' is_singular(property)=' . (int) is_singular('property')
    . ' logged=' . (int) is_user_logged_in());
}, 0); // 早期に実行

add_action('template_redirect', function () {
  error_log('[OVL:template_redirect:late]  path=' . ovl_dbg_path()
    . ' is_singular(property)=' . (int) is_singular('property')
    . ' logged=' . (int) is_user_logged_in());
}, 999); // 後段で再確認

// ---- login_url フィルター経由の呼び出しを記録 ----
add_filter('login_url', function ($login_url, $redirect, $force_reauth) {
  $bt = function_exists('wp_debug_backtrace_summary') ? wp_debug_backtrace_summary(null, 3, true) : 'n/a';
  $redirect_str = is_array($redirect) ? wp_json_encode($redirect) : (string) $redirect;
  error_log('[OVL:login_url] redirect=' . $redirect_str . ' login_url=' . $login_url . ' via=' . $bt);
  return $login_url;
}, 10, 3);

// ---- 使用テンプレートもログに残す ----
add_filter('template_include', function ($template) {
  error_log('[OVL:template_include] path=' . ovl_dbg_path() . ' template=' . $template);
  return $template;
}, 999);
