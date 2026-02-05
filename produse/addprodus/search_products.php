<?php
header('Content-Type: application/json; charset=utf-8');
require $_SERVER['DOCUMENT_ROOT'] . '/includ/db.php';

$q = trim($_GET['q'] ?? '');
if(mb_strlen($q) < 2){
  echo json_encode([]);
  exit;
}

$stmt = $conn->prepare("
  SELECT product_id, name, unit
  FROM products
  WHERE active = 1
    AND name LIKE CONCAT('%', ?, '%')
  ORDER BY name
  LIMIT 10
");
$stmt->bind_param("s", $q);
$stmt->execute();

$res = $stmt->get_result();
$out = [];

while($r = $res->fetch_assoc()){
  $out[] = [
    'id'   => (int)$r['product_id'],
    'name' => $r['name'],
    'unit' => $r['unit'] ?: 'kg'
  ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
