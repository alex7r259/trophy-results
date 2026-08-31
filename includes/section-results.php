<?php

if (!defined('ABSPATH')) {
    exit;
}

function results_stage_sections_tables() {
    return array(
        'sections' => 'stage_sections',
        'categories' => 'stage_section_categories',
        'results' => 'stage_section_results',
    );
}

function results_install_stage_sections_schema() {
    $link = db_connect();
    mysqli_query($link, "CREATE TABLE IF NOT EXISTS `stage_sections` (
        `section_id` INT NOT NULL AUTO_INCREMENT,
        `event_id` INT NOT NULL,
        `section_number` INT NOT NULL DEFAULT 1,
        `section_name` VARCHAR(255) NOT NULL,
        `description` TEXT NULL,
        `scoring_type` VARCHAR(50) NOT NULL DEFAULT 'CHECKPOINT_POINTS_TIME',
        `points_source` VARCHAR(50) NOT NULL DEFAULT 'pointstable',
        `uses_checkpoint_points` TINYINT(1) NOT NULL DEFAULT 1,
        `max_checkpoints` INT NULL,
        `max_time` VARCHAR(16) NULL,
        `rules_config` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`section_id`),
        KEY `idx_event_order` (`event_id`, `section_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    mysqli_query($link, "CREATE TABLE IF NOT EXISTS `stage_section_categories` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `section_id` INT NOT NULL,
        `class_id` INT NOT NULL,
        `display_order` INT NOT NULL DEFAULT 1,
        `scoring_type_override` VARCHAR(50) NULL,
        `rules_config_override` TEXT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_section_class` (`section_id`, `class_id`),
        KEY `idx_class_order` (`class_id`, `display_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    mysqli_query($link, "CREATE TABLE IF NOT EXISTS `stage_section_results` (
        `section_result_id` INT NOT NULL AUTO_INCREMENT,
        `section_id` INT NOT NULL,
        `class_id` INT NOT NULL,
        `participant_id` INT NOT NULL,
        `checkpoints_count` INT NOT NULL DEFAULT 0,
        `checkpoint_points` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `raw_time` VARCHAR(16) NULL,
        `penalty_points` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `penalty_time` INT NOT NULL DEFAULT 0,
        `calculated_place` INT NULL,
        `calculated_points` DECIMAL(10,2) NULL,
        `manual_place` INT NULL,
        `manual_points` DECIMAL(10,2) NULL,
        `final_place` INT NULL,
        `final_points` DECIMAL(10,2) NULL,
        `status` VARCHAR(32) NOT NULL DEFAULT 'finished',
        `manual_status` VARCHAR(32) NULL,
        `final_status` VARCHAR(32) NOT NULL DEFAULT 'finished',
        `note` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`section_result_id`),
        UNIQUE KEY `uniq_section_class_participant` (`section_id`, `class_id`, `participant_id`),
        KEY `idx_class_section_place` (`class_id`, `section_id`, `final_place`),
        KEY `idx_participant` (`participant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
}

function results_section_statuses() {
    return array('finished', 'dns', 'dnf', 'dsq', 'no_result');
}

function results_section_scoring_types() {
    return array('TIME', 'CHECKPOINT_POINTS_TIME', 'CHECKPOINT_COUNT_TIME', 'CHECKPOINT_POINTS', 'CUSTOM');
}

function results_time_to_seconds($time) {
    if ($time === null || $time === '') return null;
    if (!preg_match('/^(\d{1,3}):([0-5]\d):([0-5]\d)$/', $time, $m)) return false;
    return ((int)$m[1] * 3600) + ((int)$m[2] * 60) + (int)$m[3];
}

function results_get_points_by_position($season_id) {
    $link = db_connect();
    $stmt = mysqli_prepare($link, "SELECT position, points FROM Pointstable WHERE season_id = ? ORDER BY position");
    mysqli_stmt_bind_param($stmt, 'i', $season_id);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    $map = array();
    foreach ($rows as $row) $map[(int)$row['position']] = (float)$row['points'];
    mysqli_stmt_close($stmt);
    return $map;
}

function results_compare_section_rows($a, $b, $type) {
    if ($a['rankable'] !== $b['rankable']) return $a['rankable'] ? -1 : 1;
    if (!$a['rankable']) return 0;
    if ($type === 'TIME') return $a['time_seconds'] <=> $b['time_seconds'];
    if ($type === 'CHECKPOINT_POINTS') return $b['checkpoint_points'] <=> $a['checkpoint_points'];
    if ($type === 'CHECKPOINT_COUNT_TIME') {
        if ($r = $b['checkpoints_count'] <=> $a['checkpoints_count']) return $r;
        return $a['time_seconds'] <=> $b['time_seconds'];
    }
    if ($r = $b['checkpoint_points'] <=> $a['checkpoint_points']) return $r;
    return $a['time_seconds'] <=> $b['time_seconds'];
}

function results_recalculate_section($section_id, $class_id) {
    $link = db_connect();
    $section_id = (int)$section_id; $class_id = (int)$class_id;
    $meta = mysqli_fetch_assoc(mysqli_query($link, "SELECT ss.*, e.season_id, ssc.scoring_type_override FROM stage_sections ss JOIN Events e ON ss.event_id=e.event_id JOIN stage_section_categories ssc ON ssc.section_id=ss.section_id WHERE ss.section_id=$section_id AND ssc.class_id=$class_id"));
    if (!$meta) return false;
    $type = $meta['scoring_type_override'] ?: $meta['scoring_type'];
    if ($type === 'CUSTOM') $type = 'CHECKPOINT_POINTS_TIME';
    $points = results_get_points_by_position((int)$meta['season_id']);
    $res = mysqli_query($link, "SELECT * FROM stage_section_results WHERE section_id=$section_id AND class_id=$class_id");
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $status = $row['manual_status'] ?: $row['status'];
        $time = results_time_to_seconds($row['raw_time']);
        $row['final_status_calc'] = $status;
        $row['time_seconds'] = ($time === false || $time === null) ? PHP_INT_MAX : $time;
        $row['rankable'] = ($status === 'finished' && ($type === 'CHECKPOINT_POINTS' || $row['time_seconds'] !== PHP_INT_MAX));
    }
    unset($row);
    usort($rows, function($a, $b) use ($type) { return results_compare_section_rows($a, $b, $type); });
    $prev = null; $place = 0;
    foreach ($rows as $i => $row) {
        if ($row['rankable']) {
            $same = $prev && results_compare_section_rows($row, $prev, $type) === 0;
            $place = $same ? $place : $i + 1;
            $calc_points = $points[$place] ?? 0;
            $prev = $row;
        } else {
            $place = null; $calc_points = 0;
        }
        $final_place = $row['manual_place'] !== null && $row['manual_place'] !== '' ? (int)$row['manual_place'] : $place;
        $final_points = $row['manual_points'] !== null && $row['manual_points'] !== '' ? (float)$row['manual_points'] : $calc_points;
        $final_status = $row['manual_status'] ?: $row['status'];
        $stmt = mysqli_prepare($link, "UPDATE stage_section_results SET calculated_place=?, calculated_points=?, final_place=?, final_points=?, final_status=? WHERE section_result_id=?");
        mysqli_stmt_bind_param($stmt, 'idiisi', $place, $calc_points, $final_place, $final_points, $final_status, $row['section_result_id']);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    results_recalculate_stage_from_sections((int)$meta['event_id'], $class_id, (int)$meta['season_id']);
    return true;
}

function results_recalculate_stage_from_sections($event_id, $class_id, $season_id) {
    $link = db_connect();
    $event_id=(int)$event_id; $class_id=(int)$class_id; $season_id=(int)$season_id;
    $points = results_get_points_by_position($season_id);
    $sql = "SELECT p.participant_id, COALESCE(SUM(CASE WHEN sr.final_status='finished' THEN sr.final_points ELSE 0 END),0) section_total
            FROM Participants p
            JOIN stage_section_results sr ON sr.participant_id=p.participant_id AND sr.class_id=$class_id
            JOIN stage_sections ss ON ss.section_id=sr.section_id AND ss.event_id=$event_id
            WHERE p.season_id=$season_id GROUP BY p.participant_id ORDER BY section_total DESC, p.num ASC";
    $rows = mysqli_fetch_all(mysqli_query($link, $sql), MYSQLI_ASSOC);
    $prev = null; $place = 0;
    foreach ($rows as $i => $row) {
        $same = $prev !== null && (float)$row['section_total'] === (float)$prev;
        $place = $same ? $place : $i + 1; $prev = (float)$row['section_total'];
        $stage_points = $points[$place] ?? 0;
        $point_id_row = mysqli_fetch_assoc(mysqli_query($link, "SELECT points_id FROM Pointstable WHERE season_id=$season_id AND position=$place LIMIT 1"));
        if (!$point_id_row) continue;
        $point_id = (int)$point_id_row['points_id'];
        $participant_id = (int)$row['participant_id'];
        $exists = mysqli_fetch_assoc(mysqli_query($link, "SELECT results_id, disq FROM Results WHERE season_id=$season_id AND event_id=$event_id AND class_id=$class_id AND participant_id=$participant_id LIMIT 1"));
        if ($exists) {
            if ((int)$exists['disq'] === 0) mysqli_query($link, "UPDATE Results SET points_id=$point_id, missing=0 WHERE results_id=".(int)$exists['results_id']);
        } else {
            mysqli_query($link, "INSERT INTO Results (season_id,event_id,class_id,participant_id,points_id,missing,disq) VALUES ($season_id,$event_id,$class_id,$participant_id,$point_id,0,0)");
        }
    }
}

function results_stage_sections_ajax_bootstrap() {
    add_action('wp_ajax_results_install_stage_sections_schema', 'results_ajax_install_stage_sections_schema');
    add_action('wp_ajax_results_load_stage_sections', 'results_ajax_load_stage_sections');
    add_action('wp_ajax_results_save_stage_section', 'results_ajax_save_stage_section');
    add_action('wp_ajax_results_delete_stage_section', 'results_ajax_delete_stage_section');
    add_action('wp_ajax_results_save_section_results', 'results_ajax_save_section_results');
    add_action('wp_ajax_results_recalculate_section', 'results_ajax_recalculate_section');
}
add_action('init', 'results_stage_sections_ajax_bootstrap');

function results_ajax_install_stage_sections_schema() { check_ajax_referer('result_settings_actions_nonce','nonce'); results_install_stage_sections_schema(); wp_send_json_success('ok'); }
function results_ajax_load_stage_sections() {
    check_ajax_referer('result_settings_actions_nonce','nonce'); results_install_stage_sections_schema();
    $event_id = absint($_POST['event_id'] ?? 0); $class_id = absint($_POST['class_id'] ?? 0); $link = db_connect();
    $sections = mysqli_fetch_all(mysqli_query($link, "SELECT ss.*, ssc.class_id, ssc.display_order, COALESCE(ssc.scoring_type_override, ss.scoring_type) effective_scoring_type FROM stage_sections ss JOIN stage_section_categories ssc ON ssc.section_id=ss.section_id WHERE ss.event_id=$event_id AND ssc.class_id=$class_id ORDER BY ssc.display_order, ss.section_number"), MYSQLI_ASSOC);
    $results = mysqli_fetch_all(mysqli_query($link, "SELECT sr.* FROM stage_section_results sr JOIN stage_sections ss ON ss.section_id=sr.section_id WHERE ss.event_id=$event_id AND sr.class_id=$class_id"), MYSQLI_ASSOC);
    // Участники СУ берутся из результатов конкретного этапа и категории, а не из всего сезона.
    $participants = mysqli_fetch_all(mysqli_query($link, "SELECT DISTINCT p.participant_id, p.participants_name, p.city, p.car, p.num
        FROM Results r JOIN Participants p ON p.participant_id=r.participant_id
        JOIN Events e ON e.event_id=r.event_id
        WHERE r.event_id=$event_id AND r.class_id=$class_id AND r.season_id=e.season_id
        ORDER BY CAST(p.num AS UNSIGNED), p.participants_name"), MYSQLI_ASSOC);
    wp_send_json_success(array('sections'=>$sections, 'results'=>$results, 'participants'=>$participants));
}
function results_ajax_save_stage_section() {
    check_ajax_referer('result_settings_actions_nonce','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав'); results_install_stage_sections_schema(); $link=db_connect();
    $id=absint($_POST['section_id']??0); $event_id=absint($_POST['event_id']??0); $class_id=absint($_POST['class_id']??0);
    $name=sanitize_text_field($_POST['section_name']??'СУ'); $num=absint($_POST['section_number']??1); $desc=sanitize_textarea_field($_POST['description']??'');
    $type=sanitize_text_field($_POST['scoring_type']??'CHECKPOINT_POINTS_TIME'); if(!in_array($type, results_section_scoring_types(), true)) $type='CHECKPOINT_POINTS_TIME';
    if ($id) { $stmt=mysqli_prepare($link,"UPDATE stage_sections SET section_number=?, section_name=?, description=?, scoring_type=? WHERE section_id=?"); mysqli_stmt_bind_param($stmt,'isssi',$num,$name,$desc,$type,$id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
    else { $stmt=mysqli_prepare($link,"INSERT INTO stage_sections (event_id,section_number,section_name,description,scoring_type) VALUES (?,?,?,?,?)"); mysqli_stmt_bind_param($stmt,'iisss',$event_id,$num,$name,$desc,$type); mysqli_stmt_execute($stmt); $id=mysqli_insert_id($link); mysqli_stmt_close($stmt); }
    mysqli_query($link,"INSERT IGNORE INTO stage_section_categories (section_id,class_id,display_order) VALUES ($id,$class_id,$num)"); wp_send_json_success(array('section_id'=>$id));
}
function results_ajax_save_section_results() {
    check_ajax_referer('result_settings_actions_nonce','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав'); results_install_stage_sections_schema(); $link=db_connect();
    $section_id=absint($_POST['section_id']??0); $class_id=absint($_POST['class_id']??0); $rows=$_POST['rows']??array(); if(!is_array($rows)) wp_send_json_error('Некорректные строки');
    $section_meta = mysqli_fetch_assoc(mysqli_query($link, "SELECT ss.event_id, ssc.class_id FROM stage_sections ss JOIN stage_section_categories ssc ON ssc.section_id=ss.section_id WHERE ss.section_id=$section_id AND ssc.class_id=$class_id LIMIT 1"));
    if (!$section_meta) wp_send_json_error('СУ не найдено');
    $event_id = (int)$section_meta['event_id'];
    foreach($rows as $row){
        $pid=absint($row['participant_id']??0); if(!$pid) continue;
        // Разрешаем сохранять только экипажи, реально заявленные на этот этап в этой категории.
        $eligible = mysqli_fetch_assoc(mysqli_query($link, "SELECT participant_id FROM Results WHERE event_id=$event_id AND class_id=$class_id AND participant_id=$pid LIMIT 1"));
        if (!$eligible) continue;
        $cc=max(0,(int)($row['checkpoints_count']??0)); $cp=max(0,(float)($row['checkpoint_points']??0)); $time=sanitize_text_field($row['raw_time']??''); if($time!=='' && results_time_to_seconds($time)===false) continue; $status=sanitize_text_field($row['status']??'finished'); if(!in_array($status, results_section_statuses(), true)) $status='finished'; $note=sanitize_textarea_field($row['note']??''); $stmt=mysqli_prepare($link,"INSERT INTO stage_section_results (section_id,class_id,participant_id,checkpoints_count,checkpoint_points,raw_time,status,final_status,note) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE checkpoints_count=VALUES(checkpoints_count), checkpoint_points=VALUES(checkpoint_points), raw_time=VALUES(raw_time), status=VALUES(status), final_status=VALUES(final_status), note=VALUES(note)"); mysqli_stmt_bind_param($stmt,'iiiidssss',$section_id,$class_id,$pid,$cc,$cp,$time,$status,$status,$note); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    results_recalculate_section($section_id,$class_id); wp_send_json_success('saved');
}

function results_ajax_delete_stage_section() {
    check_ajax_referer('result_settings_actions_nonce','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав'); results_install_stage_sections_schema(); $link=db_connect();
    $section_id = absint($_POST['section_id'] ?? 0);
    if (!$section_id) wp_send_json_error('Не указан СУ');
    $meta = mysqli_fetch_assoc(mysqli_query($link, "SELECT event_id FROM stage_sections WHERE section_id=$section_id LIMIT 1"));
    if (!$meta) wp_send_json_error('СУ не найдено');
    $event_id = (int)$meta['event_id'];
    $classes = mysqli_fetch_all(mysqli_query($link, "SELECT class_id FROM stage_section_categories WHERE section_id=$section_id"), MYSQLI_ASSOC);
    mysqli_begin_transaction($link);
    try {
        mysqli_query($link, "DELETE FROM stage_section_results WHERE section_id=$section_id");
        mysqli_query($link, "DELETE FROM stage_section_categories WHERE section_id=$section_id");
        mysqli_query($link, "DELETE FROM stage_sections WHERE section_id=$section_id LIMIT 1");
        if (mysqli_errno($link)) throw new Exception(mysqli_error($link));
        mysqli_commit($link);
    } catch (Exception $e) {
        mysqli_rollback($link);
        wp_send_json_error('Не удалось удалить СУ: '.$e->getMessage());
    }
    // Пересчитать агрегаты оставшихся СУ по каждой затронутой категории.
    foreach ($classes as $class) {
        $class_id = (int)$class['class_id'];
        $event = mysqli_fetch_assoc(mysqli_query($link, "SELECT season_id FROM Events WHERE event_id=$event_id LIMIT 1"));
        if ($event) results_recalculate_stage_from_sections($event_id, $class_id, (int)$event['season_id']);
    }
    wp_send_json_success('deleted');
}

function results_ajax_recalculate_section() { check_ajax_referer('result_settings_actions_nonce','nonce'); if (!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав'); $ok=results_recalculate_section(absint($_POST['section_id']??0), absint($_POST['class_id']??0)); $ok ? wp_send_json_success('recalculated') : wp_send_json_error('СУ не найден'); }

function results_render_section_stage_protocol($event_id, $season_id, $class_name) {
    $link = db_connect();
    results_install_stage_sections_schema();
    $event_id = (int)$event_id; $season_id = (int)$season_id;
    $class_name_sql = mysqli_real_escape_string($link, $class_name);
    $class = mysqli_fetch_assoc(mysqli_query($link, "SELECT class_id, class_name FROM Class WHERE season_id=$season_id AND class_name='$class_name_sql' LIMIT 1"));
    if (!$class) return '';
    $class_id = (int)$class['class_id'];
    $sections = mysqli_fetch_all(mysqli_query($link, "SELECT ss.* FROM stage_sections ss JOIN stage_section_categories ssc ON ssc.section_id=ss.section_id WHERE ss.event_id=$event_id AND ssc.class_id=$class_id ORDER BY ssc.display_order, ss.section_number"), MYSQLI_ASSOC);
    if (!$sections) return '';
    $event = mysqli_fetch_assoc(mysqli_query($link, "SELECT event_name FROM Events WHERE event_id=$event_id LIMIT 1"));
    $rows = mysqli_fetch_all(mysqli_query($link, "SELECT p.participant_id, p.participants_name, p.city, p.num, p.car, r.points_id, pt.points stage_points, pt.position stage_place
        FROM Results r JOIN Participants p ON p.participant_id=r.participant_id JOIN Pointstable pt ON pt.points_id=r.points_id
        WHERE r.event_id=$event_id AND r.class_id=$class_id ORDER BY pt.position, p.num"), MYSQLI_ASSOC);
    $matrix = array();
    $section_results = mysqli_fetch_all(mysqli_query($link, "SELECT sr.* FROM stage_section_results sr JOIN stage_sections ss ON ss.section_id=sr.section_id WHERE ss.event_id=$event_id AND sr.class_id=$class_id"), MYSQLI_ASSOC);
    foreach ($section_results as $sr) $matrix[(int)$sr['participant_id']][(int)$sr['section_id']] = $sr;
    ob_start();
    ?>
    <div class="results-container section-results-container">
        <h2><?php echo esc_html($event['event_name'] ?? 'Этап'); ?></h2>
        <h3>Категория: <?php echo esc_html($class['class_name']); ?></h3>
        <figure class="wp-block-table is-style-stripes"><table class="results-table section-results-table">
            <thead><tr><th>Место</th><th>№</th><th>Экипаж</th><?php foreach ($sections as $section): ?><th><?php echo esc_html($section['section_name']); ?></th><?php endforeach; ?><th>Итого СУ</th><th>Очки серии</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): $total = 0; ?>
                <tr>
                    <td><?php echo esc_html($row['stage_place']); ?></td><td><?php echo esc_html($row['num']); ?></td><td><?php echo esc_html($row['participants_name']); ?></td>
                    <?php foreach ($sections as $section): $sr = $matrix[(int)$row['participant_id']][(int)$section['section_id']] ?? null; $total += $sr ? (float)$sr['final_points'] : 0; ?>
                        <td title="КП: <?php echo esc_attr($sr['checkpoints_count'] ?? ''); ?>; время: <?php echo esc_attr($sr['raw_time'] ?? ''); ?>; статус: <?php echo esc_attr($sr['final_status'] ?? ''); ?>">
                            <?php echo $sr ? esc_html(($sr['final_place'] ? $sr['final_place'].' место, ' : '').$sr['final_points'].' оч.') : '—'; ?>
                        </td>
                    <?php endforeach; ?>
                    <td><strong><?php echo esc_html($total); ?></strong></td><td><?php echo esc_html($row['stage_points']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></figure>
    </div>
    <?php
    return ob_get_clean();
}
