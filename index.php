<?php
$page_title = 'Dashboard';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';

/* ===== DB ===== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

/* ===== DATE ===== */
@setlocale(LC_TIME,'ro_RO.UTF-8','ro_RO.utf8','ro_RO','ro_RO','Romanian_Romania.1250');
$today = date('Y-m-d');

/* ===== MONTH ===== */
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

/* ===== CATEGORIES ===== */
$cats = $conn->query("
  SELECT category_ds_id, name
  FROM categories_ds
  WHERE active=1
  ORDER BY category_ds_id
");

/* ===== VENIT ===== */
$venit=[];
$stmtV=$conn->prepare("
  SELECT p.category_ds_id,SUM(ii.qty*pp.price) total
  FROM incoming_items ii
  JOIN incoming_documents d ON d.document_id=ii.document_id
  JOIN products p ON p.product_id=ii.product_id
  JOIN product_prices pp ON pp.price_id=ii.price_id
  WHERE d.doc_date BETWEEN ? AND ?
  GROUP BY p.category_ds_id
");
$stmtV->bind_param("ss",$monthStart,$monthEnd);
$stmtV->execute();
$resV=$stmtV->get_result();
while($r=$resV->fetch_assoc()){
  $venit[(int)$r['category_ds_id']] = (float)$r['total'];
}

/* ===== CONSUM ===== */
$consum=[];
$stmtC=$conn->prepare("
  SELECT p.category_ds_id,SUM(oi.qty*pp.price) total
  FROM outgoing_items oi
  JOIN outgoing_documents d ON d.document_id=oi.document_id
  JOIN products p ON p.product_id=oi.product_id
  JOIN product_prices pp ON pp.price_id=oi.price_id
  WHERE d.doc_date BETWEEN ? AND ?
  GROUP BY p.category_ds_id
");
$stmtC->bind_param("ss",$monthStart,$monthEnd);
$stmtC->execute();
$resC=$stmtC->get_result();
while($r=$resC->fetch_assoc()){
  $consum[(int)$r['category_ds_id']] = (float)$r['total'];
}

/* ===== PEOPLE ===== */
$people=['morning'=>0,'lunch'=>0,'evening'=>0];
$stmtP=$conn->prepare("
  SELECT morning_count,lunch_count,evening_count
  FROM people_daily WHERE day_date=? LIMIT 1
");
$stmtP->bind_param("s",$today);
$stmtP->execute();
if($p=$stmtP->get_result()->fetch_assoc()){
  $people['morning']=(int)$p['morning_count'];
  $people['lunch']=(int)$p['lunch_count'];
  $people['evening']=(int)$p['evening_count'];
}

/* ===== SUME ===== */
$sums=['produse'=>0,'detergenti'=>0,'total'=>0];
$stmtS=$conn->prepare("
  SELECT
    CASE WHEN p.category_ds_id=2 THEN 'detergenti' ELSE 'produse' END tip,
    SUM(oi.qty*pp.price) suma
  FROM outgoing_items oi
  JOIN outgoing_documents d ON d.document_id=oi.document_id
  JOIN products p ON p.product_id=oi.product_id
  JOIN product_prices pp ON pp.price_id=oi.price_id
  WHERE d.doc_date=?
  GROUP BY tip
");
$stmtS->bind_param("s",$today);
$stmtS->execute();
$resS=$stmtS->get_result();
while($r=$resS->fetch_assoc()){
  $sums[$r['tip']] = (float)$r['suma'];
}
$sums['total']=$sums['produse']+$sums['detergenti'];

/* ===== MENU ===== */
$stmtMenu=$conn->prepare("
  SELECT menu_id,menu_date FROM menu_days
  WHERE menu_date=? LIMIT 1
");
$stmtMenu->bind_param("s",$today);
$stmtMenu->execute();
$menu=$stmtMenu->get_result()->fetch_assoc();
?>

<div class="page-content">

  <!-- ===== DASHBOARD CARDS ===== -->
  <div class="dashboard-block">
    <div class="dash-cards">
      <?php while($c=$cats->fetch_assoc()):
        $cid=(int)$c['category_ds_id']; ?>
        <div class="dash-card">
          <div class="dash-card-title"><?= htmlspecialchars($c['name']) ?></div>
          <div class="dash-card-footer">
            <div>Venit <strong><?= number_format($venit[$cid]??0,0,' ',' ') ?></strong> lei</div>
            <div>Consum <strong><?= number_format($consum[$cid]??0,0,' ',' ') ?></strong> lei</div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>

  <h2 class="section-title">📊 Situația zilnică</h2>

  <?php if(!$menu): ?>
    <div class="alert alert-warning">Nu există meniu pentru această zi.</div>
  <?php else:

  $structure=[
    'Dejun'=>[
      ['idx'=>0,'fel'=>'Felu II'],
      ['idx'=>1,'fel'=>'Felu II'],
      ['idx'=>2,'fel'=>'Felu III'],
    ],
    'Prânz'=>[
      ['idx'=>3,'fel'=>'Gustare'],
      ['idx'=>4,'fel'=>'Felu I'],
      ['idx'=>5,'fel'=>'Felu II'],
      ['idx'=>6,'fel'=>'Felu II'],
      ['idx'=>7,'fel'=>'Felu III'],
    ],
    'Cină'=>[
      ['idx'=>8,'fel'=>'Felu II'],
      ['idx'=>9,'fel'=>'Felu II'],
      ['idx'=>10,'fel'=>'Felu III'],
    ],
  ];

  $stmtMeals=$conn->prepare("SELECT col_index,meal_name FROM menu_meals WHERE menu_id=?");
  $stmtMeals->bind_param("i",$menu['menu_id']);
  $stmtMeals->execute();
  $mealNames=[];
  $resMeals=$stmtMeals->get_result();
  while($r=$resMeals->fetch_assoc()){
    $mealNames[(int)$r['col_index']] = $r['meal_name'];
  }
  ?>

  <div class="invoice-layout">

    <!-- ===== TABLE ===== -->
    <div class="invoice-table-card">
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
          <?php
          $totalRows=array_sum(array_map('count',$structure));
          $datePrinted=false;

          foreach($structure as $masa=>$rows):
            $masaPrinted=false;
            foreach($rows as $row):
          ?>
          <tr>
            <?php if(!$datePrinted): ?>
              <td rowspan="<?= $totalRows ?>"><?= date('d.m.Y',strtotime($menu['menu_date'])) ?></td>
            <?php $datePrinted=true; endif; ?>

            <?php if(!$masaPrinted): ?>
              <td rowspan="<?= count($rows) ?>" class="vertical-text"><?= $masa ?></td>
            <?php $masaPrinted=true; endif; ?>

            <td><?= $row['fel'] ?></td>
            <td><?= htmlspecialchars($mealNames[$row['idx']] ?? '') ?></td>
            <td></td>
            <td></td>
          </tr>
          <?php endforeach; endforeach; ?>

          <tr>
            <th colspan="5">Total calorii</th>
            <th>1750</th>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ===== FACTURA + PIINE ===== -->
    <div class="invoice-summary-card">

      <div class="invoice-summary-header">Factura de azi</div>

      <div class="invoice-summary-layout">

        <div class="invoice-summary-left">
          <div class="invoice-grid">
            <div class="invoice-item"><div class="invoice-label">Dejun</div><div class="invoice-value"><?= $people['morning'] ?: '—' ?></div></div>
            <div class="invoice-item"><div class="invoice-label">Prânz</div><div class="invoice-value"><?= $people['lunch'] ?: '—' ?></div></div>
            <div class="invoice-item"><div class="invoice-label">Cină</div><div class="invoice-value"><?= $people['evening'] ?: '—' ?></div></div>
            <div class="invoice-item"><div class="invoice-label">Produse</div><div class="invoice-value"><?= number_format($sums['produse'],2,'.',' ') ?> lei</div></div>
            <div class="invoice-item"><div class="invoice-label">Detergenți</div><div class="invoice-value"><?= number_format($sums['detergenti'],2,'.',' ') ?> lei</div></div>
            <div class="invoice-item"><div class="invoice-label">Total</div><div class="invoice-value"><?= number_format($sums['total'],2,'.',' ') ?> lei</div></div>
          </div>
        </div>

        <div class="invoice-summary-right">
          <div class="invoice-preview">
            <div class="invoice-preview-box">
              <img src="/asset/img/factura-demo.jpg" alt="Factura">
            </div>
          </div>
        </div>

      </div>

      <hr class="divider">
    </div>

  </div>

  <?php endif; ?>
</div>

<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>
