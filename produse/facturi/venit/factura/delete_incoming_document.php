<?php
// delete_incoming_document.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$docId = (int)($data['document_id'] ?? 0);

if($docId <= 0){
  echo json_encode(['ok'=>false,'message'=>'Invalid document_id']);
  exit;
}

$conn->begin_transaction();

try{

  /* 🔒 Check document */
  $stmt = $conn->prepare("
    SELECT document_id
    FROM incoming_documents
    WHERE document_id = ?
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->bind_param("i", $docId);
  $stmt->execute();
  if(!$stmt->get_result()->fetch_assoc()){
    throw new Exception('Документ не найден');
  }

  /* 🚫 Check FIFO usage */
  $stmt = $conn->prepare("
    SELECT COUNT(*) c
    FROM outgoing_items oi
    JOIN incoming_items ii ON ii.item_id = oi.incoming_item_id
    WHERE ii.document_id = ?
  ");
  $stmt->bind_param("i", $docId);
  $stmt->execute();
  if((int)$stmt->get_result()->fetch_assoc()['c'] > 0){
    throw new Exception('Документ уже использован в списаниях. Удаление запрещено.');
  }

  /* 📦 Get incoming items */
  $stmt = $conn->prepare("
    SELECT product_id, price_id, qty
    FROM incoming_items
    WHERE document_id = ?
    FOR UPDATE
  ");
  $stmt->bind_param("i", $docId);
  $stmt->execute();
  $items = $stmt->get_result();

  /* ⬇️ Reduce stock */
  $updStock = $conn->prepare("
    UPDATE stock_by_price
    SET qty = qty - ?
    WHERE product_id = ? AND price_id = ?
    LIMIT 1
  ");

  while($r = $items->fetch_assoc()){
    $updStock->bind_param("dii", $r['qty'], $r['product_id'], $r['price_id']);
    $updStock->execute();

    if($updStock->affected_rows === 0){
      throw new Exception('Ошибка склада: строка stock_by_price не найдена');
    }
  }

  /* 🗑 Delete items */
  $stmt = $conn->prepare("
    DELETE FROM incoming_items
    WHERE document_id = ?
  ");
  $stmt->bind_param("i", $docId);
  $stmt->execute();

  /* 🗑 Delete document */
  $stmt = $conn->prepare("
    DELETE FROM incoming_documents
    WHERE document_id = ?
  ");
  $stmt->bind_param("i", $docId);
  $stmt->execute();

  $conn->commit();
  echo json_encode(['ok'=>true]);

}catch(Throwable $e){
  $conn->rollback();
  echo json_encode([
    'ok'=>false,
    'message'=>$e->getMessage()
  ]);
}
