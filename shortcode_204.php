<?php

function results_204_db() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    return mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");
}

function results_show_stage_sections($atts) {
    $params = shortcode_atts(array(
        'event_id' => get_query_var('event_id') ?: 0,
        'season_id' => get_query_var('season_id') ?: 0,
        'class_name' => get_query_var('class_name') ?: 'Полироль'
    ), $atts, 'results_204');
    $db = results_204_db();
    if (!$params['season_id']) { $season = mysqli_fetch_row(mysqli_query($db,"SELECT * FROM appsettings LIMIT 1")); $params['season_id']=(int)($season[3]??0); }
    $season_id=absint($params['season_id']); $event_id=absint($params['event_id']); $class_name=sanitize_text_field($params['class_name']);
    if (!$event_id) { $e=mysqli_fetch_assoc(mysqli_query($db,"SELECT event_id FROM events WHERE season_id=$season_id ORDER BY event_id DESC LIMIT 1")); $event_id=(int)($e['event_id']??0); }
    $season_rows=mysqli_fetch_all(mysqli_query($db,"SELECT * FROM seasons ORDER BY season_id DESC"),MYSQLI_ASSOC);
    $class_rows=mysqli_fetch_all(mysqli_query($db,"SELECT * FROM class WHERE season_id=$season_id ORDER BY class_id"),MYSQLI_ASSOC);
    $event_rows=mysqli_fetch_all(mysqli_query($db,"SELECT * FROM events WHERE season_id=$season_id ORDER BY event_id"),MYSQLI_ASSOC);
    $class_id=0; foreach($class_rows as $c) if($c['class_name']===$class_name){$class_id=(int)$c['class_id'];break;}
    if(!$class_id){$class_id=(int)($class_rows[0]['class_id']??0);$class_name=$class_rows[0]['class_name']??$class_name;}
    $event=mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM events WHERE event_id=$event_id AND season_id=$season_id LIMIT 1"));
    if(!$event)return '<div class="results-container"><p class="error">Этап не найден.</p></div>';
    $sections=mysqli_fetch_all(mysqli_query($db,"SELECT ss.*,ssc.display_order FROM stage_sections ss JOIN stage_section_categories ssc ON ssc.section_id=ss.section_id WHERE ss.event_id=$event_id AND ssc.class_id=$class_id ORDER BY ssc.display_order,ss.section_number"),MYSQLI_ASSOC);
    $participants=mysqli_fetch_all(mysqli_query($db,"SELECT DISTINCT p.participant_id,p.participants_name,p.car,p.num FROM Results r JOIN Participants p ON p.participant_id=r.participant_id WHERE r.event_id=$event_id AND r.season_id=$season_id AND r.class_id=$class_id ORDER BY CAST(p.num AS UNSIGNED),p.participants_name"),MYSQLI_ASSOC);
    $section_data=array(); foreach($sections as $s){$sid=(int)$s['section_id'];$section_data[$sid]=array();$q=mysqli_query($db,"SELECT * FROM stage_section_results WHERE section_id=$sid AND class_id=$class_id");while($r=mysqli_fetch_assoc($q))$section_data[$sid][(int)$r['participant_id']]=$r;}
    foreach($participants as &$p){$p['total']=0.0;$p['has_finished']=false;$p['all_dsq']=true;foreach($sections as $s){$r=$section_data[(int)$s['section_id']][$p['participant_id']]??null;if(!$r){$p['all_dsq']=false;continue;}$st=$r['final_status']?:$r['status'];if($st==='finished'){$p['has_finished']=true;$p['all_dsq']=false;$p['total']+=(float)$r['final_points'];}elseif($st!=='dsq')$p['all_dsq']=false;}}unset($p);
    usort($participants,function($a,$b){if($a['has_finished']!=$b['has_finished'])return $a['has_finished']?-1:1;if($a['has_finished']&&$a['total']!=$b['total'])return $a['total']>$b['total']?-1:1;return (int)$a['num']<=>(int)$b['num'];});
    $rank=0;$place=0;$prev=null;foreach($participants as &$p){if(!$p['has_finished']){$p['place']='—';continue;}$rank++;if($prev===null||(float)$p['total']!==(float)$prev)$place=$rank;$p['place']=$place;$prev=(float)$p['total'];}unset($p);
    $season_links='';foreach($season_rows as $s){$active=((int)$s['season_id']===$season_id)?' season-filter active':' season-filter';$season_links.='<a href="'.esc_url(add_query_arg(array('season_id'=>(int)$s['season_id'],'event_id'=>$event_id,'class_name'=>$class_name),get_permalink())).'" class="'.$active.'">'.esc_html($s['season_name']).'</a>';}
    $class_links='';foreach($class_rows as $c){$active=$c['class_name']===$class_name?' class-filter-top3 active':' class-filter-top3';$class_links.='<a href="'.esc_url(add_query_arg(array('season_id'=>$season_id,'event_id'=>$event_id,'class_name'=>$c['class_name']),get_permalink())).'" class="'.$active.'">'.esc_html($c['class_name']).'</a>';}
    $event_links='';foreach($event_rows as $e){$active=((int)$e['event_id']===$event_id)?' class-filter-top3 active':' class-filter-top3';$event_links.='<a href="'.esc_url(add_query_arg(array('season_id'=>$season_id,'event_id'=>(int)$e['event_id'],'class_name'=>$class_name),get_permalink())).'" class="'.$active.'">'.esc_html($e['event_name']).'</a>';}
    $dir='https://'.strstr(__DIR__,$_SERVER['SERVER_NAME']); ob_start(); ?>
<link rel="stylesheet" href="<?php echo esc_url($dir.'/results_style.css'); ?>">
<div class="results-container section-results-public">
 <div class="results-header"><div class="season-filters"><?php echo $season_links; ?></div></div>
 <div class="results-header"><div class="class-filters-top3"><?php echo $class_links; ?></div></div>
 <div class="results-header"><div class="class-filters-top3"><?php echo $event_links; ?></div></div>
 <div class="results-info"><strong><?php echo esc_html($event['event_name']); ?></strong><?php if(!empty($event['event_date'])): ?> — <?php echo esc_html($event['event_date']); ?><?php endif; ?></div>
 <?php if(!$sections): ?><p class="error">Подробные результаты СУ пока не опубликованы.</p><?php else: ?>
 <div class="table-responsive"><figure class="wp-block-table is-style-stripes"><table class="delivery results-table stage-results-204">
  <colgroup>
   <col class="col-place"><col class="col-number"><col class="col-crew"><col class="col-car">
   <?php foreach($sections as $s): ?><col span="4" class="col-section"><?php endforeach; ?>
   <col class="col-total">
  </colgroup>
  <thead>
   <tr>
    <th rowspan="2" scope="col" class="has-text-align-center">Место</th>
    <th rowspan="2" scope="col" class="has-text-align-center">Стартовый<br>номер</th>
    <th rowspan="2" scope="col">Фамилия Имя<br>Пилот/Штурман</th>
    <th rowspan="2" scope="col">Автомобиль</th>
    <?php foreach($sections as $s): ?><th colspan="4" scope="colgroup" class="event-header"><?php echo esc_html($s['section_name']); ?></th><?php endforeach; ?>
    <th rowspan="2" scope="col" class="scores-border">Итого<br>очков СУ</th>
   </tr>
   <tr>
    <?php foreach($sections as $s): ?>
     <th scope="col">КП</th><th scope="col">Баллы</th><th scope="col">Время</th><th scope="col">Статус</th>
    <?php endforeach; ?>
   </tr>
  </thead>
  <tbody>
   <?php foreach($participants as $p): ?>
    <tr>
     <td class="results_place"><?php echo esc_html($p['place']); ?></td><td><?php echo esc_html($p['num']); ?></td><td><?php echo esc_html($p['participants_name']); ?></td><td><?php echo esc_html($p['car']); ?></td>
     <?php foreach($sections as $s):$r=$section_data[(int)$s['section_id']][$p['participant_id']]??null;$st=$r?($r['final_status']?:$r['status']):'';if($r):?><td><?php echo esc_html($r['checkpoints_count']); ?></td><td><?php echo esc_html($r['final_points']); ?></td><td><?php echo esc_html($r['raw_time']?:'—'); ?></td><td <?php echo $st==='finished'?'bgcolor="PaleGreen"':'bgcolor="Coral"'; ?>><?php echo $st==='finished'?'Финишировал':($st==='dnf'?'Сход':'Дискв.'); ?></td><?php else: ?><td colspan="4">—</td><?php endif;endforeach; ?>
     <td class="results_scores_all scores-border"><?php echo $p['has_finished']?esc_html($p['total']):'—'; ?></td>
    </tr>
   <?php endforeach; ?>
  </tbody>
 </table></figure></div><?php endif; ?>
</div>
<?php $html=ob_get_clean();mysqli_close($db);return $html;}
