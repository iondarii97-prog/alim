<?php
header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost", "root", "", "alim");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(['results'=>[], 'error'=>'DB connection error'], JSON_UNESCAPED_UNICODE);
  exit;
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$sql = "
  SELECT product_id, name
  FROM products
  WHERE active = 1
";

$params = [];
$types  = '';

if ($q !== '') {
  $sql .= " AND name LIKE ?";
  $params[] = "%{$q}%";
  $types .= 's';
}

$sql .= " ORDER BY name LIMIT 50";

$stmt = $conn->prepare($sql);
if ($params) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while($r = $res->fetch_assoc()){
  $out[] = [
    "id"   => (int)$r["product_id"],
    "text" => $r["name"]
  ];
}

echo json_encode(["results" => $out], JSON_UNESCAPED_UNICODE);
exit;
  