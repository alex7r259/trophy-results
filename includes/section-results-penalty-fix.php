<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Correct penalty handling for stage sections.
 *
 * checkpoint_points = original points earned on checkpoints.
 * penalty_points    = separately stored penalty.
 * ranking_points    = checkpoint_points - penalty_points.
 * final_points      = points awarded for the place on the SU (100, 88, ...).
 */

function results_penalty_ranking_points(array $row) {
    $checkpoint_points = (float)($row['checkpoint_points'] ?? 0);
    $penalty_points = max(0, (float)($row['penalty_points'] ?? 0));
    return max(0, $checkpoint_points - $penalty_points);
}

function results_penalty_compare_section_rows($a, $b, $type) {
    if ($a['rankable'] !== $b['rankable']) {
        return $a['rankable'] ? -1 : 1;
    }
    if (!$a['rankable']) {
        return 0;
    }

    if ($type === 'TIME') {
        return $a['time_seconds'] <=> $b['time_seconds'];
    }

    if ($type === 'CHECKPOINT_COUNT_TIME') {
        if ($r = ($b['checkpoints_count'] <=> $a['checkpoints_count'])) {
            return $r;
        }
        return $a['time_seconds'] <=> $b['time_seconds'];
    }

    if ($r = ($b['ranking_points'] <=> $a['ranking_points'])) {
        return $r;
    }

    return $a['time_seconds'] <=> $b['time_seconds'];
}

function results_recalculate_section_with_penalty($section_id, $class_id) {
    $link = db_connect();
    $section_id = (int)$section_id;
    $class_id = (int)$class_id;

    $meta = mysqli_fetch_assoc(mysqli_query($link, "
        SELECT ss.*, e.season_id, ssc.scoring_type_override
        FROM stage_sections ss
        JOIN Events e ON ss.event_id=e.event_id
        JOIN stage_section_categories ssc ON ssc.section_id=ss.section_id
        WHERE ss.section_id=$section_id AND ssc.class_id=$class_id
        LIMIT 1
    "));

    if (!$meta) {
        return false;
    }

    $type = $meta['scoring_type_override'] ?: $meta['scoring_type'];
    if ($type === 'CUSTOM') {
        $type = 'CHECKPOINT_POINTS_TIME';
    }

    $points = results_get_points_by_position((int)$meta['season_id']);
    $res = mysqli_query($link, "SELECT * FROM stage_section_results WHERE section_id=$section_id AND class_id=$class_id");
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);

    foreach ($rows as &$row) {
        $status = $row['manual_status'] ?: $row['status'];
        $time = results_time_to_seconds($row['raw_time']);

        $row['time_seconds'] = ($time === false || $time === null) ? PHP_INT_MAX : $time;
        $row['ranking_points'] = results_penalty_ranking_points($row);
        $row['rankable'] = ($status === 'finished' && (
            $type === 'CHECKPOINT_POINTS' ||
            $row['time_seconds'] !== PHP_INT_MAX
        ));
    }
    unset($row);

    usort($rows, function($a, $b) use ($type) {
        return results_penalty_compare_section_rows($a, $b, $type);
    });

    $prev = null;
    $place = 0;

    foreach ($rows as $i => $row) {
        if ($row['rankable']) {
            $same = $prev && results_penalty_compare_section_rows($row, $prev, $type) === 0;
            $place = $same ? $place : $i + 1;
            $calc_points = $points[$place] ?? 0;
            $prev = $row;
        } else {
            $place = null;
            $calc_points = 0;
        }

        $final_place = ($row['manual_place'] !== null && $row['manual_place'] !== '')
            ? (int)$row['manual_place']
            : $place;

        $final_points = ($row['manual_points'] !== null && $row['manual_points'] !== '')
            ? (float)$row['manual_points']
            : $calc_points;

        $final_status = $row['manual_status'] ?: $row['status'];

        $stmt = mysqli_prepare($link, "
            UPDATE stage_section_results
            SET calculated_place=?, calculated_points=?, final_place=?, final_points=?, final_status=?
            WHERE section_result_id=?
        ");
        mysqli_stmt_bind_param(
            $stmt,
            'idiisi',
            $place,
            $calc_points,
            $final_place,
            $final_points,
            $final_status,
            $row['section_result_id']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    results_recalculate_stage_from_sections((int)$meta['event_id'], $class_id, (int)$meta['season_id']);
    return true;
}

function results_ajax_save_section_results_with_penalty() {
    check_ajax_referer('result_settings_actions_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    results_install_stage_sections_schema();
    $link = db_connect();

    $section_id = absint($_POST['section_id'] ?? 0);
    $class_id = absint($_POST['class_id'] ?? 0);
    $rows = $_POST['rows'] ?? array();

    if (!$section_id || !$class_id || !is_array($rows)) {
        wp_send_json_error('Некорректные данные');
    }

    $section_meta = mysqli_fetch_assoc(mysqli_query($link, "
        SELECT ss.event_id, ssc.class_id
        FROM stage_sections ss
        JOIN stage_section_categories ssc ON ssc.section_id=ss.section_id
        WHERE ss.section_id=$section_id AND ssc.class_id=$class_id
        LIMIT 1
    "));

    if (!$section_meta) {
        wp_send_json_error('СУ не найдено');
    }

    $event_id = (int)$section_meta['event_id'];

    foreach ($rows as $row) {
        $pid = absint($row['participant_id'] ?? 0);
        if (!$pid) {
            continue;
        }

        $eligible = mysqli_fetch_assoc(mysqli_query($link, "
            SELECT participant_id
            FROM Results
            WHERE event_id=$event_id AND class_id=$class_id AND participant_id=$pid
            LIMIT 1
        "));

        if (!$eligible) {
            continue;
        }

        $cc = max(0, (int)($row['checkpoints_count'] ?? 0));
        $checkpoint_points = max(0, (float)($row['checkpoint_points'] ?? 0));
        $penalty_points = max(0, (float)($row['penalty_points'] ?? 0));
        $time = sanitize_text_field($row['raw_time'] ?? '');

        if ($time !== '' && results_time_to_seconds($time) === false) {
            continue;
        }

        $status = sanitize_text_field($row['status'] ?? 'finished');
        if (!in_array($status, results_section_statuses(), true)) {
            $status = 'finished';
        }

        $note = sanitize_textarea_field($row['note'] ?? '');

        // Store original checkpoint points and penalty separately.
        $stmt = mysqli_prepare($link, "
            INSERT INTO stage_section_results
                (section_id, class_id, participant_id, checkpoints_count,
                 checkpoint_points, raw_time, penalty_points, status, final_status, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                checkpoints_count=VALUES(checkpoints_count),
                checkpoint_points=VALUES(checkpoint_points),
                raw_time=VALUES(raw_time),
                penalty_points=VALUES(penalty_points),
                status=VALUES(status),
                final_status=VALUES(final_status),
                note=VALUES(note)
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'iiiidsdsss',
            $section_id,
            $class_id,
            $pid,
            $cc,
            $checkpoint_points,
            $time,
            $penalty_points,
            $status,
            $status,
            $note
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    if (!results_recalculate_section_with_penalty($section_id, $class_id)) {
        wp_send_json_error('Не удалось пересчитать результаты СУ');
    }

    wp_send_json_success('saved');
}

function results_ajax_recalculate_section_with_penalty() {
    check_ajax_referer('result_settings_actions_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    $section_id = absint($_POST['section_id'] ?? 0);
    $class_id = absint($_POST['class_id'] ?? 0);

    if (!$section_id || !$class_id) {
        wp_send_json_error('Не указано СУ или категория');
    }

    if (!results_recalculate_section_with_penalty($section_id, $class_id)) {
        wp_send_json_error('Не удалось пересчитать результаты СУ');
    }

    wp_send_json_success('recalculated');
}

add_action('init', function() {
    remove_action('wp_ajax_results_save_section_results', 'results_ajax_save_section_results');
    remove_action('wp_ajax_results_recalculate_section', 'results_ajax_recalculate_section');

    add_action('wp_ajax_results_save_section_results', 'results_ajax_save_section_results_with_penalty');
    add_action('wp_ajax_results_recalculate_section', 'results_ajax_recalculate_section_with_penalty');
}, 20);
