<?php
header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

$product_id = (int)($_GET['product_id'] ?? 0);
if ($product_id <= 0) {
  echo json_encode(["ok"=>false, "qty"=>0], JSON_UNESCAPED_UNICODE);
  exit;
}

$stmt = $conn->prepare("
  SELECT COALESCE(SUM(qty),0) AS qty
  FROM stock_by_price
  WHERE product_id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$qty = (float)($stmt->get_result()->fetch_assoc()['qty'] ?? 0);

echo json_encode(["ok"=>true, "qty"=>$qty], JSON_UNESCAPED_UNICODE);
