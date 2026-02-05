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


$month = $_GET['month'] ?? date('Y-m');
$date_from = $month . '-01';
$date_to   = date('Y-m-d', strtotime("$date_from +1 month"));

/*
supplier_id:
CAAN = 1

categories_ds:
1 = Produse alim
3 = Piine
4 = Cruasane
2 = Detergenti
*/

/* =========================
   ДОКУМЕНТЫ CAAN
========================= */
$sql_docs = "
SELECT
  d.doc_number,
  d.doc_date,
  SUM(CASE WHEN p.category_ds_id IN (1,3,4) THEN ii.qty * pp.price ELSE 0 END) AS suma_alimentare,
  SUM(CASE WHEN p.category_ds_id = 2 THEN ii.qty * pp.price ELSE 0 END) AS suma_detergenti
FROM incoming_documents d
JOIN incoming_items ii ON ii.document_id = d.document_id
JOIN products p ON p.product_id = ii.product_id
JOIN product_prices pp ON pp.price_id = ii.price_id
WHERE d.supplier_id = 1
  AND d.doc_date >= ?
  AND d.doc_date <  ?
GROUP BY d.document_id
ORDER BY d.doc_date, d.document_id
";

$stmt_docs = $conn->prepare($sql_docs);
$stmt_docs->bind_param('ss', $date_from, $date_to);
$stmt_docs->execute();
$res_docs = $stmt_docs->get_result();


/* =========================
   ВСЕ СОХРАНЁННЫЕ ОТЧЁТЫ
========================= */
$sql_reports = "
SELECT
  r.report_name,
  r.total_sum,
  r.created_at,
  s.name AS supplier_name
FROM bread_month_reports r
LEFT JOIN suppliers s ON s.supplier_id = r.supplier_id
WHERE r.report_month = ?
ORDER BY
  FIELD(s.name,'CAAN','Franzeluta','Brodetchii'),
  r.created_at DESC
";

$stmt_rep = $conn->prepare($sql_reports);
$stmt_rep->bind_param('s', $month);
$stmt_rep->execute();
$res_reports = $stmt_rep->get_result();

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

/* ===== PAGE HEADER ===== */
$page_title = 'CAAN – Documente și rapoarte';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

<div class="page-content">

<h1 class="mb-3">CAAN – Documente și rapoarte</h1>

<form method="get" class="filter-bar mb-3">
  <span>Luna:</span>
  <input type="month" name="month" value="<?=h($month)?>">
  <button class="btn">Generează</button>
  <button type="button" class="btn btn-secondary" onclick="printReport()">🖨️ Tipărește</button>
</form>

<div class="table-responsive">
  <table class="table table-hover mb-0">
<thead>
  <tr>
  <th colspan="3">Reestrul documentelor primare</th>
</tr>
  <tr>
  <th colspan="3"> privind circulaţia valorilor materiale în serviciul alimentar a mijloacelor bugetare în luna <?= $monthName ?> <?= $year ?></th>
</tr>
<tr>
  <th>Denumirea documentului</th>
  <th>Suma produse alimentare</th>
  <th>Suma detergenti</th>
</tr>
</thead>
<tbody>

<?php
$total_alim = 0;
$total_det  = 0;

/* ===== Документы CAAN ===== */
while($r = $res_docs->fetch_assoc()){
  $alim = (float)$r['suma_alimentare'];
  $det  = (float)$r['suma_detergenti'];

  $total_alim += $alim;
  $total_det  += $det;
  ?>
  <tr>
    <td class="left">
      CAAN Factura <?=h($r['doc_number'])?> din <?=fmtDate($r['doc_date'])?>
    </td>
    <td><?=number_format($alim,2)?></td>
    <td><?=number_format($det,2)?></td>
  </tr>
  <?php
}
?>

<tr class="separator-row">
  <td colspan="3"></td>
</tr>

<?php
while($r = $res_reports->fetch_assoc()){
  $label = $r['report_name']==='prima_jumatate_lunii'
    ? 'Prima jumătate'
    : 'A doua jumătate';

  $report_sum = (float)$r['total_sum'];
  $total_alim += $report_sum;
  ?>
  <tr>
    <td class="left">
      <?=h($r['supplier_name'] ?? '—')?> <?=h($label)?> din <?=fmtDate($r['created_at'])?>
    </td>
    <td><?=number_format($report_sum,2)?></td>
    <td></td>
  </tr>
  <?php
}
?>

</tbody>
<tfoot>
<tr>
  <th style="text-align:right">TOTAL GENERAL</th>
  <th><?=number_format($total_alim,2)?></th>
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
