<?php
// delete_outgoing.php — удаление документа списания с откатом склада (FIFO)

ini_set('display_errors', 0);
error_reporting(E_ALL);

require $_SERVER['DOCUMENT_ROOT'] . '/includ/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method not allowed');
}

$docId   = (int)($_POST['id'] ?? 0);
$confirm = $_POST['confirm'] ?? '';

if ($docId <= 0 || $confirm !== 'YES') {
  exit('Invalid request');
}

$conn->begin_transaction();

try {

  /* === 1. Блокируем документ === */
  $stmt = $conn->prepare("
    SELECT document_id
    FROM outgoing_documents
    WHERE document_id = ?
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->bind_param("i", $docId);
  $stmt->execute();

  if (!$stmt->get_result()->fetch_assoc()) {
    throw new Exception('Документ не найден');
  }

  /* === 2. Получаем все строки списания === */
  $stmt = $conn->prepare("
    SELECT
      oi.product_id,
      oi.price_id,
      oi.incoming_item_id,
      oi.qty
    FROM outgoing_items oi
    WHERE oi.document_id = ?
    FOR UPDATE
  ");
  $stmt->bind_param("i", $docId);
  $stmt->execute();
  $items = $stmt->get_result();

  if ($items->num_rows === 0) {
    throw new Exception('У документа нет строк списания');
  }

  /* === 3. Подготовка запросов === */

  // вернуть FIFO
  $updIncoming = $conn->prepare("
    UPDATE incoming_items
    SET remaining_qty = remaining_qty + ?
    WHERE item_id = ?
  ");

  // вернуть склад (если нет строки — создаст)
  $updStock = $conn->prepare("
    INSERT INTO stock_by_price (product_id, price_id, qty)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE
      qty = qty + VALUES(qty)
  ");

  /* === 4. Откат FIFO и склада === */
  while ($row = $items->fetch_assoc()) {

    $qty = (float)$row['qty'];

    // 4.1 Возвращаем в FIFO
    if (!empty($row['incoming_item_id'])) {
      $updIncoming->bind_param(
        "di",
        $qty,
        $row['incoming_item_id']
      );
      $updIncoming->execute();
    }

    // 4.2 Возвращаем на склад
    $updStock->bind_param(
      "iid",
      $row['product_id'],
      $row['price_id'],
      $qty
    );
    $updStock->execute();
  }

  /* === 5. Удаляем строки списания === */
  $stmt = $conn->prepare("
    DELETE FROM outgoing_items
    WHERE document_id = ?
  ");
  $stmt->bind_param("i", $docId);
  $stmt->execute();

  /* === 6. Удаляем документ === */
  $stmt = $conn->prepare("
    DELETE FROM outgoing_documents
    WHERE document_id = ?
  ");
  $stmt->bind_param("i", $docId);
  $stmt->execute();

  $conn->commit();

  header('Location: /outgoing/list.php');
  exit;

} catch (Throwable $e) {

  $conn->rollback();
  http_response_code(500);
  exit('Ошибка удаления: ' . $e->getMessage());

}
