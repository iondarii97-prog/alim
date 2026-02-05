<?php
require $_SERVER['DOCUMENT_ROOT'].'/includ/db.php';
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function out($ok,$msg){
  echo json_encode(['ok'=>$ok,'message'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if(!$data){
  out(false,'Invalid JSON');
}

/* ===== ОБНОВЛЕНИЕ БЛЮД ===== */
if(!empty($data['meals'])){
  $stmt = $conn->prepare("
    UPDATE menu_meals 
    SET meal_name = ?
    WHERE meal_id = ?
  ");
  foreach($data['meals'] as $meal_id => $name){
    $stmt->bind_param("si", $name, $meal_id);
    $stmt->execute();
  }
}

/* ===== ОБНОВЛЕНИЕ ГРАММОВ ===== */
if(!empty($data['items'])){
  $stmt = $conn->prepare("
    UPDATE menu_items 
    SET grams = ?
    WHERE item_id = ?
  ");
  foreach($data['items'] as $item_id => $grams){
    $g = (float)$grams;
    $stmt->bind_param("di", $g, $item_id);
    $stmt->execute();
  }
}

out(true,'Сохранено успешно');
