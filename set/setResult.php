<?php

function add_result_admin_scripts($hook) {
    if ($hook != '%d0%bd%d0%b0%d1%81%d1%82%d1%80%d0%be%d0%b9%d0%ba%d0%b8-%d1%82%d1%83%d1%80%d0%bd%d0%b8%d1%80%d0%b0_page_results_result') {
        return;
    }
    
    // Регистрируем и подключаем внешний JS файл
    wp_register_script(
        'result-admin-js',
        plugins_url('js/setResult.js', __FILE__),
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'js/setResult.js')
    );
    
    // Локализуем переменные для JS
    wp_localize_script('result-admin-js', 'result_admin_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('result_settings_actions_nonce')
    ));
    
    wp_enqueue_script('result-admin-js');
}

function results_settings_result() {
    results_render_admin_page();
}
