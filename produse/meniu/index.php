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

?>
<style>
.wrap{
  background:#fff;
  padding:10px;
  overflow:visible;
  max-width:none;
}

.tbl{
  border-collapse:collapse;
  font-size:10px;
  width:100%;
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

.footer-sign,
.footer-sign table,
.footer-sign td,
.footer-sign tr{
  border:none !important;
}

/* ===== FILTER GRID ===== */
.site-header-filters{
  display:grid;
  grid-template-columns:repeat(4,1fr);
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

/* ===== SCROLL AREA ===== */
.scroll-area{
  width:100%;
  height:80vh;
  overflow:auto;
  position:relative;
  background:#f8fafc;
}

.scroll-canvas{
  min-width:400px;
  box-sizing:border-box;
}
/* скрыть полосы скролла полностью */
.scroll-area{
  scrollbar-width: none;          /* Firefox */
  -ms-overflow-style: none;       /* IE / Edge */
}

.scroll-area::-webkit-scrollbar{
  width: 0;
  height: 0;
}

@media print {

  @page{
    size:A3 landscape;
    margin:0;                 /* максимум места */
  }

  html, body{
    margin:0 !important;
    padding:0 !important;
    overflow:hidden !important;
  }

  body *{ visibility:hidden; }

  #print-area, #print-area *{ visibility:visible; }

  /* ФИЗИЧЕСКИЙ ЛИСТ A3 */
  #print-area{
    position:fixed;
    left:0; top:0;
    width:420mm;
    height:297mm;
    overflow:hidden !important;
  }

  /* 1) УЖИМАЕМ ВСЁ */
  #print-area .wrap{
    transform:scale(0.56);    /* <-- ключ: уменьши если надо */
    transform-origin:top left;
    width:178%;               /* компенсация ширины */
  }

  /* 2) УМЕНЬШАЕМ ВЫСОТУ СТРОК (это важно!) */
  .tbl{
    font-size:8px !important;
  }
  .tbl th, .tbl td{
    padding:1px 2px !important;
    line-height:1 !important;
  }

  /* заголовок над таблицей тоже меньше */
  #print-area .wrap > div{
    margin-bottom:6px !important;
    font-size:10px !important;
  }

  thead, tfoot{ display:table-row-group !important; }
  tr{ page-break-inside:avoid !important; }

  *{
    -webkit-print-color-adjust:exact !important;
    print-color-adjust:exact !important;
  }
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
  <button type="button"
          class="btn btn-sm btn-secondary"
          onclick="printPage()">
    🖨️ Печать
  </button>
</div>

<div id="print-area">
<div class="scroll-area">
  <div class="scroll-canvas">
  <!-- ===== ЛИСТ 1 (верх) ===== -->
  
    <div class="wrap">    
      <!-- ===== HEADER, ПРИВЯЗАННЫЙ К ТАБЛИЦЕ ===== -->
      <div style="width:100%;margin-bottom:20px;font-size:13px">
        <table style="width:100%;border-collapse:collapse">
          <tr>
                   <!-- LEFT -->
            <td style="width:10%;text-align:left;vertical-align:top">
              <span id="out_pos1"></span><br>
              <span id="out_rank1"></span> <b><span id="out_person1"></span></b><br><?= date('d.m.Y') ?>
            </td>

                  <!-- CENTER -->
            <td style="width:90%;text-align:center;vertical-align:top">
               REPARTIZAREA PRODUSELOR ALIMENTARE<br>
               pe perioada
               <?=date('d.m.Y',strtotime($weekStart))?> – <?=date('d.m.Y',strtotime($weekEnd))?>
            </td>

                   <!-- RIGHT -->
          </tr>
        </table>
      </div>
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

<?php
$weekTotalsGrams=[];
$weekTotalsCalories=[];
$weekCaloriesSum=0;


?>

<?php foreach($days as $d): ?>
<?php

  $dayMeals = $mealsByDay[$d['menu_id']] ?? [];
  $printedMasa = [];
$printedFel  = [];
  if(!$dayMeals) continue;

  $dayLabel = roDay($d['menu_date']).' '.date('d.m.Y',strtotime($d['menu_date']));
  $rowsInDay = count($dayMeals);

  $dayTotals=[];
  $dayCalories=[];
  $rowTotal = 0; // masa_bucate
  $rowMeat  = 0; // masa_carne
?>

<?php foreach($dayMeals as $i=>$m): ?>
<?php
$rowMeat  = 0;   // Masa carne
  $rowTotal = 0;   // Masa generală

  $masa = $mealMap[$m['col_index']]['masa'] ?? '';
  $fel  = $mealMap[$m['col_index']]['fel']  ?? '';
  
$masa = $mealMap[$m['col_index']]['masa'] ?? '';
$fel  = $mealMap[$m['col_index']]['fel']  ?? '';
?>

<tr>
  <?php if($i===0): ?>
    <td rowspan="<?=$rowsInDay?>" class="vtext"><?=$dayLabel?></td>
  <?php endif; ?>

  <?php
// --- MASA ---
if(!isset($printedMasa[$masa])){
  // считаем, сколько строк у этой masa в текущем дне
  $masaRows = 0;
  foreach($dayMeals as $mm){
    if(($mealMap[$mm['col_index']]['masa'] ?? '') === $masa){
      $masaRows++;
    }
  }
  echo '<td rowspan="'.$masaRows.'" class="vertical-text">'.h($masa).'</td>';
  $printedMasa[$masa] = true;
}

// --- FELUL ---
$keyFel = $masa.'|'.$fel;
if(!isset($printedFel[$keyFel])){
  // считаем, сколько строк у этой пары masa+fel
  $felRows = 0;
  foreach($dayMeals as $mm){
    $m2 = $mealMap[$mm['col_index']]['masa'] ?? '';
    $f2 = $mealMap[$mm['col_index']]['fel']  ?? '';
    if($m2 === $masa && $f2 === $fel){
      $felRows++;
    }
  }
  echo '<td rowspan="'.$felRows.'">'.h($fel).'</td>';
  $printedFel[$keyFel] = true;
}
?>

  <td><?=h($m['meal_name'])?></td>

  <?php foreach($products as $p): ?>
  <?php
    $pid = $p['product_id'];
    $val = $itemsByPos[$m['menu_id']][$m['col_index']][$pid] ?? '';

    if($val!==''){
      $g = (float)$val;

      // ---- grame ----
      $dayTotals[$pid] = ($dayTotals[$pid] ?? 0) + $g;
      $weekTotalsGrams[$pid] = ($weekTotalsGrams[$pid] ?? 0) + $g;

      // ---- calorii ----
      $kcal = $g * ($kcalByProduct[$pid] ?? 0);
      $dayCalories[$pid] = ($dayCalories[$pid] ?? 0) + $kcal;
      $weekTotalsCalories[$pid] = ($weekTotalsCalories[$pid] ?? 0) + $kcal;
      $weekCaloriesSum += $kcal;

      // ---- masa carne ----
      if(isset($coefCarne[$pid])){
        $rowMeat += $g * $coefCarne[$pid];
      }

      // ---- masa bucate ----
      if(isset($coefBucate[$pid])){
        $rowTotal += $g * $coefBucate[$pid];
      }
    }
  ?>
  <td><?= $val!=='' ? fmt($val) : '' ?></td>
  <?php endforeach; ?>

  <td><?= fmt($rowTotal) ?></td>
  <td><?= fmt($rowMeat) ?></td>
</tr>

<?php endforeach; ?>

<!-- ===== TOTAL GRAME PE ZI ===== -->
<tr class="total">
  <td colspan="4">Total grame pe zi</td>
  <?php foreach($products as $p): ?>
    <td><?= isset($dayTotals[$p['product_id']]) ? fmt($dayTotals[$p['product_id']]) : '' ?></td>
  <?php endforeach; ?>
  <td></td><td></td>
</tr>

<!-- ===== TOTAL CALORII PE ZI ===== -->
<?php
$dayCaloriesSum=0;
foreach($dayCalories as $v) $dayCaloriesSum+=$v;
?>
<tr class="total">
  <td colspan="4">Total calorii pe zi</td>
  <?php foreach($products as $p): ?>
    <td><?= isset($dayCalories[$p['product_id']]) ? fmt($dayCalories[$p['product_id']]) : '' ?></td>
  <?php endforeach; ?>
  <td></td><td></td>
  <td><?= fmt($dayCaloriesSum) ?></td>
</tr>

<?php endforeach; ?>

<!-- ===== TOTAL SAPTAMINA ===== -->
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
  <td><?= fmt($weekCaloriesSum) ?></td>
</tr>

</tbody>
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

</table>
</div>
</div>
</div>
</div>
</div>
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
<script>
const area = document.querySelector('.scroll-area');
let isDown = false, startX, startY, scrollLeft, scrollTop;

area.addEventListener('mousedown', e=>{
  isDown = true;
  startX = e.pageX - area.offsetLeft;
  startY = e.pageY - area.offsetTop;
  scrollLeft = area.scrollLeft;
  scrollTop  = area.scrollTop;
  area.style.cursor = 'grabbing';
});

area.addEventListener('mouseleave', ()=> isDown=false);
area.addEventListener('mouseup', ()=>{
  isDown=false;
  area.style.cursor = 'default';
});

area.addEventListener('mousemove', e=>{
  if(!isDown) return;
  e.preventDefault();
  const x = e.pageX - area.offsetLeft;
  const y = e.pageY - area.offsetTop;
  area.scrollLeft = scrollLeft - (x - startX);
  area.scrollTop  = scrollTop  - (y - startY);
});
</script>





<?php include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php'; ?>
