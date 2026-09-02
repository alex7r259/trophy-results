<?php
/* Plugin Name: Результаты соревнований */
function add_custom_query_vars($vars) { $vars[]='season_id'; $vars[]='event_id'; $vars[]='class_name'; return $vars; }
add_filter('query_vars','add_custom_query_vars');
add_action('admin_menu','results_settings',25); include 'settings.php';
include_once __DIR__.'/includes/section-results.php';
include_once __DIR__.'/includes/section-result-rules.php';
add_shortcode('results_201','results_show_new'); include 'shortcode_201.php';
add_shortcode('results_202','results_show_single_event'); include 'shortcode_202.php';
add_shortcode('results_203','results_show_top3'); include 'shortcode_203.php';
add_shortcode('results_204','results_show_stage_sections'); include 'shortcode_204.php';
add_action('wp_enqueue_scripts',function(){wp_enqueue_script('jquery');});
?>
