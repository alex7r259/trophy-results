<?php


function results_is_plugin_admin_page($hook) {
    return $hook === 'toplevel_page_results' || strpos($hook, '_page_results_') !== false;
}

function results_admin_assets($hook) {
    if (!results_is_plugin_admin_page($hook)) {
        return;
    }

    wp_enqueue_style(
        'results-admin-css',
        plugins_url('set/admin.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'set/admin.css')
    );
}

function results_render_admin_page() {
    $title = get_admin_page_title();
    ?>
    <div class="wrap results-admin-wrap">
        <div class="results-admin-shell">
            <div class="results-admin-hero">
                <div>
                    <p class="results-admin-eyebrow">Trophy Results</p>
                    <h1 class="results-admin-title"><?php echo esc_html($title); ?></h1>
                    <p class="results-admin-description">Управляйте сезонами, этапами, участниками и результатами в едином интерфейсе. Дважды кликните по ячейке таблицы, чтобы быстро отредактировать значение.</p>
                </div>
                <div class="results-admin-badge">Админ-панель</div>
            </div>
            <div class="results-admin-content">
                <div class="results-admin-toolbar" id="body"></div>
                <div id="message" aria-live="polite"></div>
            </div>
        </div>
    </div>
    <?php
}


function results_allowed_columns_for_table($table) {
    $columns = array(
        'appsettings' => array('id', 'season_id', 'event_id', 'pass'),
        'events' => array('event_id', 'event_name', 'event_date', 'season_id', 'coefficient'),
        'participants' => array('participant_id', 'participants_name', 'season_id', 'car', 'num', 'city'),
        'seasons' => array('season_id', 'season_name', 'countPlace', 'countPart'),
        'results' => array('results_id', 'season_id', 'event_id', 'class_id', 'participant_id', 'points_id', 'missing', 'disq'),
        'class' => array('class_id', 'class_name', 'season_id'),
        'pointstable' => array('points_id', 'season_id', 'position', 'points'),
        'stage_sections' => array('section_id', 'event_id', 'section_number', 'section_name', 'description', 'scoring_type', 'points_source', 'uses_checkpoint_points', 'max_checkpoints', 'max_time', 'rules_config'),
        'stage_section_categories' => array('id', 'section_id', 'class_id', 'display_order', 'scoring_type_override', 'rules_config_override'),
        'stage_section_results' => array('section_result_id', 'section_id', 'class_id', 'participant_id', 'checkpoints_count', 'checkpoint_points', 'raw_time', 'penalty_points', 'penalty_time', 'calculated_place', 'calculated_points', 'manual_place', 'manual_points', 'final_place', 'final_points', 'status', 'manual_status', 'final_status', 'note'),
    );

    return $columns[$table] ?? array();
}

function results_validate_table_column($table, $column = null) {
    $allowed_columns = results_allowed_columns_for_table($table);
    if (empty($allowed_columns)) {
        return false;
    }

    return $column === null || in_array($column, $allowed_columns, true);
}


function results_participants_city_column_exists($link) {
    $result = mysqli_query($link, "SHOW COLUMNS FROM `participants` LIKE 'city'");
    $exists = $result && mysqli_num_rows($result) > 0;
    if ($result) {
        mysqli_free_result($result);
    }

    return $exists;
}

function results_ensure_participants_city_column($link) {
    if (results_participants_city_column_exists($link)) {
        return true;
    }

    mysqli_query($link, "ALTER TABLE `participants` ADD `city` VARCHAR(255) NOT NULL DEFAULT '' AFTER `participants_name`");
    return true;
}

function results_settings() {
    add_menu_page(
        'Настройки турнира',
        'Настройки турнира',
        'manage_options',
        'results',
        'results_settings_seasons',
        'dashicons-editor-table',
        20
    );
    
    add_submenu_page( 
        'results',
        'Сезоны',
        'Сезоны',
        'manage_options',
        'results',
        'results_settings_seasons'
    );
    
    add_submenu_page( 
        'results',
        'Классы',
        'Классы',
        'manage_options',
        'results_class',
        'results_settings_class'
    );
    
    add_submenu_page( 
        'results',
        'Баллы',
        'Баллы',
        'manage_options',
        'results_point',
        'results_settings_point'
    );
    
    add_submenu_page( 
        'results',
        'Этапы',
        'Этапы',
        'manage_options',
        'results_events',
        'results_settings_events'
    );
    
    add_submenu_page( 
        'results',
        'Текущий сезон',
        'Текущий сезон',
        'manage_options',
        'results_app',
        'results_settings_app'
    );
    
    add_submenu_page( 
        'results',
        'Участники',
        'Участники',
        'manage_options',
        'results_participants',
        'results_settings_participants'
    );    
    
    add_submenu_page( 
        'results',
        'Результаты',
        'Результаты',
        'manage_options',
        'results_result',
        'results_settings_result'
    );

    add_submenu_page(
        'results',
        'Подробные результаты СУ',
        'Результаты СУ',
        'manage_options',
        'results_sections',
        'results_settings_sections'
    );    
    
    add_action('admin_enqueue_scripts', 'results_admin_assets');

    // Добавляем скрипты с nonce
    add_action('admin_enqueue_scripts', 'add_season_admin_scripts');
    add_action('admin_enqueue_scripts', 'add_part_admin_scripts');
    add_action('admin_enqueue_scripts', 'add_events_admin_scripts');
    add_action('admin_enqueue_scripts', 'add_app_admin_scripts');
    add_action('admin_enqueue_scripts', 'add_result_admin_scripts');
    add_action('admin_enqueue_scripts', 'add_class_admin_scripts');
    add_action('admin_enqueue_scripts', 'add_point_admin_scripts');
    add_action('admin_enqueue_scripts', 'add_sections_admin_scripts');
    
}

    add_action('wp_ajax_load_seasons', 'load_seasons');
    add_action('wp_ajax_load_table', 'load_table');
    add_action('wp_ajax_load_table_result', 'load_table_result');
    add_action('wp_ajax_col_delete', 'col_delete');
    add_action('wp_ajax_col_save', 'col_save');
    add_action('wp_ajax_update_table', 'update_table');
    add_action('wp_ajax_update_settings', 'update_settings');
    
function load_seasons() {
    check_ajax_referer('result_settings_actions_nonce', 'nonce');
        
    try {
        $link = mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
        $query = mysqli_query($link, 
            "SELECT * FROM appsettings LIMIT 1"
        );
        $settings = mysqli_fetch_assoc($query);
        $seasons = array();
        if (!empty($settings['season_id'])) {
            $seasons = mysqli_fetch_all(
                mysqli_query($link, 
                             "SELECT * FROM seasons"
                            ), MYSQLI_ASSOC);
        }
        array_unshift($seasons, $settings);
        wp_send_json_success($seasons);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

function load_table() {
    check_ajax_referer('result_settings_actions_nonce', 'nonce');	
    if (!isset($_POST['table']) || !isset($_POST['col']) || !isset($_POST['id'])) {
        wp_send_json_error('Не передан ID или имя таблицы');
    }	
    $table = sanitize_text_field($_POST['table']);
    $col = sanitize_text_field($_POST['col']);
    $id = intval($_POST['id']);
    if (!results_validate_table_column($table, $col)) {
        wp_send_json_error('Недопустимая таблица или столбец');
    }
    try {
        $link = mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
        if ($table === 'participants') {
            results_ensure_participants_city_column($link);
        }
        $query = mysqli_query($link, 
            "SELECT * FROM " . $table . " WHERE " . $col . " = " . $id
        );
        $response = mysqli_fetch_all($query, MYSQLI_ASSOC);
        wp_send_json_success($response);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

function load_table_result() {
    check_ajax_referer('result_settings_actions_nonce', 'nonce');
    
    // Проверяем наличие всех обязательных параметров
    if (!isset($_POST['season_id'], $_POST['event_id'], $_POST['class_id'])) {
        wp_send_json_error('Не переданы все необходимые параметры', 400);
    }

    // Валидируем и фильтруем входные данные
    $season_id = filter_var($_POST['season_id'], FILTER_VALIDATE_INT);
    $event_id = filter_var($_POST['event_id'], FILTER_VALIDATE_INT);
    $class_id = filter_var($_POST['class_id'], FILTER_VALIDATE_INT);

    if ($season_id === false || $event_id === false || $class_id === false) {
        wp_send_json_error('Некорректные идентификаторы (должны быть числами)', 400);
    }

    // Настройки подключения к базе данных (лучше вынести в wp-config.php)
    $db_host = "localhost";
    $db_user = "j84588200_result";
    $db_pass = "?fYt3K7yGaqv";
    $db_name = "j84588200_results";

    try {
        // Подключаемся к базе данных с обработкой ошибок
        $link = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        
        if (!$link) {
            throw new Exception("Ошибка подключения к базе данных: " . mysqli_connect_error());
        }

        // Подготавливаем SQL-запрос с параметрами (защита от SQL-инъекций)
        $query = "SELECT r.results_id, r.missing, r.disq, p.participant_id, p.participants_name, p.city, p.car, p.num, 
                         pt.points, pt.points_id, pt.position, e.coefficient
                  FROM Results r
                  JOIN Participants p ON r.participant_id = p.participant_id
                  JOIN Class c ON r.class_id = c.class_id
                  JOIN Pointstable pt ON r.points_id = pt.points_id
                  JOIN Seasons s ON r.season_id = s.season_id
                  JOIN Events e ON r.event_id = e.event_id
                  WHERE c.class_id = ? AND e.season_id = ? AND e.event_id = ?
                  ORDER BY pt.points_id";

        $stmt = mysqli_prepare($link, $query);
        
        if (!$stmt) {
            throw new Exception("Ошибка подготовки запроса: " . mysqli_error($link));
        }

        // Привязываем параметры
        mysqli_stmt_bind_param($stmt, "iii", $class_id, $season_id, $event_id);

        // Выполняем запрос
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Ошибка выполнения запроса: " . mysqli_stmt_error($stmt));
        }

        // Получаем результат
        $result = mysqli_stmt_get_result($stmt);
        $response = mysqli_fetch_all($result, MYSQLI_ASSOC);

        // Закрываем соединение
        mysqli_stmt_close($stmt);
        mysqli_close($link);

        // Отправляем успешный ответ
        wp_send_json_success($response);

    } catch (Exception $e) {
        // Закрываем соединение в случае ошибки (если оно было открыто)
        if (isset($link) && $link) {
            mysqli_close($link);
        }
        wp_send_json_error('Ошибка: ' . $e->getMessage(), 500);
    }
}

function col_delete() {
    check_ajax_referer('result_settings_actions_nonce', 'nonce');
    // Проверка наличия всех обязательных параметров
    if (!isset($_POST['table'], $_POST['col'], $_POST['id'])) {
        wp_send_json_error('Не переданы table, col или id');
    }
    // Валидация и санитизация данных
    $table = sanitize_text_field($_POST['table']);
    $col = sanitize_text_field($_POST['col']);
    $id = intval($_POST['id']);
    if (!results_validate_table_column($table, $col)) {
        wp_send_json_error('Недопустимая таблица или столбец');
    }
    try {
        $link = db_connect(); // Ваша функция подключения
        if (!$link) {
            throw new Exception('Ошибка подключения к базе данных');
        }
        if ($table === 'participants') {
            results_ensure_participants_city_column($link);
        }
        // Подготовленный запрос (только значение id параметризуется)
        $query = "DELETE FROM `{$table}` WHERE `{$col}` = ?";
        $stmt = mysqli_prepare($link, $query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . mysqli_error($link));
        }
        // Привязываем параметр (id)
        mysqli_stmt_bind_param($stmt, 'i', $id);
        // Выполняем запрос
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Ошибка выполнения запроса: ' . mysqli_stmt_error($stmt));
        }
        // Получаем количество удаленных строк
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        // Закрываем запрос и соединение
        mysqli_stmt_close($stmt);
        mysqli_close($link);
        // Возвращаем успешный ответ
        wp_send_json_success([
            'deleted_rows' => $affected_rows
        ]);
    } catch (Exception $e) {
        // Закрываем соединение в случае ошибки (если оно было открыто)
        if (isset($link) && $link) {
            mysqli_close($link);
        }
        wp_send_json_error($e->getMessage());
    }
}

function col_save() {
    check_ajax_referer('result_settings_actions_nonce', 'nonce');
    // Проверка наличия всех обязательных параметров
    if (!isset($_POST['table'], $_POST['variables']) || !is_array($_POST['variables'])) {
        wp_send_json_error('Не переданы необходимые параметры');
    }
    // Валидация и санитизация данных
    $table = sanitize_text_field($_POST['table']);
    if (!results_validate_table_column($table)) {
        wp_send_json_error('Недопустимая таблица');
    }
    // Подготовка данных для запроса
    $columns = [];
    $values = [];
    $placeholders = [];
    $types = '';

    foreach ($_POST['variables'] as $key => $value) {
        $key = sanitize_text_field($key);
        $value = sanitize_text_field($value);        
        if (!results_validate_table_column($table, $key)) {
            wp_send_json_error('Недопустимый столбец');
        }
        $columns[] = "`$key`";
        $values[] = $value;
        $placeholders[] = '?';
        $types .= 's'; // предполагаем строковый тип для всех значений
    }
    try {
        $link = db_connect();
        if (!$link) {
            throw new Exception('Ошибка подключения к базе данных');
        }
        if ($table === 'participants') {
            results_ensure_participants_city_column($link);
        }
        // Создаем SQL-запрос
        $sql = sprintf(
            "INSERT INTO `%s` (%s) VALUES (%s)",
            mysqli_real_escape_string($link, $table),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        // Подготавливаем запрос
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . mysqli_error($link));
        }
        // Привязываем параметры
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        // Выполняем запрос
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Ошибка выполнения запроса: ' . mysqli_stmt_error($stmt));
        }
        $affected_rows = mysqli_stmt_affected_rows($stmt);        
        // Закрываем запрос и соединение
        mysqli_stmt_close($stmt);
        mysqli_close($link);
        if ($affected_rows > 0) {
            wp_send_json_success('Успешно добавлено');
        } else {
            wp_send_json_error('Не удалось добавить запись');
        }
    } catch (Exception $e) {
        if (isset($link) && $link) {
            mysqli_close($link);
        }
        wp_send_json_error('Ошибка базы данных: ' . $e->getMessage());
    }
}

function update_table() {
    // 1. Проверка nonce
    check_ajax_referer('result_settings_actions_nonce', 'nonce');
    
    // 2. Проверка обязательных параметров
    if (!isset($_POST['table'], $_POST['id'], $_POST['col'], $_POST['field'], $_POST['value'])) {
        wp_send_json_error('Не переданы все необходимые параметры', 400);
    }
    
    // 3. Валидация и санитизация данных
    $table = sanitize_text_field($_POST['table']);
    $field = sanitize_text_field($_POST['field']);
    $col = sanitize_text_field($_POST['col']);
    if (!results_validate_table_column($table, $field) || !results_validate_table_column($table, $col)) {
        wp_send_json_error('Недопустимая таблица или столбец', 403);
    }
    
    // 4. Подключение к базе данных
    $link = db_connect();
    if (!$link) {
        wp_send_json_error('Ошибка подключения к базе данных', 500);
    }
    if ($table === 'participants') {
        results_ensure_participants_city_column($link);
    }
    
    try {
        // 5. Проверка существования колонки в таблице
        $result = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$field'");
        if (!$result || mysqli_num_rows($result) === 0) {
            throw new Exception("Колонка '$field' не существует в таблице '$table'");
        }
        mysqli_free_result($result);
        
        // 6. Подготовка значения
        $id = intval($_POST['id']);
        $value = $_POST['value'];
        
        // Специальная обработка для разных типов данных
        if ($field === 'event_date') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                throw new Exception('Неверный формат даты. Используйте YYYY-MM-DD');
            }
            $value = mysqli_real_escape_string($link, $value);
        } 
        elseif ($field === 'coefficient') {
            $value = floatval($value);
            if ($value < 1 || $value > 2) {
                throw new Exception('Коэффициент должен быть между 1 и 2');
            }
        }
        else {
            $value = mysqli_real_escape_string($link, $value);
        }
        
        // 7. Проверка существования колонки для условия WHERE
        $result = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$col'");
        if (!$result || mysqli_num_rows($result) === 0) {
            throw new Exception("Колонка '$col' не существует в таблице '$table'");
        }
        mysqli_free_result($result);
        
        // 8. Формирование и выполнение запроса
        $query = "UPDATE `$table` SET `$field` = '$value' WHERE `$col` = $id";
        
        if (!mysqli_query($link, $query)) {
            throw new Exception(mysqli_error($link));
        }
        
        $affected_rows = mysqli_affected_rows($link);
        
        // 9. Закрытие соединения
        mysqli_close($link);
        
        // 10. Ответ
        if ($affected_rows > 0) {
            wp_send_json_success('Данные успешно обновлены');
        } else {
            wp_send_json_success('Данные не изменились');
        }
        
    } catch (Exception $e) {
        // Закрытие соединения в случае ошибки
        if (isset($link)) mysqli_close($link);
        wp_send_json_error('Ошибка базы данных: ' . $e->getMessage(), 500);
    }
}

function update_settings() {
    check_ajax_referer('result_settings_actions_nonce', 'nonce');
    // Проверка наличия всех обязательных параметров
    if (!isset($_POST['table'], $_POST['season_id'], $_POST['event_id'], $_POST['pass'])) {
        wp_send_json_error('Не переданы необходимые параметры');
    }
    // Валидация и санитизация данных
    $table = sanitize_text_field($_POST['table']);
    if ($table !== 'appsettings') {
        wp_send_json_error('Недопустимая таблица');
    }

    try {
        $link = db_connect();
        if (!$link) {
            throw new Exception('Ошибка подключения к базе данных');
        }
        
        // Формирование и выполнение запроса
        $query = "UPDATE `$table` SET 
        `season_id` = " . intval($_POST['season_id']) . ", 
        `event_id` = " . intval($_POST['event_id']) . ", 
        `pass` = '" . mysqli_real_escape_string($link, sanitize_text_field($_POST['pass'])) . "' 
        WHERE `id` = 1";
        
        if (!mysqli_query($link, $query)) {
            throw new Exception(mysqli_error($link));
        }
        
        $affected_rows = mysqli_affected_rows($link);
        
        // 9. Закрытие соединения
        mysqli_close($link);
        
        // 10. Ответ
        if ($affected_rows > 0) {
            wp_send_json_success('Данные успешно обновлены');
        } else {
            wp_send_json_success('Данные не изменились');
        }
        
    } catch (Exception $e) {
        // Закрытие соединения в случае ошибки
        if (isset($link)) mysqli_close($link);
        wp_send_json_error('Ошибка базы данных: ' . $e->getMessage(), 500);
    }
}

// Функция для подключения к базе данных
function db_connect() {
    static $link;
    
    if ($link === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $link = mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
        mysqli_set_charset($link, 'utf8');
    }
    
    return $link;
}

include 'set/setSeasons.php';
include 'set/setEvent.php';
include 'set/setPart.php';
include 'set/setApp.php';
include 'set/setResult.php';
include 'set/setClass.php';
include 'set/setPoint.php';
include 'set/setSections.php';