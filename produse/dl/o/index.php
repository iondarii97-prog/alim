<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

$month = $_GET['month'] ?? date('Y-m');
$date_from = $month . '-01';
$date_to   = date('Y-m-d', strtotime("$date_from +1 month"));

function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function fmtDate($d){
  if(!$d) return '';
  $t = strtotime($d);
  return $t ? date('d.m.Y', $t) : '';
}


// ===== статичные значения =====
$observatori_static = 6;
$post_static        = 8;

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
$page_title = 'Raport persoane pe lună';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

<div class="page-content">

<h1 class="mb-3">Raport persoane pe lună</h1>

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
  <th colspan="6">Luna <?= $monthName ?> <?= $year ?></th>
</tr>
<tr>
  <th>Data</th>
  <th>Dimineață</th>
  <th>Prânz</th>
  <th>Seară</th>
  <th>Observatori</th>
  <th>Post / gardă</th>
</tr>
</thead>
<tbody>

<?php
$stmt = $conn->prepare("
  SELECT day_date, morning_count, lunch_count, evening_count
  FROM people_daily
  WHERE day_date >= ?
    AND day_date < ?
  ORDER BY day_date
");
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$res = $stmt->get_result();


$total_morning     = 0;
$total_lunch       = 0;
$total_evening     = 0;
$total_observatori = 0;
$total_post        = 0;

while($r = $res->fetch_assoc()){

  $total_morning += (int)$r['morning_count'];
  $total_lunch   += (int)$r['lunch_count'];
  $total_evening += (int)$r['evening_count'];

  $total_observatori += $observatori_static;
  $total_post        += $post_static;
  ?>
  <tr>
    <td><?=fmtDate($r['day_date'])?></td>
    <td><?=h($r['morning_count'])?></td>
    <td><?=h($r['lunch_count'])?></td>
    <td><?=h($r['evening_count'])?></td>
    <td><strong><?=$observatori_static?></strong></td>
    <td><strong><?=$post_static?></strong></td>
  </tr>
  <?php
}
?>

<tr class="total-row">
  <td><strong>TOTAL</strong></td>
  <td><?=number_format($total_morning, 0, '', '')?></td>
  <td><?=number_format($total_lunch, 0, '', '')?></td>
  <td><?=number_format($total_evening, 0, '', '')?></td>
  <td><?=number_format($total_observatori, 0, '', '')?></td>
  <td><?=number_format($total_post, 0, '', '')?></td>
</tr>

<tr>
  <th colspan="2" style="text-align:left; border:none;">șef depozit alimentar</th>
</tr>
<tr>
  <th colspan="2" style="text-align:left; border:none;">caporal&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Ion DARII</th>
</tr>
<tr>
  <th colspan="2" style="text-align:left; border:none;">LȘ</th>
</tr>
</tbody>
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