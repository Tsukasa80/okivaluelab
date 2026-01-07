<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

$page = get_page_by_path('profile');
if ($page) {
    error_log('--- EXTRACTED PROFILE CONTENT START ---');
    error_log($page->post_content);
    error_log('--- EXTRACTED PROFILE CONTENT END ---');
    echo "Content extracted to debug.log";
} else {
    // Try other common slugs
    $pages = get_posts(['post_type' => 'page', 'posts_per_page' => -1]);
    foreach ($pages as $p) {
        if (strpos($p->post_title, 'プロフィール') !== false || strpos($p->post_title, '運営') !== false) {
            error_log('--- FOUND POSSIBLE PAGE: ' . $p->post_name . ' ---');
            error_log($p->post_content);
        }
    }
    echo "Check debug.log for candidates";
}
?>