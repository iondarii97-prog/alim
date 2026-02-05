<?php
$page_title = 'Меню на день';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

<style>
.page-content{padding:14px}
.menu-wrap{
  background:#fff;
  border-radius:12px;
  box-shadow:0 2px 8px rgba(0,0,0,.06);
  padding:10px;
  overflow:auto
}
.menu-table{
  width:100%;
  min-width:1050px;
  border-collapse:collapse;
  table-layout:fixed;
  font-size:12px
}
.menu-table th,.menu-table td{
  border:1px solid rgba(0,0,0,.12);
  padding:5px;
  text-align:center
}
.menu-table thead th{background:#f8fafc;font-weight:700}

.row-label,.prod-name{
  position:sticky;left:0;z-index:3;text-align:left
}
.row-label{background:#f8fafc;width:150px}
.prod-name{background:#fff;width:150px;font-weight:500}

.vcol,.vhead{
  writing-mode:vertical-rl;
  transform:rotate(180deg);
  white-space:nowrap
}
.vcol{height:120px;background:#f8fafc;font-weight:600}
.vname{background:#fff;font-weight:500}
.vhead{width:44px;background:#f8fafc}

.plan-cell{
  width:100%;
  min-width:44px;
  font-size:11px;
  padding:4px;
  border-radius:6px;
  border:1px solid rgba(0,0,0,.2)
}
.plan-cell:focus{
  outline:none;
  border-color:#2563eb;
  box-shadow:0 0 0 2px rgba(37,99,235,.15)
}

.sumcol,.kcalcol{
  width:90px;
  background:#f8fafc;
  font-weight:700
}

tbody tr:nth-child(even) td,
tbody tr:nth-child(even) th.prod-name{background:#fcfcfd}
tbody tr:hover td,
tbody tr:hover th.prod-name{background:#eef4ff}

.editable-head{
  cursor:text;
  outline:none
}
.editable-head:focus{
  background:#eef4ff;
  box-shadow:inset 0 0 0 2px rgba(37,99,235,.3)
}

.date-header{
  display:flex;
  gap:12px;
  justify-content:center;
  align-items:center
}
</style>

<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn=new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

$cats=$conn->query("SELECT category_id,name FROM categories ORDER BY category_id");
$prods=$conn->query("
  SELECT p.product_id,p.name,p.category_id,
         COALESCE(cc.kcal_per_gram,0) kcal
  FROM products p
  LEFT JOIN product_calorie_coeff pc ON pc.product_id=p.product_id
  LEFT JOIN calorie_coefficients cc ON cc.coeff_id=pc.coeff_id
  ORDER BY p.category_id,p.name
");

$byCat=[];
while($r=$prods->fetch_assoc()){
  $byCat[$r['category_id']][]=$r;
}
?>

<div class="page-content">
<div class="menu-wrap">

<table class="menu-table">
<thead>
<tr>
  <th class="row-label">Ziua și data</th>
  <th colspan="13">
    <div class="date-header">
      <select id="dayName">
        <option>Luni</option><option>Marți</option><option>Miercuri</option>
        <option>Joi</option><option>Vineri</option>
        <option>Sâmbătă</option><option>Duminică</option>
      </select>
      <input type="date" id="menuDate" value="<?=date('Y-m-d')?>">
    </div>
  </th>
</tr>

<tr>
  <th class="row-label">Masa</th>
  <th colspan="3">Dejun</th>
  <th colspan="5">Prânz</th>
  <th colspan="3">Cină</th>
  <th class="vhead" rowspan="3">Total gr</th>
  <th class="vhead" rowspan="3">Total kcal</th>
</tr>

<tr>
  <th class="row-label">Fel</th>
  <?php for($i=0;$i<11;$i++): ?><th class="vcol">Felu</th><?php endfor; ?>
</tr>

<tr>
  <th class="row-label">Denumire</th>
  <?php for($i=0;$i<11;$i++): ?>
    <th class="vcol vname editable-head" contenteditable="true"></th>
  <?php endfor; ?>
</tr>
</thead>

<tbody>
<?php while($c=$cats->fetch_assoc()):
  foreach($byCat[$c['category_id']]??[] as $p): ?>
<tr>
  <th class="prod-name"><?=htmlspecialchars($p['name'])?></th>
  <?php for($i=0;$i<11;$i++): ?>
    <td><input type="number" step="0.001" class="plan-cell" data-row="<?=$p['product_id']?>"></td>
  <?php endfor; ?>
  <td class="sumcol row-sum" data-row="<?=$p['product_id']?>">0</td>
  <td class="kcalcol row-kcal" data-row="<?=$p['product_id']?>" data-kpg="<?=$p['kcal']?>">0</td>
</tr>
<?php endforeach; endwhile; ?>

<tr>
  <th colspan="12" style="background:#f8fafc;font-weight:800">TOTAL PE ZI</th>
  <th id="dayGr">0</th>
  <th id="dayKcal">0</th>
</tr>
</tbody>
</table>

<button id="saveMenu" class="btn btn-primary mt-2">
  💾 Сохранить меню
</button>

</div>
</div>

<script>
function n(v){return parseFloat(v)||0}
document.addEventListener('input',e=>{
 if(!e.target.classList.contains('plan-cell'))return
 const pid=e.target.dataset.row
 let s=0
 document.querySelectorAll('.plan-cell[data-row="'+pid+'"]').forEach(i=>s+=n(i.value))
 const sum=document.querySelector('.row-sum[data-row="'+pid+'"]')
 const kcal=document.querySelector('.row-kcal[data-row="'+pid+'"]')
 sum.textContent=s.toFixed(3)
 kcal.textContent=(s*n(kcal.dataset.kpg)).toFixed(2)

 let dg=0,dk=0
 document.querySelectorAll('.row-sum').forEach(t=>dg+=n(t.textContent))
 document.querySelectorAll('.row-kcal').forEach(t=>dk+=n(t.textContent))
 document.getElementById('dayGr').textContent=dg.toFixed(3)
 document.getElementById('dayKcal').textContent=dk.toFixed(2)
})
</script>

<script>
document.getElementById('saveMenu').addEventListener('click', () => {
  const data = {
    date: document.getElementById('menuDate').value,
    day: document.getElementById('dayName').value,
    meals: [],
    items: []
  };

  document.querySelectorAll('.editable-head').forEach((el, i) => {
    data.meals.push({ col: i, name: el.innerText.trim() });
  });

  document.querySelectorAll('.plan-cell').forEach(inp => {
    const val = parseFloat(inp.value);
    if (!val) return;

    data.items.push({
      product_id: inp.dataset.row,
      col: inp.closest('td').cellIndex - 1,
      grams: val
    });
  });

  fetch('save_menu.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data)
  })
  .then(r => r.json())
  .then(res => alert(res.message))
  .catch(() => alert('Ошибка сохранения'));
});
</script>

<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>
