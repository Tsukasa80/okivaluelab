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

wp_enqueue_script( 'ovl-favorites' );
$ovl_favorite_ids = is_user_logged_in() && function_exists( 'ovl_favorites_get_ids' ) ? ovl_favorites_get_ids() : [];

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
        case 'annual_full_rent':
        case 'annual_rent':
        case 'annual_rent_income':
        case 'annual_scheduled_rent':
        case 'annual_gross_rent':
          $decimals = 0;
          $suffix   = ' 円';
          break;
        case 'yield_gross':
        case 'yield_real':
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

if ( ! function_exists( 'oki_property_append_yen_suffix' ) ) {
  function oki_property_append_yen_suffix( $value, $field_name, $field_label = '' ) {
    if ( '' === $value || '―' === $value ) {
      return $value;
    }
    $yen_fields = [
      'annual_full_rent',
      'annual_rent',
      'annual_rent_income',
      'annual_scheduled_rent',
      'annual_gross_rent',
    ];
    $yen_keywords = [ '年間満室', '満室想定', '年間賃料', '年間家賃' ];
    $label_plain  = preg_replace( '/[\s　]/u', '', (string) $field_label );
    $has_keyword  = false;
    foreach ( $yen_keywords as $keyword ) {
      if ( false !== mb_strpos( $label_plain, $keyword ) ) {
        $has_keyword = true;
        break;
      }
    }
    if ( ( in_array( $field_name, $yen_fields, true ) || $has_keyword ) && false === mb_strpos( $value, '円' ) ) {
      return rtrim( $value ) . ' 円';
    }
    return $value;
  }
}

if ( ! function_exists( 'oki_property_flatten_text_values' ) ) {
  function oki_property_flatten_text_values( $value ) {
    $flattened = [];
    foreach ( (array) $value as $item ) {
      if ( is_array( $item ) ) {
        if ( isset( $item['label'] ) && is_scalar( $item['label'] ) ) {
          $flattened[] = sanitize_text_field( (string) $item['label'] );
          continue;
        }
        if ( isset( $item['value'] ) && is_scalar( $item['value'] ) ) {
          $flattened[] = sanitize_text_field( (string) $item['value'] );
          continue;
        }
        $flattened = array_merge( $flattened, oki_property_flatten_text_values( $item ) );
        continue;
      }
      if ( is_object( $item ) ) {
        if ( method_exists( $item, '__toString' ) ) {
          $flattened[] = sanitize_text_field( (string) $item );
        } else {
          $flattened[] = sanitize_text_field( wp_json_encode( $item ) );
        }
        continue;
      }
      if ( $item === null ) {
        continue;
      }
      $flattened[] = sanitize_text_field( (string) $item );
    }
    return array_values( array_filter( $flattened, static function( $text ) {
      return '' !== $text;
    } ) );
  }
}

if ( ! function_exists( 'oki_property_plain_value' ) ) {
  function oki_property_plain_value( $field, $fallback = '—' ) {
    if ( ! is_array( $field ) || ! array_key_exists( 'value', $field ) ) {
      return $fallback;
    }

    $val  = $field['value'];
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? '';

    if ( $val === '' || $val === null || $val === false ) {
      return $fallback;
    }

    switch ( $field['type'] ?? '' ) {
      case 'true_false':
        return $val ? 'あり' : 'なし';

      case 'select':
      case 'checkbox':
      case 'radio':
        if ( is_array( $val ) ) {
          return implode( ' / ', oki_property_flatten_text_values( $val ) );
        }
        return sanitize_text_field( (string) $val );

      case 'number':
        if ( ! is_numeric( $val ) ) {
          $generic = sanitize_text_field( (string) $val );
          return oki_property_append_yen_suffix( $generic, $name, $label );
        }

        $num      = (float) $val;
        $decimals = 0;
        $suffix   = '';

        switch ( $name ) {
          case 'price':
            $suffix = ' 万円';
            break;
          case 'rent_monthly':
            $suffix = ' 円';
            break;
          case 'annual_full_rent':
          case 'annual_rent':
          case 'annual_rent_income':
          case 'annual_scheduled_rent':
          case 'annual_gross_rent':
            $suffix = ' 円';
            break;
          case 'yield_gross':
          case 'yield_real':
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
            $suffix = '年';
            break;
        }

        $num = number_format_i18n( $num, $decimals );

        if ( 'building_age' === $name ) {
          return '築' . $num . $suffix;
        }

        return oki_property_append_yen_suffix( trim( $num . $suffix ), $name, $label );

      default:
        if ( is_string( $val ) ) {
          $clean = sanitize_text_field( wp_strip_all_tags( $val ) );
          return oki_property_append_yen_suffix( $clean, $name, $label );
        }

        if ( is_array( $val ) ) {
          $joined = implode( ' / ', oki_property_flatten_text_values( $val ) );
          return oki_property_append_yen_suffix( $joined, $name, $label );
        }

        $generic = sanitize_text_field( (string) $val );
        return oki_property_append_yen_suffix( $generic, $name, $label );
    }
  }
}

get_header(); ?>
<?php
// ブロックテーマのヘッダー領域を再現
if ( function_exists( 'do_blocks' ) ) {
  echo do_blocks(
    '<!-- wp:template-part {"slug":"header","area":"header"} /-->'
  );
} elseif ( function_exists( 'block_template_part' ) ) {
  block_template_part( 'header' );
}
?>

<main id="site-content" class="wp-block-group property-single alignwide" role="main">
<?php while ( have_posts() ) : the_post(); ?>
  <?php
  $is_member     = is_user_logged_in();
  $is_favorite   = $is_member && in_array( get_the_ID(), $ovl_favorite_ids, true );
  $current_url   = function_exists( 'ovl_get_current_url' ) ? ovl_get_current_url() : get_permalink();
  $login_url     = add_query_arg( 'redirect_to', rawurlencode( $current_url ), home_url( '/members/' ) );
  $register_url  = home_url( '/member-register/' );
  $field_objects = function_exists( 'get_field_objects' ) ? get_field_objects() : [];
  $field_map     = [];

  if ( $field_objects ) {
    foreach ( $field_objects as $field ) {
      $name = $field['name'] ?? '';
      if ( ! $name ) {
        continue;
      }
      $field_map[ $name ] = $field;
    }
  }

  $field_alias_sources = [
    'yield_gross' => [
      'legacy_key' => 'yield_surface',
      'label'      => '表面利回り（％）',
    ],
    'yield_real'  => [
      'legacy_key' => 'yield_actual',
      'label'      => '実質利回り（％）',
    ],
    'annual_full_rent' => [
      'legacy_key' => 'gross__income',
      'label'      => '年間満室想定賃料',
    ],
    'building_coverage' => [
      'legacy_key' => 'site_coverage',
      'label'      => '建ぺい率',
    ],
    'current_status' => [
      'legacy_key' => 'situation',
      'label'      => '現状',
    ],
    'household_count' => [
      'legacy_key' => 'units',
      'label'      => '世帯数',
    ],
    'land_use' => [
      'legacy_key' => 'zoning',
      'label'      => '用途地域',
    ],
  ];

  if ( $field_map ) {
    foreach ( $field_alias_sources as $primary => $data ) {
      if ( isset( $field_map[ $primary ] ) ) {
        continue;
      }
      $legacy_key = $data['legacy_key'] ?? '';
      if ( ! $legacy_key || ! isset( $field_map[ $legacy_key ] ) ) {
        continue;
      }
      $field_map[ $primary ]         = $field_map[ $legacy_key ];
      $field_map[ $primary ]['name'] = $primary;
      if ( ! empty( $data['label'] ) ) {
        $field_map[ $primary ]['label'] = $data['label'];
      }
      unset( $field_map[ $legacy_key ] );
    }
  }

  $hero_city      = isset( $field_map['address_city'] ) ? oki_property_plain_value( $field_map['address_city'], '' ) : '';
  $hero_address   = isset( $field_map['address_full'] ) ? oki_property_plain_value( $field_map['address_full'], '' ) : '';
  $structure      = isset( $field_map['structure'] ) ? oki_property_plain_value( $field_map['structure'], '' ) : '';
  $price_field    = $field_map['price'] ?? null;
  $price_label    = $price_field['label'] ?? '価格';
  $price_value    = $price_field ? oki_property_plain_value( $price_field, '' ) : '';
  $terms          = get_the_terms( get_the_ID(), 'property_cat' );
  $status_label   = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0]->name : '公開中';
  $is_new         = ( time() - get_post_time( 'U', true ) ) < 14 * DAY_IN_SECONDS;
  $entry_content_raw = get_the_content( null, false );
  $entry_content_clean = $entry_content_raw;
  if ( $entry_content_clean ) {
    $entry_content_clean = preg_replace( '#<figure[^>]*>.*?</figure>#is', '', $entry_content_clean );
    $entry_content_clean = preg_replace( '#<img[^>]*>#i', '', $entry_content_clean );
  }
  $has_entry_content = '' !== trim( wp_strip_all_tags( $entry_content_clean ) );

  $hero_stats = [];
  $stats_keys = [
    'yield_gross',
    'yield_real',
    'rent_monthly',
    'occupancy',
    'building_age',
  ];

  foreach ( $stats_keys as $key ) {
    if ( ! isset( $field_map[ $key ] ) ) {
      continue;
    }
    $value = oki_property_plain_value( $field_map[ $key ], '' );
    if ( '' === $value || '—' === $value ) {
      continue;
    }
    $hero_stats[] = [
      'label' => $field_map[ $key ]['label'] ?? ucwords( str_replace( '_', ' ', $key ) ),
      'value' => $value,
    ];
  }

  $station_primary = '';
  $station_fields  = [ 'station', 'nearest_station', 'station_name' ];
  foreach ( $station_fields as $station_key ) {
    if ( ! isset( $field_map[ $station_key ] ) ) {
      continue;
    }
    $value = oki_property_plain_value( $field_map[ $station_key ], '' );
    if ( '' === $value || '—' === $value ) {
      continue;
    }
    $station_primary = $value;
    break;
  }

  $station_notes  = '';
  $station_note_keys = [ 'access', 'station_access', 'route', 'train_line' ];
  foreach ( $station_note_keys as $note_key ) {
    if ( ! isset( $field_map[ $note_key ] ) ) {
      continue;
    }
    $value = oki_property_plain_value( $field_map[ $note_key ], '' );
    if ( '' === $value || '—' === $value ) {
      continue;
    }
    $station_notes = $value;
    break;
  }
  $station_display = trim( $station_primary . ( $station_notes ? ' ' . $station_notes : '' ) );

  $yield_display = '';
  $yield_keys    = [ 'yield_gross', 'yield_real', 'yield_surface', 'yield_actual' ];
  foreach ( $yield_keys as $yield_key ) {
    if ( ! isset( $field_map[ $yield_key ] ) ) {
      continue;
    }
    $value = oki_property_plain_value( $field_map[ $yield_key ], '' );
    if ( '' === $value || '—' === $value ) {
      continue;
    }
    $yield_display = $value;
    break;
  }

  $building_age_display = isset( $field_map['building_age'] ) ? oki_property_plain_value( $field_map['building_age'], '' ) : '';
  $building_type_display = '';
  $building_type_keys    = [ 'building_type', 'property_type' ];
  foreach ( $building_type_keys as $bt_key ) {
    if ( isset( $field_map[ $bt_key ] ) ) {
      $value = oki_property_plain_value( $field_map[ $bt_key ], '' );
      if ( '' !== $value && '—' !== $value ) {
        $building_type_display = $value;
        break;
      }
    }
  }

  $hero_summary_items = [
    [
      'label'    => $price_label,
      'value'    => $price_value ? $price_value : '—',
      'modifier' => 'price',
      'locked'   => ! $is_member,
    ],
    [
      'label'    => '所在地',
      'value'    => $hero_address ?: '—',
      'modifier' => 'address',
      'locked'   => false,
    ],
    [
      'label'    => isset( $field_map['yield_gross'] ) ? ( $field_map['yield_gross']['label'] ?? '利回り' ) : '利回り',
      'value'    => $yield_display ? $yield_display : '—',
      'modifier' => 'yield',
      'locked'   => ! $is_member,
    ],
    [
      'label'    => isset( $field_map['building_type'] ) ? ( $field_map['building_type']['label'] ?? '建物種別' ) : ( isset( $field_map['property_type'] ) ? ( $field_map['property_type']['label'] ?? '建物種別' ) : '建物種別' ),
      'value'    => $building_type_display ? $building_type_display : '—',
      'modifier' => 'building-type',
      'locked'   => ! $is_member,
    ],
    [
      'label'    => isset( $field_map['building_age'] ) ? ( $field_map['building_age']['label'] ?? '築年数' ) : '築年数',
      'value'    => $building_age_display ? $building_age_display : '—',
      'modifier' => 'age',
      'locked'   => ! $is_member,
    ],
  ];

  $hero_catchphrase = '';

  if ( $field_map ) {
    $catchphrase_keys = [ 'catch_copy', 'catchphrase', 'catch_phrase', 'catchcopy', 'catch' ];
    foreach ( $field_map as $field ) {
      $field_name = $field['name'] ?? '';
      $label      = $field['label'] ?? '';
      $value      = oki_property_plain_value( $field, '' );
      if ( '' === $value || '—' === $value ) {
        continue;
      }
      if ( $field_name && in_array( $field_name, $catchphrase_keys, true ) ) {
        $hero_catchphrase = $value;
        break;
      }
      if ( false !== mb_strpos( (string) $label, 'キャッチコピー' ) ) {
        $hero_catchphrase = $value;
        break;
      }
    }
  }

  $gallery_items = [];
  $gallery_seen  = [];
  if ( has_post_thumbnail() ) {
    $thumb_id  = get_post_thumbnail_id();
    $full_url  = wp_get_attachment_image_url( $thumb_id, 'full' ) ?: wp_get_attachment_image_url( $thumb_id, 'large' );
    $thumb_url = wp_get_attachment_image_url( $thumb_id, 'medium_large' ) ?: wp_get_attachment_image_url( $thumb_id, 'medium' );
    if ( $full_url && ! in_array( $full_url, $gallery_seen, true ) ) {
      $gallery_items[] = [
        'full'  => $full_url,
        'thumb' => $thumb_url ?: $full_url,
        'alt'   => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ?: get_the_title(),
      ];
      $gallery_seen[] = $full_url;
    }
  }

  $gallery_raw = function_exists( 'get_field' ) ? get_field( 'gallery' ) : [];
  if ( is_array( $gallery_raw ) ) {
    foreach ( $gallery_raw as $image ) {
      $full = $image['sizes']['1536x1536'] ?? ( $image['sizes']['large'] ?? ( $image['url'] ?? '' ) );
      if ( ! $full || in_array( $full, $gallery_seen, true ) ) {
        continue;
      }
      $thumb = $image['sizes']['medium_large'] ?? ( $image['sizes']['medium'] ?? $full );
      $gallery_items[] = [
        'full'  => $full,
        'thumb' => $thumb,
        'alt'   => $image['alt'] ?? get_the_title(),
      ];
      $gallery_seen[] = $full;
    }
  }

  $extra_gallery_keys = [ 'og_image', 'og_image2', 'og_image3', 'og_image4', 'og_image5', 'og_image6', 'og_image7', 'og_image8', 'og_image9', 'og_image10', 'og_image105' ];
  foreach ( $extra_gallery_keys as $extra_key ) {
    $extra_value = null;
    if ( isset( $field_map[ $extra_key ] ) ) {
      $extra_value = $field_map[ $extra_key ]['value'] ?? null;
    }
    if ( null === $extra_value && function_exists( 'get_field' ) ) {
      $extra_value = get_field( $extra_key );
    }
    if ( null === $extra_value || '' === $extra_value ) {
      continue;
    }
    if ( isset( $extra_value['url'] ) ) {
      $full = $extra_value['url'];
      $thumb = $extra_value['sizes']['medium_large'] ?? ( $extra_value['sizes']['medium'] ?? $full );
      if ( ! in_array( $full, $gallery_seen, true ) ) {
        $gallery_items[] = [
          'full'  => $full,
          'thumb' => $thumb,
          'alt'   => $extra_value['alt'] ?? get_the_title(),
        ];
        $gallery_seen[] = $full;
      }
    }
  }

  $gallery_count = count( $gallery_items );
  ?>

  <article <?php post_class(); ?>>
    <section class="property-hero">
      <div class="property-hero__layout">
        <div class="property-hero__media">
          <?php if ( $hero_catchphrase && $is_member ) : ?>
            <div class="property-hero__catchcopy">
              <span>物件の魅力</span>
              <p><?php echo esc_html( $hero_catchphrase ); ?></p>
            </div>
          <?php endif; ?>
          <?php if ( $gallery_count ) : ?>
            <div class="property-detail__hero-media">
              <div class="property-detail__carousel" data-property-carousel>
                <div class="property-detail__carousel-main">
                  <?php foreach ( $gallery_items as $index => $item ) : ?>
                    <figure class="property-detail__carousel-slide<?php echo 0 === $index ? ' is-active' : ''; ?>" data-carousel-slide="<?php echo esc_attr( $index ); ?>">
                      <img src="<?php echo esc_url( $item['full'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>" loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>" />
                    </figure>
                  <?php endforeach; ?>
                  <?php if ( $gallery_count > 1 ) : ?>
                    <button class="property-detail__carousel-nav property-detail__carousel-nav--prev" type="button" aria-label="前の画像" data-carousel-prev>‹</button>
                    <button class="property-detail__carousel-nav property-detail__carousel-nav--next" type="button" aria-label="次の画像" data-carousel-next>›</button>
                    <p class="property-detail__carousel-counter"><span data-carousel-current>1</span> / <?php echo esc_html( $gallery_count ); ?></p>
                  <?php endif; ?>
                </div>
                <?php if ( $gallery_count > 1 ) : ?>
                  <div class="property-detail__carousel-thumbs" role="tablist">
                    <?php foreach ( $gallery_items as $index => $item ) : ?>
                      <button class="property-detail__carousel-thumb<?php echo 0 === $index ? ' is-active' : ''; ?>" type="button" data-carousel-thumb="<?php echo esc_attr( $index ); ?>" aria-label="<?php echo esc_attr( sprintf( '画像 %d', $index + 1 ) ); ?>">
                        <img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>" loading="lazy" />
                      </button>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php else : ?>
            <div class="property-hero__media-placeholder">画像はログイン後に表示されます</div>
          <?php endif; ?>
        </div>

        <div class="property-hero__info">
          <div class="property-hero__badges">
            <?php if ( $status_label ) : ?>
              <span class="property-hero__badge"><?php echo esc_html( $status_label ); ?></span>
            <?php endif; ?>
            <?php if ( $is_new ) : ?>
              <span class="property-hero__badge is-new">NEW</span>
            <?php endif; ?>
          </div>
          <h1 class="property-hero__title"><?php the_title(); ?></h1>
          <?php if ( $hero_address ) : ?>
            <p class="property-hero__location">
              <span><?php echo esc_html( $hero_address ); ?></span>
            </p>
          <?php endif; ?>
          <?php if ( $structure ) : ?>
            <p class="property-hero__structure"><?php echo esc_html( $structure ); ?></p>
          <?php endif; ?>

          <div class="property-hero__summary-grid">
            <?php foreach ( $hero_summary_items as $item ) : ?>
              <?php
              $is_locked = ! empty( $item['locked'] );
              $value     = $is_locked ? 'ログイン後に表示' : ( $item['value'] ? $item['value'] : '—' );
              ?>
              <div class="property-hero__summary-item property-hero__summary-item--<?php echo esc_attr( $item['modifier'] ); ?>">
                <span><?php echo esc_html( $item['label'] ); ?></span>
                <strong><?php echo esc_html( $value ); ?></strong>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="property-hero__actions">
            <?php if ( $is_member ) : ?>
              <button
                type="button"
                class="ovl-favorite-button<?php echo $is_favorite ? ' is-active' : ''; ?>"
                data-ovl-fav="1"
                data-property-id="<?php echo esc_attr( get_the_ID() ); ?>"
                aria-pressed="<?php echo $is_favorite ? 'true' : 'false'; ?>"
              >
                <span class="ovl-favorite-button__icon" aria-hidden="true">♡</span>
                <span class="ovl-favorite-button__label"><?php echo $is_favorite ? 'お気に入り済み' : 'お気に入りに追加'; ?></span>
              </button>
            <?php else : ?>
              <button
                type="button"
                class="ovl-favorite-button is-disabled"
                data-ovl-fav="1"
                data-property-id="<?php echo esc_attr( get_the_ID() ); ?>"
                disabled
                aria-disabled="true"
              >
                <span class="ovl-favorite-button__icon" aria-hidden="true">♡</span>
                <span class="ovl-favorite-button__label">ログイン後にお気に入り追加</span>
              </button>
              <p class="property-hero__actions-note">
                <a href="<?php echo esc_url( $login_url ); ?>">ログイン</a> / <a href="<?php echo esc_url( $register_url ); ?>">新規会員登録</a> でお気に入りをご利用いただけます。
              </p>
            <?php endif; ?>
          </div>
          <?php if ( $is_member && $hero_stats ) : ?>
            <ul class="property-hero__stats">
              <?php foreach ( $hero_stats as $stat ) : ?>
                <li>
                  <span><?php echo esc_html( $stat['label'] ); ?></span>
                  <strong><?php echo esc_html( $stat['value'] ); ?></strong>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if ( ! $is_member ) : ?>
            <p class="property-hero__notice">価格や最寄駅などの詳細情報はログイン後にご確認いただけます。</p>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php if ( $is_member ) : ?>
      <?php
      $download_button = do_shortcode( '[ovl_download_button]' );
      $youtube_url     = function_exists( 'get_field' ) ? get_field( 'youtube_url' ) : '';
      $map_lat         = function_exists( 'get_field' ) ? get_field( 'map_lat' ) : '';
      $map_lng         = function_exists( 'get_field' ) ? get_field( 'map_lng' ) : '';
      $summary_ai      = function_exists( 'get_field' ) ? get_field( 'summary_ai' ) : '';
      $pr_text_ai      = function_exists( 'get_field' ) ? get_field( 'pr_text_ai' ) : '';
      $has_sidebar     = ! empty( $download_button );
      ?>
      <section class="entry-content property-entry <?php echo $has_sidebar ? 'has-sidebar' : ''; ?>">
        <div class="property-entry__main">
          <?php if ( $pr_text_ai ) : ?>
            <section class="property-card property-card--summary">
              <h2 class="property-section-title">運営者コメント（メリット／留意点）</h2>
              <div class="property-summary">
                <?php echo wpautop( esc_html( $pr_text_ai ) ); ?>
              </div>
            </section>
          <?php endif; ?>

          <?php
          if ( $field_objects ) {
            $fields_for_render = $field_map;
            $exclude           = [ 'doc_url', 'map_lat', 'map_lng', 'youtube_url', 'og_image', 'gallery', 'summary_ai', 'pr_text_ai', 'tag_json_ai', 'address_city' ];
            foreach ( $exclude as $removed_key ) {
              unset( $fields_for_render[ $removed_key ] );
            }

            $section_definitions = [
              'basic'    => [
                'title'   => '基本情報',
                'comment' => '基本情報',
              'keys'    => [
                  'price',
                  'address_full',
                  'yield_gross',
                  'yield_surface',
                  'yield_real',
                  'yield_actual',
                  'gross__income',
                  'annual_full_rent',
                  'annual_rent',
                  'annual_rent_income',
                  'annual_scheduled_rent',
                  'annual_gross_rent',
                  'rent_monthly',
                  'occupancy',
                  'address_city',
                  'station',
                  'nearest_station',
                  'station_access',
                  'access',
                  'situation',
                  'current_status',
                  'current_condition',
                  'status_current',
                ],
              ],
              'building' => [
                'title'   => '建物情報',
                'comment' => '建物情報',
                'keys'    => [
                  'structure',
                  'building_type',
                  'property_type',
                  'usage_type',
                  'household_count',
                  'units',
                  'number_of_households',
                  'floor_plan',
                  'building_age',
                  'floor_area',
                  'land_area',
                  'stories',
                  'total_units',
                  'completion_year',
                  'completion_date',
                  'construction_company',
                  'renovation',
                  'balcony_area',
                  'direction',
                  'parking',
                ],
              ],
              'equipment' => [
                'title'   => '設備',
                'comment' => '設備',
                'keys'    => [
                  'equipment',
                  'facilities',
                  'amenities',
                  'kitchen',
                  'bathroom',
                  'toilet',
                  'balcony',
                  'elevator',
                  'auto_lock',
                  'security',
                  'internet',
                  'air_conditioner',
                  'heating',
                  'cooling',
                  'lighting',
                  'storage',
                ],
              ],
              'rights'   => [
                'title'   => '権利・法令',
                'comment' => '権利・法令',
                'keys'    => [
                  'land_rights',
                  'land_right',
                  'urban_planning',
                  'city_planning',
                  'land_category',
                  'land_use',
                  'zoning',
                  'land_use_zone',
                  'land_use_district',
                  'road_width',
                  'road_type',
                  'road_direction',
                  'zoning',
                  'building_coverage',
                  'site_coverage',
                  'floor_area_ratio',
                  'national_land_law',
                  'law_restrictions',
                  'setback',
                ],
              ],
            ];

            $ensure_field_object = static function ( $key ) use ( &$field_map, $field_alias_sources ) {
              if ( isset( $field_map[ $key ] ) ) {
                return;
              }
              if ( function_exists( 'get_field_object' ) ) {
                $candidates = [ $key ];
                if ( isset( $field_alias_sources[ $key ]['legacy_key'] ) ) {
                  $candidates[] = $field_alias_sources[ $key ]['legacy_key' ];
                }
                foreach ( $candidates as $candidate_key ) {
                  $field_obj = get_field_object( $candidate_key, get_the_ID(), false, true );
                  if ( ! $field_obj ) {
                    continue;
                  }
                  if ( $candidate_key !== $key ) {
                    $field_obj['name'] = $key;
                    if ( ! empty( $field_alias_sources[ $key ]['label'] ) ) {
                      $field_obj['label'] = $field_alias_sources[ $key ]['label'];
                    }
                  }
                  $field_map[ $key ] = $field_obj;
                  break;
                }
              }
            };

            foreach ( $section_definitions as $config ) {
              foreach ( $config['keys'] as $key ) {
                $ensure_field_object( $key );
              }
            }

            $table_sections = [];
            $yen_force_keys = [ 'annual_full_rent', 'annual_rent', 'annual_rent_income', 'annual_scheduled_rent', 'annual_gross_rent' ];
            $append_yen_suffix = static function ( $value, $key ) use ( $yen_force_keys ) {
              if ( '―' === $value || '' === $value ) {
                return $value;
              }
              if ( in_array( $key, $yen_force_keys, true ) ) {
                if ( false === mb_strpos( $value, '円' ) ) {
                  return rtrim( $value ) . ' 円';
                }
              }
              return $value;
            };

            foreach ( $section_definitions as $slug => $config ) {
              $rows = [];
              foreach ( $config['keys'] as $key ) {
                if ( ! isset( $fields_for_render[ $key ] ) ) {
                  continue;
                }
                $raw_value     = oki_property_plain_value( $fields_for_render[ $key ], '' );
                $display_value = ( '' === $raw_value || '—' === $raw_value ) ? '―' : $raw_value;
                $display_value = $append_yen_suffix( $display_value, $key );
                $rows[] = [
                  'label' => $fields_for_render[ $key ]['label'] ?? ucwords( str_replace( '_', ' ', $key ) ),
                  'value' => $display_value,
                  'key'   => $key,
                ];
                unset( $fields_for_render[ $key ] );
              }
              if ( $rows ) {
                $table_sections[ $slug ] = [
                  'title'   => $config['title'],
                  'comment' => $config['comment'],
                  'rows'    => $rows,
                ];
              }
            }

            $other_rows = [];
            $migrated_right_rows   = [];
            $migrated_building_rows = [];
            $migrated_basic_rows    = [];
            $other_exclude_keys   = [ 'og_image', 'og_image2', 'og_image3', 'og_image4', 'og_image5', 'og_image6', 'og_image7', 'og_image8', 'og_image9', 'og_image10', 'og_image105', 'image_2', 'image_3' ];
            $building_label_terms = [ '建物種別', '物件種別', '建物タイプ' ];
            $basic_label_terms    = [ '年間満室想定賃料', '満室想定賃料', '年間満室', '現状', '現況' ];
            $matches_keywords     = static function ( $haystack, $keywords ) {
              foreach ( $keywords as $keyword ) {
                if ( false !== mb_strpos( $haystack, $keyword ) ) {
                  return true;
                }
              }
              return false;
            };
            foreach ( $fields_for_render as $field ) {
              $raw_value = oki_property_plain_value( $field, '' );
              $value     = ( '' === $raw_value || '—' === $raw_value ) ? '―' : $raw_value;
              $value     = $append_yen_suffix( $value, $field['name'] ?? '' );
              $field_key = $field['name'] ?? '';
              if ( $field_key && in_array( $field_key, $other_exclude_keys, true ) ) {
                continue;
              }
              $label = $field['label'] ?? '項目';
              $label_plain = preg_replace( '/[\s　（）()]/u', '', (string) $label );
              $row_data = [
                'label' => $label,
                'value' => $value,
                'key'   => $field_key,
              ];
              if ( false !== mb_strpos( (string) $label, 'キャッチコピー' ) ) {
                if ( '' === $hero_catchphrase ) {
                  $hero_catchphrase = $row_data['value'];
                }
                continue;
              }
              if ( $matches_keywords( $label_plain, [ '建ぺい率' ] ) ) {
                $migrated_right_rows[] = $row_data;
                continue;
              }
              if ( $matches_keywords( $label_plain, $building_label_terms ) || false !== mb_strpos( $label_plain, '世帯数' ) ) {
                $migrated_building_rows[] = $row_data;
                continue;
              }
              if ( $matches_keywords( $label_plain, $basic_label_terms ) ) {
                $migrated_basic_rows[] = $row_data;
                continue;
              }
              $other_rows[] = $row_data;
            }
            if ( $other_rows ) {
              $table_sections['others'] = [
                'title'   => 'その他',
                'comment' => 'その他',
                'rows'    => $other_rows,
              ];
            }

            if ( $migrated_right_rows ) {
              if ( isset( $table_sections['rights'] ) ) {
                $table_sections['rights']['rows'] = array_merge( $table_sections['rights']['rows'], $migrated_right_rows );
              } else {
                $table_sections['rights'] = [
                  'title'   => '権利・法令',
                  'comment' => '権利・法令',
                  'rows'    => $migrated_right_rows,
                ];
              }
            }

            if ( $migrated_building_rows ) {
              if ( isset( $table_sections['building'] ) ) {
                $table_sections['building']['rows'] = array_merge( $migrated_building_rows, $table_sections['building']['rows'] );
              } else {
                $table_sections['building'] = [
                  'title'   => '建物情報',
                  'comment' => '建物情報',
                  'rows'    => $migrated_building_rows,
                ];
              }
            }

            if ( $migrated_basic_rows ) {
              if ( isset( $table_sections['basic'] ) ) {
                $table_sections['basic']['rows'] = array_merge( $table_sections['basic']['rows'], $migrated_basic_rows );
              } else {
                $table_sections['basic'] = [
                  'title'   => '基本情報',
                  'comment' => '基本情報',
                  'rows'    => $migrated_basic_rows,
                ];
              }
            }

            $reassign_from_others = static function ( $keywords, $target_key, $prepend = false ) use ( &$table_sections, $matches_keywords ) {
              if ( ! isset( $table_sections['others'] ) || empty( $table_sections['others']['rows'] ) ) {
                return;
              }
              $remaining = [];
              $moved     = [];
              foreach ( $table_sections['others']['rows'] as $row ) {
                $label_plain = preg_replace( '/[\s　（）()]/u', '', (string) ( $row['label'] ?? '' ) );
                if ( $matches_keywords( $label_plain, $keywords ) ) {
                  $moved[] = $row;
                } else {
                  $remaining[] = $row;
                }
              }
              if ( ! $moved ) {
                return;
              }

              if ( isset( $table_sections[ $target_key ] ) ) {
                if ( $prepend ) {
                  $table_sections[ $target_key ]['rows'] = array_merge( $moved, $table_sections[ $target_key ]['rows'] );
                } else {
                  $table_sections[ $target_key ]['rows'] = array_merge( $table_sections[ $target_key ]['rows'], $moved );
                }
              } else {
                $section_title = 'basic' === $target_key ? '基本情報' : ( 'building' === $target_key ? '建物情報' : 'その他' );
                $table_sections[ $target_key ] = [
                  'title'   => $section_title,
                  'comment' => $section_title,
                  'rows'    => $moved,
                ];
              }

              $table_sections['others']['rows'] = $remaining;
            };

            $reassign_from_others( $building_label_terms, 'building', true );
            $reassign_from_others( $basic_label_terms, 'basic', false );

            $apply_priority_order = static function ( $rows, $priority ) {
              if ( empty( $rows ) ) {
                return $rows;
              }
              $priority_keys   = $priority['keys'] ?? [];
              $priority_labels = $priority['labels'] ?? [];
              $scored          = [];
              foreach ( $rows as $index => $row ) {
                $score   = 1000 + $index;
                $row_key = $row['key'] ?? '';
                if ( $row_key && isset( $priority_keys[ $row_key ] ) ) {
                  $score = $priority_keys[ $row_key ];
                } else {
                  $label_plain = preg_replace( '/[\s　]/u', '', (string) ( $row['label'] ?? '' ) );
                  foreach ( $priority_labels as $needle => $value ) {
                    if ( false !== mb_strpos( $label_plain, $needle ) ) {
                      $score = $value;
                      break;
                    }
                  }
                }
                $scored[] = [
                  'score' => $score,
                  'index' => $index,
                  'row'   => $row,
                ];
              }
              usort(
                $scored,
                static function ( $a, $b ) {
                  if ( $a['score'] === $b['score'] ) {
                    return $a['index'] <=> $b['index'];
                  }
                  return $a['score'] <=> $b['score'];
                }
              );
              return array_map(
                static function ( $item ) {
                  return $item['row'];
                },
                $scored
              );
            };

            if ( isset( $table_sections['basic'] ) ) {
              $basic_priority = [
                'keys'   => [
                  'price'               => 10,
                  'address_full'        => 20,
                  'station'             => 30,
                  'nearest_station'     => 40,
                  'station_access'      => 50,
                  'access'              => 60,
                  'yield_gross'         => 70,
                  'yield_real'          => 80,
                  'annual_full_rent'    => 90,
                  'annual_rent'         => 90,
                  'annual_rent_income'  => 90,
                  'annual_scheduled_rent' => 90,
                  'annual_gross_rent'   => 90,
                  'rent_monthly'        => 100,
                  'occupancy'           => 110,
                  'current_status'      => 120,
                  'current_condition'   => 120,
                  'status_current'      => 120,
                ],
                'labels' => [
                  '年間満室' => 90,
                  '満室想定' => 90,
                  '想定月額' => 100,
                  '月額家賃' => 100,
                  '稼働率'   => 110,
                  '現況'     => 120,
                  '現状'     => 120,
                ],
              ];
              $table_sections['basic']['rows'] = $apply_priority_order( $table_sections['basic']['rows'], $basic_priority );
            }
            if ( isset( $table_sections['building'] ) ) {
              $building_priority = [
                'keys'   => [
                  'structure'            => 10,
                  'building_type'        => 20,
                  'property_type'        => 20,
                  'building_age'         => 30,
                  'completion_year'      => 30,
                  'completion_date'      => 30,
                  'floor_plan'           => 40,
                  'household_count'      => 50,
                  'number_of_households' => 50,
                  'parking'              => 60,
                  'land_area'            => 70,
                  'floor_area'           => 80,
                ],
                'labels' => [
                  '構造'     => 10,
                  '建物種別' => 20,
                  '物件種別' => 20,
                  '築年月'   => 30,
                  '築年'     => 30,
                  '間取り'   => 40,
                  '世帯数'   => 50,
                  '戸数'     => 50,
                  '駐車場'   => 60,
                  '駐輪場'   => 60,
                  '土地面積' => 70,
                  '延床面積' => 80,
                ],
              ];
              $table_sections['building']['rows'] = $apply_priority_order( $table_sections['building']['rows'], $building_priority );
            }
            if ( isset( $table_sections['rights'] ) ) {
              $rights_priority = [
                'keys'   => [
                  'urban_planning'   => 10,
                  'city_planning'    => 10,
                  'land_use_zone'    => 20,
                  'land_use'         => 20,
                  'building_coverage'=> 30,
                  'floor_area_ratio' => 40,
                  'road_width'       => 50,
                ],
                'labels' => [
                  '都市計画' => 10,
                  '市街化'   => 10,
                  '用途地域' => 20,
                  '建ぺい率' => 30,
                  '容積率'   => 40,
                  '接道幅員' => 50,
                ],
              ];
              $table_sections['rights']['rows'] = $apply_priority_order( $table_sections['rights']['rows'], $rights_priority );
            }

            if ( $table_sections ) {
              $single_column_keys = [ 'remarks', 'remark', 'note', 'notes', 'memo', 'message', 'comment', 'comments', 'property_comment', 'other', 'others', 'tag_json_ai' ];
              $should_single_row  = static function ( $row ) use ( $single_column_keys ) {
                $row_key = $row['key'] ?? '';
                if ( $row_key && in_array( $row_key, $single_column_keys, true ) ) {
                  return true;
                }
                $value = is_scalar( $row['value'] ) ? (string) $row['value'] : '';
                return mb_strlen( wp_strip_all_tags( $value ) ) > 60;
              };

              echo '<section class="property-card property-table-card">';
              echo '<h2 class="property-section-title">物件詳細</h2>';
              echo '<div class="property-table-wrapper">';
              echo '<table class="property-table"><tbody>';
              foreach ( $table_sections as $section ) {
                echo '<!-- ' . esc_html( $section['comment'] ) . ' -->';
                echo '<tr class="property-table__section">';
                echo '<th colspan="4">' . esc_html( $section['title'] ) . '</th>';
                echo '</tr>';

                $rows = array_values( $section['rows'] );
                $count = count( $rows );
                $index = 0;
                while ( $index < $count ) {
                  $current = $rows[ $index ];
                  $is_single_current = $should_single_row( $current );

                  if ( $is_single_current ) {
                    echo '<tr class="property-table__row property-table__row--single">';
                    echo '<th>' . esc_html( $current['label'] ) . '</th>';
                    echo '<td colspan="3">' . esc_html( $current['value'] ) . '</td>';
                    echo '</tr>';
                    $index++;
                    continue;
                  }

                  $next = $rows[ $index + 1 ] ?? null;
                  if ( $next && ! $should_single_row( $next ) ) {
                    echo '<tr class="property-table__row">';
                    echo '<th>' . esc_html( $current['label'] ) . '</th>';
                    echo '<td>' . esc_html( $current['value'] ) . '</td>';
                    echo '<th>' . esc_html( $next['label'] ) . '</th>';
                    echo '<td>' . esc_html( $next['value'] ) . '</td>';
                    echo '</tr>';
                    $index += 2;
                  } else {
                    echo '<tr class="property-table__row property-table__row--single">';
                    echo '<th>' . esc_html( $current['label'] ) . '</th>';
                    echo '<td colspan="3">' . esc_html( $current['value'] ) . '</td>';
                    echo '</tr>';
                    $index++;
                  }
                }
              }
              echo '</tbody></table>';
              echo '</div>';
              echo '</section>';
            } else {
              echo '<p class="property-field notice">物件フィールドに値が保存されていません。投稿を更新してからご確認ください。</p>';
            }
          } else {
            echo '<p class="property-field notice">物件フィールドに値が保存されていません。投稿を更新してからご確認ください。</p>';
          }
          ?>

          <?php if ( $summary_ai || $has_entry_content ) : ?>
            <section class="property-card">
              <h2 class="property-section-title">備考・コメント</h2>
              <?php if ( $summary_ai ) : ?>
                <div class="property-summary property-summary--inline">
                  <div class="property-summary__body">
                    <?php echo wpautop( esc_html( $summary_ai ) ); ?>
                  </div>
                </div>
              <?php endif; ?>
              <?php if ( $has_entry_content ) : ?>
                <div class="property-description">
                  <?php echo apply_filters( 'the_content', $entry_content_clean ); ?>
                </div>
              <?php endif; ?>
            </section>
          <?php endif; ?>

          <?php if ( $youtube_url ) : ?>
            <section class="property-card property-card--media property-video">
              <h2 class="property-section-title">物件動画</h2>
              <div class="video-embed">
                <?php echo wp_oembed_get( esc_url( $youtube_url ) ); ?>
              </div>
            </section>
          <?php endif; ?>

          <?php
          $lat = is_numeric( $map_lat ) ? (float) $map_lat : null;
          $lng = is_numeric( $map_lng ) ? (float) $map_lng : null;
          if ( $lat !== null && $lng !== null ) :
            $maps_embed = sprintf(
              'https://www.google.com/maps?q=%1$s,%2$s&output=embed',
              rawurlencode( (string) $lat ),
              rawurlencode( (string) $lng )
            );
            ?>
            <section class="property-card property-card--media property-map">
              <h2 class="property-section-title">周辺地図</h2>
              <div class="map-embed">
                <iframe src="<?php echo esc_url( $maps_embed ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
              </div>
            </section>
          <?php endif; ?>
        </div>

        <?php if ( $has_sidebar ) : ?>
          <aside class="property-entry__side">
            <section id="property-download" class="property-card property-card--sidebar">
              <h2 class="property-section-title">▼ 物件資料はここから ▼</h2>
              <?php echo $download_button; ?>
            </section>
          </aside>
        <?php endif; ?>
      </section>
    <?php else : ?>
      <section class="entry-content property-guest">
        <?php
        if ( function_exists( 'get_field' ) ) {
          $city    = get_field( 'address_city' );
          $summary = get_field( 'summary_ai' );
          if ( $city ) {
            echo '<p class="property-guest__meta"><strong>エリア：</strong>' . esc_html( $city ) . '</p>';
          }
          echo '<p class="property-guest__meta"><strong>価格：</strong>ログイン後に表示されます。</p>';
          if ( $summary ) {
            echo '<div class="property-summary">' . wpautop( esc_html( $summary ) ) . '</div>';
          }
        }
        ?>
        <p class="property-guest__lead">詳しい価格・住所・間取り・図面・収益想定などは<strong>会員限定</strong>で公開しています。</p>
        <div class="property-guest__actions">
          <a class="button btn btn-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">ログインして詳細を見る</a>
          <a class="button btn btn-outline" href="<?php echo esc_url( home_url( '/member-register/' ) ); ?>">無料会員登録</a>
        </div>
        <?php if ( has_excerpt() ) : ?>
          <div class="property-excerpt">
            <?php the_excerpt(); ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <footer class="property-footer">
      <a class="btn btn-outline property-footer__back" href="<?php echo esc_url( home_url( '/property_list/' ) ); ?>">← 物件一覧へ戻る</a>
    </footer>
  </article>
<?php endwhile; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-property-carousel]').forEach(function (carousel) {
    var slides = carousel.querySelectorAll('[data-carousel-slide]');
    if (!slides.length) return;
    var current = 0;
    var total = slides.length;
    var counter = carousel.querySelector('[data-carousel-current]');
    var prev = carousel.querySelector('[data-carousel-prev]');
    var next = carousel.querySelector('[data-carousel-next]');
    var thumbs = carousel.querySelectorAll('[data-carousel-thumb]');

    var setActive = function (index) {
      current = (index + total) % total;
      slides.forEach(function (slide) {
        slide.classList.toggle('is-active', Number(slide.dataset.carouselSlide) === current);
      });
      thumbs.forEach(function (thumb) {
        thumb.classList.toggle('is-active', Number(thumb.dataset.carouselThumb) === current);
      });
      if (counter) {
        counter.textContent = current + 1;
      }
    };

    if (prev) {
      prev.addEventListener('click', function () {
        setActive(current - 1);
      });
    }
    if (next) {
      next.addEventListener('click', function () {
        setActive(current + 1);
      });
    }
    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        var target = Number(thumb.dataset.carouselThumb);
        if (!Number.isNaN(target)) {
          setActive(target);
        }
      });
    });
  });
});
</script>

<?php
if ( function_exists( 'do_blocks' ) ) {
  echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
} elseif ( function_exists( 'block_template_part' ) ) {
  block_template_part( 'footer' );
}
get_footer();
