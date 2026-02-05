<?php
require $_SERVER['DOCUMENT_ROOT'].'/includ/db.php';
header('Content-Type: application/json; charset=utf-8');

function out($ok,$msg){
  echo json_encode(['ok'=>$ok,'message'=>$msg],JSON_UNESCAPED_UNICODE);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$menu_id    = (int)($data['menu_id'] ?? 0);
$product_id = (int)($data['product_id'] ?? 0);
$col_index  = (int)($data['col_index'] ?? 0);
$grams      = (float)($data['grams'] ?? 0);

if(!$menu_id || !$product_id) out(false,'Date invalide');

/* проверка: нет ли уже */
$chk = $conn->prepare("
  SELECT item_id FROM menu_items
  WHERE menu_id=? AND product_id=? AND col_index=?
");
$chk->bind_param("iii",$menu_id,$product_id,$col_index);
$chk->execute();
if($chk->get_result()->num_rows){
  out(false,'Produs deja există în această coloană');
}

$ins = $conn->prepare("
  INSERT INTO menu_items(menu_id,product_id,col_index,grams)
  VALUES(?,?,?,?)
");
$ins->bind_param("iiid",$menu_id,$product_id,$col_index,$grams);
$ins->execute();

out(true,'Produs adăugat');
