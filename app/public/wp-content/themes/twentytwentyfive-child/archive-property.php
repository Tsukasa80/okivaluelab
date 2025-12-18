<?php
/* HIT: archive-property.php (routeA) */
get_header();
?>
<main id="site-content" class="container" role="main">
  <header class="page-header">
    <h1 class="page-title">物件一覧</h1>
  </header>

  <?php if ( have_posts() ) : ?>
    <div class="property-archive-grid">
      <?php
      while ( have_posts() ) :
        the_post();
        $pid   = get_the_ID();
        $title = get_the_title($pid);

        // -------- 画像フォールバック（アイキャッチ → ACF → 添付）--------
        $img = '';
        // 1) アイキャッチ
        if ( $thumb_id = get_post_thumbnail_id($pid) ) {
          $img = wp_get_attachment_image_url($thumb_id, 'medium');
        }
        // 2) ACF画像（フィールド名は必要に応じて追記）
        if ( ! $img && function_exists('get_field') ) {
          foreach ( ['main_image','image','thumbnail'] as $acf_key ) {
            $acf_img = get_field($acf_key, $pid);
            if ( is_array($acf_img) && ! empty($acf_img['sizes']['medium']) ) { $img = $acf_img['sizes']['medium']; break; }
            if ( is_string($acf_img) && $acf_img ) { $img = $acf_img; break; }
          }
        }
        // 3) 添付画像（最初の1枚）
        if ( ! $img ) {
          $attachments = get_attached_media('image', $pid);
          if ( $attachments ) {
            $first = array_shift($attachments);
            $img   = wp_get_attachment_image_url($first->ID, 'medium');
          }
        }
        // -------------------------------------------------------------------
        ?>
        <article <?php post_class('property-card'); ?> aria-labelledby="title-<?php echo esc_attr($pid); ?>">
          <figure class="property-thumb">
            <?php if ( $img ) : ?>
              <?php if ( is_user_logged_in() ) : ?>
                <a href="<?php echo esc_url( get_permalink($pid) ); ?>">
                  <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" decoding="async">
                </a>
              <?php else : ?>
                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" decoding="async">
              <?php endif; ?>
            <?php endif; ?>
          </figure>

          <h2 id="title-<?php echo esc_attr($pid); ?>" class="entry-title">
            <?php
            if ( is_user_logged_in() ) {
              echo '<a href="' . esc_url( get_permalink($pid) ) . '">' . esc_html($title) . '</a>';
            } else {
              echo esc_html($title);
            }
            ?>
          </h2>

          <?php if ( is_user_logged_in() ): ?>
            <div class="price">
              <?php
                $raw = function_exists('get_field') ? get_field('price', $pid) : '';
                $num = is_numeric($raw) ? (int)$raw : null;
                echo $num !== null ? number_format($num) . '円' : esc_html($raw);
              ?>
            </div>
          <?php else: ?>
            <div class="price is-obfuscated">ログインで価格を表示</div>
          <?php endif; ?>

          <?php
          // 本文・抜粋はアーカイブでは表示しない（WP-Members置換と競合させない）
          ?>
        </article>
      <?php endwhile; ?>
    </div>

    <?php
    // ページネーション（必要に応じてテーマ関数に置き換え可）
    the_posts_pagination([
      'mid_size'  => 1,
      'prev_text' => '«',
      'next_text' => '»',
    ]);
    ?>

  <?php else : ?>
    <p>現在、公開中の物件はありません。</p>
  <?php endif; ?>
</main>
<?php
get_footer();
