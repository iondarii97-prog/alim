<?php
require $_SERVER['DOCUMENT_ROOT'].'/includ/db.php';
require $_SERVER['DOCUMENT_ROOT'].'/fifo/recalc_outgoing.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$doc_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if($doc_id <= 0) exit('Нет id');

/* =========================================================
   SAVE (ONLY ONE PLACE, BEFORE ANY OUTPUT)
========================================================= */
if($_SERVER['REQUEST_METHOD']==='POST'){

  $conn->begin_transaction();

  try{

    /* ---- update header ---- */
    $stmt = $conn->prepare("
      UPDATE outgoing_documents
      SET doc_number=?, doc_date=?, note=?, fifo_done=0
      WHERE document_id=?
    ");
    $stmt->bind_param(
      "sssi",
      $_POST['doc_number'],
      $_POST['doc_date'],
      $_POST['note'],
      $doc_id
    );
    $stmt->execute();

    /* ---- update items ---- */
    foreach($_POST['items'] ?? [] as $id=>$row){

      $id = (int)$id;

      if(($row['delete'] ?? '')==='1'){
        $conn->query("DELETE FROM outgoing_items WHERE item_id=$id");
        continue;
      }

      $qty = (float)$row['qty'];
      $price_id = (int)$row['price_id'];

      $stmt = $conn->prepare("
        UPDATE outgoing_items
        SET qty=?, price_id=?
        WHERE item_id=?
      ");
      $stmt->bind_param("dii",$qty,$price_id,$id);
      $stmt->execute();
    }

    $conn->commit();

  }catch(Throwable $e){
    $conn->rollback();
    die("Ошибка сохранения: ".$e->getMessage());
  }

  /* ---- FIFO recalculation ---- */
  $res = fifo_recalc_outgoing($conn,$doc_id);
  if(!$res['ok']){
    die("FIFO ошибка: ".$res['error']);
  }

  header("Location: index.php?id=".$doc_id);
  exit;
}

/* =========================================================
   LOAD DATA FOR FORM
========================================================= */

/* ---- document ---- */
$doc = $conn->query("
  SELECT document_id, doc_number, doc_date, note
  FROM outgoing_documents
  WHERE document_id = $doc_id
")->fetch_assoc();

if(!$doc) exit('Документ не найден');

/* ---- items ---- */
$items = $conn->query("
  SELECT
    oi.item_id,
    oi.product_id,
    p.name AS product_name,
    oi.qty,
    oi.price_id,
    pp.price,
    pp.currency
  FROM outgoing_items oi
  JOIN products p ON p.product_id = oi.product_id
  JOIN product_prices pp ON pp.price_id = oi.price_id
  WHERE oi.document_id = $doc_id
  ORDER BY p.name
")->fetch_all(MYSQLI_ASSOC);

/* ---- prices ---- */
$prices = $conn->query("
  SELECT pp.price_id, p.name, pp.price, pp.currency
  FROM product_prices pp
  JOIN products p ON p.product_id = pp.product_id
  ORDER BY p.name, pp.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

/* =========================================================
   HTML PART
========================================================= */

$page_title = 'Редактирование документа (FIFO)';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

<style>
.page-wrap{padding:18px}
.card-soft{
  background:#fff;
  border-radius:14px;
  box-shadow:0 2px 10px rgba(0,0,0,.06)
}
</style>

<div class="page-content">
<div class="page-wrap">

<form method="post" class="card card-soft p-4">

<h4>Редактирование + FIFO</h4>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <label>Номер</label>
    <input class="form-control" name="doc_number"
      value="<?=h($doc['doc_number'])?>">
  </div>
  <div class="col-md-4">
    <label>Дата</label>
    <input type="date" class="form-control"
      name="doc_date" value="<?=h($doc['doc_date'])?>">
  </div>
  <div class="col-md-4">
    <label>Примечание</label>
    <input class="form-control" name="note"
      value="<?=h($doc['note'])?>">
  </div>
</div>

<table class="table table-bordered align-middle">
<thead>
<tr class="text-center">
  <th>Продукт</th>
  <th>Цена</th>
  <th>Кол-во</th>
  <th>Удалить</th>
</tr>
</thead>
<tbody>

<?php foreach($items as $it): ?>
<tr>
  <td><?=h($it['product_name'])?></td>

  <td>
    <select name="items[<?=$it['item_id']?>][price_id]" class="form-select">
      <?php foreach($prices as $p): ?>
      <option value="<?=$p['price_id']?>"
        <?=$p['price_id']==$it['price_id']?'selected':''?>>
        <?=h($p['name'])?> —
        <?=number_format($p['price'],2,'.',' ')?>
        <?=h($p['currency'])?>
      </option>
      <?php endforeach; ?>
    </select>
  </td>

  <td>
    <input type="number" step="0.001"
      class="form-control text-end"
      name="items[<?=$it['item_id']?>][qty]"
      value="<?=h($it['qty'])?>">
  </td>

  <td class="text-center">
    <input type="checkbox"
      name="items[<?=$it['item_id']?>][delete]"
      value="1">
  </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

<div class="d-flex gap-2">
  <button class="btn btn-success">
    💾 Сохранить и пересчитать FIFO
  </button>
  <a href="index.php?id=<?=$doc_id?>" class="btn btn-secondary">
    ← Назад
  </a>
</div>

</form>

</div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php'; ?>
