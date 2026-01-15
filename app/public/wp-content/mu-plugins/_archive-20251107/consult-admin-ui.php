<?php
// wp-content/mu-plugins/consult-admin-ui.php
// consult メタ登録＋編集画面メタボックス＋一覧カラム拡張

if ( ! defined('ABSPATH') ) exit;

/** 1) メタ登録（REST対応で後々も扱いやすく） */
add_action('init', function () {
    $meta_args = function($type='string', $single=true) {
        return [
            'type' => $type,
            'single' => $single,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ];
    };
    register_post_meta('consult','applicant_name',  $meta_args('string'));
    register_post_meta('consult','applicant_email', $meta_args('string'));
    register_post_meta('consult','property_url',    $meta_args('string'));
    register_post_meta('consult','contact_method',  $meta_args('string'));
    register_post_meta('consult','phone',           $meta_args('string'));
    register_post_meta('consult','preferred_time',  $meta_args('string'));
    register_post_meta('consult','privacy_agreed',  $meta_args('boolean'));
}, 9);

/** 2) 編集画面メタボックス */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'consult_details_box',
        '相談フォーム入力',
        function ($post) {
            wp_nonce_field('consult_meta_save','consult_meta_nonce');
            $g = fn($k,$d='') => esc_attr( get_post_meta($post->ID, $k, true) ?: $d );
            ?>
            <table class="form-table">
              <tr><th><label>お名前</label></th>
                <td><input type="text" name="meta_applicant_name" value="<?php echo $g('applicant_name'); ?>" class="regular-text"></td></tr>
              <tr><th><label>メール</label></th>
                <td><input type="email" name="meta_applicant_email" value="<?php echo $g('applicant_email'); ?>" class="regular-text"></td></tr>
              <tr><th><label>電話</label></th>
                <td><input type="text" name="meta_phone" value="<?php echo $g('phone'); ?>" class="regular-text"></td></tr>
              <tr><th><label>希望連絡方法</label></th>
                <td><input type="text" name="meta_contact_method" value="<?php echo $g('contact_method'); ?>" class="regular-text"></td></tr>
              <tr><th><label>都合の良い時間帯</label></th>
                <td><input type="text" name="meta_preferred_time" value="<?php echo $g('preferred_time'); ?>" class="regular-text"></td></tr>
              <tr><th><label>物件URL</label></th>
                <td><input type="url" name="meta_property_url" value="<?php echo $g('property_url'); ?>" class="regular-text"></td></tr>
              <tr><th><label>プライバシー同意</label></th>
                <td><label><input type="checkbox" name="meta_privacy_agreed" value="1" <?php checked( get_post_meta($post->ID,'privacy_agreed',true), 1 ); ?>> 同意済み</label></td></tr>
            </table>
            <p class="description">本文（相談内容）は通常のエディター（本文）に入っています。</p>
            <?php
        },
        'consult',
        'normal',
        'high'
    );
});

add_action('save_post_consult', function ($post_id) {
    if ( ! isset($_POST['consult_meta_nonce']) || ! wp_verify_nonce($_POST['consult_meta_nonce'],'consult_meta_save') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    $s = fn($key,$filter='sanitize_text_field') => isset($_POST[$key]) ? $filter($_POST[$key]) : '';

    update_post_meta($post_id, 'applicant_name',  $s('meta_applicant_name'));
    update_post_meta($post_id, 'applicant_email', $s('meta_applicant_email','sanitize_email'));
    update_post_meta($post_id, 'phone',           $s('meta_phone'));
    update_post_meta($post_id, 'contact_method',  $s('meta_contact_method'));
    update_post_meta($post_id, 'preferred_time',  $s('meta_preferred_time'));
    update_post_meta($post_id, 'property_url',    $s('meta_property_url','esc_url_raw'));
    update_post_meta($post_id, 'privacy_agreed',  isset($_POST['meta_privacy_agreed']) ? 1 : 0 );
});

/** 3) 一覧カラム */
add_filter('manage_edit-consult_columns', function ($cols) {
    // 既存の Title/Date を活かしつつ差し替え
    $new = [];
    $new['cb']    = $cols['cb'];
    $new['title'] = 'タイトル';
    $new['applicant_name']  = 'お名前';
    $new['applicant_email'] = 'メール';
    $new['contact_method']  = '連絡方法';
    $new['phone']           = '電話';
    $new['property_url']    = '物件URL';
    $new['date']            = $cols['date'];
    return $new;
});

add_action('manage_consult_posts_custom_column', function ($col, $post_id) {
   switch ($col) {
      case 'applicant_name':
           echo esc_html( get_post_meta($post_id, 'applicant_name', true) );
           break;
      case 'applicant_email':
           echo esc_html( get_post_meta($post_id, 'applicant_email', true) );
           break;
      case 'contact_method':
           echo esc_html( get_post_meta($post_id, 'contact_method', true) );
           break;
      case 'phone':
          echo esc_html( get_post_meta($post_id, 'phone', true) );
          break;
      case 'property_url':
           $u = esc_url( get_post_meta($post_id, 'property_url', true) );
           if ($u) echo '<a href="'.$u.'" target="_blank" rel="noopener">リンク</a>';
           break;
    }
}, 10, 2);

// （任意）並び替え対応：名前とメール
add_filter('manage_edit-consult_sortable_columns', function($cols){
    $cols['applicant_name']  = 'applicant_name';
    $cols['applicant_email'] = 'applicant_email';
    return $cols;
});
add_action('pre_get_posts', function($q){
    if ( ! is_admin() || ! $q->is_main_query() ) return;
    if ( $q->get('post_type') !== 'consult' ) return;
    $orderby = $q->get('orderby');
    if ( $orderby === 'applicant_name' )  { $q->set('meta_key','applicant_name');  $q->set('orderby','meta_value'); }
    if ( $orderby === 'applicant_email' ) { $q->set('meta_key','applicant_email'); $q->set('orderby','meta_value'); }
});
