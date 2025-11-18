<?php
// mu-plugins 読込チェック（ログに1行）
add_action('init', function(){
  error_log('[MU-CHECK] mu-plugins is loaded.');
});
