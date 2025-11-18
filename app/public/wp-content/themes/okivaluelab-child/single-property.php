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
  $name  = $field['name'] ?? '';

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
      if (!is_numeric($val)) {
        return "<p class=\"property-field\"><strong>{$label}：</strong> ".esc_html($val)."</p>";
      }

      $num      = (float) $val;
      $decimals = 0;
      $suffix   = '';

      switch ($name) {
        case 'price':
          $decimals = 0;
          $suffix   = ' 万円';
          break;
        case 'rent_monthly':
          $decimals = 0;
          $suffix   = ' 円';
          break;
        case 'yield_surface':
        case 'yield_actual':
        case 'occupancy':
          $decimals = 1;
          $suffix   = ' %';
          break;
        case 'floor_area':
        case 'land_area':
          $decimals = 2;
          $suffix   = ' ㎡';
          break;
        case 'road_width':
          $decimals = 1;
          $suffix   = ' m';
          break;
        case 'building_age':
          $decimals = 0;
          $suffix   = '年';
          break;
      }

      $num = number_format_i18n($num, $decimals);

      // 築年は表記を少し変える
      if ($name === 'building_age') {
        return "<p class=\"property-field\"><strong>{$label}：</strong> 築{$num}{$suffix}</p>";
      }

      return "<p class=\"property-field\"><strong>{$label}：</strong> ".esc_html($num . $suffix)."</p>";

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
    if ($name === 'tag_json_ai') {
      return "<details class=\"property-field\"><summary><strong>{$label}</strong></summary><pre>".esc_html($val)."</pre></details>";
    }
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
<?php
// ブロックテーマのヘッダー領域を再現
if ( function_exists( 'do_blocks' ) ) {
  echo do_blocks(
    '<!-- wp:template-part {"slug":"header-admin","area":"header"} /-->'
    . '<!-- wp:template-part {"slug":"header-member","area":"header"} /-->'
    . '<!-- wp:template-part {"slug":"header-guest","area":"header"} /-->'
  );
} elseif ( function_exists( 'block_template_part' ) ) {
  block_template_part( 'header-admin' );
  block_template_part( 'header-member' );
  block_template_part( 'header-guest' );
}
?>

<main id="site-content" class="wp-block-group property-single alignwide" role="main">
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
            // 表示対象外にしたいフィールドを除外
            $exclude = ['doc_url', 'map_lat', 'map_lng', 'youtube_url', 'og_image'];
            foreach ($exclude as $removed_key) {
              unset($fields[$removed_key]);
            }

            echo '<div class="property-fields">';

            // まずは優先表示（存在すれば）
            $priority_keys = [
              'address_city',
              'address_full',
              'price',
              'yield_surface',
              'yield_actual',
              'structure',
              'building_age',
              'floor_area',
              'land_area',
              'road_width',
              'zoning',
              'rent_monthly',
              'occupancy',
            ];
            foreach ($priority_keys as $key) {
              if (isset($fields[$key])) {
                echo oki_render_acf_value($fields[$key]);
                unset($fields[$key]); // 二重表示回避
              }
            }

            // 残りを自動レンダ
            foreach ($fields as $field) {
              echo oki_render_acf_value($field);
            }

            echo '</div>';
          } else {
            echo '<p class="property-field notice">物件フィールドに値が保存されていません。投稿を更新してからご確認ください。</p>';
          }
        }

        $download_button = do_shortcode('[ovl_download_button]');
        if ( $download_button ) :
        ?>
          <section class="property-download">
            <h2>資料ダウンロード</h2>
            <?php echo $download_button; ?>
          </section>
        <?php endif; ?>

        <?php
        // 本文
        if ( get_the_content() ) :
        ?>
          <hr />
          <div class="property-description">
            <?php the_content(); ?>
          </div>
        <?php endif; ?>

        <?php
        // 任意: YouTube / Google Map（対応フィールドがあれば自動）
        $youtube_url = function_exists('get_field') ? get_field('youtube_url') : '';
        $map_lat     = function_exists('get_field') ? get_field('map_lat') : '';
        $map_lng     = function_exists('get_field') ? get_field('map_lng') : '';
        ?>

        <?php if ( $youtube_url ) : ?>
          <section class="property-video">
            <h2>物件動画</h2>
            <div class="video-embed">
              <?php echo wp_oembed_get( esc_url( $youtube_url ) ); ?>
            </div>
          </section>
        <?php endif; ?>

        <?php
        $lat = is_numeric($map_lat) ? (float) $map_lat : null;
        $lng = is_numeric($map_lng) ? (float) $map_lng : null;
        if ( $lat !== null && $lng !== null ) :
          $maps_embed = sprintf(
            'https://www.google.com/maps?q=%1$s,%2$s&output=embed',
            rawurlencode((string) $lat),
            rawurlencode((string) $lng)
          );
        ?>
          <section class="property-map">
            <h2>周辺地図</h2>
            <div class="map-embed">
              <iframe src="<?php echo esc_url( $maps_embed ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
          </section>
        <?php endif; ?>

      </section>

    <?php else : // 未ログイン（チラ見せ） ?>
      <section class="entry-content">
        <?php
          // 非会員向けの最低限情報
          if ( function_exists('get_field') ) {
            $city    = get_field('address_city');
            $summary = get_field('summary_ai');
            if ( $city ) {
              echo '<p><strong>エリア：</strong>' . esc_html($city) . '</p>';
            }
            echo '<p><strong>価格：</strong>ログイン後に表示されます。</p>';
            if ( $summary ) {
              echo '<div class="property-summary">' . wpautop( esc_html( $summary ) ) . '</div>';
            }
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
.property-download { margin:1.5rem 0; }
.property-download a { margin-top:.5rem; display:inline-block; }
.property-video .video-embed,
.property-map .map-embed { aspect-ratio:16/9; background:#f5f5f5; border-radius:8px; overflow:hidden; }
.property-map iframe, .property-video iframe { width:100%; height:100%; border:0; }
.property-summary { margin-top:1rem; }
.btn { display:inline-block; padding:.6rem .9rem; border-radius:8px; text-decoration:none; border:1px solid transparent; }
.btn-primary { background:#0d6efd; color:#fff; }
.btn-outline { background:#fff; border-color:#c9d4ff; color:#0d3efd; }
.btn-link { text-decoration:none; }
</style>

<?php
if ( function_exists( 'do_blocks' ) ) {
  echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
} elseif ( function_exists( 'block_template_part' ) ) {
  block_template_part( 'footer' );
}
get_footer();
