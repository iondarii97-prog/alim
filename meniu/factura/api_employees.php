<?php
require __DIR__.'/db.php';

header('Content-Type: application/json; charset=utf-8');

$res = $conn->query("
  SELECT employee_id, full_name
  FROM employees
  WHERE active = 1
  ORDER BY full_name
");

$data = [];
while($r = $res->fetch_assoc()){
  $data[] = $r;
}

echo json_encode([
  'ok' => true,
  'data' => $data
]);
