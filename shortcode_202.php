<?php
function results_show_single_event($atts) {
    // Инициализация параметров
    $defaults = [
        'event_id' => 1,
        'season_id' => get_query_var('season_id') ?: 1,
        'class_name' => get_query_var('class_name') ?: 'Полироль'
    ];
    $params = shortcode_atts($defaults, $atts, 'results_202');
    
    // Подключение к БД с обработкой ошибок
    $db = new mysqli("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
    if ($db->connect_error) {
        return "Ошибка подключения к базе данных";
    }

    // Получаем список классов
    $classes = $db->query("SELECT class_name FROM class WHERE season_id = ".(int)$params['season_id'])
                ->fetch_all(MYSQLI_ASSOC);
    $class_names = array_column($classes, 'class_name');
    
    // Получаем этапы соревнования
    $stages = $db->query(sprintf(
        "SELECT DISTINCT stage_name 
         FROM event_stages er 
         JOIN Class c ON er.class_id = c.class_id
         WHERE c.class_name = '%s' AND er.event_id = %d
         ORDER BY stage_name",
        $db->real_escape_string($params['class_name']),
        (int)$params['event_id']
    ))->fetch_all(MYSQLI_ASSOC);

    // Формируем SQL запрос
    $select_fields = [
        "p.participant_id",
        "p.participants_name", 
        "p.num",
        "p.car"
    ];
    
    $case_statements = [];
    foreach ($stages as $i => $stage) {
        $stage_name = $db->real_escape_string($stage['stage_name']);
        $n = $i + 1;
        
        $case_statements = array_merge($case_statements, [
            "MAX(CASE WHEN er.stage_name = '$stage_name' THEN er.description END) AS e{$n}_description",
            "MAX(CASE WHEN er.stage_name = '$stage_name' THEN er.scores END) AS e{$n}_scores",
            "MAX(CASE WHEN er.stage_name = '$stage_name' THEN er.penalty_scores END) AS e{$n}_penalty_scores",
            "MAX(CASE WHEN er.stage_name = '$stage_name' THEN TIMEDIFF(er.time_finish, er.time_st) END) AS e{$n}_stage_time",
            "MAX(CASE WHEN er.stage_name = '$stage_name' THEN er.penalty_time END) AS e{$n}_penalty_time"
        ]);
    }
    
    $sql = sprintf(
        "SELECT %s, SUM(er.scores - er.penalty_scores) AS total_scores,
        SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(er.time_finish, er.time_st))) + SUM(er.penalty_time)) AS total_time
        FROM event_stages er
        JOIN Participants p ON er.participant_id = p.participant_id
        JOIN Class c ON er.class_id = c.class_id
        WHERE c.class_name = '%s' AND er.event_id = %d
        GROUP BY p.participant_id, p.participants_name, p.num, p.car
        ORDER BY total_scores DESC, total_time ASC",
        implode(', ', array_merge($select_fields, $case_statements)),
        $db->real_escape_string($params['class_name']),
        (int)$params['event_id']
    );

    // Выполняем запрос
    $result = $db->query($sql);
    
    
    if (!$result) {
        error_log("SQL Error: ".$db->error);
        return "Ошибка при получении данных";
    }
    $participants = $result->fetch_all(MYSQLI_ASSOC);
    var_dump($participants);
    
    // Генерируем ссылки на классы
    $current_url = home_url();
    $class_links = array_map(function($class) use ($params, $current_url) {
        $url = add_query_arg([
            'class_name' => urlencode($class),
            'event_id' => $params['event_id']
        ], $current_url);
        
        $style = $params['class_name'] === $class ? 'font-weight:bold' : '';
        return sprintf('<a style="margin-right:7px;%s" href="%s">%s</a>', 
                     $style, esc_url($url), esc_html($class));
    }, $class_names);
    
    // Определяем видимые колонки
    $visible_columns = [
        'place' => true,
        'num' => !empty(array_column($participants, 'num')),
        'participants_name' => true,
        'car' => !empty(array_column($participants, 'car'))
    ];
    
    // Генерируем таблицу
    ob_start();
    ?>
    <div class="results-container"><?= implode('', $class_links) ?></div>
    <figure class="wp-block-table is-style-stripes">
        <table class="delivery">
            <thead>
                <tr>
                    <?php if ($visible_columns['place']): ?>
                        <th class="has-text-align-center" rowspan="2">Место</th>
                    <?php endif; ?>
                    <?php if ($visible_columns['num']): ?>
                        <th class="has-text-align-center" rowspan="2">№</th>
                    <?php endif; ?>
                    <th class="has-text-align-center" rowspan="2">Участник</th>
                    <?php if ($visible_columns['car']): ?>
                        <th class="has-text-align-center" rowspan="2">Автомобиль</th>
                    <?php endif; ?>
                    
                    <?php foreach ($stages as $stage): ?>
                        <th class="has-text-align-center" colspan="4"><?= esc_html($stage['stage_name']) ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($stages as $stage): ?>
                        <th class="has-text-align-center">Точки</th>
                        <th class="has-text-align-center">Баллы</th>
                        <th class="has-text-align-center">Время</th>
                        <th class="has-text-align-center">Штраф</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $position = 1;
                $prev_scores = null;
                $prev_time = null;
                
                foreach ($participants as $i => $row): 
                    $current_scores = $row['total_scores'];
                    $current_time = $row['total_time'];
                    
                    if ($i > 0 && ($current_scores != $prev_scores || $current_time != $prev_time)) {
                        $position = $i + 1;
                    }
                    $prev_scores = $current_scores;
                    $prev_time = $current_time;
                ?>
                    <tr>
                        <?php if ($visible_columns['place']): ?>
                            <td class="has-text-align-center"><?= $position ?></td>
                        <?php endif; ?>
                        <?php if ($visible_columns['num']): ?>
                            <td class="has-text-align-center"><?= esc_html($row['num']) ?></td>
                        <?php endif; ?>
                        <td class="has-text-align-center"><?= esc_html($row['participants_name']) ?></td>
                        <?php if ($visible_columns['car']): ?>
                            <td class="has-text-align-center"><?= esc_html($row['car']) ?></td>
                        <?php endif; ?>
                        
                        <?php foreach ($stages as $n => $stage): 
                            $stage_num = $n + 1;
                        ?>
                            <td class="has-text-align-center"><?= esc_html($row["e{$stage_num}_description"] ?? '') ?></td>
                            <td class="has-text-align-center"><?= esc_html($row["e{$stage_num}_scores"] ?? '') ?></td>
                            <td class="has-text-align-center"><?= esc_html($row["e{$stage_num}_stage_time"] ?? '') ?></td>
                            <td class="has-text-align-center"><?= esc_html($row["e{$stage_num}_penalty_time"] ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="total-row">
                        <td colspan="<?= count($visible_columns) ?>" class="has-text-align-right">Итого:</td>
                        <?php foreach ($stages as $n => $stage): ?>
                            <?php if ($n === count($stages) - 1): ?>
                                <td colspan="4" class="has-text-align-center">
                                    <strong>Баллы: <?= $row['total_scores'] ?></strong><br>
                                    <strong>Время: <?= $row['total_time'] ?></strong>
                                </td>
                            <?php else: ?>
                                <td colspan="4"></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </figure>
    <?php
    
    $db->close();
    return ob_get_clean();
}