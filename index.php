<!DOCTYPE html>
<html lang="ru">
<head>
  <?php include 'includ/head.php' ?>
  <style>
   
  </style>
</head>

<body>

<?php include 'includ/navbar.php' ?>

<?php
$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

/* локаль для названия месяца (если на сервере нет ro_RO — будет fallback) */
@setlocale(LC_TIME, 'ro_RO.UTF-8', 'ro_RO.utf8', 'ro_RO', 'Romanian_Romania.1250');

/* текущий месяц */
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');
$monthName  = strftime('%B %Y');
if(!$monthName || $monthName === '%B %Y'){
  $monthsRo = [1=>'Ianuarie',2=>'Februarie',3=>'Martie',4=>'Aprilie',5=>'Mai',6=>'Iunie',7=>'Iulie',8=>'August',9=>'Septembrie',10=>'Octombrie',11=>'Noiembrie',12=>'Decembrie'];
  $monthName = $monthsRo[(int)date('n')] . ' ' . date('Y');
}

/* категории */
$cats = $conn->query("
  SELECT category_ds_id, name
  FROM categories_ds
  WHERE active = 1
  ORDER BY category_ds_id
");

/* приход */
$venit = [];
$stmtV = $conn->prepare("
  SELECT
    p.category_ds_id,
    SUM(ii.qty * pp.price) AS total
  FROM incoming_items ii
  JOIN incoming_documents d ON d.document_id = ii.document_id
  JOIN products p ON p.product_id = ii.product_id
  JOIN product_prices pp ON pp.price_id = ii.price_id
  WHERE d.doc_date BETWEEN ? AND ?
  GROUP BY p.category_ds_id
");
$stmtV->bind_param("ss", $monthStart, $monthEnd);
$stmtV->execute();
$resV = $stmtV->get_result();
while($r = $resV->fetch_assoc()){
  $venit[(int)$r['category_ds_id']] = (float)$r['total'];
}

/* consum */
$consum = [];
$stmtC = $conn->prepare("
  SELECT
    p.category_ds_id,
    SUM(oi.qty * pp.price) AS total
  FROM outgoing_items oi
  JOIN outgoing_documents d ON d.document_id = oi.document_id
  JOIN products p ON p.product_id = oi.product_id
  JOIN product_prices pp ON pp.price_id = oi.price_id
  WHERE d.doc_date BETWEEN ? AND ?
  GROUP BY p.category_ds_id
");
$stmtC->bind_param("ss", $monthStart, $monthEnd);
$stmtC->execute();
$resC = $stmtC->get_result();
while($r = $resC->fetch_assoc()){
  $consum[(int)$r['category_ds_id']] = (float)$r['total'];
}

/* progress: Consum/Venit */
$totalVenit  = array_sum($venit);
$totalConsum = array_sum($consum);
$progress = ($totalVenit > 0) ? min(100, round(($totalConsum / $totalVenit) * 100)) : 0;
?>

<div class="page-content">

  <!-- ===== BLOCK 1: Current month + progress + cards ===== -->
  <div class="dashboard-block">

    <div class="dash-cards">
      <?php if($cats && $cats->num_rows): ?>
        <?php while($c = $cats->fetch_assoc()):
          $cid = (int)$c['category_ds_id'];
          $v = $venit[$cid]  ?? 0;
          $k = $consum[$cid] ?? 0;
        ?>
          <div class="dash-card">
            <div class="dash-card-title"><?= htmlspecialchars($c['name']) ?></div>

            <div class="dash-card-footer">
              <div class="dash-kpi">
                <span class="dash-kpi-label">Venit:</span>
                <span class="dash-kpi-value"><?= number_format($v, 0, '.', ' ') ?> lei</span>
              </div>

              <div class="dash-divider"></div>

              <div class="dash-kpi">
                <span class="dash-kpi-label">Consum:</span>
                <span class="dash-kpi-value"><?= number_format($k, 0, '.', ' ') ?> lei</span>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="alert alert-warning mb-0">Nu există categorii active.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== BLOCK 2: Table + Factura cards ===== -->
  <div class="invoice-layout">

    <!-- LEFT: TABLE -->
    <div class="invoice-table-card">
      <div class="invoice-table-wrap">
        <table class="menu-table">
          <thead>
            <tr>
              <th>Ziua și data</th>
              <th>Masa</th>
              <th>Felurile</th>
              <th>Denumirea bucatelor</th>
              <th>Masa generală, gr</th>
              <th>Masa carne/pește, gr</th>
            </tr>
          </thead>
          <tbody>

            <tr>
              <td rowspan="8" class="vertical-text">24.11.2025</td>
              <td rowspan="3" class="vertical-text">Dejun</td>
              <td rowspan="2">Felu II</td>
              <td>Terci de Hrișcă</td>
              <td>230</td>
              <td></td>
            </tr>

            <tr>
              <td>Sold de pui</td>
              <td></td>
              <td>58</td>
            </tr>

            <tr>
              <td>Felu III</td>
              <td>Pâine, ceai, unt, ou</td>
              <td>250</td>
              <td></td>
            </tr>

            <tr>
              <td rowspan="5" class="vertical-text">Prânz</td>
              <td>Gustare</td>
              <td>Salată de varză</td>
              <td>120</td>
              <td>12</td>
            </tr>

            <tr>
              <td>Felu I</td>
              <td>Borș roșu</td>
              <td>500</td>
              <td></td>
            </tr>

            <tr>
              <td rowspan="2">Felu II</td>
              <td>Paste făinoase</td>
              <td>230</td>
              <td></td>
            </tr>

            <tr>
              <td>Carne de porc</td>
              <td></td>
              <td>58</td>
            </tr>

            <tr>
              <td>Felu III</td>
              <td>Pâine, compot</td>
              <td>250</td>
              <td></td>
            </tr>

            <tr>
              <td rowspan="3" class="vertical-text">Luni</td>
              <td rowspan="3" class="vertical-text">Cină</td>
              <td rowspan="2">Felu II</td>
              <td>Ragù din legume</td>
              <td>230</td>
              <td></td>
            </tr>

            <tr>
              <td>Carne de porc</td>
              <td></td>
              <td>58</td>
            </tr>

            <tr>
              <td>Felu III</td>
              <td>Ceai, biscuiți</td>
              <td>250</td>
              <td></td>
            </tr>

            <tr>
              <th colspan="5">Total calorii</th>
              <th>1750</th>
            </tr>

          </tbody>
        </table>
      </div>
    </div>

    <!-- RIGHT: FACTURA CARD -->
    <div class="invoice-summary-card">

      <!-- Factura de azi -->
      <div class="invoice-summary-header">Factura de azi</div>

      <div class="invoice-summary-body">
        <div class="invoice-summary-layout">

          <!-- LEFT -->
          <div class="invoice-summary-left">
            <div class="invoice-summary-title">Numaru de oameni</div>

            <div class="invoice-grid">
              <div class="invoice-item">
                <div class="invoice-label">Dejun</div>
                <div class="invoice-value">—</div>
              </div>
              <div class="invoice-item">
                <div class="invoice-label">Prânz</div>
                <div class="invoice-value">—</div>
              </div>
              <div class="invoice-item">
                <div class="invoice-label">Cină</div>
                <div class="invoice-value">—</div>
              </div>

              <div class="invoice-item">
                <div class="invoice-label">Produse Suma</div>
                <div class="invoice-value">—</div>
              </div>
              <div class="invoice-item">
                <div class="invoice-label">Detergenți Suma</div>
                <div class="invoice-value">—</div>
              </div>
              <div class="invoice-item">
                <div class="invoice-label">Total Suma</div>
                <div class="invoice-value">—</div>
              </div>
            </div>
          </div>

          <!-- RIGHT -->
          <div class="invoice-summary-right">
            <div class="invoice-preview">
              <div class="invoice-preview-box">
                <img src="asset/img/factura-preview.jpg" alt="Factura">
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Factura pe mâine -->
      <div class="invoice-form-card">
        <div class="invoice-form-header">Factura pe mâine</div>

        <form id="peopleForm">
          <div class="invoice-form-body">

            <div class="invoice-form-row">
  <div class="form-group">
    <label class="form-label">Data</label>
    <input type="date" class="form-control" name="day_date" value="<?= date('Y-m-d') ?>" required>
  </div>

  <div class="form-group">
    <label class="form-label">Dejun</label>
    <input type="number" min="0" class="form-control" name="morning_count">
  </div>

  <div class="form-group">
    <label class="form-label">Prânz</label>
    <input type="number" min="0" class="form-control" name="lunch_count">
  </div>

  <div class="form-group">
    <label class="form-label">Cină</label>
    <input type="number" min="0" class="form-control" name="evening_count">
  </div>
</div>


            <div class="invoice-form-actions mt-3">
              <button class="btn btn-primary px-4" type="submit">Salvează</button>
            </div>

            <div id="msg" class="mt-3"></div>

          </div>
        </form>
      </div>

    </div>
  </div>

</div>

<?php include 'includ/scrypt.php' ?>

<script>
/* people_daily save */
document.getElementById('peopleForm').addEventListener('submit', async function(e){
  e.preventDefault();

  const msg = document.getElementById('msg');
  msg.innerHTML = '';

  const fd = new FormData(this);

  try{
    const res = await fetch('save_people_daily.php',{ method:'POST', body:fd });
    const text = await res.text();
    msg.innerHTML = `<div class="alert alert-${res.ok ? 'success':'danger'}">${text}</div>`;
  }catch(err){
    msg.innerHTML = `<div class="alert alert-danger">Eroare: ${err.message}</div>`;
  }
});
</script>

</body>
</html>
