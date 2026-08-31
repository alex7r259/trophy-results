<?php

function add_season_admin_scripts($hook) {
    if ($hook != 'toplevel_page_results') {
        return;
    }
    
    // Регистрируем и подключаем внешний JS файл
    wp_register_script(
        'season-admin-js',
        plugins_url('js/setSeasons.js', __FILE__),
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'js/setSeasons.js')
    );
    
    // Локализуем переменные для JS
    wp_localize_script('season-admin-js', 'season_admin_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('result_settings_actions_nonce')
    ));
    
    wp_enqueue_script('season-admin-js');
}

function results_settings_seasons() {
    results_render_admin_page();
}
