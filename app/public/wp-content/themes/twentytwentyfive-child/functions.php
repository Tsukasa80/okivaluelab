<?php
add_action('after_setup_theme', function(){
  add_theme_support('post-thumbnails', ['post', 'page', 'property']);
  add_image_size('property_card', 600, 400, true);   // 横600×縦400のトリミング（お好みで）


});
