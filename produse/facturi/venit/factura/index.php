<?php
$page_title = 'Factura intrare';
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

.preview{
  width:100%;
  height:220px;
  border-radius:10px;
  background:#d1d5db;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#6b7280;
  font-weight:600;
  overflow:hidden;
}
.preview img{
  width:100%;
  height:100%;
  object-fit:cover;
}

.btn-green{
  background:#41a546;
  border-color:#41a546;
  color:#fff;
  font-weight:600;
  padding:10px 14px;
  border-radius:6px;
}
.btn-green:hover{
  background:#37903c;
  border-color:#37903c;
  color:#fff;
}

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
</style>

<?php
$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($d){ return $d ? date('d.m.Y', strtotime($d)) : ''; }

$doc_id = (int)($_GET['id'] ?? 0);
if($doc_id <= 0){
  http_response_code(400);
  exit("Нет id документа.");
}

/* DOCUMENT */
$stmt = $conn->prepare("
  SELECT
    d.document_id,
    d.doc_type,
    d.doc_number,
    d.doc_date,
    d.file_path,
    s.name AS supplier_name
  FROM incoming_documents d
  LEFT JOIN suppliers s ON s.supplier_id = d.supplier_id
  WHERE d.document_id = ?
  LIMIT 1
");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if(!$doc){
  http_response_code(404);
  exit("Документ не найден.");
}

$fileUrl = !empty($doc['file_path']) ? '/' . ltrim($doc['file_path'], '/') : '';
$ext = strtolower(pathinfo((string)$doc['file_path'], PATHINFO_EXTENSION));
$isImage = in_array($ext, ['jpg','jpeg','png','webp','gif'], true);

/* ITEMS */
$itemsStmt = $conn->prepare("
  SELECT
    p.name AS product_name,
    p.unit,
    pp.price,
    pp.currency,
    ii.qty,
    (ii.qty * pp.price) AS sum
  FROM incoming_items ii
  JOIN products p ON p.product_id = ii.product_id
  JOIN product_prices pp ON pp.price_id = ii.price_id
  WHERE ii.document_id = ?
  ORDER BY ii.item_id
");
$itemsStmt->bind_param("i", $doc_id);
$itemsStmt->execute();
$res = $itemsStmt->get_result();

$totalSum = 0;
$count = 0;
$items = [];
$currency = 'MDL';

while($r = $res->fetch_assoc()){
  $items[] = $r;
  $totalSum += (float)$r['sum'];
  $currency = $r['currency'] ?: $currency;
  $count++;
}
?>

<div class="page-content">
<div class="page-wrap">

  <!-- TOP -->
  <div class="card card-soft mb-3">
    <div class="card-body p-4">
      <div class="row g-4 align-items-center">

        <div class="col-lg-8">
          <p class="factura-title">
            <?= h(($doc['doc_type'] ?: 'Factura').' '.($doc['doc_number'] ?: '#'.$doc_id)) ?>
          </p>

          <div class="row g-4">
            <div class="col-md-7">
              <div class="label">Furnizor</div>
              <div class="value-strong"><?= h($doc['supplier_name'] ?: '-') ?></div>
            </div>
            <div class="col-md-5">
              <div class="label">Data</div>
              <div class="value"><?= fmtDate($doc['doc_date']) ?></div>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="preview mb-2">
            <?php if($fileUrl && $isImage && file_exists($_SERVER['DOCUMENT_ROOT'].$fileUrl)): ?>
              <img src="<?= h($fileUrl) ?>" alt="Factura">
            <?php elseif($fileUrl): ?>
              <?= strtoupper($ext ?: 'FILE') ?>
            <?php else: ?>
              Нет файла
            <?php endif; ?>
          </div>

          <?php if($fileUrl): ?>
            <a class="btn btn-green w-100" href="<?= h($fileUrl) ?>" download>Скачать документ</a>
          <?php else: ?>
            <button class="btn btn-secondary w-100" disabled>Файл не загружен</button>
          <?php endif; ?>

          <?php if($count > 0): ?>
            <button class="btn btn-danger w-100 mt-2" onclick="deleteDocument(<?= (int)$doc['document_id'] ?>)">
              🗑 Удалить документ
            </button>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>

  <!-- ITEMS -->
  <div class="card card-soft">
    <div class="card-body p-4">

      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead>
            <tr class="text-center">
              <th>Название товара</th>
              <th>Цена / unitate</th>
              <th>Количество</th>
              <th>Сумма</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!$count): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">Нет товаров</td></tr>
            <?php else: foreach($items as $it): ?>
              <tr>
                <td><?= h($it['product_name']) ?></td>
                <td class="text-end"><?= number_format($it['price'],2,'.',' ') ?> <?= h($it['currency']) ?></td>
                <td class="text-end"><?= number_format($it['qty'],3,'.',' ') ?> <?= h($it['unit']) ?></td>
                <td class="text-end"><?= number_format($it['sum'],2,'.',' ') ?> <?= h($it['currency']) ?></td>
              </tr>
            <?php endforeach; ?>
              <tr class="fw-bold">
                <td colspan="3" class="text-end">Итого</td>
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

<?php include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php'; ?>
<script>
function deleteDocument(id){
  if(!confirm('Удалить документ?\nВсе изменения будут отменены!')) return;

  fetch('delete_incoming_document.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ document_id: id })
  })
  .then(r => r.json())
  .then(res => {
    if(res.ok){
      location.href = '/incoming/list.php';
    }else{
      alert(res.message || 'Ошибка удаления');
    }
  })
  .catch(e => alert('Fetch error: ' + e));
}
</script>

<?php include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php'; ?>
