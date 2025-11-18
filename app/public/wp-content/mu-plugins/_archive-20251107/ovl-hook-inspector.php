<?php
if ( ! defined('ABSPATH') ) exit;

add_action('template_redirect', function () {
  if ( is_admin() ) return;
  $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
  if ( $path !== 'member-gate' ) return;

  global $wp_filter;
  if ( empty($wp_filter['template_redirect']) ) return;

  $cbs = $wp_filter['template_redirect']->callbacks ?? [];
  foreach ($cbs as $prio => $arr) {
    foreach ($arr as $key => $cb) {
      $name = 'unknown';
      $where = '';
      if ( is_string($cb['function']) ) {
        $name = $cb['function'];
        if ( function_exists($name) ) {
          $rf = new ReflectionFunction($name);
          $where = $rf->getFileName() . ':' . $rf->getStartLine();
        }
      } elseif ( is_array($cb['function']) ) {
        $obj = $cb['function'][0];
        $m   = $cb['function'][1] ?? '';
        $name = (is_object($obj) ? get_class($obj) : (string)$obj) . '::' . $m;
        try {
          $rf = new ReflectionMethod($obj, $m);
          $where = $rf->getFileName() . ':' . $rf->getStartLine();
        } catch (\Throwable $e) {}
      } elseif ($cb['function'] instanceof Closure) {
        $name = 'Closure';
        $rf = new ReflectionFunction($cb['function']);
        $where = $rf->getFileName() . ':' . $rf->getStartLine();
      }
      error_log(sprintf('[OVL:inspect] template_redirect prio=%s cb=%s @ %s', $prio, $name, $where));
    }
  }
}, -1000); // なるべく早く
