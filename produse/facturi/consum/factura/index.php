<?php
$page_title = 'Документ списания (Consum)';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

<style>
.page-wrap{ padding:18px; }

.card-soft{
  background:#fff;
  border-radius:14px;
  box-shadow:0 2px 10px rgba(0,0,0,.06);
  border:0;
}

.factura-title{
  font-size:16px;
  font-weight:600;
  color:#111827;
  margin:0 0 12px 0;
}
.label{ font-size:13px; color:#6b7280; margin-bottom:2px; }
.value-strong{ font-size:18px; font-weight:700; color:#374151; }
.value{ font-size:14px; font-weight:600; color:#374151; }

table thead th{ background:#fff; font-weight:700; font-size:13px; }
table tbody td{ font-size:13px; }

.totals{
  display:flex;
  justify-content:flex-end;
  gap:44px;
  margin-top:18px;
  color:#6b7280;
  font-size:13px;
  flex-wrap:wrap;
}
.totals .big{
  font-size:20px;
  color:#111827;
  font-weight:800;
}
@media print {

  @page {
    size: A4 portrait;
    margin: 10mm;
  }

  /* ===== скрываем всё ===== */
  body * {
    visibility: hidden !important;
  }

  /* ===== показываем только печатный блок ===== */
  .print-only,
  .print-only * {
    visibility: visible !important;
  }

  .print-only {
    position: absolute !important;
    left: 0;
    top: 0;
    width: 100% !important;
    margin: 0 !important;
    padding: 10mm !important;
    background: #fff !important;
  }

  /* ===== таблица ===== */
  table {
    width: 100% !important;
    border-collapse: collapse !important;
  }

  thead { display: table-header-group !important; }

  th, td {
    border: 1px solid #000 !important;
    color: #000 !important;
    background: #fff !important;
    font-size: 11px !important;
    padding: 4px !important;
  }

  /* ===== без разрывов ===== */
  table, tr, td, th {
    page-break-inside: avoid !important;
  }

  /* ===== кнопки ===== */
  .btn, form {
    display: none !important;
  }
}
</style>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/includ/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($d){ return $d ? date('d.m.Y', strtotime($d)) : ''; }

$doc_id = (int)($_GET['id'] ?? 0);
if($doc_id <= 0){
  http_response_code(400);
  exit('Нет id документа');
}

/* ===== DOCUMENT ===== */
$stmt = $conn->prepare("
  SELECT document_id, doc_type, doc_number, doc_date, note
  FROM outgoing_documents
  WHERE document_id = ?
  LIMIT 1
");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if(!$doc){
  http_response_code(404);
  exit('Документ не найден');
}

/* ===== ITEMS ===== */
$stmt = $conn->prepare("
  SELECT
  CASE
    WHEN cds.name IN ('Produse alim','Piine','Cruasane') THEN 'Produse alimentare'
    WHEN cds.name = 'Detergenti' THEN 'Detergenti'
    ELSE cds.name
  END AS category_group,

  w.name AS warehouse_name,
  p.name AS product_name,
  p.unit,
  pp.price,
  pp.currency,
  SUM(oi.qty) AS qty,
  SUM(oi.qty * pp.price) AS sum

FROM outgoing_items oi
JOIN products p ON p.product_id = oi.product_id
LEFT JOIN categories_ds cds ON cds.category_ds_id = p.category_ds_id
LEFT JOIN warehouses w ON w.warehouse_id = p.warehouse_id
JOIN product_prices pp ON pp.price_id = oi.price_id

WHERE oi.document_id = ?

GROUP BY category_group, w.warehouse_id, oi.product_id, pp.price

ORDER BY
  CASE WHEN category_group='Detergenti' THEN 2 ELSE 1 END,
  category_group,
  w.name,
  p.name

");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
$totalSum = 0;
$count = 0;
$currency = 'MDL';

while($r = $res->fetch_assoc()){
  $items[] = $r;
  $totalSum += (float)$r['sum'];
  $currency = $r['currency'] ?: $currency;
  $count++;
}

$title = trim(($doc['doc_type'] ?: 'Consum').' '.($doc['doc_number'] ?: ''));
if($title === '' || strtolower($title) === 'consum'){
  $title = 'Consum #' . $doc_id;
}

$note = trim((string)$doc['note']);
if($note === '') $note = '-';
?>

<div class="page-content">
<div class="page-wrap">

  <!-- HEADER -->
  <div class="card card-soft mb-3">
    <div class="card-body p-4">
      <div class="row g-4 align-items-center">

        <div class="col-lg-8">
          <p class="factura-title"><?= h($title) ?></p>

          <div class="row g-4">
            <div class="col-md-7">
              <div class="label">Denumirea</div>
              <div class="value-strong"><?= h($note) ?></div>
            </div>
            <div class="col-md-5">
              <div class="label">Data</div>
              <div class="value"><?= fmtDate($doc['doc_date']) ?></div>
            </div>
          </div>
        </div>

        <div class="col-lg-4 d-flex flex-column gap-2">

  <!-- ПЕЧАТЬ -->
  <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
  🖨 Печать таблицы
</button>
  <!-- УДАЛЕНИЕ -->
  <form action="delete_outgoing.php" method="post"
    onsubmit="return confirm('Точно удалить документ?\nБудет выполнен полный откат склада (FIFO).');">

    <input type="hidden" name="id" value="<?= (int)$doc_id ?>">
    <input type="hidden" name="confirm" value="YES">

    <button type="submit" class="btn btn-danger w-100">
      🗑 Удалить документ
    </button>
  </form>

</div>


      </div>
    </div>
  </div>

  <!-- ITEMS -->
  <div class="print-only">
  <div class="card card-soft">
    <div class="card-body p-4">

      <div class="table-responsive">
        <table class="table  table-bordered align-middle">
          <thead>
            <tr class="text-center">
              <th>Denumirea produsului</th>
              <th>Prețu</th>
              <th>Cantitatea</th>
              <th>Suma</th>
            </tr>
          </thead>
          <tbody>
<?php
$currentCat = null;
$currentWarehouse = null;
$groupSum = 0;
?>

<?php if(!$count): ?>
<tr>
  <td colspan="4" class="text-center text-muted py-4">Нет товаров</td>
</tr>

<?php else: foreach($items as $it): ?>

<?php
// если группа изменилась — закрываем старую
if($currentCat !== null && $currentCat !== $it['category_group']):
?>
<tr style="font-weight:700;background:#f3f4f6">
  <td colspan="3" class="text-end">TOTAL</td>
  <td class="text-end"><?= number_format($groupSum,2,'.',' ') ?> <?= h($currency) ?></td>
</tr>
<?php
$groupSum = 0;
$currentWarehouse = null;
endif;

// если новая группа — печатаем заголовок
if($currentCat !== $it['category_group']):
  $currentCat = $it['category_group'];
?>
<tr style="background:#e5e7eb;font-weight:700">
  <td colspan="4"><?= h($currentCat ?: 'Без категории') ?></td>
</tr>
<?php endif; ?>

<?php
// склад
if($currentWarehouse !== $it['warehouse_name']):
  $currentWarehouse = $it['warehouse_name'];
?>

<?php endif; ?>

<tr>
  <td><?= h($it['product_name']) ?></td>
  <td class="text-end"><?= number_format($it['price'],2,'.',' ') ?> <?= h($it['currency']) ?></td>
  <td class="text-end"><?= number_format($it['qty'],3,'.',' ') ?> <?= h($it['unit']) ?></td>
  <td class="text-end"><?= number_format($it['sum'],2,'.',' ') ?> <?= h($it['currency']) ?></td>
</tr>

<?php $groupSum += (float)$it['sum']; ?>

<?php endforeach; ?>

<!-- закрываем последнюю группу -->
<tr style="font-weight:700;background:#f3f4f6">
  <td colspan="3" class="text-end">TOTAL</td>
  <td class="text-end"><?= number_format($groupSum,2,'.',' ') ?> <?= h($currency) ?></td>
</tr>

<tr class="fw-bold">
  <td colspan="3" class="text-end">Total</td>
  <td class="text-end"><?= number_format($totalSum,2,'.',' ') ?> <?= h($currency) ?></td>
</tr>

<?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
</div>
<script>
function printDocument(){
  window.print();
}
</script>

<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>
