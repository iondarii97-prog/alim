<?php
header('Content-Type: text/plain; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  /* ================== DB ================== */
  $conn = new mysqli("localhost","root","","alim");
  $conn->set_charset("utf8mb4");

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Метод не разрешён");
  }

  /* ================== ДАННЫЕ ФОРМЫ ================== */
  $doc_number  = trim($_POST['doc_number'] ?? '');
  $doc_date    = trim($_POST['doc_date'] ?? '');
  $note        = trim($_POST['note'] ?? '');
  $consumer_id = (int)($_POST['consumer_id'] ?? 0);
  $items_json  = $_POST['items_json'] ?? '';

  if ($doc_number === '' || $doc_date === '' || $items_json === '') {
    http_response_code(400);
    exit("Заполни номер, дату и товары.");
  }
  if ($consumer_id <= 0) {
    http_response_code(400);
    exit("Выбери потребителя.");
  }

  $items = json_decode($items_json, true);
  if (!is_array($items) || !count($items)) {
    http_response_code(400);
    exit("Неверный items_json.");
  }

  $conn->begin_transaction();

  /* ================== 1) СОЗДАЁМ ДОКУМЕНТ ================== */
  $stmtDoc = $conn->prepare("
    INSERT INTO outgoing_documents
      (doc_type, doc_number, doc_date, note, consumer_id, created_at)
    VALUES
      ('Consum', ?, ?, ?, ?, NOW())
  ");
  $stmtDoc->bind_param("sssi", $doc_number, $doc_date, $note, $consumer_id);
  $stmtDoc->execute();
  $out_doc_id = (int)$stmtDoc->insert_id;

  /* ================== 2) ПОДГОТОВКА ЗАПРОСОВ ================== */

  // проверка общего остатка
  $stmtAvail = $conn->prepare("
    SELECT COALESCE(SUM(remaining_qty),0) AS avail
    FROM incoming_items
    WHERE product_id = ?
  ");

  // FIFO-партии с блокировкой
  $stmtLots = $conn->prepare("
    SELECT
      ii.item_id,
      ii.price_id,
      ii.remaining_qty
    FROM incoming_items ii
    WHERE ii.product_id = ?
      AND ii.remaining_qty > 0
    ORDER BY ii.item_id ASC
    FOR UPDATE
  ");

  // уменьшить FIFO
  $stmtUpdLot = $conn->prepare("
    UPDATE incoming_items
    SET remaining_qty = remaining_qty - ?
    WHERE item_id = ?
  ");

  // записать строку списания (ВАЖНО: сохраняем incoming_item_id)
  $stmtInsOutItem = $conn->prepare("
    INSERT INTO outgoing_items
      (document_id, product_id, price_id, incoming_item_id, qty)
    VALUES (?, ?, ?, ?, ?)
  ");

  // уменьшить склад
  $stmtUpdStock = $conn->prepare("
    UPDATE stock_by_price
    SET qty = qty - ?
    WHERE product_id = ? AND price_id = ?
  ");

  /* ================== 3) FIFO-СПИСАНИЕ ================== */
  $savedLines = 0;

  foreach ($items as $it) {
    $product_id = (int)($it['product_id'] ?? 0);
    $need       = (float)($it['qty'] ?? 0);

    if ($product_id <= 0 || $need <= 0) continue;

    // 3.1 Проверяем общий остаток
    $stmtAvail->bind_param("i", $product_id);
    $stmtAvail->execute();
    $avail = (float)($stmtAvail->get_result()->fetch_assoc()['avail'] ?? 0);

    if ($avail + 1e-9 < $need) {
      throw new Exception("Недостаточно товара product_id={$product_id}. Доступно: {$avail}, нужно: {$need}");
    }

    // 3.2 Берём FIFO-партии (уже заблокированы FOR UPDATE)
    $stmtLots->bind_param("i", $product_id);
    $stmtLots->execute();
    $lots = $stmtLots->get_result();

    $remain_to_take = $need;

    while ($remain_to_take > 0 && ($lot = $lots->fetch_assoc())) {

      $lot_item_id = (int)$lot['item_id'];
      $price_id    = (int)$lot['price_id'];
      $lot_remain  = (float)$lot['remaining_qty'];

      if ($lot_remain <= 0) continue;

      $take = min($lot_remain, $remain_to_take);

      // 3.3 Пишем строку списания с incoming_item_id
      $stmtInsOutItem->bind_param(
        "iiiid",
        $out_doc_id,
        $product_id,
        $price_id,
        $lot_item_id,
        $take
      );
      $stmtInsOutItem->execute();

      // 3.4 Уменьшаем FIFO
      $stmtUpdLot->bind_param("di", $take, $lot_item_id);
      $stmtUpdLot->execute();

      // 3.5 Уменьшаем склад
      $stmtUpdStock->bind_param("dii", $take, $product_id, $price_id);
      $stmtUpdStock->execute();

      $savedLines++;
      $remain_to_take -= $take;
    }
  }

  if ($savedLines === 0) {
    throw new Exception("Ничего не списано (проверь товары).");
  }

  $conn->commit();
  echo "OK: списание #{$out_doc_id}, строк партий: {$savedLines}";

} catch (Throwable $e) {
  if (isset($conn)) { try { $conn->rollback(); } catch(Throwable $x) {} }
  http_response_code(500);
  echo "Ошибка: " . $e->getMessage();
}
