<?php
require $_SERVER['DOCUMENT_ROOT'].'/includ/db.php';
require $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$menu_id = (int)($_GET['menu_id'] ?? 0);
if(!$menu_id) die('No menu');

/* ===== день ===== */
$day = $conn->query("
  SELECT menu_date, day_name
  FROM menu_days WHERE menu_id = $menu_id
")->fetch_assoc();

/* ===== блюда ===== */
$meals = $conn->query("
  SELECT col_index, meal_name
  FROM menu_meals
  WHERE menu_id = $menu_id
  ORDER BY col_index
")->fetch_all(MYSQLI_ASSOC);

/* ===== продукты ===== */
$res = $conn->query("
  SELECT 
    mi.product_id,
    p.name,
    p.unit,
    mi.col_index,
    mi.grams
  FROM menu_items mi
  JOIN products p ON p.product_id = mi.product_id
  WHERE mi.menu_id = $menu_id
  ORDER BY p.name, mi.col_index
");

$items = [];
while($r = $res->fetch_assoc()){
  $pid = $r['product_id'];
  if(!isset($items[$pid])){
    $items[$pid] = [
      'name' => $r['name'],
      'unit' => $r['unit'],
      'rows' => []
    ];
  }
  $items[$pid]['rows'][$r['col_index']] = $r['grams'];
}

/* ===== Excel ===== */
$sheet = new Spreadsheet();
$ws = $sheet->getActiveSheet();

$ws->setCellValue('A1', 'Дата: '.$day['menu_date']);
$ws->setCellValue('A2', 'День: '.$day['day_name']);

/* ===== header ===== */
$row = 4;
$ws->setCellValueByColumnAndRow(1,$row,'Продукт');

$col = 2;
foreach($meals as $m){
  $ws->setCellValueByColumnAndRow($col,$row,$m['meal_name']);
  $col++;
}

/* ===== data ===== */
$row++;
foreach($items as $pid=>$p){
  $ws->setCellValueByColumnAndRow(1,$row,$p['name'].' ('.$p['unit'].')');

  $col = 2;
  foreach($meals as $m){
    $c = $m['col_index'];
    $val = $p['rows'][$c] ?? '';
    $ws->setCellValueByColumnAndRow($col,$row,$val);
    $col++;
  }
  $row++;
}

/* ===== download ===== */
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="menu_'.$menu_id.'.xlsx"');

$writer = new Xlsx($sheet);
$writer->save('php://output');
exit;
