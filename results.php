<?php

/*
 * Plugin Name: Результаты соревнований
 */

// Регистрируем пользовательские параметры запроса
function add_custom_query_vars($vars) {
    $vars[] = 'season_id';  // Добавляем season_id
    $vars[] = 'event_id'; // Добавляем class_name
    $vars[] = 'class_name'; // Добавляем class_name
    return $vars;
}

add_filter('query_vars', 'add_custom_query_vars');

add_action( 'admin_menu', 'results_settings', 25 );
include 'settings.php';

// Зарегистрируйте шорткод с обновленной функцией
add_shortcode('results_201', 'results_show_new');
include 'shortcode_201.php';

add_shortcode( 'results_202', 'results_show_single_event' );
include 'shortcode_202.php';

add_shortcode( 'results_203', 'results_show_top3' );
include 'shortcode_203.php';

// Добавляем скрипт jQuery если не подключен
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('jquery');
});
 
?>