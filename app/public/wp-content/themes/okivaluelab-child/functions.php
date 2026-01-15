<?php

// 親→子の順でCSSを読み込み
add_action('wp_enqueue_scripts', function () {
  $parent = 'twenty-twenty-five-style';
  wp_enqueue_style($parent, get_template_directory_uri() . '/style.css', [], null);
  wp_enqueue_style('okivaluelab-child-style', get_stylesheet_uri(), [$parent], filemtime(get_stylesheet_directory() . '/style.css'));

  // ヘッダー表示修正用CSS
  wp_enqueue_style(
    'vlab-header-fix',
    get_stylesheet_directory_uri() . '/assets/css/header-fix.css',
    ['okivaluelab-child-style'],
    filemtime(get_stylesheet_directory() . '/assets/css/header-fix.css')
  );

  // コラム記事（投稿）: 読みやすい1カラム用CSS
  $column_css = get_stylesheet_directory() . '/assets/css/column-article.css';
  if (file_exists($column_css) && is_singular('post')) {
    wp_enqueue_style(
      'ovl-column-article',
      get_stylesheet_directory_uri() . '/assets/css/column-article.css',
      ['okivaluelab-child-style'],
      filemtime($column_css)
    );

    $toc_script = get_stylesheet_directory() . '/assets/js/ovl-toc.js';
    if (file_exists($toc_script)) {
      wp_enqueue_script(
        'ovl-toc',
        get_stylesheet_directory_uri() . '/assets/js/ovl-toc.js',
        [],
        filemtime($toc_script),
        true
      );
    }

    $featured_blur_script = get_stylesheet_directory() . '/assets/js/ovl-featured-blur.js';
    if (file_exists($featured_blur_script)) {
      wp_enqueue_script(
        'ovl-featured-blur',
        get_stylesheet_directory_uri() . '/assets/js/ovl-featured-blur.js',
        [],
        filemtime($featured_blur_script),
        true
      );
    }
  }

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
    // single-property.php では header を do_blocks で出しているため、ナビのviewモジュールが wp_head 後に enqueue されがち。
    // 先に読み込んでおくと、モバイルのハンバーガー（responsive navigation）が動く。
    if (function_exists('wp_enqueue_script_module')) {
      wp_enqueue_script_module('@wordpress/block-library/navigation/view');
    } elseif (function_exists('wp_script_is') && wp_script_is('wp-navigation', 'registered')) {
      wp_enqueue_script('wp-navigation');
    }

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

function ovl_is_local_env() {
  $env = defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : '';
  if ($env) {
    return in_array($env, ['local', 'development'], true);
  }

  $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
  if (!$host) {
    return false;
  }

  if ($host === 'localhost') {
    return true;
  }

  $local_suffixes = ['.local', '.test', '.localhost'];
  foreach ($local_suffixes as $suffix) {
    if (substr($host, -strlen($suffix)) === $suffix) {
      return true;
    }
  }

  return false;
}

function ovl_render_post_list( $per_page ) {
  $per_page = (int) $per_page;
  if ( $per_page <= 0 ) {
    $per_page = 12;
  }

  // カテゴリチップは自動生成（増やしてもコード改修不要）。
  $terms = get_terms(
    [
      'taxonomy'   => 'category',
      'hide_empty' => false,
      'orderby'    => 'name',
      'order'      => 'ASC',
    ]
  );

  $chips = [ '' => 'すべて' ];
  $allowed_category_slugs = [];

  if ( ! is_wp_error( $terms ) ) {
    foreach ( $terms as $term ) {
      if ( ! $term || empty( $term->slug ) ) {
        continue;
      }

      // 「未分類」は表示しない（必要なら後で解除できます）。
      if ( 'uncategorized' === $term->slug || '未分類' === $term->name ) {
        continue;
      }

      $chips[ $term->slug ] = $term->name;
      $allowed_category_slugs[] = $term->slug;
    }
  }

  $selected_category = isset( $_GET['ovl_cat'] ) ? sanitize_title( wp_unslash( $_GET['ovl_cat'] ) ) : '';
  $selected_category = in_array( $selected_category, $allowed_category_slugs, true ) ? $selected_category : '';

  $paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

  $query_args = [
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
  ];

  if ( $selected_category ) {
    $query_args['category_name'] = $selected_category;
  }

  $post_query = new WP_Query( $query_args );

  $page_id  = (int) get_queried_object_id();
  $base_url = $page_id ? get_permalink( $page_id ) : home_url( '/' );

  ob_start();
  ?>
  <div class="ovl-post-list__block">
    <nav class="ovl-post-list__chips" aria-label="<?php echo esc_attr__( 'カテゴリー', 'okivaluelab-child' ); ?>">
      <?php foreach ( $chips as $slug => $label ) : ?>
        <?php
        $url = $slug ? add_query_arg( 'ovl_cat', $slug, $base_url ) : $base_url;
        $is_current = ( $slug === $selected_category ) || ( '' === $slug && '' === $selected_category );
        ?>
        <a class="ovl-chip <?php echo $is_current ? 'is-current' : ''; ?>" href="<?php echo esc_url( $url ); ?>"<?php echo $is_current ? ' aria-current="page"' : ''; ?>>
          <?php echo esc_html( $label ); ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <?php if ( $post_query->have_posts() ) : ?>
      <div class="ovl-post-grid" role="list">
        <?php
        while ( $post_query->have_posts() ) :
          $post_query->the_post();

          $categories = get_the_category();
          $category_name = $categories ? $categories[0]->name : '';

          $title_raw = (string) get_the_title();
          $title = trim( wp_strip_all_tags( $title_raw ) );
          $title = ( '' !== $title ) ? $title : __( '（無題）', 'okivaluelab-child' );

          $thumbnail_id = (int) get_post_thumbnail_id( get_the_ID() );
          $thumb_html = $thumbnail_id ? wp_get_attachment_image(
            $thumbnail_id,
            'medium_large',
            false,
            [
              'loading' => 'lazy',
            ]
          ) : '';
          $has_thumb = '' !== trim( (string) $thumb_html );
          ?>
          <a class="ovl-post-card <?php echo $has_thumb ? 'has-thumb' : 'has-no-thumb'; ?>" href="<?php echo esc_url( get_permalink() ); ?>" role="listitem" aria-label="<?php echo esc_attr( $title ); ?>">
            <span class="ovl-post-card__thumb" aria-hidden="true">
              <span class="ovl-post-card__thumb-inner">
                <?php
                if ( $has_thumb ) {
                  echo $thumb_html;
                } else {
                  echo '<span class="ovl-post-card__placeholder">NO IMAGE</span>';
                }
                ?>
              </span>
            </span>

            <span class="ovl-post-card__meta">
              <?php if ( $category_name ) : ?>
                <span class="ovl-post-card__cat"><?php echo esc_html( $category_name ); ?></span>
              <?php endif; ?>
              <time class="ovl-post-card__date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
                <?php echo esc_html( get_the_date() ); ?>
              </time>
            </span>

            <span class="ovl-post-card__title"><?php echo esc_html( $title ); ?></span>
          </a>
        <?php endwhile; ?>
      </div>

      <nav class="ovl-post-list__pager" aria-label="<?php echo esc_attr__( 'ページ送り', 'okivaluelab-child' ); ?>">
        <?php
        $big  = 999999999;
        $base = str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) );
        echo paginate_links(
          [
            'base'      => $base,
            'format'    => 'page/%#%/',
            'current'   => $paged,
            'total'     => (int) $post_query->max_num_pages,
            'mid_size'  => 1,
            'prev_text' => '前へ',
            'next_text' => '次へ',
            'add_args'  => $selected_category ? [ 'ovl_cat' => $selected_category ] : false,
          ]
        );
        ?>
      </nav>
    <?php else : ?>
      <p><?php echo esc_html__( '該当する投稿がありません。', 'okivaluelab-child' ); ?></p>
    <?php endif; ?>
  </div>
  <?php

  wp_reset_postdata();
  return (string) ob_get_clean();
}

add_shortcode( 'ovl_post_list', function ( $atts ) {
  $atts = shortcode_atts(
    [
      'per_page' => 12,
    ],
    $atts,
    'ovl_post_list'
  );

  return ovl_render_post_list( $atts['per_page'] );
} );

add_action( 'init', function () {
  register_block_type(
    'ovl/post-list',
    [
      'api_version' => 2,
      'attributes'  => [
        'perPage' => [
          'type'    => 'number',
          'default' => 12,
        ],
      ],
      'render_callback' => function ( $attributes ) {
        $per_page = isset( $attributes['perPage'] ) ? (int) $attributes['perPage'] : 12;
        return ovl_render_post_list( $per_page );
      },
    ]
  );
} );

add_filter('body_class', function ($classes) {
  if (ovl_is_local_env()) {
    $classes[] = 'ovl-env-local';
  }
  return $classes;
});

// 個別投稿のカテゴリリンクは投稿一覧（/post-list/）の絞り込みURLに向ける
add_filter('render_block', function ($block_content, $block) {
  if (!is_singular('post')) {
    return $block_content;
  }

  if (empty($block['blockName']) || $block['blockName'] !== 'core/post-terms') {
    return $block_content;
  }

  $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
  if (($attrs['term'] ?? '') !== 'category') {
    return $block_content;
  }

  // single.html 側のカテゴリ表示（ovl-post-single__cats）のみ差し替える
  $class_name = (string) ($attrs['className'] ?? '');
  if (strpos($class_name, 'ovl-post-single__cats') === false) {
    return $block_content;
  }

  $terms = get_the_terms(get_the_ID(), 'category');
  if (empty($terms) || is_wp_error($terms)) {
    return $block_content;
  }

  $base_url = home_url('/post-list/');
  $links = [];

  foreach ($terms as $term) {
    if (empty($term->slug) || empty($term->name)) {
      continue;
    }

    $url = add_query_arg('ovl_cat', $term->slug, $base_url);
    $links[] = '<a href="' . esc_url($url) . '">' . esc_html($term->name) . '</a>';
  }

  if (!$links) {
    return $block_content;
  }

  $wrapper_classes = 'wp-block-post-terms ' . esc_attr($class_name);
  return '<div class="' . $wrapper_classes . '">' . implode("\n", $links) . '</div>';
}, 9, 2);

add_action('wp_body_open', function () {
  if (!ovl_is_local_env()) {
    return;
  }
  echo '<div class="ovl-env-badge" role="status" aria-label="Local environment">LOCAL</div>';
});

add_action('admin_bar_menu', function ($wp_admin_bar) {
  if (!ovl_is_local_env()) {
    return;
  }

  $wp_admin_bar->add_node([
    'id' => 'ovl-env-local',
    'title' => 'LOCAL',
    'meta' => [
      'class' => 'ovl-env-badge-admin',
      'title' => 'Local environment',
    ],
  ]);
}, 100);

add_action('admin_enqueue_scripts', function () {
  if (!ovl_is_local_env()) {
    return;
  }

  wp_register_style('ovl-env-admin-badge', false);
  wp_enqueue_style('ovl-env-admin-badge');
  wp_add_inline_style('ovl-env-admin-badge', '
#wpadminbar #wp-admin-bar-ovl-env-local > .ab-item {
  background: #f97316;
  color: #ffffff;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  padding: 0 10px;
}
#wpadminbar #wp-admin-bar-ovl-env-local > .ab-item:hover {
  background: #ea580c;
  color: #ffffff;
}
');
});

// ブロックテーマでのサムネ等（必要に応じて）
add_action('after_setup_theme', function () {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails', ['post', 'page', 'property']);
  add_image_size('property_card', 600, 400, true); // 物件カード向けサイズ

  // エディター側も近い見た目に寄せる（読みやすいコラム記事）
  add_theme_support('editor-styles');
  add_editor_style('assets/css/editor-column-article.css');
});

// Gutenbergで選べる「補足」スタイル（Group/Quoteに付与）
add_action('init', function () {
  if (!function_exists('register_block_style')) {
    return;
  }

  register_block_style(
    'core/group',
    [
      'name'  => 'c-note',
      'label' => '補足（Note）',
    ]
  );

  register_block_style(
    'core/group',
    [
      'name'  => 'c-tip',
      'label' => 'ポイント（Tip）',
    ]
  );

  register_block_style(
    'core/quote',
    [
      'name'  => 'c-quote',
      'label' => '引用（Quote）',
    ]
  );
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

// /category/{slug}/ は投稿一覧（/post-list/）の絞り込みに統一（SEO/導線の二重化対策）
add_action('template_redirect', function () {
  if (!is_category()) {
    return;
  }

  $term = get_queried_object();
  if (!$term || empty($term->slug)) {
    return;
  }

  $url = add_query_arg('ovl_cat', $term->slug, home_url('/post-list/'));
  wp_safe_redirect($url, 301);
  exit;
}, 1);

add_action(
  'init',
  function () {
    $block_dir = get_stylesheet_directory() . '/blocks/property-meta';
    if (file_exists($block_dir . '/block.json') && !WP_Block_Type_Registry::get_instance()->is_registered('okivaluelab/property-meta')) {
      register_block_type($block_dir);
    }
  }
);

// コラム記事（投稿）専用のbody class
add_filter('body_class', function ( $classes ) {
  if (is_singular('post')) {
    $classes[] = 'is-column-article';
  }
  return $classes;
});

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

// /profile/ のインラインSVG内に混入する <br> を除去して崩れを防ぐ
function ovl_strip_svg_breaks($content)
{
  return preg_replace_callback(
    '/<svg\\b[^>]*>.*?<\\/svg>/is',
    function ($matches) {
      return preg_replace('/<\\/?br\\s*\\/?\\s*>/i', '', $matches[0]);
    },
    $content
  );
}

add_filter('the_content', function ($content) {
  if (!is_page('profile')) {
    return $content;
  }

  return ovl_strip_svg_breaks($content);
}, 20);

add_filter('render_block', function ($block_content) {
  if (!is_page('profile')) {
    return $block_content;
  }

  return ovl_strip_svg_breaks($block_content);
}, 20);

/**
 * WP-Members: 会員登録フォームに「プライバシーポリシー同意」チェックを追加し、未同意なら登録を止める
 */
function ovl_wpmem_register_privacy_consent_html()
{
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

  $error = '<div id="ovl-register-error" class="ovl-register-error" role="alert" hidden>プライバシーポリシーの同意にチェックをお願いします。</div>';
  return $button_html['reset'] . ovl_wpmem_register_privacy_consent_html() . $submit . $error;
}, 10, 3);

add_filter('wpmem_default_text', function ($text) {
  if (!is_page('member-register') && !is_page('member-peregister') && !is_page(46) && !is_page('members') && !is_page(52)) {
    return $text;
  }

  $text['login_heading'] = 'ご登録済みの会員さまはこちら';
  $text['login_username'] = 'メールアドレス';

  return $text;
});

add_action('wpmem_pre_register_data', function ($post_data) {
  global $wpmem_themsg;

  if ($wpmem_themsg) {
    return;
  }

  if (empty($_POST['ovl_privacy_consent'])) {
    $wpmem_themsg = '<div><strong>プライバシーポリシーの同意にチェックをお願いします。</strong></div>';
  }
});

add_filter('wpmem_msg_defaults', function ($defaults, $tag, $dialogs) {
  $success_tags = [
    'success',
    'editsuccess',
    'pwdchangesuccess',
    'pwdresetsuccess',
  ];
  $error_tags = [
    'user',
    'email',
    'pwdchangerr',
    'pwdreseterr',
    'loginfailed',
  ];

  $variant = '';
  if (in_array($tag, $success_tags, true)) {
    $variant = 'wpmem_msg--success';
  } elseif (in_array($tag, $error_tags, true)) {
    $variant = 'wpmem_msg--error';
  }

  if ($variant) {
    if (strpos($defaults['div_before'], 'class=') !== false) {
      $defaults['div_before'] = preg_replace(
        '/class="([^"]*)"/',
        'class="$1 ' . $variant . '"',
        $defaults['div_before'],
        1
      );
    } else {
      $defaults['div_before'] = str_replace(
        '<div',
        '<div class="' . $variant . '"',
        $defaults['div_before']
      );
    }
  }

  return $defaults;
}, 10, 3);

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
