<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($d){
  if(!$d) return '';
  $t = strtotime($d);
  return $t ? date('d.m.Y', $t) : '';
}


/*
categories_ds:
2 = Detergenti
restul = produse
*/

// ===== LUNA =====
$month = $_GET['month'] ?? date('Y-m');
$date_from = $month . '-01';
$date_to   = date('Y-m-d', strtotime("$date_from +1 month"));

// ===== OUTGOING DOCUMENTS =====
$sql = "
SELECT
  d.document_id,
  d.doc_number,
  d.doc_date,
  COALESCE(c.name, '—') AS destination,

  SUM(
    CASE WHEN p.category_ds_id <> 2
    THEN oi.qty * COALESCE(pp.price,0) ELSE 0 END
  ) AS suma_produse,

  SUM(
    CASE WHEN p.category_ds_id = 2
    THEN oi.qty * COALESCE(pp.price,0) ELSE 0 END
  ) AS suma_detergenti

FROM outgoing_documents d
LEFT JOIN consumers c       ON c.consumer_id = d.consumer_id
JOIN outgoing_items oi      ON oi.document_id = d.document_id
JOIN products p             ON p.product_id = oi.product_id
LEFT JOIN product_prices pp ON pp.price_id = oi.price_id

WHERE d.doc_date >= ?
  AND d.doc_date <  ?
  AND d.doc_type = 'Consum'

GROUP BY d.document_id
ORDER BY d.doc_date, d.document_id;
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



// ===== PAGE TITLE =====
$page_title = 'Ieșiri produse';

// ===== HEADER =====
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

<div class="page-content">

<h1 class="mb-3">Ieșiri produse</h1>

<form method="get" class="filter-bar mb-3">
  <span>Luna:</span>
  <input type="month" name="month" value="<?=h($month)?>">
  <button class="btn">Afișează</button>
  <button type="button" class="btn btn-secondary" onclick="printReport()">🖨️ Tipărește</button>
</form>

<div class="table-responsive">
  <table class="table table-hover mb-0">
<thead>
  <tr>
  <th colspan="3">Reestrul documentelor primare</th>
</tr>
  <tr>
  <th colspan="3">privind circulaţia valorilor materiale în serviciul alimentar a mijloacelor bugetare în luna <?= $monthName ?> <?= $year ?></th>
</tr>
<tr>
  <th>Denumirea documentului</th>
  <th>Suma produse</th>
  <th>Suma detergenti</th>
</tr>
</thead>
<tbody>

<?php
$total_produse = 0;
$total_det     = 0;

while($r = $res->fetch_assoc()){
  $prod = (float)$r['suma_produse'];
  $det  = (float)$r['suma_detergenti'];

  $total_produse += $prod;
  $total_det     += $det;
  ?>
  <tr>
    <td class="left">
      Factura <?=h($r['doc_number'])?>
      → <?=h($r['destination'])?>
    </td>
    <td><?=number_format($prod,2)?></td>
    <td><?=number_format($det,2)?></td>
  </tr>
  <?php
}

if($res->num_rows === 0){
  echo "<tr>
  <td colspan='3' class='text-center text-muted py-4'>
    Nu există ieșiri în această lună
  </td>
</tr>";
}
?>

</tbody>
<tfoot>
<tr>
  <th style="text-align:right">TOTAL</th>
  <th><?=number_format($total_produse,2)?></th>
  <th><?=number_format($total_det,2)?></th>
</tr>
<tr>
  <th style="text-align:left; border:none;">șef depozit alimentar</th>
</tr>
<tr>
  <th style="text-align:left; border:none;">caporal&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Ion DARII</th>
</tr>
<tr>
  <th style="text-align:left; border:none;">LȘ</th>
</tr>
</tfoot>
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