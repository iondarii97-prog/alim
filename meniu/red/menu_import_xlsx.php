<?php
require $_SERVER['DOCUMENT_ROOT'].'/includ/db.php';
require $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$menu_id = (int)($_POST['menu_id'] ?? 0);
if(!$menu_id || !isset($_FILES['xlsx'])) die('Invalid');

$spreadsheet = IOFactory::load($_FILES['xlsx']['tmp_name']);
$ws = $spreadsheet->getActiveSheet();

/* ===== блюда из БД ===== */
$meals = $conn->query("
  SELECT col_index, meal_name
  FROM menu_meals
  WHERE menu_id = $menu_id
  ORDER BY col_index
")->fetch_all(MYSQLI_ASSOC);

if(!$meals) die('No meals');

/* ===== очистка ===== */
$conn->begin_transaction();
try{

  $conn->query("DELETE FROM menu_items WHERE menu_id = $menu_id");

  $stmtProd = $conn->prepare("
    SELECT product_id FROM products WHERE name = ?
  ");

  $stmtIns = $conn->prepare("
    INSERT INTO menu_items (menu_id, product_id, col_index, grams)
    VALUES (?,?,?,?)
  ");

  $row = 5; // данные начинаются с 5 строки
  while(true){
    $nameCell = trim((string)$ws->getCellByColumnAndRow(1,$row)->getValue());
    if($nameCell==='') break;

    // убираем (kg)
    $productName = preg_replace('/\s*\(.*\)$/','',$nameCell);

    $stmtProd->bind_param("s",$productName);
    $stmtProd->execute();
    $res = $stmtProd->get_result()->fetch_assoc();
    if(!$res){ $row++; continue; }

    $pid = (int)$res['product_id'];

    $colExcel = 2;
    foreach($meals as $m){
      $val = $ws->getCellByColumnAndRow($colExcel,$row)->getValue();
      if($val!=='' && is_numeric($val)){
        $grams = (float)$val;
        $c = (int)$m['col_index'];

        $stmtIns->bind_param("iiid",
          $menu_id,$pid,$c,$grams
        );
        $stmtIns->execute();
      }
      $colExcel++;
    }

    $row++;
  }

  $conn->commit();
  header("Location: menu_edit.php?menu_id=".$menu_id);
  exit;

}catch(Exception $e){
  $conn->rollback();
  die("Import error: ".$e->getMessage());
}
