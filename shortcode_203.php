<?php

function results_show_top3($atts) {

    /** -------- СЕЗОН -------- */
    $season = get_season();
    $season_id   = (int) ($season[0] ?? 0);
    $season_name = $season[1] ?? '';

    /** -------- КЛАССЫ -------- */
    $class_names = get_top3_classes($season_id);
    if (empty($class_names)) {
        return '<p>Нет данных</p>';
    }

    /** -------- ТЕКУЩИЙ КЛАСС -------- */
    $class_name = get_query_var('class_name')
        ? sanitize_text_field(get_query_var('class_name'))
        : $class_names[0];

    if (!in_array($class_name, $class_names, true)) {
        $class_name = $class_names[0];
    }

    /** -------- ДАННЫЕ ДЛЯ ВСЕХ КЛАССОВ (С КЕШЕМ) -------- */
    $all_top3_tables = [];

    foreach ($class_names as $class) {

        $cache_key = "top3_{$season_id}_" . md5($class);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            $all_top3_tables[$class] = $cached;
            continue;
        }

        $data = get_top3_data($season_id, $class);
        $html = generate_top3_table_html($data, $class);

        set_transient($cache_key, $html, 300); // 5 минут
        $all_top3_tables[$class] = $html;
    }

    /** -------- ID КОНТЕЙНЕРА -------- */
    $container_id = 'top3-container-' . uniqid();

    /** -------- НАЧАЛО ВЫВОДА -------- */
    ob_start(); ?>

<section class="section results-section">
  <div class="container">

    <div class="section-header">
      <h2>Результаты <?php echo esc_html($season_name); ?></h2>
      <a href="<?php echo home_url('/results/'); ?>" class="section-link">Подробнее →</a>
    </div>

    <div id="<?php echo esc_attr($container_id); ?>">

      <!-- вкладки -->
      <div class="results-tabs">
        <?php foreach ($class_names as $index => $class): ?>
          <button
            class="<?php echo ($class === $class_name) ? 'active' : ''; ?>"
            data-class="<?php echo esc_attr($class); ?>"
            data-index="<?php echo esc_attr($index); ?>">
            <?php echo esc_html($class); ?>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- прогресс -->
      <div class="progress-bar-container">
        <div class="progress-bar"></div>
      </div>

      <!-- таблицы -->
      <div class="results-tables">
        <?php foreach ($all_top3_tables as $class => $html): ?>
          <div class="results-table-wrapper"
               data-class="<?php echo esc_attr($class); ?>"
               style="<?php echo ($class === $class_name) ? '' : 'display:none;'; ?>">
            <?php echo $html; ?>
          </div>
        <?php endforeach; ?>
      </div>

    </div>

  </div>
</section>
    
    <script>
(function($){
$(function(){

  const container = $('#<?php echo esc_js($container_id); ?>');
  const progressBar = container.find('.progress-bar');
  const buttons = container.find('.results-tabs button');
  const tables  = container.find('.results-table-wrapper');

  const switchInterval = 7000;
  let timer = null;
  let progressTimer = null;
  let isHover = false;

  let currentIndex = <?php
    $idx = array_search($class_name, $class_names, true);
    echo ($idx === false) ? 0 : $idx;
  ?>;

  function startProgress(duration = switchInterval){
    progressBar.css('width','0%');
    clearInterval(progressTimer);

    let step = 0;
    const steps = duration / 50;

    progressTimer = setInterval(()=>{
      step++;
      progressBar.css('width', Math.min(step/steps*100,100)+'%');
      if(step>=steps) clearInterval(progressTimer);
    },50);
  }

  function stopAuto(){
    clearTimeout(timer);
    clearInterval(progressTimer);
  }

  function startAuto(){
    stopAuto();
    startProgress();
    timer = setTimeout(()=>{
      if(!isHover) nextClass();
    },switchInterval);
  }

  function nextClass(){
    currentIndex = (currentIndex+1)%buttons.length;
    buttons.eq(currentIndex).trigger('click');
  }

  buttons.on('click',function(e){
    e.preventDefault();

    const btn = $(this);
    const cls = btn.data('class');
    currentIndex = btn.data('index');

    buttons.removeClass('active');
    btn.addClass('active');

    tables.hide();
    tables.filter('[data-class="'+cls+'"]').fadeIn(200);

    const url = new URL(window.location);
    url.searchParams.set('class_name', cls);
    history.pushState({}, '', url);

    startAuto();
  });

  container.on('mouseenter',()=>{isHover=true;stopAuto();});
  container.on('mouseleave',()=>{isHover=false;startAuto();});

  startAuto();

});
})(jQuery);
</script>
<?php
    return ob_get_clean();
}

// Функция для получения текущего сезона
function get_season() {
    $link = mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
    if (function_exists('results_ensure_participants_city_column')) {
        results_ensure_participants_city_column($link);
    }
    // Получение данных о сезоне
    $season_id = mysqli_fetch_row(mysqli_query($link, "SELECT * FROM appsettings"));
    // Получение данных о сезоне
    $season = mysqli_fetch_row(mysqli_query($link, "SELECT * FROM seasons WHERE season_id = '$season_id[3]'"));

    mysqli_close($link);
    
    return $season;
}


// Функция для получения списка классов
function get_top3_classes($season_id) {
    
    $link = mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
    if (function_exists('results_ensure_participants_city_column')) {
        results_ensure_participants_city_column($link);
    }
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
    
    return $class_names;
}

// Функция для получения данных топа-3
function get_top3_data($season_id, $class_name) {
    $link = mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
    if (function_exists('results_ensure_participants_city_column')) {
        results_ensure_participants_city_column($link);
    }

    $season_id  = (int)$season_id;
    $class_name = mysqli_real_escape_string($link, $class_name);
    
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
    $minParticipants = $rows_cp[0]['countPart'] ?? 0;
    
    // Получаем статистику по количеству участников
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
    
    // Создаем массив для валидности этапов
    $validEvents = array();
    foreach ($stats as $stat) {
        $validEvents[$stat['class_name']][$stat['event_id']] = ($stat['participants_count'] >= $minParticipants);
    }
    
    // Формирование SQL запроса
    $sql = "SELECT p.participant_id, p.participants_name, p.city, p.car, p.num, s.countPart";
    $i = 1;
    
    foreach ($rows_ev as $k) {
        $sql .= ", MAX(CASE WHEN e.event_id = " . $k['event_id'] . " THEN pt.points ELSE 0 END) AS e{$i}_points,
                 MAX(CASE WHEN e.event_id = " . $k['event_id'] . " THEN r.missing ELSE 0 END) AS e{$i}_missing,
                 MAX(CASE WHEN e.event_id = " . $k['event_id'] . " THEN r.disq ELSE 0 END) AS e{$i}_disq,
                 MAX(CASE WHEN e.event_id = " . $k['event_id'] . " THEN e.coefficient ELSE 0 END) AS e{$i}_coef";
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
    
    // Получение и обработка данных
    $rows = mysqli_fetch_all(mysqli_query($link, $sql), MYSQLI_ASSOC);
    
    foreach ($rows as &$row) {
        $row['count_ev'] = 0;
        $row['count_ev_t'] = 0;
        $min_points = [];
        $row['sum_all'] = 0;
        
        for ($n = 1; $n <= $count_event; $n++) {
            $event_id = $rows_ev[$n - 1]['event_id'];
            $isValidEvent = $validEvents[$class_name][$event_id] ?? true;
            
            if ($isValidEvent && $row["e{$n}_missing"] == 0 && $row["e{$n}_disq"] == 0) {
                $points = $row["e{$n}_points"] ?? 0;
            } else {
                $points = 0;
            }
            
            $coef = $row["e{$n}_coef"] ?? 0;
            $calculated = $points * $coef;
            
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
    
    // Сортировка
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
    mysqli_close($link);
    
    return array(
        'rows' => $rows,
        'class_name' => $class_name,
        'season_id' => $season_id
    );
}

// Функция для генерации HTML таблицы
function generate_top3_table_html($data, $class_name) {
    $rows = $data['rows'];
    $top3 = array_slice($rows, 0, 4);
    $podium_classes = [1 => 'podium-1', 2 => 'podium-2', 3 => 'podium-3'];
    
    ob_start();
    ?>
    
    <table class="results-table">
        <thead>
            <tr>
                <th>Ст. номер</th>
                <th>Пилот/Штурман</th>
                <th>Город</th>
                <th>Автомобиль</th>
                <th>Баллы</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($top3 as $index => $row): 
                $place = $index + 1;
                $podium_class = isset($podium_classes[$place]) ? $podium_classes[$place] : '';
            ?>
            <tr class="<?php echo $podium_class; ?>">
                <td><span class="participant-number"><?php echo esc_html($row['num']); ?></span></td>
                <td class="participant-name"><?php echo esc_html($row['participants_name']); ?></td>
                <td class="participant-city"><?php echo esc_html($row['city'] ?? ''); ?></td>
                <td class="participant-car"><?php echo esc_html($row['car']); ?></td>
                <td class="top3-points"><?php echo number_format($row['sum'], 1); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php for ($i = count($top3); $i < 3; $i++): 
                $place = $i + 1;
                $podium_class = isset($podium_classes[$place]) ? $podium_classes[$place] : '';
            ?>
            <tr class="<?php echo $podium_class; ?>">
                <td>—</td>
                <td>—</td>
                <td>—</td>
                <td>—</td>
                <td>—</td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>
    
    <?php
    return ob_get_clean();
}

?>