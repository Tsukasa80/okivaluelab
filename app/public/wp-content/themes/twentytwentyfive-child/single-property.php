<?php
/**
 * Single template for CPT: property
 * 要件:
 * - 未ログイン: タイトル/サムネ/チラ見せ + ログイン/登録導線 + noindex
 * - ログイン: ACF主要型を網羅して自動レンダリング（ギャラリー/画像/ファイル/リピーター/関係/真偽/セレクト/数値など）
 * - 任意: youtube_url / gmap_iframe があれば自動セクション表示
 */

if ( ! defined('ABSPATH') ) exit;

// 未ログイン時はインデックスさせない
if ( ! is_user_logged_in() ) {
  add_action('wp_head', function () {
    echo '<meta name="robots" content="noindex,follow" />' . "\n";
  });
}

/**
 * ACF値を型に応じて整形して出力する簡易レンダラ
 */
function oki_render_acf_value($field) {
  $label = esc_html($field['label']);
  $val   = $field['value'];

  // 空値はスキップ
  if ($val === '' || $val === null || $val === false || $val === []) return '';

  switch ($field['type']) {
    case 'true_false':
      $out = $val ? 'あり' : 'なし';
      return "<p class=\"property-field\"><strong>{$label}：</strong> {$out}</p>";

    case 'select':
    case 'checkbox':
    case 'radio':
      $out = is_array($val) ? implode('、', array_map('esc_html', $val)) : esc_html($val);
      return "<p class=\"property-field\"><strong>{$label}：</strong> {$out}</p>";

    case 'number':
      $num = is_numeric($val) ? number_format_i18n((float)$val) : $val;
      return "<p class=\"property-field\"><strong>{$label}：</strong> ".esc_html($num)."</p>";

    case 'image':
      if (is_array($val) && !empty($val['url'])) {
        $url = esc_url($val['url']);
        $alt = esc_attr($val['alt'] ?? $label);
        return "<div class=\"property-field\"><strong>{$label}：</strong><br><img src=\"{$url}\" alt=\"{$alt}\" style=\"max-width:240px;height:auto;border-radius:8px;margin-top:6px;\" /></div>";
      }
      break;

    case 'gallery':
      if (is_array($val) && $val) {
        $imgs = [];
        foreach ($val as $img) {
          $url = esc_url($img['sizes']['large'] ?? ($img['url'] ?? ''));
          if ($url) $imgs[] = "<figure class=\"gallery-item\"><img src=\"{$url}\" alt=\"\" /></figure>";
        }
        if ($imgs) {
          return "<div class=\"property-field\"><strong>{$label}：</strong><div class=\"gallery-grid\">".implode('', $imgs)."</div></div>";
        }
      }
      break;

    case 'file':
      if (is_array($val) && !empty($val['url'])) {
        $url = esc_url($val['url']);
        $name = esc_html($val['filename'] ?? 'ファイル');
        return "<p class=\"property-field\"><strong>{$label}：</strong> <a href=\"{$url}\" target=\"_blank\" rel=\"noopener\">{$name}</a></p>";
      }
      break;

    case 'relationship':
    case 'post_object':
      $items = is_array($val) ? $val : [$val];
      $links = [];
      foreach ($items as $post_obj) {
        if (empty($post_obj)) continue;
        $links[] = '<a href="'.esc_url(get_permalink($post_obj)).'">'.esc_html(get_the_title($post_obj)).'</a>';
      }
      if ($links) {
        return "<p class=\"property-field\"><strong>{$label}：</strong> ".implode('、', $links)."</p>";
      }
      break;

    case 'repeater':
      if (is_array($val) && $val) {
        $rows = [];
        foreach ($val as $row) {
          $parts = [];
          foreach ($row as $k => $v) {
            if ($v === '' || $v === null) continue;
            $parts[] = esc_html(is_scalar($v) ? $v : (is_array($v) ? implode(' / ', array_map('strval', $v)) : ''));
          }
          if ($parts) $rows[] = '<li>'.implode(' ・ ', $parts).'</li>';
        }
        if ($rows) {
          return "<div class=\"property-field\"><strong>{$label}：</strong><ul class=\"repeater-list\">".implode('', $rows)."</ul></div>";
        }
      }
      break;
  }

  // テキスト/WYSIWYG
  if (is_string($val)) {
    if (strlen(strip_tags($val)) > 120) {
      return "<div class=\"property-field\"><h3 class=\"field-title\">{$label}</h3>".wp_kses_post(wpautop($val))."</div>";
    }
    return "<p class=\"property-field\"><strong>{$label}：</strong> ".esc_html($val)."</p>";
  }

  // 配列その他はJSONで回避
  if (is_array($val)) {
    return "<details class=\"property-field\"><summary><strong>{$label}</strong></summary><pre>".esc_html(wp_json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))."</pre></details>";
  }

  return '';
}

get_header(); ?>

<main id="site-content" class="property-single container" role="main">
<?php while ( have_posts() ) : the_post(); ?>

  <article <?php post_class(); ?>>

    <header class="entry-header">
      <h1 class="entry-title"><?php the_title(); ?></h1>
      <?php if ( has_post_thumbnail() ) : ?>
        <div class="entry-thumbnail">
          <?php the_post_thumbnail( 'large', ['class'=>'property-hero'] ); ?>
        </div>
      <?php endif; ?>
    </header>

    <?php if ( is_user_logged_in() ) : ?>
      <section class="entry-content">
        <?php
        // ACF 全項目を一括表示（優先キー→その他の順）
        if ( function_exists('get_field_objects') ) {
          $fields = get_field_objects();
          if ( $fields ) {
            echo '<div class="property-fields">';

            // まずは優先表示（存在すれば）
            $priority_keys = ['price','address','floor_plan','area_size','building_age'];
            foreach ($priority_keys as $key) {
              if (isset($fields[$key])) {
                if ($key === 'price' && is_numeric($fields[$key]['value'])) {
                  $fields[$key]['type']  = 'number';
                  $fields[$key]['value'] = (float)$fields[$key]['value'];
                }
                echo oki_render_acf_value($fields[$key]);
                unset($fields[$key]); // 二重表示回避
              }
            }

            // 残りを自動レンダ
            foreach ($fields as $field) {
              echo oki_render_acf_value($field);
            }

            echo '</div>';
          }
        }

        // 本文
        if ( get_the_content() ) : ?>
          <hr />
          <div class="property-description">
            <?php the_content(); ?>
          </div>
        <?php endif; ?>

        <?php
        // 任意: YouTube / Google Map（対応フィールドがあれば自動）
        $youtube_url = function_exists('get_field') ? get_field('youtube_url') : '';
        $gmap_iframe = function_exists('get_field') ? get_field('gmap_iframe') : '';
        ?>

        <?php if ( $youtube_url ) : ?>
          <section class="property-video">
            <h2>物件動画</h2>
            <div class="video-embed">
              <?php echo wp_oembed_get( esc_url( $youtube_url ) ); ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ( $gmap_iframe ) : ?>
          <section class="property-map">
            <h2>周辺地図</h2>
            <div class="map-embed">
              <?php
                // 編集者のみが入力する前提でそのまま表示。厳密にするなら src 抽出で再構築。
                echo $gmap_iframe;
              ?>
            </div>
          </section>
        <?php endif; ?>

      </section>

    <?php else : // 未ログイン（チラ見せ） ?>
      <section class="entry-content">
        <?php
          // 非会員向けに、最低限の項目だけチラ見せ（例：市町村/価格帯のメモ）
          if ( function_exists('get_field') ) {
            $city   = get_field('city');             // 例: 市町村だけ
            $price  = get_field('price_range_note'); // 例: 「価格は会員限定（目安：〇〇万円台）」など
            if ( $city )  echo '<p><strong>エリア：</strong>'.esc_html($city).'</p>';
            if ( $price ) echo '<p><strong>価格の目安：</strong>'.esc_html($price).'</p>';
          }
        ?>
        <p>詳しい価格・住所・間取り・図面・収益想定などは<strong>会員限定</strong>で公開しています。</p>

        <p style="margin-top:1rem;">
          <a class="button btn btn-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">ログインして詳細を見る</a>
          &nbsp; / &nbsp;
          <a class="button btn btn-outline" href="<?php echo esc_url( home_url('/member-register/') ); ?>">無料会員登録</a>
        </p>

        <?php if ( has_excerpt() ) : ?>
          <div class="property-excerpt" style="margin-top:1rem;">
            <?php the_excerpt(); ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <footer class="property-footer" style="margin-top:2rem;">
      <a class="btn btn-link" href="<?php echo esc_url( home_url( '/property_list/' ) ); ?>">← 物件一覧へ戻る</a>
    </footer>

  </article>

<?php endwhile; ?>
</main>

<style>
.property-single .property-hero { width:100%; height:auto; border-radius:8px; }
.property-fields { margin-top:.75rem; }
.property-field { margin:.5rem 0; }
.field-title { margin:.75rem 0 .25rem; font-size:1.1rem; }
.gallery-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap:.5rem; }
.gallery-grid img { width:100%; height:auto; border-radius:8px; }
.repeater-list { margin:.25rem 0 .5rem 1rem; }
.property-video .video-embed,
.property-map .map-embed { aspect-ratio:16/9; background:#f5f5f5; border-radius:8px; overflow:hidden; }
.property-map iframe, .property-video iframe { width:100%; height:100%; border:0; }
.btn { display:inline-block; padding:.6rem .9rem; border-radius:8px; text-decoration:none; border:1px solid transparent; }
.btn-primary { background:#0d6efd; color:#fff; }
.btn-outline { background:#fff; border-color:#c9d4ff; color:#0d3efd; }
.btn-link { text-decoration:none; }
</style>

<?php get_footer();
