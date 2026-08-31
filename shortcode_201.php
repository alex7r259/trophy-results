<?php

// Регистрируем AJAX обработчики в начале файла
add_action('wp_ajax_get_class_results', 'ajax_get_class_results');
add_action('wp_ajax_nopriv_get_class_results', 'ajax_get_class_results');

function results_show_new($atts) {
    // Безопасное получение параметров из GET
    $class_name = get_query_var('class_name') ? sanitize_text_field(get_query_var('class_name')) : 'Полироль';
    
    // Путь к папке скрипта
    $_dir = 'https://'.strstr(__DIR__, $_SERVER['SERVER_NAME']);
    
    // Подключение к базе данных
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $link = mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
    
    // Получение данных о сезоне
    $season = mysqli_fetch_row(mysqli_query($link, "SELECT * FROM appsettings"));    
    
    $season_id = get_query_var('season_id') ? absint(get_query_var('season_id')) : $season[3];

    // Получение данных о сезонах
    $seasons = mysqli_fetch_all(
        mysqli_query($link, "SELECT * FROM seasons"),
        MYSQLI_ASSOC
    ); 
    
    // Выводим ссылки для каждого значения $seasons
    $link_seasons = '';
    foreach ($seasons as $s) {
        $active_season = ($season_id == $s['season_id']) ? ' season-filter active' : ' season-filter';
        $link_seasons .= '<a href="?season_id=' . esc_html($s['season_id']) . '" class="' . $active_season . '">' . esc_html($s['season_name']) . '</a>';
    }
    
    // Получение данных о сезоне
    $rows_cp = mysqli_fetch_all(
        mysqli_query($link, "SELECT * FROM seasons WHERE season_id = '$season_id'"),
        MYSQLI_ASSOC
    );
    $minParticipants = $rows_cp[0]['countPart'] ?? 0;
    
    // Получение данных о классах
    $rows_cl = mysqli_fetch_all(
        mysqli_query($link, "SELECT * FROM class WHERE season_id = '$season_id'"),
        MYSQLI_ASSOC
    );
    $class_names = array();
    
    foreach ($rows_cl as $k) {
        if (isset($k['class_name'])) {
            $class_names[] = $k['class_name'];
        }
    }
    
    mysqli_close($link);
    
    // Создаем nonce для безопасности
    $ajax_nonce = wp_create_nonce('results_ajax_nonce');
    
    // Выводим ссылки для каждого значения class_name
    $link_class = '';
    foreach ($class_names as $class) {
        $active_class = ($class_name == $class) ? ' class-filter-top3 active' : ' class-filter-top3';
        $link_class .= '<a href="#" data-class="' . esc_attr($class) . '" class="' . $active_class . '">' . esc_html($class) . '</a>';
    }
    
    // Генерация контейнера
    $output = "<link rel=\"stylesheet\" href=\"$_dir/results_style.css\">
          <div class=\"results-container\" data-season-id=\"$season_id\" data-nonce=\"$ajax_nonce\">
            <div class=\"results-header\">
              <div class=\"season-filters\">
                $link_seasons
              </div>
            </div>
            <div class=\"results-header\">
              <div class=\"class-filters-top3\">
                $link_class
              </div>
            </div>
            <div id=\"results-table-content\">";
    
    // Выводим начальную таблицу
    $output .= generate_results_table($season_id, $class_name, $minParticipants);
    
    $output .= "</div></div>";
    
    // JavaScript для AJAX переключения
    $output .= "
    <script type=\"text/javascript\">
    document.addEventListener('DOMContentLoaded', function() {
        var container = document.querySelector('.results-container');
        var seasonId = container.getAttribute('data-season-id');
        var ajaxNonce = container.getAttribute('data-nonce');
        var ajaxUrl = '" . admin_url('admin-ajax.php') . "';
        
        // Обработчик клика по фильтрам классов
        var filters = document.querySelectorAll('.class-filters-top3 a');
        filters.forEach(function(filter) {
            filter.addEventListener('click', function(e) {
                e.preventDefault();
                
                var className = this.getAttribute('data-class');
                
                // Убираем активный класс у всех фильтров
                filters.forEach(function(f) {
                    f.classList.remove('active');
                });
                // Добавляем активный класс текущему фильтру
                this.classList.add('active');
                
                
                // Создаем FormData для отправки
                var formData = new FormData();
                formData.append('action', 'get_class_results');
                formData.append('season_id', seasonId);
                formData.append('class_name', className);
                formData.append('security', ajaxNonce);
                
                // AJAX запрос с использованием fetch
                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(function(data) {
                    document.getElementById('results-table-content').innerHTML = data;
                    initTableResponsive();
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    document.getElementById('results-table-content').innerHTML = '<p class=\"error\">Ошибка загрузки данных</p>';
                });
            });
        });
        
        // Инициализация адаптивности таблицы
        function initTableResponsive() {
            var tables = document.querySelectorAll('.results-table');
            tables.forEach(function(table) {
                var containerWidth = table.parentElement.offsetWidth;
                var tableWidth = table.offsetWidth;
                
                if (containerWidth < tableWidth || window.innerWidth <= 600) {
                    table.classList.add('responsive-mode');
                } else {
                    table.classList.remove('responsive-mode');
                }
            });
            
            // Обработчики для стрелочек
            document.querySelectorAll('.shScores').forEach(function(arrow) {
                arrow.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
        }
        
        // Проверяем при загрузке и изменении размера окна
        initTableResponsive();
        window.addEventListener('resize', initTableResponsive);
    });
    </script>
    
    
    
    <div class=\"documents-grid\">
    <a href=\"#\" class=\"document-card\" onclick=\"document.getElementById('formExcel').submit();\">

        <div class=\"document-info\">
            <h4>Результаты сезона</h4>
            <div class=\"document-meta\">
                XLSX
            </div>
        </div>

        <span class=\"document-download\">Скачать</span>

    </a>
    <form id=\"formExcel\" action=\"".$_dir."/excel_res.php?season_id=$season_id\" method=\"POST\" class=\"excel-download\"></form>
    </div>";
    
    echo $output;
}

// AJAX обработчик для загрузки данных класса
function ajax_get_class_results() {
    // Проверка безопасности через nonce
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'results_ajax_nonce')) {
        wp_die('Security check failed');
    }
    
    $season_id = isset($_POST['season_id']) ? absint($_POST['season_id']) : 1;
    $class_name = isset($_POST['class_name']) ? sanitize_text_field($_POST['class_name']) : 'Полироль';
    
    // Получаем данные о сезоне для minParticipants
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $link = mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
    
    $rows_cp = mysqli_fetch_all(
        mysqli_query($link, "SELECT * FROM seasons WHERE season_id = '$season_id'"),
        MYSQLI_ASSOC
    );
    $minParticipants = $rows_cp[0]['countPart'] ?? 0;
    
    echo generate_results_table($season_id, $class_name, $minParticipants);

    mysqli_close($link);
    wp_die();
}

// Функция генерации таблицы результатов
function generate_results_table($season_id, $class_name, $minParticipants) {
    
    // Путь к папке скрипта
    $_dir = 'https://'.strstr(__DIR__, $_SERVER['SERVER_NAME']);
    
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $link = mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
    
    // Получение данных о событиях
    $rows_ev = mysqli_fetch_all(
        mysqli_query($link, "SELECT * FROM events WHERE season_id = '$season_id'"),
        MYSQLI_ASSOC
    );
    $count_event = count($rows_ev);
    
    // Получение данных о сезоне
    $rows_cp = mysqli_fetch_all(
        mysqli_query($link, "SELECT * FROM seasons WHERE season_id = '$season_id'"),
        MYSQLI_ASSOC
    );
    $countPlace = $rows_cp[0]['countPlace'] ?? 0;
    
    // Получаем статистику по количеству участников в каждом классе для каждого события
    $statsQuery = "
        SELECT 
            c.class_name, 
            e.event_id, 
            COUNT(DISTINCT r.participant_id) AS participants_count
        FROM Results r
        JOIN Class c ON r.class_id = c.class_id
        JOIN Events e ON r.event_id = e.event_id
        WHERE e.season_id = '$season_id'
        GROUP BY c.class_name, e.event_id
    ";
    
    $statsResult = mysqli_query($link, $statsQuery);
    $stats = mysqli_fetch_all($statsResult, MYSQLI_ASSOC);
    
    // Создаем массив для хранения информации о валидности этапов
    $validEvents = array();
    foreach ($stats as $stat) {
        $validEvents[$stat['class_name']][$stat['event_id']] = ($stat['participants_count'] >= $minParticipants);
    }
    
    // Формирование SQL запроса для участников
    $sql = "SELECT p.participant_id, p.participants_name, p.car, p.num, s.countPart";
    $i=1;
    
    foreach ($rows_ev as $k){
        $sql .= ", MAX(CASE WHEN e.event_id = ".$k['event_id']." THEN pt.position ELSE '-' END) AS e{$i}_position,
                 MAX(CASE WHEN e.event_id = ".$k['event_id']." THEN pt.points ELSE 0 END) AS e{$i}_points,
                 MAX(CASE WHEN e.event_id = ".$k['event_id']." THEN pt.points_id ELSE 0 END) AS e{$i}_points_id,
                 MAX(CASE WHEN e.event_id = ".$k['event_id']." THEN r.missing ELSE 0 END) AS e{$i}_missing,
                 MAX(CASE WHEN e.event_id = ".$k['event_id']." THEN r.disq ELSE 0 END) AS e{$i}_disq,
                 MAX(CASE WHEN e.event_id = ".$k['event_id']." THEN e.coefficient ELSE 0 END) AS e{$i}_coef";
        $i++;
    }
    
    $sql .= " FROM Results r
              JOIN Participants p ON r.participant_id = p.participant_id
              JOIN Class c ON r.class_id = c.class_id
              JOIN Pointstable pt ON r.points_id = pt.points_id
              JOIN Seasons s ON r.season_id = s.season_id
              JOIN Events e ON r.event_id = e.event_id
              WHERE class_name = '$class_name' AND e.season_id = '$season_id'
              GROUP BY r.participant_id";
    
    // Получение и обработка данных участников
    $rows = mysqli_fetch_all(mysqli_query($link, $sql), MYSQLI_ASSOC);
    
    foreach ($rows as &$row) {
        $row['count_ev'] = 0;
        $row['count_ev_t'] = 0;
        $min_points = [];
        $row['sum_all'] = 0;
        
        for ($n = 1; $n <= $count_event; $n++) {
            $event_id = $rows_ev[$n-1]['event_id'];
            $isValidEvent = $validEvents[$class_name][$event_id] ?? true;
            
            if ($isValidEvent && $row["e{$n}_missing"] == 0 && $row["e{$n}_disq"] == 0) {
                $points = $row["e{$n}_points"] ?? 0;
            } else {
                $points = 0;
            }
            
            $coef = $row["e{$n}_coef"] ?? 0;
            $calculated = $points * $coef;
            
            // Учитываем только валидные этапы
            if ($isValidEvent) {
                $row['sum_all'] += $calculated;
                $min_points[] = $calculated;
            }
            
            if ($coef != 0.00 && $isValidEvent) {
                $row['count_ev']++;
            }
        }
        
        $row['min'] = ($row['count_ev'] > $countPlace) ? min($min_points) : 0;
        $row['sum'] = $row['sum_all'] - $row['min'];
        $row['count_ev_t'] = ($row['count_ev'] >= $countPlace) ? 1 : 0;
    }
    unset($row);
    
    // Функция сравнения для сортировки
    $cmp = function($a, $b) use ($count_event) {
        if ($result = $b['count_ev_t'] <=> $a['count_ev_t']) return $result;
        if ($result = $b['sum'] <=> $a['sum']) return $result;
        if ($result = $b['sum_all'] <=> $a['sum_all']) return $result;
        
        for ($n = $count_event; $n >= 1; $n--) {
            if ($result = $b["e{$n}_points"] <=> $a["e{$n}_points"]) {
                return $result;
            }
        }
        return 0;
    };
    
    usort($rows, $cmp);
    
    // Генерация таблицы
    $table = "<div class=\"table-responsive\">
              <figure class=\"wp-block-table is-style-stripes\">
                <table class=\"delivery results-table\">
                  <thead>
                    <tr>
                      <th rowspan=\"2\" class=\"has-text-align-center\" data-align=\"center\">Место</th>
                      <th rowspan=\"2\" class=\"has-text-align-center\" data-align=\"center\">Стартовый<br>номер</th>
                      <th rowspan=\"2\" class=\"has-text-align-center\" data-align=\"center\">Фамилия Имя Пилот/Штурман</th>
                      <th rowspan=\"2\" class=\"has-text-align-center\" data-align=\"center\">Автомобиль</th>";
    
    for ($n = 1; $n <= $count_event; $n++) {
        $event_id = $rows_ev[$n-1]['event_id'];
        $isValidEvent = $validEvents[$class_name][$event_id] ?? true;
        $coef = rtrim($rows_ev[$n-1]['coefficient'] ?? '0', '0.');
        $table .= "<th colspan=\"2\" class=\"has-text-align-center\" data-align=\"center\">{$n} этап".(!$isValidEvent ? "<br>*" : "")."</th>";
    }
    
    $table .= "<th rowspan=\"2\" class=\"has-text-align-center\" data-align=\"center\">Баллы<br>Итог</th>
                    </tr><tr>";
                  
    for ($n = 1; $n <= $count_event; $n++) {
        $event_id = $rows_ev[$n-1]['event_id'];
        $isValidEvent = $validEvents[$class_name][$event_id] ?? true;
        $coef = rtrim($rows_ev[$n-1]['coefficient'] ?? '0', '0.');
        $table .= "<th class=\"has-text-align-center\" data-align=\"center\">Место".(!$isValidEvent ? "<br>*" : "")."</th>";
        $table .= "<th class=\"has-text-align-center\" data-align=\"center\">Баллы<br>(x{$coef})".(!$isValidEvent ? "<br>*" : "")."</th>";
    }
    
    $table .= "</tr>
                  </thead>
                  <tbody>";
    
    // Генерация строк таблицы
    $i = 1;
    foreach ($rows as $row) {
        $m = ($row['count_ev_t'] == 1) ? $i++ : '-';
        
        $table .= "<tr>
            <td aria-label=\"Место\" class=\"has-text-align-center results_place\" data-align=\"center\">$m</td>
            <td aria-label=\"Стартовый номер\" class=\"has-text-align-center\" data-align=\"center\">{$row['num']}</td>
            <td aria-label=\"ФИО Пилот/Штурман\" class=\"has-text-align-center\" data-align=\"center\">{$row['participants_name']}</td>
            <td aria-label=\"Автомобиль\" class=\"has-text-align-center scores-border\" data-align=\"center\">
                {$row['car']}<br>
            </td>";
        
        for ($n = 1; $n <= $count_event; $n++) {
            $event_id = $rows_ev[$n-1]['event_id'];
            $isValidEvent = $validEvents[$class_name][$event_id] ?? true;
            
            if($row["e{$n}_missing"]==1){
                $table .= "<td id=\"mm{$row['participant_id']}$n\" 
                      aria-label=\"Место $n этап\" 
                      class=\"has-text-align-center results_scores\" data-align=\"center\"".(!$isValidEvent ? ' bgcolor="Coral"' : '').">Сход</td>
                      <td id=\"t{$row['participant_id']}$n\" 
                      aria-label=\"Баллы $n этап (x".rtrim($rows_ev[$n-1]['coefficient'] ?? '0', '0.').")\" 
                      class=\"has-text-align-center results_scores scores-border\" data-align=\"center\"".(!$isValidEvent ? ' bgcolor="Coral"' : '').">0 (0)</td>";
            }elseif($row["e{$n}_disq"]==1){
                $table .= "<td id=\"mm{$row['participant_id']}$n\" 
                      aria-label=\"Место $n этап\" 
                      class=\"has-text-align-center results_scores\" data-align=\"center\"".(!$isValidEvent ? ' bgcolor="Coral"' : '').">Дискв.</td>
                      <td id=\"t{$row['participant_id']}$n\" 
                      aria-label=\"Баллы $n этап (x".rtrim($rows_ev[$n-1]['coefficient'] ?? '0', '0.').")\" 
                      class=\"has-text-align-center results_scores scores-border\" data-align=\"center\"".(!$isValidEvent ? ' bgcolor="Coral"' : '').">0 (0)</td>";
            }else{
                $points = $row["e{$n}_points"];
                $calculated = $points * $row["e{$n}_coef"];
                $color = (abs($row['min'] - $calculated) > 0.0001 ? 'bgcolor="PaleGreen" ' : '');
                
                if (!$isValidEvent) {
                    $color = 'bgcolor="Coral" ';
                }
                
                $table .= "<td $color id=\"mm{$row['participant_id']}$n\" 
                      aria-label=\"Место $n этап\" 
                      class=\"has-text-align-center results_scores\" data-align=\"center\">"
                      .$row["e{$n}_position"]."</td>
                      <td $color id=\"t{$row['participant_id']}$n\" 
                      aria-label=\"Баллы $n этап (x".rtrim($rows_ev[$n-1]['coefficient'] ?? '0', '0.').")\" 
                      class=\"has-text-align-center results_scores scores-border\" data-align=\"center\">
                      $points ($calculated)".(!$isValidEvent ? "*" : "")."</td>";
            }
        }
        
        $table .= "<td aria-label=\"Баллы\" class=\"has-text-align-center results_scores_all\" data-align=\"center\">{$row['sum']}</td>
                </tr>";
    }
    
    $table .= "</tbody></table>";
    
    // Добавляем пояснение, если есть неучтенные этапы
    $hasInvalidEvents = false;
    foreach ($validEvents[$class_name] ?? [] as $event_id => $valid) {
        if (!$valid) {
            $hasInvalidEvents = true;
            break;
        }
    }
    
    if ($hasInvalidEvents) {
        $table .= "<div class=\"results-info\">
            <p>* Этапы, отмеченные звездочкой, не учитываются в общем зачёте, так как количество участников в классе было меньше минимального ($minParticipants)</p>
        </div>";
    }
    
    $table .= "</div></div>";
    
    mysqli_close($link);
    return $table;
}
?>