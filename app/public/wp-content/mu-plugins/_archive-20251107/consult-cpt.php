<?php
// wp-content/mu-plugins/consult-cpt.php

add_action('init', function () {
    $caps = [
        'edit_post' => 'edit_consult', 'read_post' => 'read_consult', 'delete_post' => 'delete_consult',
        'edit_posts' => 'edit_consults','edit_others_posts'=>'edit_others_consults',
        'publish_posts'=>'publish_consults','read_private_posts'=>'read_private_consults',
        'create_posts'=>'create_consults','delete_posts'=>'delete_consults',
        'delete_private_posts'=>'delete_private_consults','delete_published_posts'=>'delete_published_consults',
        'delete_others_posts'=>'delete_others_consults','edit_private_posts'=>'edit_private_consults',
        'edit_published_posts'=>'edit_published_consults',
    ];

    register_post_type('consult', [
        'label'=>'Consults','public'=>false,'show_ui'=>true,'show_in_menu'=>true,
        'supports'=>['title','editor','custom-fields','author'],
        'menu_position'=>25,'menu_icon'=>'dashicons-feedback',
        'map_meta_cap'=>true,'capability_type'=>['consult','consults'],
        'capabilities'=>$caps,
    ]);
}, 1);

add_action('init', function () {
    $caps = [
        'edit_consult','read_consult','delete_consult','edit_consults','edit_others_consults','publish_consults',
        'read_private_consults','delete_consults','delete_private_consults','delete_published_consults',
        'delete_others_consults','edit_private_consults','edit_published_consults','create_consults'
    ];
    if ($admin=get_role('administrator')) foreach($caps as $cap){$admin->add_cap($cap);}
    if ($member=(get_role('member')?:get_role('subscriber'))){$member->add_cap('create_consults');$member->add_cap('read');}
}, 2);
