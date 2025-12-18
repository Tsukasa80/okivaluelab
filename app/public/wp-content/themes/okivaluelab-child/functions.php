<?php
	
// 親→子の順でCSSを読み込み
add_action('wp_enqueue_scripts', function () {
  $parent = 'twenty-twenty-five-style';
  wp_enqueue_style($parent, get_template_directory_uri() . '/style.css', [], null);
  wp_enqueue_style('okivaluelab-child-style', get_stylesheet_uri(), [$parent], filemtime(get_stylesheet_directory() . '/style.css'));

  // お気に入り用スクリプトを登録（必要なページで各テンプレートからenqueue）
  $favorites_script = get_stylesheet_directory() . '/assets/js/ovl-favorites.js';
  if ( file_exists( $favorites_script ) ) {
    wp_register_script(
      'ovl-favorites',
      get_stylesheet_directory_uri() . '/assets/js/ovl-favorites.js',
      [],
      filemtime( $favorites_script ),
      true
    );

    $current_url  = function_exists( 'ovl_get_current_url' ) ? ovl_get_current_url() : home_url();
    $login_url    = add_query_arg( 'redirect_to', rawurlencode( $current_url ), home_url( '/members/' ) );
    $register_url = home_url( '/member-register/' );

    wp_localize_script(
      'ovl-favorites',
      'ovlFavoritesData',
      [
        'restUrl'      => esc_url_raw( rest_url( 'ovl/v1/favorites' ) ),
        'toggleUrl'    => esc_url_raw( rest_url( 'ovl/v1/favorites/toggle' ) ),
        'nonce'        => wp_create_nonce( 'wp_rest' ),
        'isLoggedIn'   => is_user_logged_in(),
        'favoriteIds'  => is_user_logged_in() ? ovl_favorites_get_ids( get_current_user_id() ) : [],
        'loginUrl'     => $login_url,
        'registerUrl'  => $register_url,
      ]
    );
  }

  if ( is_singular( 'property' ) ) {
    wp_enqueue_script( 'ovl-favorites' );
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
  if (!is_user_logged_in()) return '';
  $u = wp_get_current_user();
  return esc_html($u->display_name ?: $u->user_login);
});

require_once get_stylesheet_directory() . '/inc/shortcodes-property.php';

// CPT:property だけはクラシックPHPテンプレートを強制使用（従来レイアウト優先）
add_action('template_redirect', function () {
  if (!is_singular('property')) {
    return;
  }

  $template = locate_template('single-property.php');
  if (! $template) {
    return;
  }

  include $template;
  exit;
});

add_action(
  'init',
  function () {
    $block_dir = get_stylesheet_directory() . '/blocks/property-meta';
    if ( file_exists( $block_dir . '/block.json' ) && ! WP_Block_Type_Registry::get_instance()->is_registered( 'okivaluelab/property-meta' ) ) {
      register_block_type( $block_dir );
    }
  }
);


//コンポーネント
function ovl_register_codex_patterns() {
    register_block_pattern(
        'okivaluelab/codex-hero-sample',
        [
        'title'       => 'フロントページヒーローセクション',
        'description' => 'ヒーロー＋セクション',
        'categories'  => [ 'layout' ],
        'content'     => <<<HTML

<!-- wp:group {"tagName":"section","className":"front-hero section","layout":{"type":"constrained"}} -->
<section class="wp-block-group front-hero section">
  <div class="front-hero__inner">
    <div class="front-hero__copy">
      <!-- wp:paragraph {"className":"front-hero__eyebrow"} -->
      <p class="front-hero__eyebrow">Invest in Okinawa</p>
      <!-- /wp:paragraph -->

      <!-- wp:heading {"level":1,"className":"front-hero__title"} -->
      <h1 class="wp-block-heading front-hero__title">沖縄なら、不動産投資を長く安心して持ち続けられる</h1>
      <!-- /wp:heading -->

      <!-- wp:paragraph {"className":"front-hero__text"} -->
      <p class="front-hero__text">都市型の洗練とリゾートの伸びしろを併せ持つ沖縄中南部エリア。地元密着チームがキャッシュフロー設計から出口戦略まで伴走し、グラフィック主体の資料でも伝わる滑らかなUXを届けます。</p>
      <!-- /wp:paragraph -->

      <!-- wp:buttons {"className":"front-hero__actions"} -->
      <div class="wp-block-buttons front-hero__actions">
        <!-- wp:button {"className":"is-style-fill front-hero__action"} -->
        <div class="wp-block-button is-style-fill front-hero__action"><a class="wp-block-button__link" href="/consult/">無料オンライン相談</a></div>
        <!-- /wp:button -->

        <!-- wp:button {"className":"is-style-outline front-hero__action"} -->
        <div class="wp-block-button is-style-outline front-hero__action"><a class="wp-block-button__link" href="/member-register/">資料ダウンロード</a></div>
        <!-- /wp:button -->
      </div>
      <!-- /wp:buttons -->

      <ul class="front-hero__stats">
        <li>
          <span>会員投資家</span>
          <strong>3,200+</strong>
          <small>Monthly Active</small>
        </li>
        <li>
          <span>平均運用期間</span>
          <strong>8.4年</strong>
          <small>Long Term Hold</small>
        </li>
        <li>
          <span>掲載成約率</span>
          <strong>92%</strong>
          <small>Screened Assets</small>
        </li>
      </ul>
    </div>

    <div class="front-hero__media">
      <div class="front-hero__media-graphic" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
      </div>
      <p class="front-hero__media-caption">グラフィックやベクター素材を重ねても映えるリビングホワイトの余白設計。</p>
    </div>
  </div>
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"front-feature section","layout":{"type":"constrained"}} -->
<section class="wp-block-group front-feature section">
  <div class="front-feature__intro">
    <!-- wp:paragraph {"className":"front-eyebrow"} -->
    <p class="front-eyebrow">Why OkiValueLab</p>
    <!-- /wp:paragraph -->

    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">沖縄投資を、もっとシンプルで分かりやすく。</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>都市データ・観光需要・自衛隊・物流など、沖縄ならではの定性的/定量的指標を一枚のダッシュボードに集約。ベクターアイコンやイラストで要点をひと目で理解できます。</p>
    <!-- /wp:paragraph -->
  </div>

  <div class="front-feature__grid">
    <article class="front-feature__card">
      <h3>即時レポーティング</h3>
      <p>会員サイトではKPIダッシュボードとPDFが自動生成され、物件の健全性を視覚的に把握。</p>
      <a href="/members/" class="front-feature__link">会員限定ダッシュボード</a>
    </article>
    <article class="front-feature__card">
      <h3>立地×人流データ</h3>
      <p>人流ヒートマップや航空需要データを重ねたベクター地図で、将来性を客観評価。</p>
      <a href="/property_list/" class="front-feature__link">公開中の物件を見る</a>
    </article>
    <article class="front-feature__card">
      <h3>専門チームの伴走</h3>
      <p>建築士・税理士・金融の専門家がチームで伴走。チャットUIでクイックに相談。</p>
      <a href="/consult/" class="front-feature__link">無料相談を予約</a>
    </article>
  </div>
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"front-property section","layout":{"type":"constrained"}} -->
<section class="wp-block-group front-property section">
  <div class="front-property__header">
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">ピックアップ物件</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>非公開データは会員サイトでご案内しています。公開範囲から一部抜粋。</p>
    <!-- /wp:paragraph -->
  </div>

  <div class="front-property__cards">
    <article class="front-property__card">
      <p class="front-property__city">那覇市前島</p>
      <h3>モノレール徒歩3分の再生レジデンス</h3>
      <ul>
        <li>想定利回り 4.6%</li>
        <li>価格 4,380万円</li>
        <li>RC造 / 1LDK × 6戸</li>
      </ul>
      <a class="front-property__link" href="/property_list/">詳細を見る</a>
    </article>
    <article class="front-property__card">
      <p class="front-property__city">浦添市牧港</p>
      <h3>港湾アクセス×法人需要のミニオフィス</h3>
      <ul>
        <li>想定利回り 5.1%</li>
        <li>価格 6,800万円</li>
        <li>S造 / 区画貸し</li>
      </ul>
      <a class="front-property__link" href="/property_list/">詳細を見る</a>
    </article>
    <article class="front-property__card">
      <p class="front-property__city">豊見城市豊崎</p>
      <h3>リゾート併設SOHO</h3>
      <ul>
        <li>想定利回り 4.1%</li>
        <li>価格 5,240万円</li>
        <li>RC造 / SOHO仕様</li>
      </ul>
      <a class="front-property__link" href="/property_list/">詳細を見る</a>
    </article>
  </div>
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"front-cta section","layout":{"type":"constrained"}} -->
<section class="wp-block-group front-cta section">
  <div class="front-cta__inner">
    <div>
      <!-- wp:paragraph {"className":"front-eyebrow"} -->
      <p class="front-eyebrow">Get Started</p>
      <!-- /wp:paragraph -->
      <!-- wp:heading {"level":2} -->
      <h2 class="wp-block-heading">沖縄投資の「次の一手」を10分で相談。</h2>
      <!-- /wp:heading -->
      <!-- wp:paragraph -->
      <p>RenosyのようなミニマルUIを意識した資料テンプレもご用意。ベクターグラフィック素材を差し替えるだけで自社色に染められます。</p>
      <!-- /wp:paragraph -->
    </div>

    <!-- wp:buttons {"className":"front-cta__actions"} -->
    <div class="wp-block-buttons front-cta__actions">
      <!-- wp:button {"className":"is-style-fill"} -->
      <div class="wp-block-button is-style-fill"><a class="wp-block-button__link" href="/consult/">10分で相談予約</a></div>
      <!-- /wp:button -->
      <!-- wp:button {"className":"is-style-outline"} -->
      <div class="wp-block-button is-style-outline"><a class="wp-block-button__link" href="/member-register/">会員登録</a></div>
      <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
  </div>
</section>
<!-- /wp:group -->

HTML
        ]
    );
}
add_action( 'init', 'ovl_register_codex_patterns' );
