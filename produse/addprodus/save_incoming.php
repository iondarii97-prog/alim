<?php
header('Content-Type: text/plain; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

  /* ================== ПОДКЛЮЧЕНИЕ ================== */
  $conn = new mysqli("localhost", "root", "", "alim");
  $conn->set_charset("utf8mb4");

  /* ================== ПРОВЕРКА МЕТОДА ================== */
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Метод не разрешён");
  }

  /* ================== ПОЛЯ ДОКУМЕНТА ================== */
  $doc_type    = trim($_POST['doc_type'] ?? '');
  $supplier_id = (int)($_POST['supplier_id'] ?? 0);
  $doc_number  = trim($_POST['doc_number'] ?? '');
  $doc_date    = trim($_POST['doc_date'] ?? '');
  $items_json  = $_POST['items_json'] ?? '';

  if (
    $doc_type === '' ||
    $supplier_id <= 0 ||
    $doc_number === '' ||
    $doc_date === '' ||
    $items_json === ''
  ) {
    http_response_code(400);
    exit("Заполни шапку документа и товары.");
  }

  $items = json_decode($items_json, true);
  if (!is_array($items) || !count($items)) {
    http_response_code(400);
    exit("Неверные данные товаров.");
  }

  /* ================== ЗАГРУЗКА ФАЙЛА ================== */
  $filePath = null;

  if (!empty($_FILES['doc_file']['name'])) {

    $dir = __DIR__ . '/../../asset/img/facturi';

    if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
      http_response_code(500);
      exit("Не удалось создать папку для файлов.");
    }

    if (!is_writable($dir)) {
      http_response_code(500);
      exit("Папка недоступна для записи.");
    }

    $ext = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','pdf'];
    if (!in_array($ext, $allowed, true)) {
      http_response_code(400);
      exit("Разрешены только: jpg, jpeg, png, pdf.");
    }

    $safeName = time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/','_', $_FILES['doc_file']['name']);
    $dest = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($_FILES['doc_file']['tmp_name'], $dest)) {
      http_response_code(500);
      exit("Ошибка сохранения файла.");
    }

    $filePath = "asset/img/facturi/" . $safeName;
  }

  /* ================== ТРАНЗАКЦИЯ ================== */
  $conn->begin_transaction();

  /* ================== ДОКУМЕНТ ПРИХОДА ================== */
  $stmtDoc = $conn->prepare("
    INSERT INTO incoming_documents
      (supplier_id, doc_type, doc_number, doc_date, file_path)
    VALUES (?, ?, ?, ?, ?)
  ");
  $stmtDoc->bind_param("issss", $supplier_id, $doc_type, $doc_number, $doc_date, $filePath);
  $stmtDoc->execute();
  $document_id = (int)$stmtDoc->insert_id;
  $stmtDoc->close();

  /* ================== ПОДГОТОВКА ЗАПРОСОВ ================== */
  $findProduct = $conn->prepare("SELECT product_id FROM products WHERE name=? LIMIT 1");
  $insProduct  = $conn->prepare("INSERT INTO products (name, active) VALUES (?, 1)");

  $findPrice = $conn->prepare("
    SELECT price_id
    FROM product_prices
    WHERE product_id=? AND price=?
    LIMIT 1
  ");
  $insPrice = $conn->prepare("
    INSERT INTO product_prices (product_id, price, currency)
    VALUES (?, ?, 'MDL')
  ");

  $insIncoming = $conn->prepare("
    INSERT INTO incoming_items
      (document_id, product_id, price_id, qty, remaining_qty)
    VALUES (?, ?, ?, ?, ?)
  ");

  $upsertStock = $conn->prepare("
    INSERT INTO stock_by_price (product_id, price_id, qty)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
  ");

  /* ================== ОБРАБОТКА ТОВАРОВ ================== */
  $saved = 0;

  foreach ($items as $it) {

    $name  = trim(mb_strtolower((string)($it['name'] ?? '')));
    $price = (float)($it['price'] ?? 0);
    $qty   = (float)($it['qty'] ?? 0);

    if ($name === '' || $price <= 0 || $qty <= 0) {
      continue;
    }

    // product_id
    $findProduct->bind_param("s", $name);
    $findProduct->execute();
    $r = $findProduct->get_result();

    if ($row = $r->fetch_assoc()) {
      $product_id = (int)$row['product_id'];
    } else {
      $insProduct->bind_param("s", $name);
      $insProduct->execute();
      $product_id = (int)$insProduct->insert_id;
    }

    // price_id
    $findPrice->bind_param("id", $product_id, $price);
    $findPrice->execute();
    $r2 = $findPrice->get_result();

    if ($row2 = $r2->fetch_assoc()) {
      $price_id = (int)$row2['price_id'];
    } else {
      $insPrice->bind_param("id", $product_id, $price);
      $insPrice->execute();
      $price_id = (int)$insPrice->insert_id;
    }

    // incoming_items
    $insIncoming->bind_param("iiidd", $document_id, $product_id, $price_id, $qty, $qty);
    $insIncoming->execute();

    // stock_by_price
    $upsertStock->bind_param("iid", $product_id, $price_id, $qty);
    $upsertStock->execute();

    $saved++;
  }

  if ($saved === 0) {
    $conn->rollback();
    http_response_code(400);
    exit("Ни одна строка товара не сохранена.");
  }

  /* ================== COMMIT ================== */
  $conn->commit();

  echo "OK: документ #{$document_id}, товаров: {$saved}";

} catch (Throwable $e) {
  if (isset($conn)) {
    try { $conn->rollback(); } catch(Throwable $x) {}
  }
  http_response_code(500);
  echo "Ошибка: " . $e->getMessage();
}
