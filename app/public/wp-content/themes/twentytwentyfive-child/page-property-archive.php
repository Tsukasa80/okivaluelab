<?php
/**
 * Template Name: Property Archive Page
 * Template Post Type: page
 */
get_header(); ?>

<main id="site-content" class="container" role="main">
  <header class="page-header">
    <h1 class="page-title"><?php the_title(); ?></h1>
  </header>

  <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
    <div class="page-intro entry-content">
      <?php the_content(); ?>
    </div>
  <?php endwhile; endif; ?>

  <?php
  // ページ番号（固定ページは paged/page 両睨み）
  $paged = max(1, intval(get_query_var('paged')), intval(get_query_var('page')));
  $q = new WP_Query([
    'post_type'      => 'property',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
  ]);
  ?>

  <?php if ( $q->have_posts() ) : ?>
    <div class="property-archive-grid">
      <?php while ( $q->have_posts() ) : $q->the_post(); ?>
        <article <?php post_class('property-card'); ?>>
          <h2 class="entry-title">
            <?php if ( is_user_logged_in() ) : ?>
              <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            <?php else : ?>
              <?php the_title(); ?>
            <?php endif; ?>
          </h2>
          <?php if ( is_user_logged_in() && function_exists('get_field') ) :
            $price = get_field('price');
            if ( $price !== '' && $price !== null ) {
              $n = is_numeric($price) ? number_format((int)$price) . '円' : esc_html($price);
              echo '<p class="price">価格：' . $n . '</p>';
            }
          else : ?>
            <p class="price is-obfuscated">ログインで価格を表示</p>
          <?php endif; ?>
        </article>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>

    <?php
      $big  = 999999999;
      $base = str_replace($big, '%#%', esc_url(get_pagenum_link($big)));
      echo '<nav class="pagination">';
      echo paginate_links([
        'base'      => $base,
        'format'    => 'page/%#%/',
        'current'   => $paged,
        'total'     => $q->max_num_pages,
        'mid_size'  => 1,
        'prev_text' => '«',
        'next_text' => '»',
      ]);
      echo '</nav>';
    ?>
  <?php else : ?>
    <p>現在、公開中の物件はありません。</p>
  <?php endif; ?>
</main>

<?php get_footer();
