<?php

function add_app_admin_scripts($hook) {
    if ($hook != '%d0%bd%d0%b0%d1%81%d1%82%d1%80%d0%be%d0%b9%d0%ba%d0%b8-%d1%82%d1%83%d1%80%d0%bd%d0%b8%d1%80%d0%b0_page_results_app') {
        return;
    }
    
    // Регистрируем и подключаем внешний JS файл
    wp_register_script(
        'app-admin-js',
        plugins_url('js/setApp.js', __FILE__),
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'js/setApp.js')
    );
    
    // Локализуем переменные для JS
    wp_localize_script('app-admin-js', 'app_admin_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('result_settings_actions_nonce')
    ));
    
    wp_enqueue_script('app-admin-js');
}

function results_settings_app() {

    $link = db_connect();
    
    $style = '<style>.admin_table {
        width: 100%;
        border: none;
        margin-bottom: 20px;
        border-collapse: separate;
    }
    
    .admin_table thead th {
        font-weight: bold;
        text-align: center;
        border: none;
        padding: 10px 15px;
        background: #EDEDED;
        font-size: 14px;
        border-top: 1px solid #ddd;
    }
    .admin_table tr th:first-child, .table tr td:first-child {
        border-left: 1px solid #ddd;
    }
    .admin_table tr th:last-child, .table tr td:last-child {
        border-right: 1px solid #ddd;
    }
    .admin_table thead tr th:first-child {
        border-radius: 20px 0 0 0;
    }
    .admin_table thead tr th:last-child {
        border-radius: 0 20px 0 0;
    }
    .admin_table tbody td {
        text-align: center;
        border: none;
        padding: 10px 15px;
        font-size: 14px;
        vertical-align: center;
    }
    .admin_table tbody tr:nth-child(even) {
        background: #F8F8F8;
    }
    .admin_table tbody tr:last-child td{
        border-bottom: 1px solid #ddd;
    }
    .admin_table tbody tr:last-child td:first-child {
        border-radius: 0 0 0 20px;
    }
    .admin_table tbody tr:last-child td:last-child {
        border-radius: 0 0 20px 0;
    }
    
    .edit-btn, .delete-btn, .save-btn, .cancel-btn {
        cursor: pointer;
        margin: 0 5px;
    }
    
    .edit-season-name, .edit-count-place {
        width: 90%;
        padding: 5px;
    }
    </style>';
    
    $table = $style.'<div class="wrap"><h2>' . get_admin_page_title() . '</h2></div><div id="body" style="margin-top:30px"></div>';
    
    echo $table;
    echo '<div id="message"></div>';
}