<?php
/**
 * Plugin Name: Property CPT (site)
 */
add_action('init', function () {
  register_post_type('property', [
    'label'               => 'Properties',
    'public'              => true,
    'publicly_queryable'  => true,
    // 固定ページ /property_list/ を公式アーカイブとするため CPT アーカイブは無効化
    'has_archive'         => false,
    'rewrite'             => [ 'slug' => 'property', 'with_front' => false ],
    'show_in_rest'        => true,
    'supports'            => [ 'title', 'editor', 'excerpt', 'thumbnail' ],
  ]);
}, 0);
