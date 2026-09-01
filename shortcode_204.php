<?php

function results_show_stage_sections($atts) {
    $defaults = array(
        'event_id' => get_query_var('event_id') ?: 1,
        'season_id' => get_query_var('season_id') ?: 1,
        'class_name' => get_query_var('class_name') ?: 'Полироль'
    );
    $params = shortcode_atts($defaults, $atts, 'results_204');
    $db = new mysqli('localhost','j84588200_result','?fYt3K7yGaqv','j84588200_results');
    if ($db->connect_error) return 'Ошибка подключения к базе данных';
    $event_id=(int)$params['event_id']; $season_id=(int)$params['season_id']; $class=$db->real_escape_string($params['class_name']);
    $classes=$db->query("SELECT class_id,class_name FROM class WHERE season_id=$season_id ORDER BY class_id");
    $class_rows=$classes?$classes->fetch_all(MYSQLI_ASSOC):array();
    $class_id=0; foreach($class_rows as $c) if($c['class_name']==$params['class_name']) {$class_id=(int)$c['class_id']; break;}
    if(!$class_id) return '<p>Категория не найдена.</p>';
    $event=$db->query("SELECT event_name,event_date FROM events WHERE event_id=$event_id AND season_id=$season_id LIMIT 1");
    $event_row=$event?$event->fetch_assoc():array();
    $sections_q=$db->query("SELECT ss.section_id,ss.section_number,ss.section_name FROM stage_sections ss JOIN stage_section_categories ssc ON ssc.section_id=ss.section_id WHERE ss.event_id=$event_id AND ssc.class_id=$class_id ORDER BY ssc.display_order,ss.section_number");
    $sections=$sections_q?$sections_q->fetch_all(MYSQLI_ASSOC):array();
    if(!$sections) return '<p>Подробные результаты СУ пока не опубликованы.</p>';
    $section_ids=implode(',',array_map('intval',array_column($sections,'section_id')));
    $rows_q=$db->query("SELECT p.participant_id,p.participants_name,p.num,p.car,sr.section_id,sr.checkpoints_count,sr.checkpoint_points,sr.start_at,sr.finish_at,sr.raw_time,sr.final_status,sr.final_place,sr.final_points FROM stage_section_results sr JOIN Participants p ON p.participant_id=sr.participant_id WHERE sr.class_id=$class_id AND sr.section_id IN ($section_ids) ORDER BY p.num, sr.section_id");
    $data=array(); if($rows_q) while($r=$rows_q->fetch_assoc()) {$pid=(int)$r['participant_id']; if(!isset($data[$pid])) $data[$pid]=array('participant_id'=>$pid,'participants_name'=>$r['participants_name'],'num'=>$r['num'],'car'=>$r['car'],'sections'=>array(),'total'=>0.0,'finished'=>false); $data[$pid]['sections'][(int)$r['section_id']]=$r; if($r['final_status']==='finished'){ $data[$pid]['total']+=(float)$r['final_points']; $data[$pid]['finished']=true; }}
    $participants=array_values($data); usort($participants,function($a,$b){if($a['finished']!=$b['finished'])return $a['finished']?-1:1; if($a['total']==$b['total'])return (int)$a['num']<=>(int)$b['num']; return $a['total']>$b['total']?-1:1;});
    $place=0;$prev=null;$ranked=0; foreach($participants as &$p){if(!$p['finished']){$p['place']='—';continue;} $ranked++; if($prev===null||$p['total']!=$prev)$place=$ranked; $p['place']=$place;$prev=$p['total'];} unset($p);
    $links=array(); foreach($class_rows as $c){$url=add_query_arg(array('event_id'=>$event_id,'season_id'=>$season_id,'class_name'=>$c['class_name']),get_permalink());$links[]='<a style="margin-right:7px;'.($c['class_name']==$params['class_name']?'font-weight:bold;':'').'" href="'.esc_url($url).'">'.esc_html($c['class_name']).'</a>';}
    ob_start(); ?>
    <div class="results-container section-results-public">
      <div class="results-header"><div class="class-filters-top3"><?php echo implode('',$links); ?></div></div>
      <h2><?php echo esc_html($event_row['event_name']??'Результаты этапа'); ?></h2>
      <?php if(!empty($event_row['event_date'])): ?><p><?php echo esc_html($event_row['event_date']); ?></p><?php endif; ?>
      <div class="table-responsive"><figure class="wp-block-table is-style-stripes"><table class="delivery results-table">
        <thead><tr><th>Место</th><th>№</th><th>Участник</th><th>Автомобиль</th><?php foreach($sections as $s): ?><th colspan="4"><?php echo esc_html($s['section_name']); ?></th><?php endforeach; ?><th>Итого</th></tr>
        <tr><th colspan="4"></th><?php foreach($sections as $s): ?><th>КП</th><th>Баллы</th><th>Время</th><th>Статус</th><?php endforeach; ?><th>Очки</th></tr></thead>
        <tbody><?php foreach($participants as $p): ?><tr><td><?php echo esc_html($p['place']); ?></td><td><?php echo esc_html($p['num']); ?></td><td><?php echo esc_html($p['participants_name']); ?></td><td><?php echo esc_html($p['car']); ?></td><?php foreach($sections as $s): $r=$p['sections'][(int)$s['section_id']]??null; ?><td><?php echo $r?esc_html($r['checkpoints_count']):'—'; ?></td><td><?php echo $r?esc_html($r['final_points']):'—'; ?></td><td><?php echo $r?esc_html($r['raw_time']):'—'; ?></td><td><?php echo $r?esc_html(($r['final_status']==='finished'?'Финишировал':($r['final_status']==='dnf'?'Сход':'Дискв.'))):'—'; ?></td><?php endforeach; ?><td><strong><?php echo $p['finished']?esc_html($p['total']):'—'; ?></strong></td></tr><?php endforeach; ?></tbody>
      </table></figure></div>
    </div>
    <?php $db->close(); return ob_get_clean();
}
