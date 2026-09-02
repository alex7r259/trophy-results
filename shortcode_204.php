<?php

add_action('wp_ajax_results_204_load_class', 'results_204_ajax_load_class');
add_action('wp_ajax_nopriv_results_204_load_class', 'results_204_ajax_load_class');

function results_204_db() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    return mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
}

function results_show_stage_sections($atts) {
    $params = shortcode_atts(array(
        'event_id' => get_query_var('event_id') ?: 0,
        'season_id' => get_query_var('season_id') ?: 0,
        'class_name' => get_query_var('class_name') ?: 'Полироль',
        'render_scripts' => 1
    ), $atts, 'results_204');

    $db = results_204_db();
    if (!$params['season_id']) {
        $season = mysqli_fetch_row(mysqli_query($db, "SELECT * FROM appsettings LIMIT 1"));
        $params['season_id'] = (int)($season[3] ?? 0);
    }

    $season_id = absint($params['season_id']);
    $event_id = absint($params['event_id']);
    $class_name = sanitize_text_field($params['class_name']);

    if (!$event_id) {
        $e = mysqli_fetch_assoc(mysqli_query($db, "SELECT event_id FROM events WHERE season_id=$season_id ORDER BY event_id DESC LIMIT 1"));
        $event_id = (int)($e['event_id'] ?? 0);
    }

    $season_rows = mysqli_fetch_all(mysqli_query($db, "SELECT * FROM seasons ORDER BY season_id DESC"), MYSQLI_ASSOC);
    $current_season_name = '';
    foreach ($season_rows as $season) {
        if ((int)$season['season_id'] === $season_id) {
            $current_season_name = $season['season_name'];
            break;
        }
    }

    $class_rows = mysqli_fetch_all(mysqli_query($db, "SELECT * FROM class WHERE season_id=$season_id ORDER BY class_id"), MYSQLI_ASSOC);
    $event_rows = mysqli_fetch_all(mysqli_query($db, "SELECT * FROM events WHERE season_id=$season_id ORDER BY event_id"), MYSQLI_ASSOC);

    $class_id = 0;
    foreach ($class_rows as $c) {
        if ($c['class_name'] === $class_name) {
            $class_id = (int)$c['class_id'];
            break;
        }
    }

    if (!$class_id) {
        $class_id = (int)($class_rows[0]['class_id'] ?? 0);
        $class_name = $class_rows[0]['class_name'] ?? $class_name;
    }

    $event = mysqli_fetch_assoc(mysqli_query($db, "SELECT * FROM events WHERE event_id=$event_id AND season_id=$season_id LIMIT 1"));
    if (!$event) {
        mysqli_close($db);
        return '<div class="results-container"><p class="error">Этап не найден.</p></div>';
    }

    $sections = mysqli_fetch_all(mysqli_query($db, "
        SELECT ss.*, ssc.display_order
        FROM stage_sections ss
        JOIN stage_section_categories ssc ON ssc.section_id=ss.section_id
        WHERE ss.event_id=$event_id AND ssc.class_id=$class_id
        ORDER BY ssc.display_order, ss.section_number
    "), MYSQLI_ASSOC);

    $participants = mysqli_fetch_all(mysqli_query($db, "
        SELECT DISTINCT p.participant_id,p.participants_name,p.car,p.num
        FROM Results r
        JOIN Participants p ON p.participant_id=r.participant_id
        WHERE r.event_id=$event_id AND r.season_id=$season_id AND r.class_id=$class_id
        ORDER BY CAST(p.num AS UNSIGNED),p.participants_name
    "), MYSQLI_ASSOC);

    $section_data = array();
    foreach ($sections as $s) {
        $sid = (int)$s['section_id'];
        $section_data[$sid] = array();
        $q = mysqli_query($db, "SELECT * FROM stage_section_results WHERE section_id=$sid AND class_id=$class_id");
        while ($r = mysqli_fetch_assoc($q)) {
            $section_data[$sid][(int)$r['participant_id']] = $r;
        }
    }

    foreach ($participants as &$p) {
        $p['total'] = 0.0;
        $p['has_finished'] = false;
        foreach ($sections as $s) {
            $r = $section_data[(int)$s['section_id']][$p['participant_id']] ?? null;
            if (!$r) continue;
            $st = $r['final_status'] ?: $r['status'];
            if ($st === 'finished') {
                $p['has_finished'] = true;
                $p['total'] += (float)$r['final_points'];
            }
        }
    }
    unset($p);

    usort($participants, function($a, $b) {
        if ($a['has_finished'] != $b['has_finished']) return $a['has_finished'] ? -1 : 1;
        if ($a['has_finished'] && $a['total'] != $b['total']) return $a['total'] > $b['total'] ? -1 : 1;
        return (int)$a['num'] <=> (int)$b['num'];
    });

    $rank = 0;
    $place = 0;
    $prev = null;
    foreach ($participants as &$p) {
        if (!$p['has_finished']) {
            $p['place'] = '—';
            continue;
        }
        $rank++;
        if ($prev === null || (float)$p['total'] !== (float)$prev) $place = $rank;
        $p['place'] = $place;
        $prev = (float)$p['total'];
    }
    unset($p);

    $season_links = '';
    foreach ($season_rows as $s) {
        $active = ((int)$s['season_id'] === $season_id) ? ' season-filter active' : ' season-filter';
        $season_links .= '<a href="' . esc_url(add_query_arg(array('season_id'=>(int)$s['season_id'],'event_id'=>$event_id,'class_name'=>$class_name), get_permalink())) . '" class="' . $active . '">' . esc_html($s['season_name']) . '</a>';
    }

    $class_links = '';
    foreach ($class_rows as $c) {
        $active = $c['class_name'] === $class_name ? ' class-filter-top3 active' : ' class-filter-top3';
        $class_links .= '<a href="' . esc_url(add_query_arg(array('season_id'=>$season_id,'event_id'=>$event_id,'class_name'=>$c['class_name']), get_permalink())) . '" class="' . $active . '">' . esc_html($c['class_name']) . '</a>';
    }

    $event_links = '';
    foreach ($event_rows as $e) {
        $active = ((int)$e['event_id'] === $event_id) ? ' class-filter-top3 active' : ' class-filter-top3';
        $event_links .= '<a href="' . esc_url(add_query_arg(array('season_id'=>$season_id,'event_id'=>(int)$e['event_id'],'class_name'=>$class_name), get_permalink())) . '" class="' . $active . '">' . esc_html($e['event_name']) . '</a>';
    }

    $dir = 'https://' . strstr(__DIR__, $_SERVER['SERVER_NAME']);
    $nonce = wp_create_nonce('results_204_ajax_nonce');

    ob_start(); ?>
<link rel="stylesheet" href="<?php echo esc_url($dir . '/results_style.css'); ?>">
<div class="results-container section-results-public" data-season-id="<?php echo esc_attr($season_id); ?>" data-event-id="<?php echo esc_attr($event_id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
<div class="results-info"><h3 style="font-size: 20px; margin-bottom: 20px; color: var(--text-main);">Таблица результатов <?php echo esc_html($event['event_name']); ?> <?php echo esc_html($current_season_name); ?></h3></div>
<div class="results-header"><div class="class-filters-top3"><?php echo $class_links; ?></div></div>

<?php if (!$sections): ?>
<p class="error">Подробные результаты СУ пока не опубликованы.</p>
<?php else: ?>
<div class="table-responsive"><figure class="wp-block-table is-style-stripes"><table class="delivery results-table stage-results-204">
<colgroup>
<col class="col-number"><col class="col-crew"><col class="col-car">
<?php foreach ($sections as $s): ?>
<col class="col-section-cp"><col class="col-section-penalty"><col class="col-section-time"><col class="col-section-points">
<?php endforeach; ?>
<col class="col-total"><col class="col-place">
</colgroup>
<thead>
<tr>
<th rowspan="2" scope="col" class="has-text-align-center">Стартовый<br>номер</th>
<th rowspan="2" scope="col">Фамилия Имя<br>Пилот/Штурман</th>
<th rowspan="2" scope="col">Автомобиль</th>
<?php foreach($sections as $s): ?><th colspan="4" class="event-header"><?php echo esc_html($s['section_name']); ?></th><?php endforeach; ?>
<th rowspan="2" scope="col" class="scores-border">Всего<br>баллов</th>
<th rowspan="2" scope="col" class="has-text-align-center">Место</th>
</tr>
<tr>
<?php foreach($sections as $s): ?>
<th scope="col">КП</th><th scope="col">Штраф</th><th scope="col">Время</th><th scope="col">Баллы</th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach($participants as $p): ?>
<tr>
<td><?php echo esc_html($p['num']); ?></td><td><?php echo esc_html($p['participants_name']); ?></td><td><?php echo esc_html($p['car']); ?></td>
<?php foreach($sections as $s):
$r=$section_data[(int)$s['section_id']][$p['participant_id']]??null;
$st=$r?($r['final_status']?:$r['status']):'';
if($r): ?>
<td><?php echo esc_html($r['checkpoints_count']); ?></td>
<td><?php echo ((float)$r['penalty_points'] != 0) ? esc_html(rtrim(rtrim(number_format((float)$r['penalty_points'], 2, '.', ''), '0'), '.')) : '—'; ?></td>
<td><?php echo esc_html($r['raw_time']?:'—'); ?></td>
<td <?php echo $st==='finished'?'bgcolor="PaleGreen"':'bgcolor="Coral"'; ?>><?php echo $st==='finished'?esc_html($r['final_points']):($st==='dnf'?'Сход':'Дискв.'); ?></td>
<?php else: ?><td colspan="4">—</td><?php endif; endforeach; ?>
<td class="results_scores_all scores-border"><?php echo $p['has_finished']?esc_html($p['total']):'—'; ?></td>
<td class="results_place"><?php echo esc_html($p['place']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></figure></div>
<?php endif; ?>
</div>
<?php
$html = ob_get_clean();
mysqli_close($db);

if (!empty($params['render_scripts'])) {
    $html .= '<script>
(function(){
    if (window.results204Initialized) return;
    window.results204Initialized = true;
    document.addEventListener("click", function(e){
        var link = e.target.closest(".section-results-public .class-filters-top3 a");
        if (!link) return;
        var container = link.closest(".section-results-public");
        if (!container) return;
        e.preventDefault();
        var className = new URL(link.href, window.location.href).searchParams.get("class_name") || "";
        var formData = new FormData();
        formData.append("action", "results_204_load_class");
        formData.append("season_id", container.dataset.seasonId || "0");
        formData.append("event_id", container.dataset.eventId || "0");
        formData.append("class_name", className);
        formData.append("nonce", container.dataset.nonce || "");
        container.classList.add("results-loading");
        fetch(container.dataset.ajaxUrl, { method:"POST", body:formData, credentials:"same-origin" })
        .then(function(response){ if(!response.ok) throw new Error("HTTP " + response.status); return response.text(); })
        .then(function(html){
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, "text/html");
            var newContainer = doc.querySelector(".section-results-public");
            if(!newContainer) throw new Error("Некорректный ответ сервера");
            container.replaceWith(newContainer);
            window.history.pushState({}, "", link.href);
        })
        .catch(function(error){
            console.error("results_204:", error);
            container.classList.remove("results-loading");
            var errorBox = container.querySelector(".results-info");
            if(errorBox) errorBox.insertAdjacentHTML("afterend", "<p class=\"error results-ajax-error\">Ошибка загрузки результатов. Попробуйте ещё раз.</p>");
        });
    });
})();
</script>';
}
return $html;
}

function results_204_ajax_load_class() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'results_204_ajax_nonce')) {
        status_header(403);
        echo '<div class="results-container"><p class="error">Ошибка проверки безопасности.</p></div>';
        wp_die();
    }
    $season_id = isset($_POST['season_id']) ? absint($_POST['season_id']) : 0;
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $class_name = isset($_POST['class_name']) ? sanitize_text_field(wp_unslash($_POST['class_name'])) : '';
    if (!$season_id || !$event_id || !$class_name) {
        status_header(400);
        echo '<div class="results-container"><p class="error">Недостаточно данных для загрузки результатов.</p></div>';
        wp_die();
    }
    echo results_show_stage_sections(array('season_id'=>$season_id,'event_id'=>$event_id,'class_name'=>$class_name,'render_scripts'=>0));
    wp_die();
}