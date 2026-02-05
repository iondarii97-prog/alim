<?php
require $_SERVER['DOCUMENT_ROOT'].'/includ/db.php';

$menu_id = (int)($_GET['menu_id'] ?? 0);
if(!$menu_id) die('No menu');

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="menu_'.$menu_id.'.json"');

/* ===== день ===== */
$day = $conn->query("
  SELECT menu_id, menu_date, day_name
  FROM menu_days
  WHERE menu_id = $menu_id
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
      'product_id' => $pid,
      'name' => $r['name'],
      'unit' => $r['unit'],
      'rows' => []
    ];
  }
  $items[$pid]['rows'][$r['col_index']] = $r['grams'];
}

echo json_encode([
  'day'   => $day,
  'meals' => $meals,
  'items' => array_values($items)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
