<?php
require $_SERVER['DOCUMENT_ROOT'].'/includ/db.php';
require $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$data = json_decode(file_get_contents('php://input'), true);
if(!$data) die('No data');

$meta  = $data['meta'];
$table = $data['table'];

$sheet = new Spreadsheet();
$ws = $sheet->getActiveSheet();

/* ===== СТИЛИ ===== */
$styleHeader = [
  'font'=>['bold'=>true],
  'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F3F3F3']]
];

$styleCell = [
  'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]]
];

$styleTotal = [
  'font'=>['bold'=>true],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E0E0E0']]
];

/* ===== META ВВЕРХУ ===== */
$ws->setCellValue('A1','Săptămîna: '.$meta['week']);
$ws->setCellValue('A2','Export: '.date('d.m.Y H:i'));

/* ===== ТАБЛИЦА ===== */
$rowNum = 4;
foreach($table as $rIndex=>$row){
  $colNum = 1;
  foreach($row as $cell){
    $ws->setCellValueByColumnAndRow($colNum,$rowNum,$cell);
    $colNum++;
  }

  // стили
  if($rIndex === 0){
    $ws->getStyle("A$rowNum:".chr(64+$colNum-1)."$rowNum")
       ->applyFromArray($styleHeader);
  }else{
    $ws->getStyle("A$rowNum:".chr(64+$colNum-1)."$rowNum")
       ->applyFromArray($styleCell);
  }

  $rowNum++;
}

/* ===== автоширина ===== */
foreach(range('A',$ws->getHighestColumn()) as $c){
  $ws->getColumnDimension($c)->setAutoSize(true);
}

/* ===== отдаём файл ===== */
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="repartizare.xlsx"');

$writer = new Xlsx($sheet);
$writer->save('php://output');
exit;
