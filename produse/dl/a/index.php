<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

$month = $_GET['month'] ?? date('Y-m');
$date_from = $month . '-01';
$date_to   = date('Y-m-d', strtotime("$date_from +1 month"));

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* =========================
   SQL
========================= */
$sql = "
SELECT
  cds.category_ds_id,
  cds.name AS cat_name,
  p.product_id,
  p.name AS product_name,
  p.unit AS unit_name,
  pp.price,
  SUM(ii.qty) AS total_qty
FROM incoming_items ii
JOIN incoming_documents id ON id.document_id = ii.document_id
JOIN products p ON p.product_id = ii.product_id
JOIN categories_ds cds ON cds.category_ds_id = p.category_ds_id
JOIN product_prices pp ON pp.price_id = ii.price_id
WHERE id.doc_date >= ?
  AND id.doc_date < ?
  AND p.active = 1
  AND cds.name IN ('Produse alim', 'Detergenti')
GROUP BY cds.category_ds_id, p.product_id, pp.price
ORDER BY cds.category_ds_id, p.sort_report, p.name, pp.price
";


$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$res = $stmt->get_result();


// месяц из GET уже есть в $month
$timestamp = strtotime($month . '-01');

// месяцы на румынском
$monthsRo = [
  1=>'Ianuarie',2=>'Februarie',3=>'Martie',4=>'Aprilie',
  5=>'Mai',6=>'Iunie',7=>'Iulie',8=>'August',
  9=>'Septembrie',10=>'Octombrie',11=>'Noiembrie',12=>'Decembrie'
];

$monthName = $monthsRo[(int)date('n', $timestamp)];
$year      = date('Y', $timestamp);


/* ===== HEADER ===== */
$page_title = 'Produse intrate pe lună';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>
<style>
/* =============================
   BASE
============================= */
body{
  font-family: Arial, sans-serif;
}

/* =============================
   TABLE
============================= */
table{
  width:100%;
  border-collapse:collapse;
  font-size:10px;
  table-layout:auto; /* главное */
}

th, td{
  border:1px solid #000;
  padding:3px 6px;
  text-align:center;
  vertical-align:middle;
  white-space:nowrap; /* по умолчанию */
}
/* Разрешаем перенос только для названий */
td.left{
  white-space:normal;
  word-break:break-word;
}

/* Текст слева */
td.left,
th.left{
  text-align:left !important;
}

/* =============================
   HEADER
============================= */
thead th{
  background:#eee;
  font-weight:bold;
}

/* =============================
   ROW STYLES
============================= */
.cat-row{
  background:#e5e7eb;
  font-weight:bold;
}

.total-row{
  background:#f3f4f6;
  font-weight:bold;
}

.grand-total{
  background:#fde68a;
  font-weight:bold;
  font-size:11px;
}

/* =============================
   PRINT
============================= */
@page{
  size:A4;
  margin:10mm;
}

@media print{
  body{
    margin:0;
  }
  table{
    font-size:10px;
  }

  /* Узкая колонка Nr. d/o */
  table th:nth-child(1),
  table td:nth-child(1){
    width:1% !important;
    white-space:nowrap;
  }

  /* Узкая колонка Unitatea */
  table th:nth-child(3),
  table td:nth-child(3){
    width:1% !important;
    white-space:nowrap;
  }

  table th:nth-child(4),
table td:nth-child(4){
  width:8% !important;
}

table th:nth-child(2),
table td:nth-child(2){
  width:70% !important;
}

}

</style>
<div class="page-content">

<h1 class="mb-3">Produse intrate pe lună (alimente & detergenți)</h1>

<form method="get" class="filter-bar mb-3">
  <span>Luna:</span>
  <input type="month" name="month" value="<?=h($month)?>">
  <button class="btn">Generează</button>
  <button type="button" class="btn btn-secondary" onclick="printReport()">🖨️ Tipărește</button>
</form>

<div class="card-soft p-4 report-wrap">
    <div class="table-responsive">
<table class="table table-hover mb-0">
<thead>
  <tr><th colspan="8">Actul de verificare a produselor alimentare primite de către BT 3 FMP de la Centru Alimentar al AN pentru luna <?= $monthName ?> <?= $year ?></th></tr>
<tr>
  <th rowspan="2">Nr. d/o</th>
  <th rowspan="2" >Denumirea produselor</th>
  <th rowspan="2">Unitatea de măsură</th>
  <th rowspan="2">Preț</th>
  <th colspan="2">Primit</th>
  <th colspan="2">Returnat</th>
</tr>
<tr>
  <th>Cantitate</th>
  <th>suma</th>
  <th>Cantitate</th>
  <th>Sumă</th>
</tr>
</thead>
<tbody>

<?php
$current_cat = null;
$cat_total_sum = 0.0;
$grand_total = 0.0;

$nr = 1;
while($r = $res->fetch_assoc()){

  if($current_cat !== $r['category_ds_id']){
    if($current_cat !== null){
      ?>
      <tr class="total-row">
        <td colspan="5" style="text-align:center;" ><strong>TOTAL</strong></td>

        <td><strong><?=number_format($cat_total_sum, 2, '.', '')?></strong></td>
        <td></td>
        <td></td>
      </tr>
      <?php
      $cat_total_sum = 0.0;
    }
    $current_cat = $r['category_ds_id'];
   
  }

  $sum = $r['total_qty'] * $r['price'];
  $grand_total += $sum;

  ?>
  <tr>
    <td><?= $nr++ ?></td>
    <td class="left"><?=h($r['product_name'])?></td>
    <td><?=h($r['unit_name'])?></td>
    <td><?= rtrim(rtrim(number_format($r['price'], 8, '.', ''), '0'), '.') ?></td>
    <td><?=number_format($r['total_qty'],2)?></td>
    <td><?=number_format($sum,2)?></td>
    <td></td>
    <td></td>
  </tr>
  <?php

  $cat_total_sum += $sum;
}

if($current_cat !== null){
?>
<tr class="total-row">
  <td colspan="5" style="text-align:center;" ><strong>TOTAL</strong></td>
  <td><strong><?=number_format($cat_total_sum, 2, '.', '')?></strong></td>
  <td></td>
  <td></td>
</tr>
<?php } ?>
<tr class="grand-total">
  <td colspan="5" style="text-align:center;" ><strong>TOTAL</strong></td>
  <td><strong><?=number_format($grand_total, 2, '.', '')?></strong></td>
  <td></td>
  <td></td>
</tr>

</tbody>
<tr>
  <th style="text-align:left; border:none;">Verificat</th>
  <th colspan="2" style="text-align:left; border:none;"></th>
  <th colspan="3" style="text-align:left; border:none;">Coordonat cu Centru Alimentar al AN</th>
</tr>
<tr>
  <th style="text-align:left; border:none;">șef depozit alimentar</th>
  <th colspan="2" style="text-align:left; border:none;"></th>
  <th colspan="3" style="text-align:left; border:none;">Șef centru alimentar al AN</th>
</tr>
<tr>
  <th colspan="2"style="text-align:left; border:none;">caporal&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Ion DARII</th>
  <th colspan="1" style="text-align:left; border:none;"></th>
  <th colspan="3" style="text-align:left; border:none;">Contabil__________________________</th>
</tr>
<tr>
  <th style="text-align:left; border:none;">LȘ ,, ____''__________2026</th>
  <th colspan="2" style="text-align:left; border:none;"></th>
  <th colspan="3" style="text-align:left; border:none;">LȘ ,, ____''__________2026</th>
</tr>
</table>
</div>
</div>
<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>
<script>
function printReport(){
  const content = document.querySelector('.table-responsive').innerHTML;
  const win = window.open('', '', 'width=900,height=650');
  win.document.write(`
    <html>
    <head>
      <title>Darea de seamă</title>
      <style>
body{font-family:Arial}
table{width:100%;border-collapse:collapse;font-size:11px;table-layout:auto}

th,td{
  border:1px solid #000;
  padding:4px 6px;
  text-align:center;
  vertical-align:middle;
  white-space:nowrap;
}

td.left{
  white-space:normal;
  word-break:break-word;
  text-align:left;
}

/* === PRINT WIDTH TUNING === */
table th:nth-child(1),
table td:nth-child(1){
  width:1% !important;
  white-space:nowrap;
}

table th:nth-child(3),
table td:nth-child(3){
  width:1% !important;
  white-space:nowrap;
}
table th:nth-child(4),
table td:nth-child(4){
  width:8% !important;
}

table th:nth-child(2),
table td:nth-child(2){
  width:70% !important;
}


th{background:#eee}
</style>


    </head>
    <body>
      ${content}
    </body>
    </html>
  `);
  win.document.close();
  win.focus();
  win.print();
  win.close();
}
</script>