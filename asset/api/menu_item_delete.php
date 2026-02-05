<?php
require $_SERVER['DOCUMENT_ROOT'].'/includ/db.php';
header('Content-Type: application/json; charset=utf-8');

function out($ok,$msg){
  echo json_encode(['ok'=>$ok,'message'=>$msg],JSON_UNESCAPED_UNICODE);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['item_id'] ?? 0);

if(!$id) out(false,'No id');

$stmt = $conn->prepare("DELETE FROM menu_items WHERE item_id=?");
$stmt->bind_param("i",$id);
$stmt->execute();

out(true,'Șters');
