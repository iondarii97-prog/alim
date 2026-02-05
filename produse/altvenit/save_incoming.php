<?php
// save_incoming.php — UPDATE STOCK ONLY
// affects: products, product_prices, stock_by_price

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/../../db.php';

/* ================= helpers ================= */

function fail($msg, $code = 400){
  http_response_code($code);
  exit($msg);
}

/* ================= read JSON ================= */

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if(!is_array($data)){
  fail('JSON invalid');
}

$items = $data['items'] ?? [];
if(!is_array($items) || !count($items)){
  fail('Nu există produse');
}

/* ================= transaction ================= */

$conn->begin_transaction();

try{

  /* prepared statements */

  // find product (also get unit!)
  $stmtFindProduct = $conn->prepare("
    SELECT product_id, unit
    FROM products
    WHERE name = ?
    LIMIT 1
  ");

  // add product WITH unit
  $stmtAddProduct = $conn->prepare("
    INSERT INTO products (name, unit)
    VALUES (?, ?)
  ");

  // find price
  $stmtFindPrice = $conn->prepare("
    SELECT price_id
    FROM product_prices
    WHERE product_id = ? AND price = ?
    LIMIT 1
  ");

  // add price
  $stmtAddPrice = $conn->prepare("
    INSERT INTO product_prices (product_id, price)
    VALUES (?, ?)
  ");

  // update stock
  $stmtUpdStock = $conn->prepare("
    INSERT INTO stock_by_price (product_id, price_id, qty)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE
      qty = qty + VALUES(qty)
  ");

  /* ================= process items ================= */

  foreach($items as $row){

    $name  = trim((string)($row['name'] ?? ''));
    $price = (float)($row['price'] ?? 0);
    $qty   = (float)($row['qty'] ?? 0);

    // unit from UI (kg / l / buc)
    $unit = in_array($row['unit'] ?? '', ['kg','l','buc'], true)
      ? $row['unit']
      : 'kg';

    if($name === '' || $price <= 0 || $qty <= 0){
      continue;
    }

    /* product */
    $stmtFindProduct->bind_param("s", $name);
    $stmtFindProduct->execute();
    $p = $stmtFindProduct->get_result()->fetch_assoc();

    if($p){
      // product exists → DO NOT change unit
      $productId = (int)$p['product_id'];
    } else {
      // new product → save unit
      $stmtAddProduct->bind_param("ss", $name, $unit);
      $stmtAddProduct->execute();
      $productId = (int)$conn->insert_id;
    }

    /* price */
    $stmtFindPrice->bind_param("id", $productId, $price);
    $stmtFindPrice->execute();
    $pr = $stmtFindPrice->get_result()->fetch_assoc();

    if($pr){
      $priceId = (int)$pr['price_id'];
    } else {
      $stmtAddPrice->bind_param("id", $productId, $price);
      $stmtAddPrice->execute();
      $priceId = (int)$conn->insert_id;
    }

    /* stock */
    $stmtUpdStock->bind_param("iid", $productId, $priceId, $qty);
    $stmtUpdStock->execute();
  }

  $conn->commit();
  echo "Склад успешно обновлён";

}catch(Throwable $e){
  $conn->rollback();
  fail("Ошибка: ".$e->getMessage(), 500);
}
