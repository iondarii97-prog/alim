<?php
$page_title = 'Редактирование меню';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
require $_SERVER['DOCUMENT_ROOT'].'/includ/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$menu_id = (int)($_GET['menu_id'] ?? 0);

/* ===== список дней ===== */
$days = $conn->query("
  SELECT menu_id, menu_date, day_name
  FROM menu_days
  ORDER BY menu_date DESC
");

/* ===== если выбран день ===== */
$meals = [];
$items = [];

if($menu_id){
  // блюда (заголовки колонок)
  $res = $conn->prepare("
    SELECT meal_id, col_index, meal_name
    FROM menu_meals
    WHERE menu_id = ?
    ORDER BY col_index
  ");
  $res->bind_param("i",$menu_id);
  $res->execute();
  $meals = $res->get_result()->fetch_all(MYSQLI_ASSOC);

  // продукты в меню
  $res = $conn->prepare("
  SELECT 
    mi.item_id,
    mi.product_id,
    mi.col_index,
    mi.grams,
    p.name,
    p.unit,
    p.category_id
  FROM menu_items mi
  JOIN products p ON p.product_id = mi.product_id
  WHERE mi.menu_id = ?
  ORDER BY 
    p.category_id ASC,
    p.name ASC,
    mi.col_index ASC
");

$res->bind_param("i",$menu_id);
$res->execute();
$rawItems = $res->get_result()->fetch_all(MYSQLI_ASSOC);
}
$items = [];

foreach($rawItems as $r){
  $pid = $r['product_id'];

  if(!isset($items[$pid])){
    $items[$pid] = [
      'name' => $r['name'],
      'unit' => $r['unit'],
      'rows' => []
    ];
  }

  // кладём граммы в нужную колонку
  $items[$pid]['rows'][$r['col_index']] = [
    'grams' => $r['grams'],
    'item_id' => $r['item_id']
  ];
}


/* ===== все продукты ===== */
$products = $conn->query("
  SELECT product_id, name, unit
  FROM products
  WHERE active = 1
  ORDER BY name
");
$allProducts = $products->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-content p-3">

<h2>Редактирование меню</h2>

<form method="get" class="mb-3">
  <label class="form-label">Выбери день меню</label>
  <select name="menu_id" class="form-select" onchange="this.form.submit()">
    <option value="">— выбрать —</option>
    <?php while($d = $days->fetch_assoc()): ?>
      <option value="<?=$d['menu_id']?>" <?=$menu_id==$d['menu_id']?'selected':''?>>
        <?=h($d['menu_date'])?> — <?=h($d['day_name'])?>
      </option>
    <?php endwhile; ?>
  </select>
</form>

<?php if($menu_id): ?>

<form method="post" action="menu_save.php">

<input type="hidden" name="menu_id" value="<?=$menu_id?>">

<!-- ===== Заголовки блюд ===== -->
<div class="card mb-3">
  <div class="card-header">Приёмы пищи</div>
  <div class="card-body row g-2">
    <?php foreach($meals as $m): ?>
      <div class="col-md-4">
        <label class="form-label">Колонка <?=$m['col_index']?></label>
        <input type="text"
          class="form-control"
          name="meals[<?=$m['meal_id']?>]"
          value="<?=h($m['meal_name'])?>">
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ===== Таблица меню ===== -->
<div class="card">
  <div class="card-header">Продукты</div>
  <div class="card-body p-0">

<table class="table table-bordered align-middle mb-0">
<thead>
<tr>
  <th>Продукт</th>
  <?php foreach($meals as $m): ?>
    <th style="writing-mode:vertical-rl;transform:rotate(180deg);text-align:center;white-space:nowrap"><?=h($m['meal_name'])?></th>
  <?php endforeach; ?>
  <th></th>
</tr>
</thead>
<tbody>

<?php foreach($items as $pid => $row): ?>
<tr>
  <td><?=h($row['name'])?> (<?=h($row['unit'])?>)</td>

  <?php foreach($meals as $m): 
    $col = $m['col_index'];
    $cell = $row['rows'][$col] ?? null;
  ?>
    <td class="text-center">

      <?php if($cell): ?>
        <input type="number" step="0.001"
          name="items[<?=$cell['item_id']?>]"
          value="<?=h($cell['grams'])?>"
          class="form-control form-control-sm text-end">
      <?php else: ?>
        —
      <?php endif; ?>

    </td>
  <?php endforeach; ?>

  <td class="text-center">
    <?php foreach($row['rows'] as $c): ?>
      <a href="menu_item_delete.php?id=<?=$c['item_id']?>"
         onclick="return confirm('Удалить запись?')">🗑</a>
    <?php endforeach; ?>
  </td>
</tr>
<?php endforeach; ?>

</tbody>

</table>

</div>
</div>

<!-- ===== Добавить продукт ===== -->
<div class="card mt-3">
  <div class="card-header">Добавить продукт</div>
  <div class="card-body row g-2">

    <div class="col-md-6">
      <select name="new_product_id" class="form-select">
        <option value="">— продукт —</option>
        <?php foreach($allProducts as $p): ?>
          <option value="<?=$p['product_id']?>">
            <?=h($p['name'])?> (<?=$p['unit']?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
      <select name="new_col_index" class="form-select">
        <?php foreach($meals as $m): ?>
          <option value="<?=$m['col_index']?>"><?=$m['meal_name']?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
      <input type="number" step="0.001" name="new_grams"
        class="form-control" placeholder="гр.">
    </div>

  </div>
</div>

<div class="mt-3 d-flex gap-2">
  <button type="button" class="btn btn-primary" onclick="saveMenu()">💾 Сохранить</button>

  <button type="button" class="btn btn-success" onclick="exportExcel()">
  📊 Export Excel
</button>

</div>


</form>
<div class="card mt-4">
  <div class="card-header">Импорт из Excel</div>
  <div class="card-body">
    <form method="post" action="menu_import_xlsx.php" enctype="multipart/form-data">
      <input type="hidden" name="menu_id" value="<?=$menu_id?>">
      <input type="file" name="xlsx"
             accept=".xlsx"
             class="form-control mb-2" required>
      <button class="btn btn-warning">⬆ Импортировать</button>
    </form>
  </div>
</div>

<?php endif; ?>

</div>
<script>
function saveMenu(){
  const data = {
    meals: {},
    items: {}
  };

  document.querySelectorAll('input[name^="meals"]').forEach(i=>{
    const id = i.name.match(/\[(\d+)\]/)[1];
    data.meals[id] = i.value;
  });

  document.querySelectorAll('input[name^="items"]').forEach(i=>{
    const id = i.name.match(/\[(\d+)\]/)[1];
    data.items[id] = i.value;
  });

  fetch('/asset/api/menu_update.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(data)
  })
  .then(r=>r.json())
  .then(j=>{
    alert(j.message);
  });
}
function exportExcel(){
  const table = document.querySelector('#print-area table.tbl');
  if(!table){
    alert('Таблица не найдена');
    return;
  }

  // собираем селекты
  const meta = {
    pos1: document.getElementById('pos1')?.value || '',
    rank1: document.getElementById('rank1')?.value || '',
    person1: document.getElementById('person1')?.value || '',
    pos2: document.getElementById('pos2')?.value || '',
    rank2: document.getElementById('rank2')?.value || '',
    person2: document.getElementById('person2')?.value || '',
    week: document.querySelector('input[name="week"]')?.value || ''
  };

  // парсим таблицу
  const rows = [];
  table.querySelectorAll('tr').forEach(tr=>{
    const row = [];
    tr.querySelectorAll('th,td').forEach(td=>{
      row.push(td.innerText.trim());
    });
    rows.push(row);
  });

  // формируем payload
  const payload = {
    meta,
    table: rows
  };

  // отправляем в export_print_excel.php
  fetch('export_print_excel.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  })
  .then(res => res.blob())
  .then(blob=>{
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'repartizare.xlsx';
    a.click();
  })
  .catch(err=>{
    console.error(err);
    alert('Ошибка экспорта');
  });
}
</script>


<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>
