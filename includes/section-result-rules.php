<?php

if (!defined('ABSPATH')) exit;

function results_section_result_status($row) {
    $status = $row['manual_status'] ?? '';
    if ($status === '') $status = $row['status'] ?? 'finished';
    return in_array($status, array('finished', 'dnf', 'dsq'), true) ? $status : 'dnf';
}

function results_stage_dash_points_id($season_id) {
    $link = db_connect();
    $season_id = (int)$season_id;
    $query = mysqli_query($link, "SELECT points_id FROM Pointstable WHERE season_id=$season_id AND (position='-' OR position=0) ORDER BY points_id LIMIT 1");
    if ($query) {
        $row = mysqli_fetch_assoc($query);
        if ($row) return (int)$row['points_id'];
    }
    return null;
}

function results_recalculate_stage_from_sections_v2($event_id, $class_id, $season_id) {
    $link = db_connect();
    $event_id=(int)$event_id; $class_id=(int)$class_id; $season_id=(int)$season_id;

    $eligible_query=mysqli_query($link,"SELECT DISTINCT r.participant_id FROM Results r WHERE r.event_id=$event_id AND r.season_id=$season_id AND r.class_id=$class_id");
    $eligible=array();
    if($eligible_query){
        while($row=mysqli_fetch_assoc($eligible_query)){
            $eligible[(int)$row['participant_id']]=array('participant_id'=>(int)$row['participant_id'],'total'=>0.0,'has_finished'=>false,'has_dnf'=>false,'has_dsq'=>false,'section_count'=>0);
        }
    }
    if(!$eligible) return true;

    $section_query=mysqli_query($link,"SELECT sr.participant_id,sr.final_points,sr.final_status,sr.status,sr.manual_status FROM stage_section_results sr JOIN stage_sections ss ON ss.section_id=sr.section_id JOIN stage_section_categories ssc ON ssc.section_id=sr.section_id AND ssc.class_id=sr.class_id WHERE ss.event_id=$event_id AND sr.class_id=$class_id");
    if($section_query){
        while($row=mysqli_fetch_assoc($section_query)){
            $pid=(int)$row['participant_id'];
            if(!isset($eligible[$pid])) continue;
            $status=results_section_result_status($row);
            $eligible[$pid]['section_count']++;
            if($status==='finished'){
                $eligible[$pid]['has_finished']=true;
                $eligible[$pid]['total']+=(float)($row['final_points']??0);
            }elseif($status==='dsq'){
                $eligible[$pid]['has_dsq']=true;
            }else{
                $eligible[$pid]['has_dnf']=true;
            }
        }
    }

    $ranked=array_values(array_filter($eligible,function($p){return $p['has_finished'];}));
    usort($ranked,function($a,$b){
        if((float)$a['total']===(float)$b['total']) return $a['participant_id']<=>$b['participant_id'];
        return (float)$a['total']>(float)$b['total']?-1:1;
    });

    $position_points=results_get_points_by_position($season_id);
    $rank=array(); $place=0; $previous_total=null;
    foreach($ranked as $index=>$participant){
        if($previous_total===null || (float)$participant['total']!==(float)$previous_total) $place=$index+1;
        $previous_total=(float)$participant['total'];
        $rank[(int)$participant['participant_id']=array('place'=>$place,'points'=>(float)($position_points[$place]??0));
    }

    $dash_points_id=results_stage_dash_points_id($season_id);
    foreach($eligible as $participant){
        $pid=(int)$participant['participant_id'];
        $existing=mysqli_fetch_assoc(mysqli_query($link,"SELECT results_id FROM Results WHERE season_id=$season_id AND event_id=$event_id AND class_id=$class_id AND participant_id=$pid LIMIT 1"));
        if(isset($rank[$pid])){
            $place=(int)$rank[$pid]['place'];
            $point_row=mysqli_fetch_assoc(mysqli_query($link,"SELECT points_id FROM Pointstable WHERE season_id=$season_id AND position=$place LIMIT 1"));
            $points_id=$point_row?(int)$point_row['points_id']:null;
            if($existing){
                $sql="UPDATE Results SET missing=0, disq=0";
                if($points_id!==null) $sql.=", points_id=$points_id";
                $sql.=" WHERE results_id=".(int)$existing['results_id'];
                mysqli_query($link,$sql);
            }
            continue;
        }

        $all_dsq=$participant['section_count']>0 && $participant['has_dsq'] && !$participant['has_dnf'];
        $missing=$all_dsq?0:1; $disq=$all_dsq?1:0;
        if($existing){
            $sql="UPDATE Results SET missing=$missing, disq=$disq";
            if($dash_points_id!==null) $sql.=", points_id=$dash_points_id";
            $sql.=" WHERE results_id=".(int)$existing['results_id'];
            mysqli_query($link,$sql);
        }
    }
    return true;
}

function results_ajax_save_section_results_v2() {
    check_ajax_referer('result_settings_actions_nonce','nonce');
    if(!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав');
    results_install_stage_sections_schema();
    $link=db_connect(); $section_id=absint($_POST['section_id']??0); $class_id=absint($_POST['class_id']??0); $rows=$_POST['rows']??array();
    if(!$section_id||!$class_id||!is_array($rows)) wp_send_json_error('Некорректные данные');
    $section_meta=mysqli_fetch_assoc(mysqli_query($link,"SELECT ss.event_id FROM stage_sections ss JOIN stage_section_categories ssc ON ssc.section_id=ss.section_id WHERE ss.section_id=$section_id AND ssc.class_id=$class_id LIMIT 1"));
    if(!$section_meta) wp_send_json_error('СУ не найдено');
    $event_id=(int)$section_meta['event_id'];
    foreach($rows as $row){
        $pid=absint($row['participant_id']??0); if(!$pid) continue;
        $eligible=mysqli_fetch_assoc(mysqli_query($link,"SELECT participant_id FROM Results WHERE event_id=$event_id AND class_id=$class_id AND participant_id=$pid LIMIT 1"));
        if(!$eligible) continue;
        $cc=max(0,(int)($row['checkpoints_count']??0)); $cp=max(0,(float)($row['checkpoint_points']??0)); $time=sanitize_text_field($row['raw_time']??''); $status=sanitize_text_field($row['status']??'finished');
        if(!in_array($status,array('finished','dnf','dsq'),true)) $status='finished';
        if($time!==''&&results_time_to_seconds($time)===false) wp_send_json_error('Неверный формат времени. Используйте HH:MM:SS');
        $stmt=mysqli_prepare($link,"INSERT INTO stage_section_results (section_id,class_id,participant_id,checkpoints_count,checkpoint_points,raw_time,status,final_status,note) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE checkpoints_count=VALUES(checkpoints_count),checkpoint_points=VALUES(checkpoint_points),raw_time=VALUES(raw_time),status=VALUES(status),manual_status=NULL,final_status=VALUES(final_status),note=VALUES(note)");
        $note=sanitize_textarea_field($row['note']??'');
        mysqli_stmt_bind_param($stmt,'iiidssss',$section_id,$class_id,$pid,$cc,$cp,$time,$status,$status,$note);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    results_recalculate_section($section_id,$class_id);
    $meta=mysqli_fetch_assoc(mysqli_query($link,"SELECT e.event_id,e.season_id FROM stage_sections ss JOIN Events e ON e.event_id=ss.event_id WHERE ss.section_id=$section_id LIMIT 1"));
    if($meta) results_recalculate_stage_from_sections_v2($meta['event_id'],$class_id,$meta['season_id']);
    wp_send_json_success('saved');
}

function results_ajax_recalculate_section_v2() {
    check_ajax_referer('result_settings_actions_nonce','nonce');
    if(!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав');
    $section_id=absint($_POST['section_id']??0); $class_id=absint($_POST['class_id']??0);
    if(!results_recalculate_section($section_id,$class_id)) wp_send_json_error('СУ не найден');
    $link=db_connect();
    $meta=mysqli_fetch_assoc(mysqli_query($link,"SELECT e.event_id,e.season_id FROM stage_sections ss JOIN Events e ON e.event_id=ss.event_id WHERE ss.section_id=$section_id LIMIT 1"));
    if($meta) results_recalculate_stage_from_sections_v2($meta['event_id'],$class_id,$meta['season_id']);
    wp_send_json_success('recalculated');
}

function results_ajax_delete_stage_section_v2() {
    check_ajax_referer('result_settings_actions_nonce','nonce');
    if(!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав');
    results_install_stage_sections_schema(); $link=db_connect(); $section_id=absint($_POST['section_id']??0);
    if(!$section_id) wp_send_json_error('Не указан СУ');
    $meta=mysqli_fetch_assoc(mysqli_query($link,"SELECT event_id FROM stage_sections WHERE section_id=$section_id LIMIT 1"));
    if(!$meta) wp_send_json_error('СУ не найдено');
    $event_id=(int)$meta['event_id']; $classes=mysqli_fetch_all(mysqli_query($link,"SELECT class_id FROM stage_section_categories WHERE section_id=$section_id"),MYSQLI_ASSOC);
    mysqli_begin_transaction($link);
    try{
        mysqli_query($link,"DELETE FROM stage_section_results WHERE section_id=$section_id");
        mysqli_query($link,"DELETE FROM stage_section_categories WHERE section_id=$section_id");
        mysqli_query($link,"DELETE FROM stage_sections WHERE section_id=$section_id LIMIT 1");
        if(mysqli_errno($link)) throw new Exception(mysqli_error($link));
        mysqli_commit($link);
    }catch(Exception $e){mysqli_rollback($link);wp_send_json_error('Не удалось удалить СУ: '.$e->getMessage());}
    $event=mysqli_fetch_assoc(mysqli_query($link,"SELECT season_id FROM Events WHERE event_id=$event_id LIMIT 1"));
    if($event) foreach($classes as $class) results_recalculate_stage_from_sections_v2($event_id,(int)$class['class_id'],(int)$event['season_id']);
    wp_send_json_success('deleted');
}

function results_install_section_result_rules_override(){
    remove_action('wp_ajax_results_save_section_results','results_ajax_save_section_results');
    remove_action('wp_ajax_results_recalculate_section','results_ajax_recalculate_section');
    remove_action('wp_ajax_results_delete_stage_section','results_ajax_delete_stage_section');
    add_action('wp_ajax_results_save_section_results','results_ajax_save_section_results_v2');
    add_action('wp_ajax_results_recalculate_section','results_ajax_recalculate_section_v2');
    add_action('wp_ajax_results_delete_stage_section','results_ajax_delete_stage_section_v2');
}
add_action('init','results_install_section_result_rules_override',100);
