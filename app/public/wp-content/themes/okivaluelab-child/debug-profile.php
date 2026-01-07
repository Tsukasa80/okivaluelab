<?php
/**
 * Template Name: Profile Helper
 */
ob_start();
the_content();
$content = ob_get_clean();
error_log('PROFILE_CONTENT_START');
error_log($content);
error_log('PROFILE_CONTENT_END');
echo "Check error log";
?>