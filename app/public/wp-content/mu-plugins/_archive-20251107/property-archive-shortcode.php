<?php
/**
 * Plugin Name: OkiValueLab - Property List Shortcode (with Price Filter)
 * Description: [property_archive] 物件一覧（価格フィルタ＋GET保持＋ページネーション＋会員/非会員の出し分け）
 * Author: OkiValueLab
 * Version: 1.1.1
 */

if ( ! defined('ABSPATH') ) exit;

add_shortcode('property_archive', function () {

	// ====== 入力値の取得・サニタイズ ======
	$min_price = isset($_GET['min_price']) ? preg_replace('/\D+/', '', $_GET['min_price']) : '';
	$max_price = isset($_GET['max_price']) ? preg_replace('/\D+/', '', $_GET['max_price']) : '';

	// ページ番号（ブロックテーマでも安定）
	$paged = get_query_var('paged') ? intval(get_query_var('paged')) : 1;
	if ( $paged < 1 && get_query_var('page') ) { // page クエリ対策
		$paged = intval(get_query_var('page'));
	}
	if ( $paged < 1 && isset($_GET['paged']) ) {
		$paged = max(1, intval($_GET['paged']));
	}

	// ====== WP_Query（価格フィルタ）======
	$meta_query = ['relation' => 'AND'];

	if ( $min_price !== '' ) {
		$meta_query[] = [
			'key'     => 'price',
			'value'   => (int) $min_price,
			'type'    => 'NUMERIC',
			'compare' => '>='
		];
	}
	if ( $max_price !== '' ) {
		$meta_query[] = [
			'key'     => 'price',
			'value'   => (int) $max_price,
			'type'    => 'NUMERIC',
			'compare' => '<='
		];
	}
	if ( count($meta_query) === 1 ) {
		$meta_query = []; // 実質条件なし
	}

	$args = [
		'post_type'      => 'property',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => $meta_query,
	];

	$q = new WP_Query($args);

	// ACFが無効でも落ちないようにラッパ
	$acf_get = function($field, $post_id = null) {
		if ( function_exists('get_field') ) return get_field($field, $post_id);
		return get_post_meta($post_id ?: get_the_ID(), $field, true);
	};

	// ====== HTML 出力開始 ======
	ob_start(); ?>

	<div class="ovl-property-list">

		<!-- 絞り込みフォーム（GETパラメータ維持） -->
		<form method="get" action="<?php echo esc_url( get_permalink() ); ?>" class="ovl-property-filter" style="margin: 1rem 0; display:grid; gap:.5rem; grid-template-columns:repeat(6,minmax(0,1fr)); align-items:end;">
			<div style="grid-column: span 2;">
				<label for="min_price" style="display:block;font-size:.9rem;">最小価格（円）</label>
				<input type="number" inputmode="numeric" min="0" step="1" name="min_price" id="min_price" value="<?php echo esc_attr($min_price); ?>" style="width:100%;padding:.5rem;">
			</div>
			<div style="grid-column: span 2;">
				<label for="max_price" style="display:block;font-size:.9rem;">最大価格（円）</label>
				<input type="number" inputmode="numeric" min="0" step="1" name="max_price" id="max_price" value="<?php echo esc_attr($max_price); ?>" style="width:100%;padding:.5rem;">
			</div>

			<?php
			// 既存の任意の GET を hidden で維持（min/max/paged 以外）
			foreach ( $_GET as $k => $v ) {
				if ( in_array($k, ['min_price','max_price','paged'], true) ) continue;
				if ( is_array($v) ) continue;
				echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
			}
			?>

			<div style="grid-column: span 2; display:flex; gap:.5rem;">
				<button type="submit" class="button" style="padding:.6rem 1rem;">絞り込み</button>
				<a href="<?php echo esc_url(
					remove_query_arg(['min_price','max_price','paged'], get_permalink( get_queried_object_id() ))
				); ?>" class="button" style="padding:.6rem 1rem; text-decoration:none;">条件クリア</a>
			</div>
		</form>

		<?php if ( $q->have_posts() ) : ?>
			<div class="ovl-grid" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;">
				<?php
				$is_member = is_user_logged_in();

				while ( $q->have_posts() ) : $q->the_post();
					$post_id  = get_the_ID();
					$title    = get_the_title();
					$detail   = get_permalink($post_id); // 詳細ページ URL
					$price    = $acf_get('price', $post_id);
					$address  = $acf_get('address', $post_id);
					$card_class = $is_member ? 'ovl-card' : 'ovl-card is-guest';

					$login_url = wp_login_url($detail);
					$register_url = '';
					if ( get_option('users_can_register') ) {
						$register_url = add_query_arg('redirect_to', rawurlencode($detail), wp_registration_url());
					}

					$link = $is_member ? $detail : $login_url;


				?>
					<article <?php post_class($card_class); ?> style="border:1px solid #ddd;border-radius:12px;overflow:hidden;position:relative;">

 <a href="<?php echo esc_url($link); ?>"
   class="thumb-blur"
   <?php if ( ! $is_member ) echo 'aria-label="会員ログインが必要です" rel="nofollow"'; ?>
   style="display:block;aspect-ratio:16/9;background:#f4f4f4;overflow:hidden;">

 
    <?php
  // --- デバッグ表示（暫定）：一旦出して原因を見切る ---
/*  echo '<pre style="font:12px/1.2 monospace;background:#fff;padding:4px;margin:0;opacity:.8;">#'
     . $post_id
     . ' has_thumb=' . ( has_post_thumbnail($post_id) ? 'YES' : 'NO' )
     . ' thumb_id=' . get_post_thumbnail_id($post_id)
     . "</pre>";
*/

  // サムネ or フォールバック（16:9 統一）
  if ( has_post_thumbnail($post_id) ) {
    echo get_the_post_thumbnail(
      $post_id,
      'ovl_card', // ← 16:9 固定サイズ（mu-plugins で定義済み）
      [
        'class'   => 'ovl-card-thumb',
        'loading' => 'lazy',
        'sizes'   => '(max-width: 900px) 100vw, 33vw',
        'style'   => 'width:100%;height:100%;object-fit:cover;display:block;aspect-ratio:16/9;'
      ]
    );
  } else {
    echo '<div class="ovl-card-thumb-fallback" style="width:100%;aspect-ratio:16/9;display:grid;place-items:center;background:#eee;color:#666;">NO IMAGE</div>';
  }
  ?>


    // 問題なければ 'medium_large' や新設サイズ 'ovl_card' に戻す
    // echo get_the_post_thumbnail($post_id, 'medium_large', [...]);
  ?>

  <?php if ( ! $is_member ) : ?>
    <span class="ovl-badge" style="position:absolute;top:.5rem;left:.5rem;background:#000;color:#fff;font-size:.8rem;padding:.25rem .5rem;border-radius:.5rem;opacity:.8;">会員限定</span>
  <?php endif; ?>
</a>

						<div style="padding:.8rem 1rem 1rem;">
							<h3 style="margin:.2rem 0 .5rem;font-size:1.05rem;line-height:1.4;">
								<a href="<?php echo esc_url($link); ?>" style="text-decoration:none;"><?php echo esc_html($title); ?></a>
							</h3>

							<?php if ( $is_member ) : ?>
								<p style="margin:.25rem 0; font-weight:600;">
									価格：<?php
										$price_num = is_numeric($price) ? (float)$price : (float)preg_replace('/[^\d.]/', '', (string)$price);
										echo $price !== '' ? esc_html( number_format( $price_num ) ).'円' : '—';
									?>
								</p>
								<p style="margin:.25rem 0; color:#555;">所在地：<?php echo $address ? esc_html($address) : '—'; ?></p>
							<?php else : ?>
								<p style="margin:.25rem 0; font-weight:600;">価格：<span style="opacity:.7;">（会員限定）</span></p>
								<p style="margin:.25rem 0; color:#555;">所在地：<span style="opacity:.7;">（会員限定）</span></p>
								<p style="margin:.5rem 0 0; font-size:.9rem;">
									詳細は会員に公開中。
									<a href="<?php echo esc_url( $login_url ); ?>">ログイン</a>
									<?php if ( $register_url ) : ?>
										/ <a href="<?php echo esc_url( $register_url ); ?>">新規登録</a>
									<?php endif; ?>
								</p>
							<?php endif; ?>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
		<?php else : ?>
			<p>条件に一致する物件は見つかりませんでした。</p>
		<?php endif; ?>

		<?php
		// ====== ページネーション（GET 維持）======
		$add_args = [];
		foreach ( $_GET as $k => $v ) {
			if ( $k === 'paged' ) continue;
			if ( is_array($v) ) continue;
			$add_args[$k] = wp_strip_all_tags($v);
		}

		$big    = 999999999;
		$format = get_option('permalink_structure') ? 'page/%#%/' : '?paged=%#%';

		$links = paginate_links([
			'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
			'format'    => $format,
			'current'   => max(1, $paged),
			'total'     => max(1, (int)$q->max_num_pages),
			'type'      => 'list',
			'prev_text' => '«',
			'next_text' => '»',
			'add_args'  => $add_args,
		]);

		if ( $links ) {
			echo '<nav class="ovl-pagination" style="margin:1.25rem 0 0;">'.$links.'</nav>';
		}
		?>

	</div><!-- /.ovl-property-list -->

	<?php
	wp_reset_postdata();
	return ob_get_clean();
});
