<?php
require $_SERVER['DOCUMENT_ROOT'].'/includ/db.php';

$menu_id = (int)($_POST['menu_id'] ?? 0);
if(!$menu_id || !isset($_FILES['json_file'])){
  die('Invalid input');
}

$data = json_decode(file_get_contents($_FILES['json_file']['tmp_name']), true);
if(!$data) die('Invalid JSON');

$conn->begin_transaction();

try {

  /* ===== чистим старые данные ===== */
  $conn->query("DELETE FROM menu_items WHERE menu_id = $menu_id");
  $conn->query("DELETE FROM menu_meals WHERE menu_id = $menu_id");

  /* ===== блюда ===== */
  $stmt = $conn->prepare("
    INSERT INTO menu_meals (menu_id, col_index, meal_name)
    VALUES (?,?,?)
  ");
  foreach($data['meals'] as $m){
    $stmt->bind_param("iis",
      $menu_id,
      $m['col_index'],
      $m['meal_name']
    );
    $stmt->execute();
  }

  /* ===== продукты ===== */
  $stmt = $conn->prepare("
    INSERT INTO menu_items (menu_id, product_id, col_index, grams)
    VALUES (?,?,?,?)
  ");

  foreach($data['items'] as $p){
    $pid = (int)$p['product_id'];

    foreach($p['rows'] as $col => $grams){
      $g = (float)$grams;
      $c = (int)$col;

      $stmt->bind_param("iiid",
        $menu_id,
        $pid,
        $c,
        $g
      );
      $stmt->execute();
    }
  }

  $conn->commit();

  header("Location: menu_edit.php?menu_id=".$menu_id);
  exit;

} catch(Exception $e){
  $conn->rollback();
  die("Import error: ".$e->getMessage());
}
