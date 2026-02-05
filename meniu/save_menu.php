<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

$data = json_decode(file_get_contents("php://input"), true);

$conn->begin_transaction();

try {
  // 1. день
  $stmt = $conn->prepare("
    INSERT INTO menu_days (menu_date, day_name)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE day_name=VALUES(day_name)
  ");
  $stmt->bind_param("ss", $data['date'], $data['day']);
  $stmt->execute();

  $menuId = $conn->insert_id ?: 
    $conn->query("SELECT menu_id FROM menu_days WHERE menu_date='{$data['date']}'")
          ->fetch_assoc()['menu_id'];

  // чистим старые данные
  $conn->query("DELETE FROM menu_meals WHERE menu_id=$menuId");
  $conn->query("DELETE FROM menu_items WHERE menu_id=$menuId");

  // 2. блюда
  $stmt = $conn->prepare("
    INSERT INTO menu_meals (menu_id, col_index, meal_name)
    VALUES (?, ?, ?)
  ");
  foreach ($data['meals'] as $m) {
    if ($m['name'] === '') continue;
    $stmt->bind_param("iis", $menuId, $m['col'], $m['name']);
    $stmt->execute();
  }

  // 3. продукты
  $stmt = $conn->prepare("
    INSERT INTO menu_items (menu_id, product_id, col_index, grams)
    VALUES (?, ?, ?, ?)
  ");
  foreach ($data['items'] as $i) {
    $stmt->bind_param(
      "iiid",
      $menuId,
      $i['product_id'],
      $i['col'],
      $i['grams']
    );
    $stmt->execute();
  }

  $conn->commit();
  echo json_encode(["message"=>"✅ Меню сохранено"]);

} catch(Exception $e){
  $conn->rollback();
  http_response_code(500);
  echo json_encode(["message"=>"❌ Ошибка"]);
}
