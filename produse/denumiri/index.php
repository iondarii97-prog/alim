<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($dbDate){
  if(!$dbDate) return '';
  $t = strtotime($dbDate);
  return $t ? date('d.m.Y', $t) : h($dbDate);
}

$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

$product_id = (int)($_GET['id'] ?? 0);
if ($product_id <= 0) { http_response_code(400); exit("Нет id товара."); }

// ===== SAVE SETTINGS (TAB: Setări produs) =====
$flash = null;
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_product_settings'){
  $warehouse_id    = (int)($_POST['warehouse_id'] ?? 0);
  $category_id     = (int)($_POST['category_id'] ?? 0);
  $category_ds_id  = (int)($_POST['category_ds_id'] ?? 0);
  $coeff_id        = (int)($_POST['coeff_id'] ?? 0);
  $conditions_text = (string)($_POST['conditions_text'] ?? '');

  $coef_bucate = round((float)($_POST['coef_bucate'] ?? 0), 4);
  $coef_carne  = round((float)($_POST['coef_carne'] ?? 0), 4);

  // нормализация "0" -> NULL там, где у тебя допускается NULL
  $warehouse_id   = $warehouse_id > 0 ? $warehouse_id : null;
  $category_id    = $category_id > 0 ? $category_id : null;
  $category_ds_id = $category_ds_id > 0 ? $category_ds_id : null;
  $coeff_id       = $coeff_id > 0 ? $coeff_id : null;
  $sort_report = (int)($_POST['sort_report'] ?? 0);
  $sort_site   = (int)($_POST['sort_site'] ?? 0);

  $conn->begin_transaction();
  try{
    // 1) products: warehouse_id, category_id, category_ds_id
    // bind_param не любит NULL с типом i -> делаем IF(?=0,NULL,?)
    $stmt = $conn->prepare("
      UPDATE products
      SET
  warehouse_id   = IF(?=0, NULL, ?),
  category_id    = IF(?=0, NULL, ?),
  category_ds_id = IF(?=0, NULL, ?),
  sort_report    = ?,
  sort_site      = ?
      WHERE product_id = ?
      LIMIT 1
    ");
    $w  = $warehouse_id ?? 0;
    $c  = $category_id ?? 0;
    $cd = $category_ds_id ?? 0;
    $stmt->bind_param(
  "iiiiiiiii",
  $w,$w,
  $c,$c,
  $cd,$cd,
  $sort_report,
  $sort_site,
  $product_id
);

    $stmt->execute();

    // 2) product_calorie_coeff: (product_id PRIMARY KEY)
    if($coeff_id === null){
      $stmt = $conn->prepare("DELETE FROM product_calorie_coeff WHERE product_id = ? LIMIT 1");
      $stmt->bind_param("i", $product_id);
      $stmt->execute();
    }else{
      $stmt = $conn->prepare("
        INSERT INTO product_calorie_coeff (product_id, coeff_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE coeff_id = VALUES(coeff_id)
      ");
      $stmt->bind_param("ii", $product_id, $coeff_id);
      $stmt->execute();
    }

    // 3) product_storage_conditions
    if(trim($conditions_text) === ''){
      $stmt = $conn->prepare("DELETE FROM product_storage_conditions WHERE product_id=? LIMIT 1");
      $stmt->bind_param("i", $product_id);
      $stmt->execute();
    }else{
      $stmt = $conn->prepare("
        INSERT INTO product_storage_conditions (product_id, conditions_text)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE conditions_text = VALUES(conditions_text)
      ");
      $stmt->bind_param("is", $product_id, $conditions_text);
      $stmt->execute();
    }

    /* ===== MASA_BUCATE ===== */
    if ($coef_bucate > 0) {
      $stmt = $conn->prepare("
        INSERT INTO masa_bucate (product_id, coef)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE coef = VALUES(coef)
      ");
      $stmt->bind_param("id", $product_id, $coef_bucate);
      $stmt->execute();
    } else {
      $stmt = $conn->prepare("DELETE FROM masa_bucate WHERE product_id = ?");
      $stmt->bind_param("i", $product_id);
      $stmt->execute();
    }

    /* ===== MASA_CARNE ===== */
    if ($coef_carne > 0) {
      $stmt = $conn->prepare("
        INSERT INTO masa_carne (product_id, coef)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE coef = VALUES(coef)
      ");
      $stmt->bind_param("id", $product_id, $coef_carne);
      $stmt->execute();
    } else {
      $stmt = $conn->prepare("DELETE FROM masa_carne WHERE product_id = ?");
      $stmt->bind_param("i", $product_id);
      $stmt->execute();
    }
    /* ===== IMAGE UPLOAD ===== */
if (!empty($_FILES['product_image']['name'])) {

  $dir = $_SERVER['DOCUMENT_ROOT']."/asset/uploads/products/";
  if (!is_dir($dir)) mkdir($dir, 0777, true);

  $ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
  $allowed = ['jpg','jpeg','png','webp'];

  if (!in_array($ext, $allowed)) {
      throw new Exception("Tip fișier invalid (jpg, png, webp)");
  }

  $newName = "prod_".$product_id."_".time().".".$ext;
  $target = $dir.$newName;

  if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $target)) {
      throw new Exception("Nu s-a putut salva imaginea.");
  }

  $dbPath = "/asset/uploads/products/".$newName;

  $stmt = $conn->prepare("UPDATE products SET image_path=? WHERE product_id=?");
  $stmt->bind_param("si", $dbPath, $product_id);
  $stmt->execute();
}


    $conn->commit();

    header("Location: ".$_SERVER['PHP_SELF']."?id=".$product_id."&saved=1");
    exit;

  }catch(Throwable $e){
    $conn->rollback();
    $flash = "Eroare la salvare: ".$e->getMessage();
  }
}

// ===== READ PRODUCT (категории + склад + коэффициент + условия) =====
$stmt = $conn->prepare("
  SELECT
  p.product_id,
  p.name AS product_name,
  p.unit,
  p.image_path,
  p.warehouse_id,
  p.category_id,
  p.category_ds_id,
  p.sort_report,
  p.sort_site,
  w.name AS warehouse_name,
  c.name AS category_name,
  cds.name AS category_ds_name,
    COALESCE(cc.coeff_id, 0) AS coeff_id,
    COALESCE(cc.title, '') AS coeff_title,
    COALESCE(cc.kcal_per_gram, 0) AS kcal_per_gram,
    COALESCE(mb.coef, 0) AS coef_bucate,
    COALESCE(mc.coef, 0) AS coef_carne,
    COALESCE(psc.conditions_text, '') AS conditions_text
  FROM products p
  LEFT JOIN warehouses w ON w.warehouse_id = p.warehouse_id
  LEFT JOIN categories c ON c.category_id = p.category_id
  LEFT JOIN categories_ds cds ON cds.category_ds_id = p.category_ds_id
  LEFT JOIN product_calorie_coeff pcc ON pcc.product_id = p.product_id
  LEFT JOIN calorie_coefficients cc ON cc.coeff_id = pcc.coeff_id
  LEFT JOIN product_storage_conditions psc ON psc.product_id = p.product_id
  LEFT JOIN masa_bucate mb ON mb.product_id = p.product_id
  LEFT JOIN masa_carne mc ON mc.product_id = p.product_id
  WHERE p.product_id = ?
  LIMIT 1
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
if(!$product){ http_response_code(404); exit("Товар не найден."); }

// ===== LISTS FOR SELECTS =====
$warehouses = $conn->query("SELECT warehouse_id, name FROM warehouses WHERE active=1 ORDER BY warehouse_id");
$cats       = $conn->query("SELECT category_id, name FROM categories ORDER BY category_id");
$catsDs     = $conn->query("SELECT category_ds_id, name FROM categories_ds WHERE active=1 ORDER BY category_ds_id");
$coeffs     = $conn->query("SELECT coeff_id, title, kcal_per_gram FROM calorie_coefficients ORDER BY coeff_id");

// ===== KPI / tables =====
$month = (int)($_GET['m'] ?? date('n'));
$year  = (int)($_GET['y'] ?? date('Y'));
if ($month < 1 || $month > 12) $month = (int)date('n');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

$monthNames = [1=>"Ianuarie",2=>"Februarie",3=>"Martie",4=>"Aprilie",5=>"Mai",6=>"Iunie",7=>"Iulie",8=>"August",9=>"Septembrie",10=>"Octombrie",11=>"Noiembrie",12=>"Decembrie"];
$monthTitle = $monthNames[$month] ?? "Luna";

$sold = $conn->prepare("
  SELECT sbp.qty, pp.price, (sbp.qty * pp.price) AS sum
  FROM stock_by_price sbp
  JOIN product_prices pp ON pp.price_id = sbp.price_id
  WHERE sbp.product_id = ?
  ORDER BY pp.price ASC
");
$sold->bind_param("i", $product_id);
$sold->execute();
$soldRows = $sold->get_result();

$stmtFifo = $conn->prepare("SELECT COALESCE(SUM(remaining_qty),0) AS fifo_qty FROM incoming_items WHERE product_id = ?");
$stmtFifo->bind_param("i", $product_id);
$stmtFifo->execute();
$fifoQty = (float)($stmtFifo->get_result()->fetch_assoc()['fifo_qty'] ?? 0);

$venit = $conn->prepare("
  SELECT d.doc_date, d.doc_type, d.doc_number, s.name AS supplier_name, pp.price, ii.qty, (ii.qty*pp.price) AS sum
  FROM incoming_items ii
  JOIN incoming_documents d ON d.document_id = ii.document_id
  JOIN product_prices pp ON pp.price_id = ii.price_id
  LEFT JOIN suppliers s ON s.supplier_id = d.supplier_id
  WHERE ii.product_id = ? AND MONTH(d.doc_date)=? AND YEAR(d.doc_date)=?
  ORDER BY d.doc_date DESC, d.document_id DESC
");
$venit->bind_param("iii", $product_id, $month, $year);
$venit->execute();
$venitRows = $venit->get_result();

$consum = $conn->prepare("
  SELECT d.doc_date, d.doc_type, d.doc_number, pp.price, oi.qty, (oi.qty*pp.price) AS sum
  FROM outgoing_items oi
  JOIN outgoing_documents d ON d.document_id = oi.document_id
  JOIN product_prices pp ON pp.price_id = oi.price_id
  WHERE oi.product_id = ? AND MONTH(d.doc_date)=? AND YEAR(d.doc_date)=?
  ORDER BY d.doc_date DESC, d.document_id DESC
");
$consum->bind_param("iii", $product_id, $month, $year);
$consum->execute();
$consumRows = $consum->get_result();

$stmtKpiV = $conn->prepare("
  SELECT COALESCE(SUM(ii.qty),0) AS qty, COALESCE(SUM(ii.qty * pp.price),0) AS sum
  FROM incoming_items ii
  JOIN incoming_documents d ON d.document_id = ii.document_id
  JOIN product_prices pp ON pp.price_id = ii.price_id
  WHERE ii.product_id = ? AND MONTH(d.doc_date)=? AND YEAR(d.doc_date)=?
");
$stmtKpiV->bind_param("iii", $product_id, $month, $year);
$stmtKpiV->execute();
$kpiV = $stmtKpiV->get_result()->fetch_assoc();

$stmtKpiC = $conn->prepare("
  SELECT COALESCE(SUM(oi.qty),0) AS qty, COALESCE(SUM(oi.qty * pp.price),0) AS sum
  FROM outgoing_items oi
  JOIN outgoing_documents d ON d.document_id = oi.document_id
  JOIN product_prices pp ON pp.price_id = oi.price_id
  WHERE oi.product_id = ? AND MONTH(d.doc_date)=? AND YEAR(d.doc_date)=?
");
$stmtKpiC->bind_param("iii", $product_id, $month, $year);
$stmtKpiC->execute();
$kpiC = $stmtKpiC->get_result()->fetch_assoc();

$move = $conn->prepare("
  (SELECT d.doc_date,'VENIT' move_type,d.doc_type,d.doc_number,s.name partner_name,pp.price,ii.qty,(ii.qty*pp.price) sum
   FROM incoming_items ii
   JOIN incoming_documents d ON d.document_id=ii.document_id
   JOIN product_prices pp ON pp.price_id=ii.price_id
   LEFT JOIN suppliers s ON s.supplier_id=d.supplier_id
   WHERE ii.product_id=?)
  UNION ALL
  (SELECT d.doc_date,'CONSUM' move_type,d.doc_type,d.doc_number,'' partner_name,pp.price,oi.qty,(oi.qty*pp.price) sum
   FROM outgoing_items oi
   JOIN outgoing_documents d ON d.document_id=oi.document_id
   JOIN product_prices pp ON pp.price_id=oi.price_id
   WHERE oi.product_id=?)
  ORDER BY doc_date DESC
  LIMIT 500
");
$productUnit = $product['unit'] ?: '';
$move->bind_param("ii", $product_id, $product_id);
$move->execute();
$moveRows = $move->get_result();

// ===== PAGE HEADER =====
$page_title = 'Dashboard';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

<div class="page-content">
  <div class="product-layout">

    <!-- LEFT -->
    <div>
      <div class="product-card">
        <div class="product-image">
          <img src="<?= h($product['image_path'] ?: '/asset/img/factura-preview.jpg') ?>" alt="">
        </div>

        <div class="product-sub"><?= h($product['category_name'] ?? 'Fără categorie') ?></div>
        <div class="product-title"><?= h($product['product_name']) ?></div>

        <!-- 1 РЯД -->
        <div class="warehouse-grid">
          <div class="product-card product-card--mini">
            <div class="warehouse-title">Depozit</div>
            <div class="warehouse-name">
              <?= h($product['warehouse_name'] ?: 'Nu este setat') ?>
            </div>
          </div>

          <div class="product-card product-card--mini">
            <div class="warehouse-title">Unitate</div>
            <div class="warehouse-name">
              <?= h($productUnit) ?>
            </div>
          </div>
        </div>

        <!-- 2 РЯД -->
        <div class="warehouse-grid">
          <div class="product-card product-card--mini">
            <div class="warehouse-title">Coef. bucate</div>
            <div class="warehouse-name">
              <?= rtrim(rtrim(number_format((float)$product['coef_bucate'],4,'.',''),'0'),'.') ?>
            </div>
          </div>

          <div class="product-card product-card--mini">
            <div class="warehouse-title">Coef. carne</div>
            <div class="warehouse-name">
              <?= rtrim(rtrim(number_format((float)$product['coef_carne'],4,'.',''),'0'),'.') ?>
            </div>
          </div>
        </div>

        <!-- 3 РЯД -->
        <div class="warehouse-grid">
          <div class="product-card product-card--mini">
            <div class="warehouse-title">Coeficient calorii</div>
            <div class="warehouse-name">
              <?php if((int)$product['coeff_id'] > 0): ?>
                <?= h($product['coeff_title']) ?>
                (<?= rtrim(rtrim(number_format((float)$product['kcal_per_gram'],6,'.',''),'0'),'.') ?> kcal/g)
              <?php else: ?>
                —
              <?php endif; ?>
            </div>
          </div>

          <div class="product-card product-card--mini">
            <div class="warehouse-title">DS categorie</div>
            <div class="warehouse-name">
              <?= h($product['category_ds_name'] ?: '—') ?>
            </div>
          </div>
        </div>

        <!-- 4 РЯД -->
        <div class="warehouse-grid">
          <div class="product-card product-card--mini">
            <div class="warehouse-title">Categorie</div>
            <div class="warehouse-name">
              <?= h($product['category_name'] ?: '—') ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT -->
    <div class="product-main">

      <?php if(isset($_GET['saved'])): ?>
        <div class="alert alert-success py-2">Salvat cu succes.</div>
      <?php elseif($flash): ?>
        <div class="alert alert-danger py-2"><?= h($flash) ?></div>
      <?php endif; ?>

      <!-- ОБЩИЙ CARD НА ПРАВОЙ ЧАСТИ (как на скрине) -->
      <div class="card-soft p-4">

        <ul class="nav nav-tabs mb-4" role="tablist">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sold" type="button">Sold</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#istorie" type="button">Istori Sold</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#conditii" type="button">Condiții de păstrare</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#setari" type="button">Setări produs</button>
          </li>
        </ul>

        <div class="tab-content">

          <!-- SOLD -->
          <div class="tab-pane fade show active" id="sold">

            <div class="kpi-row">
              <div class="kpi">
                <div class="kpi-title">Sold FIFO (partii)</div>
                <div class="kpi-value"><?= number_format($fifoQty, 3, '.', ' ') ?> <?= h($productUnit) ?></div>
              </div>

              <div class="kpi">
                <div class="kpi-title">Luna selectata</div>
                <div class="kpi-value"><?= h($monthTitle) ?> <?= (int)$year ?></div>
              </div>

              <div class="kpi">
                <div class="kpi-title">Venit luna</div>
                <div class="kpi-value"><?= number_format((float)$kpiV['qty'], 3, '.', ' ') ?> <?= h($productUnit) ?></div>
                <div class="kpi-title" style="margin-top:6px;">Suma</div>
                <div class="kpi-value"><?= number_format((float)$kpiV['sum'], 2, '.', ' ') ?> lei</div>
              </div>

              <div class="kpi">
                <div class="kpi-title">Consum luna</div>
                <div class="kpi-value"><?= number_format((float)$kpiC['qty'], 3, '.', ' ') ?> <?= h($productUnit) ?></div>
                <div class="kpi-title" style="margin-top:6px;">Suma</div>
                <div class="kpi-value"><?= number_format((float)$kpiC['sum'], 2, '.', ' ') ?> lei</div>
              </div>
            </div>

            <div class="card-soft p-3 mb-3">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th style="width:60px;">#</th>
                    <th>Cantitatea</th>
                    <th>Pretu</th>
                    <th>Suma</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $i=0; $totalQty=0; $totalSum=0;
                  while($r = $soldRows->fetch_assoc()):
                    $i++;
                    $totalQty += (float)$r['qty'];
                    $totalSum += (float)$r['sum'];
                  ?>
                    <tr>
                      <td><?= $i ?></td>
                      <td><?= number_format((float)$r['qty'], 3, '.', ' ') ?> <?= h($productUnit) ?></td>
                      <td><?= number_format((float)$r['price'], 2, '.', ' ') ?> lei</td>
                      <td><?= number_format((float)$r['sum'], 2, '.', ' ') ?> lei</td>
                    </tr>
                  <?php endwhile; ?>

                  <?php if($i === 0): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Нет остатков по этому товару.</td></tr>
                  <?php else: ?>
                    <tr class="table-light fw-semibold">
                      <td>Итого</td>
                      <td><?= number_format($totalQty, 3, '.', ' ') ?> <?= h($productUnit) ?></td>
                      <td>—</td>
                      <td><?= number_format($totalSum, 2, '.', ' ') ?> lei</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <form class="row g-3 align-items-end mb-4" method="get">
              <input type="hidden" name="id" value="<?= (int)$product_id ?>">
              <div class="col-md-4">
                <label class="form-label">Luna</label>
                <select class="form-select" name="m">
                  <?php foreach($monthNames as $num=>$name): ?>
                    <option value="<?= $num ?>" <?= ($num===$month?'selected':'') ?>><?= h($name) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">An</label>
                <input type="number" class="form-control" name="y" value="<?= (int)$year ?>" min="2000" max="2100">
              </div>
              <div class="col-md-4">
                <button class="btn btn-primary w-100">Показать</button>
              </div>
            </form>

            <h2 class="month-title text-center"><?= h($monthTitle) ?></h2>

            <div class="vertical-tabs">
              <div class="nav nav-pills" role="tablist">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#venit" type="button">Venit</button>
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#consum" type="button">Consum</button>
              </div>

              <div class="tab-content">
                <div class="tab-pane fade show active" id="venit">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Data</th>
                          <th>Document</th>
                          <th>Furnizor</th>
                          <th>Pretu</th>
                          <th>Cantitatea</th>
                          <th>Suma</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php $cnt=0; while($r = $venitRows->fetch_assoc()): $cnt++; ?>
                          <tr>
                            <td><?= fmtDate($r['doc_date']) ?></td>
                            <td><strong><?= h($r['doc_type'].' '.$r['doc_number']) ?></strong></td>
                            <td><?= h($r['supplier_name'] ?? '-') ?></td>
                            <td><?= number_format((float)$r['price'], 2, '.', ' ') ?> lei</td>
                            <td><?= number_format((float)$r['qty'], 3, '.', ' ') ?> <?= h($productUnit) ?></td>
                            <td><?= number_format((float)$r['sum'], 2, '.', ' ') ?> lei</td>
                          </tr>
                        <?php endwhile; ?>
                        <?php if($cnt===0): ?>
                          <tr><td colspan="6" class="text-center text-muted py-4">Нет приходов за выбранный месяц.</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="consum">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Data</th>
                          <th>Document</th>
                          <th>Pretu</th>
                          <th>Cantitatea</th>
                          <th>Suma</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php $cnt2=0; while($r = $consumRows->fetch_assoc()): $cnt2++; ?>
                          <tr>
                            <td><?= fmtDate($r['doc_date']) ?></td>
                            <td><strong><?= h($r['doc_type'].' '.$r['doc_number']) ?></strong></td>
                            <td><?= number_format((float)$r['price'], 2, '.', ' ') ?> lei</td>
                            <td><?= number_format((float)$r['qty'], 3, '.', ' ') ?> <?= h($productUnit) ?></td>
                            <td><?= number_format((float)$r['sum'], 2, '.', ' ') ?> lei</td>
                          </tr>
                        <?php endwhile; ?>
                        <?php if($cnt2===0): ?>
                          <tr><td colspan="5" class="text-center text-muted py-4">Нет списаний за выбранный месяц.</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  <div class="text-muted" style="font-size:12px;">
                    * Consum считается по `outgoing_documents/outgoing_items`.
                  </div>
                </div>

              </div>
            </div>

          </div>

          <!-- HISTORY -->
          <div class="tab-pane fade" id="istorie">
            <div class="card-soft p-3 mb-3">
              <table class="table table-striped align-middle mb-0">
                <thead>
                  <tr>
                    <th>Data</th>
                    <th>Tip</th>
                    <th>Document</th>
                    <th>Partener</th>
                    <th>Pretu</th>
                    <th>Cantitatea</th>
                    <th>Suma</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $k=0; while($r = $moveRows->fetch_assoc()): $k++; ?>
                    <tr>
                      <td><?= fmtDate($r['doc_date']) ?></td>
                      <td>
                        <?php if($r['move_type'] === 'VENIT'): ?>
                          <span class="badge bg-success">VENIT</span>
                        <?php else: ?>
                          <span class="badge bg-danger">CONSUM</span>
                        <?php endif; ?>
                      </td>
                      <td><strong><?= h($r['doc_type'].' '.$r['doc_number']) ?></strong></td>
                      <td><?= h($r['partner_name'] ?: '-') ?></td>
                      <td><?= number_format((float)$r['price'], 2, '.', ' ') ?> lei</td>
                      <td>
                        <?php if($r['move_type'] === 'VENIT'): ?>
                          <span class="text-success">+<?= number_format((float)$r['qty'], 3, '.', ' ') ?> <?= h($productUnit) ?></span>
                        <?php else: ?>
                          <span class="text-danger">-<?= number_format((float)$r['qty'], 3, '.', ' ') ?> <?= h($productUnit) ?></span>
                        <?php endif; ?>
                      </td>
                      <td><?= number_format((float)$r['sum'], 2, '.', ' ') ?> lei</td>
                    </tr>
                  <?php endwhile; ?>
                  <?php if($k === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">История пуста.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- STORAGE CONDITIONS -->
          <div class="tab-pane fade" id="conditii">
            <h5>Condiții de păstrare</h5>
            <?php if(trim($product['conditions_text']) !== ''): ?>
              <div class="storage-box">
                <?= nl2br(h($product['conditions_text'])) ?>
              </div>
            <?php else: ?>
              <div class="text-muted">Nu sunt setate condiții pentru acest produs.</div>
            <?php endif; ?>
          </div>

          <!-- SETTINGS -->
          <div class="tab-pane fade" id="setari">
            <h5 class="mb-3">Setări produs</h5>

            <form method="post" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="action" value="save_product_settings">

              <div class="col-md-6">
                <label class="form-label">Depozit (warehouse)</label>
                <select class="form-select" name="warehouse_id">
                  <option value="0">—</option>
                  <?php while($w = $warehouses->fetch_assoc()): ?>
                    <option value="<?= (int)$w['warehouse_id'] ?>" <?= ((int)$product['warehouse_id'] === (int)$w['warehouse_id'] ? 'selected' : '') ?>>
                      <?= h($w['name']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Coeficient calorii</label>
                <select class="form-select" name="coeff_id">
                  <option value="0">—</option>
                  <?php while($co = $coeffs->fetch_assoc()): ?>
                    <option value="<?= (int)$co['coeff_id'] ?>" <?= ((int)$product['coeff_id'] === (int)$co['coeff_id'] ? 'selected' : '') ?>>
                      <?= h($co['title']) ?> (<?= rtrim(rtrim(number_format((float)$co['kcal_per_gram'], 6, '.', ''), '0'), '.') ?> kcal/g)
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">DS categorie (categories_ds)</label>
                <select class="form-select" name="category_ds_id">
                  <option value="0">—</option>
                  <?php while($cds = $catsDs->fetch_assoc()): ?>
                    <option value="<?= (int)$cds['category_ds_id'] ?>" <?= ((int)$product['category_ds_id'] === (int)$cds['category_ds_id'] ? 'selected' : '') ?>>
                      <?= h($cds['name']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Categorie (categories)</label>
                <select class="form-select" name="category_id">
                  <option value="0">—</option>
                  <?php while($c = $cats->fetch_assoc()): ?>
                    <option value="<?= (int)$c['category_id'] ?>" <?= ((int)$product['category_id'] === (int)$c['category_id'] ? 'selected' : '') ?>>
                      <?= h($c['name']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Coeficient masă bucate</label>
                <input type="number" step="0.0001" name="coef_bucate" class="form-control"
                       value="<?= h($product['coef_bucate'] ?? 0) ?>">
              </div>

              <div class="col-md-6">
                <label class="form-label">Coeficient masă carne</label>
                <input type="number" step="0.0001" name="coef_carne" class="form-control"
                       value="<?= h($product['coef_carne'] ?? 0) ?>">
              </div>
              <div class="col-md-6">
  <label class="form-label">Ordine în raport</label>
  <input type="number" name="sort_report" class="form-control"
         value="<?= (int)($product['sort_report'] ?? 0) ?>">
</div>

<div class="col-md-6">
  <label class="form-label">Ordine pe site</label>
  <input type="number" name="sort_site" class="form-control"
         value="<?= (int)($product['sort_site'] ?? 0) ?>">
</div>

              <div class="col-12">
                <label class="form-label">Condiții de păstrare (text)</label>
                <textarea class="form-control" name="conditions_text" rows="5" placeholder="Scrie condițiile..."><?= h($product['conditions_text']) ?></textarea>
                <div class="form-text">Если оставить пустым — условия будут удалены.</div>
              </div>

              <div class="col-12">
                <label class="form-label">Imagine produs</label>
                <input type="file" name="product_image" class="form-control" accept="image/*">
                <div class="form-text">Загрузка новой картинки заменит старую.</div>
              </div>

              <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Salvează</button>
                <a class="btn btn-outline-secondary" href="<?= h($_SERVER['PHP_SELF'].'?id='.$product_id) ?>">Anulează</a>
              </div>
            </form>
          </div>

        </div><!-- /tab-content -->

      </div><!-- /card-soft -->
    </div><!-- /product-main -->
  </div><!-- /layout -->
</div><!-- /page-content -->

<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>
