<?php

// 親→子の順でCSSを読み込み
add_action('wp_enqueue_scripts', function () {
  $parent = 'twenty-twenty-five-style';
  wp_enqueue_style($parent, get_template_directory_uri() . '/style.css', [], null);
  wp_enqueue_style('okivaluelab-child-style', get_stylesheet_uri(), [$parent], filemtime(get_stylesheet_directory() . '/style.css'));

  // お気に入り用スクリプトを登録（必要なページで各テンプレートからenqueue）
  $favorites_script = get_stylesheet_directory() . '/assets/js/ovl-favorites.js';
  if (file_exists($favorites_script)) {
    wp_register_script(
      'ovl-favorites',
      get_stylesheet_directory_uri() . '/assets/js/ovl-favorites.js',
      [],
      filemtime($favorites_script),
      true
    );

    $current_url = function_exists('ovl_get_current_url') ? ovl_get_current_url() : home_url();
    $login_url = add_query_arg('redirect_to', rawurlencode($current_url), home_url('/members/'));
    $register_url = home_url('/member-register/');

    wp_localize_script(
      'ovl-favorites',
      'ovlFavoritesData',
      [
        'restUrl' => esc_url_raw(rest_url('ovl/v1/favorites')),
        'toggleUrl' => esc_url_raw(rest_url('ovl/v1/favorites/toggle')),
        'nonce' => wp_create_nonce('wp_rest'),
        'isLoggedIn' => is_user_logged_in(),
        'favoriteIds' => is_user_logged_in() ? ovl_favorites_get_ids(get_current_user_id()) : [],
        'loginUrl' => $login_url,
        'registerUrl' => $register_url,
      ]
    );
  }

  if (is_singular('property')) {
    wp_enqueue_script('ovl-favorites');
  }

  $cta_script = get_stylesheet_directory() . '/assets/js/ovl-cta-links.js';
  if (file_exists($cta_script) && (is_front_page() || is_page(260))) {
    wp_enqueue_script(
      'ovl-cta-links',
      get_stylesheet_directory_uri() . '/assets/js/ovl-cta-links.js',
      [],
      filemtime($cta_script),
      true
    );
  }

  $privacy_modal_script = get_stylesheet_directory() . '/assets/js/ovl-privacy-modal.js';
  if (file_exists($privacy_modal_script) && (is_page('member-register') || is_page(46))) {
    wp_enqueue_script(
      'ovl-privacy-modal',
      get_stylesheet_directory_uri() . '/assets/js/ovl-privacy-modal.js',
      [],
      filemtime($privacy_modal_script),
      true
    );

    $privacy_page_id = (int) get_option('wp_page_for_privacy_policy');
    $privacy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
    if (!$privacy_url) {
      $privacy_url = home_url('/privacy-policy/');
    }

    wp_localize_script(
      'ovl-privacy-modal',
      'ovlPrivacyModalData',
      [
        'privacyUrl' => esc_url_raw($privacy_url),
        'restUrl' => $privacy_page_id ? esc_url_raw(rest_url('wp/v2/pages/' . $privacy_page_id . '?_fields=title,content')) : '',
      ]
    );
  }

});

// ブロックテーマでのサムネ等（必要に応じて）
add_action('after_setup_theme', function () {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails', ['post', 'page', 'property']);
  add_image_size('property_card', 600, 400, true); // 物件カード向けサイズ
});

// ユーザー名を出す最小ショートコード（5章で使用）
add_shortcode('ovl_username', function () {
  if (!is_user_logged_in()) {
    return '';
  }
  $u = wp_get_current_user();
  return esc_html($u->display_name ?: $u->user_login);
});

require_once get_stylesheet_directory() . '/inc/shortcodes-property.php';

add_filter(
  'wpmem_username_link_str',
  function ($str, $link, $tag) {
    return '';
  },
  10,
  3
);

add_filter(
  'wpmem_fields',
  function ($fields, $tag, $form) {
    if ('register' !== $tag) {
      return $fields;
    }
    if (!is_page('member-register') && !is_page(46)) {
      return $fields;
    }
    if (isset($fields['user_email'])) {
      $fields['user_email']['label'] = 'メールアドレス';
    }
    return $fields;
  },
  10,
  3
);

// CPT:property だけはクラシックPHPテンプレートを強制使用（従来レイアウト優先）
add_action('template_redirect', function () {
  if (!is_singular('property')) {
    return;
  }

  $template = locate_template('single-property.php');
  if (!$template) {
    return;
  }

  include $template;
  exit;
});

add_action(
  'init',
  function () {
    $block_dir = get_stylesheet_directory() . '/blocks/property-meta';
    if (file_exists($block_dir . '/block.json') && !WP_Block_Type_Registry::get_instance()->is_registered('okivaluelab/property-meta')) {
      register_block_type($block_dir);
    }
  }
);

/**
 * 投資ページの「Reason 05」の直前に見出しを挿入する
 */
add_filter('the_content', function ($content) {
  if (is_page('chunanbu-investment') || is_page('okinawa-investment')) {
    $title_html = '<h2 id="okinawa-investment-extra-title">追加メリット（+3）</h2>';
    $reason_label = '<div class="ovl-accordion-label">Reason 05</div>';

    // 既にタイトルが存在しないか確認
    if (strpos($content, 'id="okinawa-investment-extra-title"') === false) {
      // Reason 05 を含む details/summary 構造の「直前」に挿入を試みる
      // detailsタグの直前に挿入することで、アコーディオンの外側にタイトルを配置する
      if (strpos($content, $reason_label) === false) {
        return $content;
      }

      // シンプルに Reason 05 が最初に出現する details の手前に挿入
      $parts = explode($reason_label, $content, 2);
      if (count($parts) < 2) {
        return $content;
      }

      $before_reason = $parts[0];
      $after_reason = $parts[1];

      // Reason 05 の直近の開始タグ <details を探す
      $last_details_pos = strrpos($before_reason, '<details');
      if ($last_details_pos === false) {
        return $content;
      }

      $content = substr($before_reason, 0, $last_details_pos)
        . $title_html
        . substr($before_reason, $last_details_pos)
        . $reason_label
        . $after_reason;
    }
  }
  return $content;
}, 20);

/**
 * WP-Members: 会員登録フォームに「プライバシーポリシー同意」チェックを追加し、未同意なら登録を止める
 */
function ovl_wpmem_register_privacy_consent_html() {
  $privacy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
  if (!$privacy_url) {
    $privacy_url = home_url('/privacy-policy/');
  }

  $checked = !empty($_POST['ovl_privacy_consent']) ? ' checked' : '';

  return '<div class="ovl-wpmem-consent">'
    . '<label class="ovl-wpmem-consent__label">'
    . '<input type="checkbox" id="ovl-privacy-consent" name="ovl_privacy_consent" value="1"' . $checked . ' /> '
    . '<span> <a class="ovl-privacy-modal-trigger" href="' . esc_url($privacy_url) . '" aria-haspopup="dialog">プライバシーポリシー</a>への同意が必要です。<br />チェックを入れてから登録してください。</span>'
    . '</label>'
    . '</div>';
}

add_filter('wpmem_register_form_buttons', function ($buttons, $tag, $button_html) {
  if ($tag !== 'new') {
    return $buttons;
  }

  $submit = $button_html['submit'];
  if (strpos($submit, 'id="ovl-register-btn"') === false) {
    $submit = preg_replace('/<input\\b/', '<input id="ovl-register-btn"', $submit, 1);
  }

  $error = '<div id="ovl-register-error" class="ovl-register-error" role="alert" hidden>プライバシーの同意にチェックをお願いします。</div>';
  return $button_html['reset'] . ovl_wpmem_register_privacy_consent_html() . $submit . $error;
}, 10, 3);

add_action('wpmem_pre_register_data', function ($post_data) {
  global $wpmem_themsg;

  if ($wpmem_themsg) {
    return;
  }

  if (empty($_POST['ovl_privacy_consent'])) {
    $wpmem_themsg = '<div><strong>プライバシーポリシーへの同意が必要です。</strong></div><div>チェックを入れてから登録してください。</div>';
  }
});

add_filter('wpmem_register_form_args', function ($defaults, $tag, $id) {
  if ($tag !== 'new') {
    return $defaults;
  }

  $defaults['req_label_before'] = '';
  $defaults['req_label'] = '';
  $defaults['req_label_after'] = '';

  return $defaults;
}, 10, 3);

add_filter('wpmem_register_form_rows', function ($rows, $tag) {
  if ($tag !== 'new') {
    return $rows;
  }

  // ユーザー名は自動生成するため、入力欄を表示しない
  if (isset($rows['username'])) {
    unset($rows['username']);
  }

  $name_order_pairs = [
    ['last_name', 'first_name'],
    ['billing_last_name', 'billing_first_name'],
  ];

  foreach ($name_order_pairs as [$last_key, $first_key]) {
    if (!array_key_exists($last_key, $rows) || !array_key_exists($first_key, $rows)) {
      continue;
    }

    $keys = array_keys($rows);
    $first_pos = array_search($first_key, $keys, true);
    $last_pos = array_search($last_key, $keys, true);
    if ($first_pos === false || $last_pos === false) {
      continue;
    }

    if ($last_pos < $first_pos) {
      break;
    }

    $insert_at = min($first_pos, $last_pos);
    $last_row = $rows[$last_key];
    $first_row = $rows[$first_key];

    unset($rows[$last_key], $rows[$first_key]);

    $rebuilt = [];
    $i = 0;
    foreach ($rows as $key => $value) {
      if ($i === $insert_at) {
        $rebuilt[$last_key] = $last_row;
        $rebuilt[$first_key] = $first_row;
      }
      $rebuilt[$key] = $value;
      $i++;
    }

    if ($insert_at >= $i) {
      $rebuilt[$last_key] = $last_row;
      $rebuilt[$first_key] = $first_row;
    }

    $rows = $rebuilt;
    break;
  }

  $label_map = [
    'billing_phone' => '携帯電話番号（任意）',
    'phone' => '携帯電話番号（任意）',
  ];

  foreach ($label_map as $meta_key => $label_text) {
    if (empty($rows[$meta_key]['label'])) {
      continue;
    }

    if (preg_match('/^<label([^>]*)>.*<\\/label>$/', $rows[$meta_key]['label'], $matches)) {
      $rows[$meta_key]['label'] = '<label' . $matches[1] . '>' . esc_html($label_text) . '</label>';
    }
  }

  $first_key = array_key_first($rows);
  if (!$first_key || empty($rows[$first_key]['label'])) {
    return $rows;
  }

  $required_label = function_exists('wpmem_get_text') ? wpmem_get_text('register_required') : '';
  if (!$required_label) {
    $required_label = '*必須項目';
  }

  $note = '<div class="ovl-wpmem-required-note">' . wp_kses($required_label, ['span' => ['class' => true]]) . '</div>';
  $rows[$first_key]['label'] = $note . $rows[$first_key]['label'];

  return $rows;
}, 10, 2);

/**
 * ヘッダーのモバイルメニュー内にも「マイページ/ログアウト」を表示する
 * - ブロックナビ（core/navigation）の中身に li を差し込む
 * - デスクトップは既存の別表示（header-auth）を使うため、CSSで差し込み分を隠す
 */
add_filter('render_block', function ($block_content, $block) {
  if (empty($block['blockName']) || $block['blockName'] !== 'core/navigation') {
    return $block_content;
  }

  $aria_label = $block['attrs']['ariaLabel'] ?? '';
  if ($aria_label !== 'Primary Navigation (Member)') {
    return $block_content;
  }

  if (!is_user_logged_in()) {
    return $block_content;
  }

  if (strpos($block_content, 'ovl-nav-auth-item') !== false) {
    return $block_content;
  }

  $mypage_url = home_url('/members/');
  $logout_url = wp_logout_url(home_url('/'));

  $items_html = ''
    . '<li class="wp-block-navigation-item wp-block-navigation-link ovl-nav-auth-item nav-icon nav-icon--mypage">'
    . '<a class="wp-block-navigation-item__content" href="' . esc_url($mypage_url) . '">'
    . '<span class="wp-block-navigation-item__label">マイページ</span>'
    . '</a>'
    . '</li>'
    . '<li class="wp-block-navigation-item wp-block-navigation-link ovl-nav-auth-item nav-icon nav-icon--logout">'
    . '<a class="wp-block-navigation-item__content" href="' . esc_url($logout_url) . '">'
    . '<span class="wp-block-navigation-item__label">ログアウト</span>'
    . '</a>'
    . '</li>';

  $insert_pos = strripos($block_content, '</ul>');
  if ($insert_pos === false) {
    return $block_content;
  }

  return substr_replace($block_content, $items_html . '</ul>', $insert_pos, strlen('</ul>'));
}, 20, 2);

/**
 * WP-Members: ユーザー名を入力させず、メールアドレスから自動生成する
 */
add_filter('wpmem_pre_validate_form', function ($post_data, $tag) {
  if ($tag !== 'register') {
    return $post_data;
  }

  if (!empty($post_data['username'])) {
    return $post_data;
  }

  $email = isset($post_data['user_email']) ? sanitize_email($post_data['user_email']) : '';
  if (!$email || !is_email($email)) {
    return $post_data;
  }

  $local = strstr($email, '@', true);
  $base = sanitize_user($local ?: 'user', true);
  if ($base === '') {
    $base = 'user';
  }

  $candidate = $base;
  $i = 0;
  while (true) {
    $is_available = true;

    if (is_multisite() && function_exists('wpmu_validate_user_signup')) {
      $result = wpmu_validate_user_signup($candidate, $email);
      if (!empty($result['errors']) && $result['errors']->get_error_messages()) {
        $is_available = false;
      }
    } else {
      if (username_exists($candidate)) {
        $is_available = false;
      }
    }

    if ($is_available) {
      break;
    }

    $i++;
    $suffix = (string) $i;
    $max_base_len = max(1, 60 - strlen($suffix));
    $candidate = substr($base, 0, $max_base_len) . $suffix;

    if ($i > 9999) {
      break;
    }
  }

  $post_data['username'] = $candidate;
  return $post_data;
}, 10, 2);
