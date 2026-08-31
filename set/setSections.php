<?php

function add_sections_admin_scripts($hook) {
    if ($hook != '%d0%bd%d0%b0%d1%81%d1%82%d1%80%d0%be%d0%b9%d0%ba%d0%b8-%d1%82%d1%83%d1%80%d0%bd%d0%b8%d1%80%d0%b0_page_results_sections') {
        return;
    }

    wp_register_script(
        'sections-admin-js',
        plugins_url('js/setSections.js', __FILE__),
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'js/setSections.js')
    );

    wp_localize_script('sections-admin-js', 'sections_admin_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('result_settings_actions_nonce')
    ));

    wp_enqueue_script('sections-admin-js');
}

function results_settings_sections() {
    results_render_admin_page();
}
