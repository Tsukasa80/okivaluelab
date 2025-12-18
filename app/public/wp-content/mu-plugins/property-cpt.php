<?php
/**
 * Plugin Name: Property CPT (site)
 */
add_action('init', function () {
  register_post_type('property', [
    'label'               => '物件',
    'labels'              => [
      'name'          => '物件',
      'singular_name' => '物件',
      'add_new_item'  => '新規物件を追加',
      'edit_item'     => '物件を編集',
      'all_items'     => '物件一覧',
    ],
    'public'              => true,
    'publicly_queryable'  => true,
    // 固定ページ /property_list/ を公式アーカイブとするため CPT アーカイブは無効化
    'has_archive'         => false,
    'show_ui'             => true,
    'show_in_menu'        => true,
    'menu_position'       => 20,
    'menu_icon'           => 'dashicons-building',
    'rewrite'             => [ 'slug' => 'property', 'with_front' => false ],
    'show_in_rest'        => true,
    'supports'            => [ 'title', 'editor', 'excerpt', 'thumbnail' ],
    'capability_type'     => 'post',
    'map_meta_cap'        => true,
  ]);

add_filter( 'use_block_editor_for_post_type', function ( $use_block_editor, $post_type ) {
  return ( $post_type === 'property' ) ? false : $use_block_editor;
}, 10, 2 );

}, 0);
