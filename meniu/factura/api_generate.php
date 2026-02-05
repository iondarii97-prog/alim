<?php
require __DIR__.'/db.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ===== READ JSON ===== */
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  echo json_encode(['ok'=>false,'message'=>'Invalid JSON']);
  exit;
}

/* ===== INPUT ===== */
$date = $data['date'] ?? date('Y-m-d');

$doc_no = trim(
  ($data['doc_no'] ?? '') .
  ((isset($data['doc_extra']) && trim($data['doc_extra']) !== '')
    ? ' / ' . trim($data['doc_extra'])
    : '')
);
// люди за день
$pm = (int)($data['people_morning'] ?? 0);
$pl = (int)($data['people_lunch']   ?? 0);
$pe = (int)($data['people_evening'] ?? 0);

$consumer_id = 1;                // Cantină
$note        = 'Cantină';        // примечание

$issued_by   = isset($data['issued_by'])   && $data['issued_by']   !== '' ? (int)$data['issued_by']   : null;
$received_by = isset($data['received_by']) && $data['received_by'] !== '' ? (int)$data['received_by'] : null;

$items = $data['items'] ?? [];

if(!$doc_no || !count($items)){
  echo json_encode(['ok'=>false,'message'=>'Date lipsă']);
  exit;
}

$conn->begin_transaction();
/* ===== SAVE PEOPLE DAILY ===== */
$stmt = $conn->prepare("
  SELECT day_id
  FROM people_daily
  WHERE day_date = ?
  LIMIT 1
");
$stmt->bind_param("s", $date);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
  // --- UPDATE ---
  $stmt = $conn->prepare("
    UPDATE people_daily
    SET
      morning_count = ?,
      lunch_count   = ?,
      evening_count = ?,
      updated_at    = NOW()
    WHERE day_id = ?
  ");
  $stmt->bind_param(
    "iiii",
    $pm, $pl, $pe,
    $row['day_id']
  );
  $stmt->execute();

} else {
  // --- INSERT ---
  $stmt = $conn->prepare("
    INSERT INTO people_daily
      (day_date, morning_count, lunch_count, evening_count)
    VALUES
      (?, ?, ?, ?)
  ");
  $stmt->bind_param(
    "siii",
    $date, $pm, $pl, $pe
  );
  $stmt->execute();
}

try {

  /* ===== 1. CREATE OUTGOING DOCUMENT ===== */
  $stmt = $conn->prepare("
    INSERT INTO outgoing_documents
      (doc_type, doc_number, doc_date, note, issued_by, received_by, fifo_done, consumer_id)
    VALUES
      ('Consum', ?, ?, ?, ?, ?, 0, ?)
  ");
  $stmt->bind_param(
    "sssiii",
    $doc_no,
    $date,
    $note,
    $issued_by,
    $received_by,
    $consumer_id
  );
  $stmt->execute();

  $out_doc_id = $stmt->insert_id;

  /* ===== 2. FIFO PER ITEM ===== */
  $shortages = [];
  $fifo_rows = []; // для preview

  foreach($items as $it){

    $productName = trim($it['name'] ?? '');
    $need = (float)($it['total'] ?? 0);

    if($need <= 0) continue;

    /* --- get product_id + unit --- */
    $stmt = $conn->prepare("
      SELECT product_id, unit
      FROM products
      WHERE name = ?
      LIMIT 1
    ");
    $stmt->bind_param("s", $productName);
    $stmt->execute();
    $prod = $stmt->get_result()->fetch_assoc();

    if(!$prod){
      throw new Exception("Produs inexistent: $productName");
    }

    $product_id = (int)$prod['product_id'];
    $unit = $prod['unit'];

    /* --- FIFO batches (lock rows) --- */
    $stmt = $conn->prepare("
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
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $batches = $stmt->get_result();

    $left = $need;

    while($row = $batches->fetch_assoc()){
      if($left <= 0) break;

      $incoming_item_id = (int)$row['item_id'];
      $price_id = (int)$row['price_id'];
      $have = (float)$row['remaining_qty'];

      $take = min($have, $left);

      /* --- 2.1 update incoming_items.remaining_qty --- */
      $stmt2 = $conn->prepare("
        UPDATE incoming_items
        SET remaining_qty = remaining_qty - ?
        WHERE item_id = ?
      ");
      $stmt2->bind_param("di", $take, $incoming_item_id);
      $stmt2->execute();

      /* --- 2.2 update stock_by_price.qty (защита от минуса) --- */
      $stmt2 = $conn->prepare("
        UPDATE stock_by_price
        SET qty = qty - ?
        WHERE product_id = ? AND price_id = ? AND qty >= ?
      ");
      $stmt2->bind_param("diid", $take, $product_id, $price_id, $take);
      $stmt2->execute();

      if($stmt2->affected_rows === 0){
        // попытка уйти в минус — стоп
        throw new Exception("Stoc negativ: $productName");
      }

      /* --- 2.3 insert outgoing_items --- */
      $stmt2 = $conn->prepare("
        INSERT INTO outgoing_items
          (document_id, product_id, price_id, incoming_item_id, qty)
        VALUES
          (?, ?, ?, ?, ?)
      ");
      $stmt2->bind_param(
        "iiiid",
        $out_doc_id,
        $product_id,
        $price_id,
        $incoming_item_id,
        $take
      );
      $stmt2->execute();

      // для preview
      $fifo_rows[] = [
        'product' => $productName,
        'incoming_item_id' => $incoming_item_id,
        'price_id' => $price_id,
        'qty' => $take,
        'unit' => $unit
      ];

      $left -= $take;
    }

    if($left > 0){
      $shortages[] = [
        'name' => $productName,
        'missing' => number_format($left,3,'.',''),
        'um' => $unit
      ];
    }
  }

  /* ===== 3. CHECK SHORTAGES ===== */
  if(count($shortages)){
    $conn->rollback();
    echo json_encode([
      'ok'=>false,
      'message'=>'Stoc insuficient',
      'shortages'=>$shortages
    ]);
    exit;
  }

  /* ===== 4. MARK FIFO DONE ===== */
  $stmt = $conn->prepare("
    UPDATE outgoing_documents
    SET fifo_done = 1,
        fifo_done_at = NOW()
    WHERE document_id = ?
  ");
  $stmt->bind_param("i", $out_doc_id);
  $stmt->execute();

  $conn->commit();

  echo json_encode([
    'ok'=>true,
    'message'=>'FIFO executat cu succes',
    'document_id'=>$out_doc_id,
    'fifo_rows'=>$fifo_rows // можно использовать в fifo_preview.php
  ]);
  exit;

} catch(Throwable $e){
  $conn->rollback();
  echo json_encode([
    'ok'=>false,
    'message'=>'Eroare FIFO: '.$e->getMessage()
  ]);
  exit;
}
