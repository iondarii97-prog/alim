<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

$month = $_GET['month'] ?? date('Y-m');
$date_from = $month . '-01';
$date_to   = date('Y-m-d', strtotime("$date_from +1 month"));

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$page_title = 'Darea de seama FIFO';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';

$start_label = date('d.m.Y', strtotime($date_from));
$end_label   = date('d.m.Y', strtotime("$date_to -1 day"));

// luna din GET
$ym = $_GET['month'] ?? date('Y-m');
$timestamp = strtotime($ym . '-01');

$monthsRo = [
  1=>'Ianuarie',2=>'Februarie',3=>'Martie',4=>'Aprilie',5=>'Mai',6=>'Iunie',
  7=>'Iulie',8=>'August',9=>'Septembrie',10=>'Octombrie',11=>'Noiembrie',12=>'Decembrie'
];

$monthName = $monthsRo[(int)date('n', $timestamp)];
$year      = date('Y', $timestamp);
?>
<div class="page-content">
<form method="get" class="filter-bar mb-3">
  Luna:
  <input type="month" name="month" value="<?=h($month)?>">
  <button class="btn">Generează</button>
  <button type="button" class="btn btn-secondary" onclick="printReport()">🖨️ Tipărește</button>
</form>

<h1>Darea de seama</h1>

<div class="table-responsive">
<table class="table table-hover mb-0">
<tbody>
<tr>
  <th colspan="12">
    BATALIONUL 3 INFANTERIE INDEPENDENTĂ FMP RM(COȘNIȚA)
    Darea de seamă pentru luna <?= $monthName ?> <?= $year ?>
  </th>
</tr>
<tr>
  <th rowspan="2">Denumire produs</th>
  <th rowspan="2">Preț/kg</th>
  <th colspan="2"><?=$start_label?></th>
  <th colspan="2">Venit</th>
  <th colspan="2">Consum</th>
  <th colspan="2"><?=$end_label?></th>
</tr>
<tr>
  <th>Sold</th><th>Sumă</th>
  <th>Sold</th><th>Sumă</th>
  <th>Sold</th><th>Sumă</th>
  <th>Sold</th><th>Sumă</th>
</tr>

<?php
$sql = "
SELECT
  p.product_id,
  p.name,
  p.category_ds_id,
  cds.name AS cat_name,
  pp.price_id,
  pp.price
FROM products p
JOIN categories_ds cds ON cds.category_ds_id = p.category_ds_id
JOIN product_prices pp ON pp.product_id = p.product_id
WHERE p.active = 1
ORDER BY p.category_ds_id, p.name, pp.price
";
$res = $conn->query($sql);

$current_cat = null;

// TOTAL pe categorie (sume)
$cat_open = $cat_in = $cat_out = $cat_close = 0.0;

while($r = $res->fetch_assoc()){
  $pid      = (int)$r['product_id'];
  $price_id= (int)$r['price_id'];
  $price   = (float)$r['price'];

  // === sold inițial (qty) ===
  $opening_qty = $conn->query("
    SELECT
      IFNULL(SUM(ii.qty),0)
      -
      IFNULL((
        SELECT SUM(oi.qty)
        FROM outgoing_items oi
        JOIN outgoing_documents od ON od.document_id = oi.document_id
        WHERE oi.product_id = $pid
          AND oi.price_id = $price_id
          AND od.doc_date < '$date_from'
      ),0)
    FROM incoming_items ii
    JOIN incoming_documents id ON id.document_id = ii.document_id
    WHERE ii.product_id = $pid
      AND ii.price_id = $price_id
      AND id.doc_date < '$date_from'
  ")->fetch_row()[0];

  // === venit (qty) ===
  $incoming_qty = $conn->query("
    SELECT IFNULL(SUM(ii.qty),0)
    FROM incoming_items ii
    JOIN incoming_documents id ON id.document_id = ii.document_id
    WHERE ii.product_id = $pid
      AND ii.price_id = $price_id
      AND id.doc_date >= '$date_from'
      AND id.doc_date < '$date_to'
  ")->fetch_row()[0];

  // === consum (qty) ===
  $outgoing_qty = $conn->query("
    SELECT IFNULL(SUM(oi.qty),0)
    FROM outgoing_items oi
    JOIN outgoing_documents od ON od.document_id = oi.document_id
    WHERE oi.product_id = $pid
      AND oi.price_id = $price_id
      AND od.doc_date >= '$date_from'
      AND od.doc_date < '$date_to'
  ")->fetch_row()[0];

  // если всё ноль — пропускаем
  if($opening_qty==0 && $incoming_qty==0 && $outgoing_qty==0){
    continue;
  }

  // === смена категории ===
  if($current_cat !== $r['category_ds_id']){
    if($current_cat !== null){
      ?>
      <tr class="total-row">
        <td colspan="3">TOTAL categorie</td>
        <td><?=number_format($cat_open,2)?></td><td></td>
        <td><?=number_format($cat_in,2)?></td><td></td>
        <td><?=number_format($cat_out,2)?></td><td></td>
        <td><?=number_format($cat_close,2)?></td>
      </tr>
      <?php
      $cat_open = $cat_in = $cat_out = $cat_close = 0.0;
    }
    $current_cat = $r['category_ds_id'];
  }

  // ===== CALCUL FĂRĂ ROTUNJIRE (full precision) =====
$open_sum  = $opening_qty  * $price;
$in_sum    = $incoming_qty * $price;
$out_sum   = $outgoing_qty * $price;

// formulă contabilă
$close_sum = $open_sum + $in_sum - $out_sum;
error_log("DEBUG SUMS: open=$open_sum in=$in_sum out=$out_sum close=$close_sum");

// VARIANTA 2 — cantitatea din sumă
$closing_qty_accounting = ($price > 0)
  ? ($close_sum / $price)
  : 0;

  ?>

  <tr>
    <td class="name"><?=h($r['name'])?></td>
    <td><?=number_format($price,2)?></td>

    <td><?=number_format($opening_qty,3)?></td>
    <td><?=number_format($open_sum,2)?></td>

    <td><?=number_format($incoming_qty,3)?></td>
    <td><?=number_format($in_sum,2)?></td>

    <td><?=number_format($outgoing_qty,3)?></td>
    <td><?=number_format($out_sum,2)?></td>

    <!-- VARIANTA 2: qty din sumă -->
    <td><?=number_format($closing_qty_accounting,3)?></td>
    <td><?=number_format($close_sum,2)?></td>
  </tr>

  <?php
  $cat_open  += $open_sum;
  $cat_in    += $in_sum;
  $cat_out   += $out_sum;
  $cat_close += $close_sum;
}

// TOTAL ultima categorie
if($current_cat !== null){
?>
<tr class="total-row">
  <td colspan="3">TOTAL categorie</td>
  <td><?=number_format($cat_open,2)?></td><td></td>
  <td><?=number_format($cat_in,2)?></td><td></td>
  <td><?=number_format($cat_out,2)?></td><td></td>
  <td><?=number_format($cat_close,2)?></td>
</tr>
<?php } ?>
<tr>
  <th colspan="10" style="text-align:left; border:none;">șef depozit (alimentar) B 3 I.I. FMP RM:caporal&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Ion DARII</th>
</tr>
<tr>
  <th colspan="10" style="text-align:left; border:none;">LȘ &nbsp;&nbsp;Coordonat: Loc'iitor interimar comandant batalion(logistică): locotenent major&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Mihail ROTARU</th>
</tr>
</tbody>
</table>
</div>
</div>

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
        table{width:100%;border-collapse:collapse;font-size:11px}
        th,td{border:1px solid #000;padding:4px;text-align:center}
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

<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>
