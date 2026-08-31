<?php

require_once __DIR__ . '/PHPExcel/Classes/PHPExcel.php';
require_once __DIR__ . '/PHPExcel/Classes/PHPExcel/Writer/Excel2007.php';

$season_id = htmlspecialchars($_GET["season_id"]);

// Подключение к базе данных
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$link = mysqli_connect("localhost", "j84588200_result", "?fYt3K7yGaqv", "j84588200_results");

// Создаем новый Excel документ
$objPHPExcel = new PHPExcel();
$objPHPExcel->removeSheetByIndex(0); // Удаляем дефолтный лист

// Получение данных о событиях
$rows_ev = mysqli_fetch_all(
    mysqli_query($link, "SELECT * FROM events WHERE season_id = '$season_id'"),
    MYSQLI_ASSOC
);
$count_event = count($rows_ev);

// Получение данных о классах
$rows_cl = mysqli_fetch_all(
    mysqli_query($link, "SELECT * FROM class WHERE season_id = '$season_id'"),
    MYSQLI_ASSOC
);

// Получение данных о сезоне
$rows_cp = mysqli_fetch_all(
    mysqli_query($link, "SELECT * FROM seasons WHERE season_id = '$season_id'"),
    MYSQLI_ASSOC
);
$countPlace = $rows_cp[0]['countPlace'] ?? 0;
$minParticipants = $rows_cp[0]['countPart'] ?? 0; // Минимальное количество участников
$season_name = $rows_cp[0]['season_name'] ?? '';

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

// Для каждого класса создаем отдельный лист
foreach ($rows_cl as $class) {
    $class_name = $class['class_name'];
    
    // Создаем новый лист
    $objWorksheet = new PHPExcel_Worksheet($objPHPExcel);
    $objPHPExcel->addSheet($objWorksheet);
    $objWorksheet->setTitle(substr($class_name, 0, 31)); // Ограничение длины названия листа
    
    // Формирование SQL запроса для участников
    $sql = "SELECT p.participant_id, p.participants_name, p.car, p.num";
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
    
    // Заголовки таблицы
    $headers = [
        'Место', '№', 'Пилот', 'Автомобиль', 
        'Сумма баллов'
    ];
    
    // Добавляем заголовки для каждого события
    for ($i = 1; $i <= $count_event; $i++) {
        $event_id = $rows_ev[$i-1]['event_id'];
        $isValidEvent = $validEvents[$class_name][$event_id] ?? true;
        $suffix = $isValidEvent ? '' : '*';
        $headers[] = "Этап $i (место)$suffix";
        $headers[] = "Этап $i (итого)$suffix";
    }
    
    // Теперь, когда $headers объявлена, добавляем заголовки листа
    $highestColumn = PHPExcel_Cell::stringFromColumnIndex(count($headers) - 1);
    
    // Добавляем заголовки
    $objWorksheet->mergeCells('A1:' . $highestColumn . '1');
    $objWorksheet->setCellValue('A1', 'Внедорожная серия Прикамья ' . $season_name);
    $objWorksheet->mergeCells('A2:' . $highestColumn . '2');
    $objWorksheet->setCellValue('A2', 'Класс ' . $class_name);
    
    // Стили для заголовков
    $titleStyle = [
        'font' => ['bold' => true, 'size' => 14],
        'alignment' => [
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER
        ]
    ];
    $objWorksheet->getStyle('A1:A2')->applyFromArray($titleStyle);
    
    // Устанавливаем высоту строки заголовков таблицы
    $objWorksheet->getRowDimension(3)->setRowHeight(30);
    
    // Заполняем заголовки таблицы (смещаем на 2 строки вниз)
    $col = 0;
    foreach ($headers as $header) {
        $objWorksheet->setCellValueByColumnAndRow($col++, 3, $header);
    }
    
    // Устанавливаем ширину для колонок с баллами
    $pointsColumns = ['E']; // Колонки "Сумма баллов"
    foreach ($pointsColumns as $col) {
        $objWorksheet->getColumnDimension($col)->setWidth(9);
    }
    
    // Также устанавливаем ширину для колонок с очками по этапам
    for ($i = 0; $i < $count_event; $i++) {
        // Каждый этап занимает 2 колонки, очки - вторая из них
        $pointsCol1 = PHPExcel_Cell::stringFromColumnIndex(5 + $i * 2);
        $pointsCol2 = PHPExcel_Cell::stringFromColumnIndex(5 + $i * 2 + 1);
        array_push($pointsColumns, $pointsCol1);
        array_push($pointsColumns, $pointsCol2);
        $objWorksheet->getColumnDimension($pointsCol1)->setWidth(9);
        $objWorksheet->getColumnDimension($pointsCol2)->setWidth(9);
    }
    
    // Заполняем данные (смещаем на 2 строки вниз)
    $rowNumber = 4;
    $ii = 1;
    foreach ($rows as $index => $row) {
        $col = 0;
        $m = ($row['count_ev_t'] == 1) ? $ii++ : '-';
        
        // Основные данные
        $objWorksheet->setCellValueByColumnAndRow($col++, $rowNumber, $m);
        $objWorksheet->setCellValueByColumnAndRow($col++, $rowNumber, $row['num']);
        $objWorksheet->setCellValueByColumnAndRow($col++, $rowNumber, $row['participants_name']);
        $objWorksheet->setCellValueByColumnAndRow($col++, $rowNumber, $row['car']);
        $objWorksheet->setCellValueByColumnAndRow($col++, $rowNumber, $row['sum']);
        
        // Данные по событиям
        for ($i = 1; $i <= $count_event; $i++) {
            $event_id = $rows_ev[$i-1]['event_id'];
            $isValidEvent = $validEvents[$class_name][$event_id] ?? true;
            
            if($row["e{$i}_missing"]==1){ 
                $pos="Сход";
            }elseif($row["e{$i}_disq"]==1){ 
                $pos="Дискв.";
            }else{ 
                $pos = $row["e{$i}_position"];
            }
            
            // Записываем позицию
            $objWorksheet->setCellValueByColumnAndRow($col, $rowNumber, $pos);
            
            // Применяем стиль для невалидных этапов
            if (!$isValidEvent) {
                $objWorksheet->getStyleByColumnAndRow($col, $rowNumber)
                    ->getFill()
                    ->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('FF7F50'); // Coral
            }
            $col++;
            
            // Записываем баллы
            $points = $row["e{$i}_points"] * $row["e{$i}_coef"];
            $objWorksheet->setCellValueByColumnAndRow($col, $rowNumber, $points);
            
            // Применяем стиль для невалидных этапов
            if (!$isValidEvent) {
                $objWorksheet->getStyleByColumnAndRow($col, $rowNumber)
                    ->getFill()
                    ->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('FF7F50'); // Coral
            }
            $col++;
        }
        
        $rowNumber++;
    }
    
    // Автоширина для остальных колонок
    for ($col = 0; $col < count($headers); $col++) {
        $colLetter = PHPExcel_Cell::stringFromColumnIndex($col);
        // Пропускаем колонки, для которых уже установлена ширина
        if (!in_array($colLetter, $pointsColumns)) {
            $objWorksheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
    }
    
    // Стили для заголовков таблицы
    $headerStyle = [
        'font' => ['bold' => true],
        'alignment' => [
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            'wrap' => true
        ],
        'borders' => ['allborders' => ['style' => PHPExcel_Style_Border::BORDER_THIN]],
        'fill' => ['type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => ['rgb' => 'DDDDDD']]
    ];
    
    $objWorksheet->getStyle('A3:' . $highestColumn . '3')->applyFromArray($headerStyle);
    
    // Границы для данных
    $dataStyle = [
        'borders' => ['allborders' => ['style' => PHPExcel_Style_Border::BORDER_THIN]],
        'alignment' => [
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER
        ]
    ];
    
    $objWorksheet->getStyle('A4:' . $highestColumn . $objWorksheet->getHighestRow())->applyFromArray($dataStyle);
    
    // Выравнивание по центру для всех числовых колонок
    $objWorksheet->getStyle('A4:' . $highestColumn . $objWorksheet->getHighestRow())
        ->getAlignment()
        ->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    
    // Добавляем пояснение, если есть неучтенные этапы
    $hasInvalidEvents = false;
    foreach ($validEvents[$class_name] ?? [] as $event_id => $valid) {
        if (!$valid) {
            $hasInvalidEvents = true;
            break;
        }
    }
    
    if ($hasInvalidEvents) {
        $objWorksheet->setCellValue('A' . ($objWorksheet->getHighestRow() + 1), 
            "* Этапы, отмеченные звездочкой и выделенные цветом, не учитываются в общем зачёте, так как количество участников было меньше минимального ($minParticipants)");
        $objWorksheet->mergeCells('A' . ($objWorksheet->getHighestRow()) . ':' . $highestColumn . ($objWorksheet->getHighestRow()));
        $objWorksheet->getStyle('A' . ($objWorksheet->getHighestRow()))
            ->getFont()
            ->setItalic(true);
    }
}

// Сохраняем файл
$filename = 'ВСП_ результаты_сезона_' . $season_name . '.xlsx';
$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$objWriter->save('php://output');
exit;
?>