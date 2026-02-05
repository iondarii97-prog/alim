<?php
$page_title = 'Repartizarea produselor alimentare';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
require $_SERVER['DOCUMENT_ROOT'].'/includ/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ================= helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function roDay($date){
  $days = [
    'Sun'=>'Duminică','Mon'=>'Luni','Tue'=>'Marți',
    'Wed'=>'Miercuri','Thu'=>'Joi',
    'Fri'=>'Vineri','Sat'=>'Sîmbătă'
  ];
  return $days[date('D', strtotime($date))] ?? date('D', strtotime($date));
}
function fmt($n){
  if($n === '' || $n === null) return '';
  return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

/* ================= STRUCTURA MESE ================= */
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

$mealMap=[];
foreach($structure as $masa=>$rows){
  foreach($rows as $r){
    $mealMap[$r['idx']] = ['masa'=>$masa,'fel'=>$r['fel']];
  }
}

/* ================= ALEGERE SAPTAMINA ================= */
$weekDate = $_GET['week'] ?? date('Y-m-d');
$ts = strtotime($weekDate);
$weekStart = date('Y-m-d', strtotime('monday this week', $ts));
$weekEnd   = date('Y-m-d', strtotime('sunday this week', $ts));

/* ================= PRODUSE + CALORII ================= */
$res = $conn->query("
  SELECT p.product_id,p.name,p.unit,p.category_id,cc.kcal_per_gram
  FROM products p
  LEFT JOIN categories c ON c.category_id = p.category_id
  LEFT JOIN product_calorie_coeff pc ON pc.product_id=p.product_id
  LEFT JOIN calorie_coefficients cc ON cc.coeff_id=pc.coeff_id
  WHERE p.active = 1
    AND p.category_id IS NOT NULL
    AND p.category_id <> 0
    AND c.name <> 'Detergenti'
  ORDER BY p.category_id ASC,p.name ASC
");

$products = $res->fetch_all(MYSQLI_ASSOC);

$kcalByProduct=[];
foreach($products as $p){
  $kcalByProduct[$p['product_id']] = (float)($p['kcal_per_gram'] ?? 0);
}

/* ================= COEFICIENTI ================= */
$coefCarne=[];
$r = $conn->query("SELECT product_id,coef FROM masa_carne WHERE active=1");
while($x=$r->fetch_assoc()) $coefCarne[$x['product_id']] = (float)$x['coef'];

$coefBucate=[];
$r = $conn->query("SELECT product_id,coef FROM masa_bucate WHERE active=1");
while($x=$r->fetch_assoc()) $coefBucate[$x['product_id']] = (float)$x['coef'];

/* ================= ZILE ================= */
$daysRes = $conn->query("
  SELECT menu_id,menu_date
  FROM menu_days
  WHERE menu_date BETWEEN '$weekStart' AND '$weekEnd'
  ORDER BY menu_date ASC
");
$days = $daysRes->fetch_all(MYSQLI_ASSOC);

/* ================= MENIURI ================= */
$mealsRaw = $conn->query("
  SELECT meal_id,menu_id,col_index,meal_name
  FROM menu_meals
  ORDER BY menu_id,col_index
")->fetch_all(MYSQLI_ASSOC);

/* ================= ITEMS ================= */
$itemsRaw = $conn->query("
  SELECT menu_id,col_index,product_id,grams
  FROM menu_items
")->fetch_all(MYSQLI_ASSOC);

/* ================= INDEXARE ================= */
$mealsByDay=[];
foreach($mealsRaw as $m){
  $mealsByDay[$m['menu_id']][]=$m;
}

$itemsByPos=[];
foreach($itemsRaw as $it){
  $itemsByPos[$it['menu_id']][$it['col_index']][$it['product_id']] = $it['grams'];
}
$positionsRes = $conn->query("
  SELECT position_id, position_name
  FROM person_positions
  WHERE active = 1
  ORDER BY position_name
");
$positions = $positionsRes->fetch_all(MYSQLI_ASSOC);
$rankRes = $conn->query("
  SELECT position_id, position_name
  FROM positions
  WHERE active = 1
  ORDER BY position_id
");
$ranks = $rankRes->fetch_all(MYSQLI_ASSOC);
$personsRes = $conn->query("
  SELECT person_id, name
  FROM persons
  WHERE active = 1
  ORDER BY name
");
$persons = $personsRes->fetch_all(MYSQLI_ASSOC);

/* ===== делим дни на 3 листа ===== */
$days_page1 = array_slice($days, 0, 2); // Luni + Marti
$days_page2 = array_slice($days, 2, 3); // Miercuri + Joi + Vineri
$days_page3 = array_slice($days, 5);    // Simbata + Duminica

?>

<style>
.wrap{
  background:#fff;
  padding:10px;
  overflow:auto;       /* скролл */
  max-width:100%;
}

.tbl{
  border-collapse:collapse;
  font-size:10px;
  min-width:2600px;
}

.tbl th,.tbl td{
  border:1px solid #000;
  padding:2px 3px;
  text-align:center;
  white-space:nowrap;
}

.tbl thead th{
  background:#f3f3f3;
  font-weight:700;
}

.vtext{
  writing-mode:vertical-rl;
  transform:rotate(180deg);
  padding:6px 2px;
}

.total{
  background:#e0e0e0;
  font-weight:700;
}

/* убрать границы в подписи футера */
.footer-sign,
.footer-sign table,
.footer-sign td,
.footer-sign tr{
  border:none !important;
}

/* 4 блока по 3 селекта */
.site-header-filters{
  display:grid;
  grid-template-columns:repeat(4, 1fr);
  gap:14px;
  background:#fff;
  padding:12px;
  border-radius:10px;
  box-shadow:0 2px 8px rgba(0,0,0,.08);
  margin-bottom:15px;
}

.filter-block{
  display:flex;
  flex-direction:column;
  gap:8px;
}

.filter-block select{
  width:100%;
  padding:6px 8px;
  font-size:13px;
  border:1px solid #ccc;
  border-radius:6px;
  background:#f9fafb;
}
/* ===== ПЕЧАТЬ: 3 ЛИСТА ПО ВЫСОТЕ ===== */
.print-page{
  width:210mm;
  height:297mm;        /* высота A4 */
  padding:15mm;
  box-sizing:border-box;
  overflow:hidden;    /* как в Excel — лишнее уходит на следующий лист */
  page-break-after: always;
}

.print-page:last-child{
  page-break-after: auto;
}

/* ===== ПЕЧАТЬ: 3 ЛИСТА ПО ВЫСОТЕ ===== */
.print-page{
  width:210mm;
  height:297mm;        /* A4 */
  padding:15mm;
  box-sizing:border-box;
  overflow:hidden;    /* как в Excel — лишнее уходит на следующий лист */
  page-break-after: always;
}
.print-page:last-child{ page-break-after:auto; }

@media print {

  /* показываем только область печати */
  body *{ visibility:hidden; }

  #print-area,
  #print-area *{ visibility:visible; }

  #print-area{
    position:absolute;
    left:0; top:0;
    width:210mm;
  }

  /* шапка/футер таблицы НЕ повторяются */
  thead, tfoot{ display: table-row-group; }

  /* строки не рвём */
  tr{ page-break-inside: avoid; }
}

</style>

<div class="page-content p-2">
  <div class="site-header-filters">

  <!-- ===== BLOCK 1 ===== -->
  <div class="filter-block">
    <select name="block1_position" class="form-select" id="pos1">
  <option value="">— Alege funcția —</option>
  <?php foreach($positions as $p): ?>
    <option value="<?=$p['position_id']?>">
      <?=h($p['position_name'])?>
    </option>
  <?php endforeach; ?>
</select>

    <select name="block1_rank" class="form-select mb-2" id="rank1">
  <option value="">— Grad —</option>
  <?php foreach($ranks as $r): ?>
    <option value="<?=$r['position_id']?>">
      <?=h($r['position_name'])?>
    </option>
  <?php endforeach; ?>
</select>

    <select name="block1_person" class="form-select" id="person1">
  <option value="">— Persoană —</option>
  <?php foreach($persons as $p): ?>
    <option value="<?=$p['person_id']?>">
      <?=h($p['name'])?>
    </option>
  <?php endforeach; ?>
</select>

  </div>

  <!-- ===== BLOCK 2 ===== -->
  <div class="filter-block">
    <select name="block1_position" class="form-select" id="pos2">
  <option value="">— Alege funcția —</option>
  <?php foreach($positions as $p): ?>
    <option value="<?=$p['position_id']?>">
      <?=h($p['position_name'])?>
    </option>
  <?php endforeach; ?>
</select>

    <select name="block1_rank" class="form-select mb-2" id="rank2">
  <option value="">— Grad —</option>
  <?php foreach($ranks as $r): ?>
    <option value="<?=$r['position_id']?>">
      <?=h($r['position_name'])?>
    </option>
  <?php endforeach; ?>
</select>

    <select name="block1_person" class="form-select" id="person2">
  <option value="">— Persoană —</option>
  <?php foreach($persons as $p): ?>
    <option value="<?=$p['person_id']?>">
      <?=h($p['name'])?>
    </option>
  <?php endforeach; ?>
</select>

  </div>

  <!-- ===== BLOCK 3 ===== -->
  <div class="filter-block">
    <select name="block1_position" class="form-select" id="pos3">
  <option value="">— Alege funcția —</option>
  <?php foreach($positions as $p): ?>
    <option value="<?=$p['position_id']?>">
      <?=h($p['position_name'])?>
    </option>
  <?php endforeach; ?>
</select>

    <select name="block1_rank" class="form-select mb-2" id="rank3">
  <option value="">— Grad —</option>
  <?php foreach($ranks as $r): ?>
    <option value="<?=$r['position_id']?>">
      <?=h($r['position_name'])?>
    </option>
  <?php endforeach; ?>
</select>

    <select name="block1_person" class="form-select" id="person3">
  <option value="">— Persoană —</option>
  <?php foreach($persons as $p): ?>
    <option value="<?=$p['person_id']?>">
      <?=h($p['name'])?>
    </option>
  <?php endforeach; ?>
</select>

  </div>

  <!-- ===== BLOCK 4 ===== -->
  <div class="filter-block">
    <select name="block1_position" class="form-select" id="pos4">
  <option value="">— Alege funcția —</option>
  <?php foreach($positions as $p): ?>
    <option value="<?=$p['position_id']?>">
      <?=h($p['position_name'])?>
    </option>
  <?php endforeach; ?>
</select>

    <select name="block1_rank" class="form-select mb-2" id="rank4">
  <option value="">— Grad —</option>
  <?php foreach($ranks as $r): ?>
    <option value="<?=$r['position_id']?>">
      <?=h($r['position_name'])?>
    </option>
  <?php endforeach; ?>
</select>

    <select name="block1_person" class="form-select" id="person4">
  <option value="">— Persoană —</option>
  <?php foreach($persons as $p): ?>
    <option value="<?=$p['person_id']?>">
      <?=h($p['name'])?>
    </option>
  <?php endforeach; ?>
</select>

  </div>

</div>

<form method="get" class="mb-3" style="text-align:center">
  <label>
    Alege săptămîna:
    <input type="date" name="week" value="<?=$weekDate?>">
  </label>
  <button class="btn btn-sm btn-primary">Arată</button>
</form>
<div style="text-align:center;margin:10px 0">
  <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()">🖨️ Печать</button>
</div>
</div>
<div id="print-area">

<div class="wrap">    
      <!-- ===== HEADER, ПРИВЯЗАННЫЙ К ТАБЛИЦЕ ===== -->
      <div style="width:100%;margin-bottom:20px;font-size:13px">
        <table style="width:100%;border-collapse:collapse">
          <tr>
                   <!-- LEFT -->
            <td style="width:60%;text-align:left;vertical-align:top">
              <span id="out_pos1"></span><br>
              <span id="out_rank1"></span> <b><span id="out_person1"></span></b><br><?= date('d.m.Y') ?>
            </td>

                  <!-- CENTER -->
            <td style="width:40%;text-align:center;vertical-align:top">
               REPARTIZAREA PRODUSELOR ALIMENTARE<br>
               pe perioada
               <?=date('d.m.Y',strtotime($weekStart))?> – <?=date('d.m.Y',strtotime($weekEnd))?>
            </td>

                   <!-- RIGHT -->
            <td style="width:35%"></td>
          </tr>
        </table>
      </div>
<?php
function renderDays($daysPart, $products, $mealsByDay, $itemsByPos, $mealMap,
                    $kcalByProduct, $coefCarne, $coefBucate,
                    &$weekTotalsGrams, &$weekTotalsCalories, &$weekCaloriesSum,
                    $showTotals=false, $showFooter=false){
?>

<table class="tbl">
<thead>
                      <!-- ===== TABLE HEADER ===== -->
          <tr>
             <th>Data</th>
             <th>Masa</th>
             <th>Felul</th>
             <th>Denumirea bucatelor</th>
           <?php foreach($products as $p): ?>
             <th class="vtext"><?=h($p['name'])?></th>
           <?php endforeach; ?>
             <th class="vtext">Masa generală</th>
             <th class="vtext">Masa carne/pește</th>
</tr>

</thead>
<tbody>

<?php foreach($daysPart as $d):
  $dayMeals = $mealsByDay[$d['menu_id']] ?? [];
  if(!$dayMeals) continue;

  $printedMasa=[]; $printedFel=[];
  $dayLabel = roDay($d['menu_date']).' '.date('d.m.Y',strtotime($d['menu_date']));
  $rowsInDay = count($dayMeals);

  $dayTotals=[]; $dayCalories=[];
?>

<?php foreach($dayMeals as $i=>$m):
  $rowMeat=0; $rowTotal=0;
  $masa = $mealMap[$m['col_index']]['masa'] ?? '';
  $fel  = $mealMap[$m['col_index']]['fel']  ?? '';
?>
<tr>
<?php if($i===0): ?><td rowspan="<?=$rowsInDay?>" class="vtext"><?=$dayLabel?></td><?php endif; ?>

<?php
if(!isset($printedMasa[$masa])){
  $masaRows=0;
  foreach($dayMeals as $mm){
    if(($mealMap[$mm['col_index']]['masa'] ?? '') === $masa) $masaRows++;
  }
  echo '<td rowspan="'.$masaRows.'">'.h($masa).'</td>';
  $printedMasa[$masa]=true;
}

$keyFel=$masa.'|'.$fel;
if(!isset($printedFel[$keyFel])){
  $felRows=0;
  foreach($dayMeals as $mm){
    $m2=$mealMap[$mm['col_index']]['masa'] ?? '';
    $f2=$mealMap[$mm['col_index']]['fel']  ?? '';
    if($m2===$masa && $f2===$fel) $felRows++;
  }
  echo '<td rowspan="'.$felRows.'">'.h($fel).'</td>';
  $printedFel[$keyFel]=true;
}
?>

<td><?=h($m['meal_name'])?></td>

<?php foreach($products as $p):
  $pid=$p['product_id'];
  $val=$itemsByPos[$m['menu_id']][$m['col_index']][$pid] ?? '';
  if($val!==''){
    $g=(float)$val;
    $dayTotals[$pid]=($dayTotals[$pid]??0)+$g;
    $weekTotalsGrams[$pid]=($weekTotalsGrams[$pid]??0)+$g;

    $kcal=$g*($kcalByProduct[$pid]??0);
    $dayCalories[$pid]=($dayCalories[$pid]??0)+$kcal;
    $weekTotalsCalories[$pid]=($weekTotalsCalories[$pid]??0)+$kcal;
    $weekCaloriesSum+=$kcal;

    if(isset($coefCarne[$pid]))  $rowMeat += $g*$coefCarne[$pid];
    if(isset($coefBucate[$pid])) $rowTotal+= $g*$coefBucate[$pid];
  }
?>
<td><?= $val!=='' ? fmt($val) : '' ?></td>
<?php endforeach; ?>

<td><?=fmt($rowTotal)?></td>
<td><?=fmt($rowMeat)?></td>
</tr>
<?php endforeach; ?>

<tr class="total">
  <td colspan="4">Total grame pe zi</td>
  <?php foreach($products as $p): ?>
    <td><?= isset($dayTotals[$p['product_id']]) ? fmt($dayTotals[$p['product_id']]) : '' ?></td>
  <?php endforeach; ?>
  <td></td><td></td>
</tr>

<?php
$dayCaloriesSum=0; foreach($dayCalories as $v) $dayCaloriesSum+=$v;
?>
<tr class="total">
  <td colspan="4">Total calorii pe zi</td>
  <?php foreach($products as $p): ?>
    <td><?= isset($dayCalories[$p['product_id']]) ? fmt($dayCalories[$p['product_id']]) : '' ?></td>
  <?php endforeach; ?>
  <td></td><td></td>
  <td><?=fmt($dayCaloriesSum)?></td>
</tr>

<?php endforeach; ?>

<?php if($showTotals): ?>
<tr class="total">
  <td colspan="4">Total grame pe săptămînă</td>
  <?php foreach($products as $p): ?>
    <td><?= isset($weekTotalsGrams[$p['product_id']]) ? fmt($weekTotalsGrams[$p['product_id']]) : '' ?></td>
  <?php endforeach; ?>
  <td></td><td></td>
</tr>

<tr class="total">
  <td colspan="4">Total calorii pe săptămînă</td>
  <?php foreach($products as $p): ?>
    <td><?= isset($weekTotalsCalories[$p['product_id']]) ? fmt($weekTotalsCalories[$p['product_id']]) : '' ?></td>
  <?php endforeach; ?>
  <td></td><td></td>
  <td><?=fmt($weekCaloriesSum)?></td>
</tr>
<?php endif; ?>

</tbody>

<?php if($showFooter): ?>
<tfoot class="footer-sign">
<tr>
  <td colspan="<?= 4 + count($products) + 2 ?>" style="padding:25px 10px;border-top:2px solid #000">

    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <tr>
        <!-- LEFT -->
        <td style="padding:10px;vertical-align:top">

<table style="width:100%;border-collapse:collapse;text-align:center;font-size:12px">
  <!-- ===== РЯД 1 ===== -->
  <tr>
    <td colspan="3" style="text-align:left;vertical-align:top"><span id="out_pos2"></span></td>
  </tr>

  <!-- ===== РЯД 2 ===== -->
  <tr>
    <td style="text-align:left;vertical-align:top"><span id="out_rank2"></span></td>
    <td></td>
    <td style="text-align:right;vertical-align:top"><span id="out_person2"></span></td>
  </tr>

  <!-- ===== РЯД 3 ===== -->
  <tr>
    <td  colspan="3" style="text-align:left;vertical-align:top"><?= date('d.m.Y') ?></td>
  </tr>
</table>

</td>

        <td style="width:10%;text-align:right;vertical-align:top"></td>
        <td style="width:10%;text-align:right;vertical-align:top"></td>
        <td style="width:10%;text-align:right;vertical-align:top"></td>
        <!-- CENTER -->
        <td style="width:10%;text-align:center;vertical-align:top">
          <table style="width:100%;border-collapse:collapse;text-align:center;font-size:12px">
  <!-- ===== РЯД 1 ===== -->
  <tr>
    <td colspan="3" style="text-align:left;vertical-align:top"><span id="out_pos3"></span></td>
  </tr>

  <!-- ===== РЯД 2 ===== -->
  <tr>
    <td style="text-align:left;vertical-align:top"><span id="out_rank3"></span></td>
    <td></td>
    <td style="text-align:right;vertical-align:top"><span id="out_person3"></span></td>
  </tr>

  <!-- ===== РЯД 3 ===== -->
  <tr>
    <td  colspan="3" style="text-align:left;vertical-align:top"><?= date('d.m.Y') ?></td>
  </tr>
</table>

        </td>
         <td style="width:10%;text-align:right;vertical-align:top"></td>
        <td style="width:10%;text-align:right;vertical-align:top"></td>
        <td style="width:10%;text-align:right;vertical-align:top"></td>
        <!-- RIGHT -->
        <td style="width:10%;text-align:right;vertical-align:top">
<table style="width:100%;border-collapse:collapse;text-align:center;font-size:12px">          
  <tr>
    <td colspan="3" style="text-align:left;vertical-align:top"><span id="out_pos4"></span></td>
  </tr>

  <!-- ===== РЯД 2 ===== -->
  <tr>
    <td style="text-align:left;vertical-align:top"><span id="out_rank4"></span></td>
    <td></td>
    <td style="text-align:right;vertical-align:top"><span id="out_person4"></span></td>
  </tr>

  <!-- ===== РЯД 3 ===== -->
  <tr>
    <td  colspan="3" style="text-align:left;vertical-align:top"><?= date('d.m.Y') ?></td>
  </tr>
</table>
        </td>
        <td style="width:10%;text-align:right;vertical-align:top"></td>
      </tr>
    </table>

  </td>
</tr>
</tfoot>
<?php endif; ?>

</table>

<?php } // end renderDays ?>

<?php
$weekTotalsGrams=[];
$weekTotalsCalories=[];
$weekCaloriesSum=0;
?>

<!-- ===== ЛИСТ 1 ===== -->
<div class="print-page">
  <div class="wrap">
    <h3 style="text-align:center;margin:0 0 10px">
      REPARTIZAREA PRODUSELOR ALIMENTARE<br>
      pe perioada <?=date('d.m.Y',strtotime($weekStart))?> – <?=date('d.m.Y',strtotime($weekEnd))?>
    </h3>
    <?php renderDays($days_page1,$products,$mealsByDay,$itemsByPos,$mealMap,$kcalByProduct,$coefCarne,$coefBucate,$weekTotalsGrams,$weekTotalsCalories,$weekCaloriesSum,false,false); ?>
  </div>
</div>

<!-- ===== ЛИСТ 2 ===== -->
<div class="print-page">
  <div class="wrap">
    <?php renderDays($days_page2,$products,$mealsByDay,$itemsByPos,$mealMap,$kcalByProduct,$coefCarne,$coefBucate,$weekTotalsGrams,$weekTotalsCalories,$weekCaloriesSum,false,false); ?>
  </div>
</div>

<!-- ===== ЛИСТ 3 ===== -->
<div class="print-page">
  <div class="wrap">
    <?php renderDays($days_page3,$products,$mealsByDay,$itemsByPos,$mealMap,$kcalByProduct,$coefCarne,$coefBucate,$weekTotalsGrams,$weekTotalsCalories,$weekCaloriesSum,true,true); ?>
  </div>
</div>

</div><!-- /print-area -->
</div><!-- /page-content -->
</div>
<script>
function updateBlock(n){
  const posSel   = document.getElementById('pos'+n);
  const rankSel  = document.getElementById('rank'+n);
  const personSel= document.getElementById('person'+n);

  const pos  = posSel?.options[posSel.selectedIndex]?.text || '';
  const rank = rankSel?.options[rankSel.selectedIndex]?.text || '';
  const pers = personSel?.options[personSel.selectedIndex]?.text || '';

  const outPos   = document.getElementById('out_pos'+n);
  const outRank  = document.getElementById('out_rank'+n);
  const outPerson= document.getElementById('out_person'+n);

  if(outPos) outPos.textContent = pos;
  if(outRank) outRank.textContent = rank;
  if(outPerson) outPerson.textContent = pers;
}

for(let n=1; n<=4; n++){
  ['pos','rank','person'].forEach(prefix=>{
    const el = document.getElementById(prefix+n);
    if(el) el.addEventListener('change', ()=>updateBlock(n));
  });
  // чтобы при загрузке тоже сразу заполнилось
  updateBlock(n);
}
</script>
<script>
function printPage(){
  window.print();
}
</script>
<?php include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php'; ?>
