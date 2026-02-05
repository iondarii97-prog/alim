<?php
/**
 * admin_catalog.php
 * Управление справочниками БД alim:
 * calorie_coefficients, categories, categories_ds,
 * employees, product_post_garda_norms, suppliers, warehouses
 */

session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ===== CSRF (PHP 7.3) =====
if (!isset($_SESSION['csrf']) || $_SESSION['csrf'] === '') {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ===== DB =====
$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ===== LOAD DATA =====
$calorie = $conn->query("SELECT * FROM calorie_coefficients ORDER BY coeff_id DESC");
$categories = $conn->query("SELECT * FROM categories ORDER BY category_id DESC");
$categories_ds = $conn->query("SELECT * FROM categories_ds ORDER BY category_ds_id DESC");
$employees = $conn->query("SELECT * FROM employees ORDER BY employee_id DESC");

$post_garda = $conn->query("
  SELECT p.product_id, pr.name, p.grams_per_person
  FROM product_post_garda_norms p
  JOIN products pr ON pr.product_id = p.product_id
  ORDER BY pr.name
");

$products_no_pg = $conn->query("
  SELECT p.product_id, p.name
  FROM products p
  LEFT JOIN product_post_garda_norms pg ON pg.product_id = p.product_id
  WHERE pg.product_id IS NULL
  ORDER BY p.name
");

$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY supplier_id DESC");
$consumers = $conn->query("SELECT * FROM consumers ORDER BY consumer_id DESC");
$warehouses = $conn->query("SELECT * FROM warehouses ORDER BY warehouse_id DESC");
?>
<?php
$page_title = 'Управление справочниками';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

<style>
.is-valid{ border-color:#198754!important }
.is-invalid{ border-color:#dc3545!important }

.admin-tabs .nav-link{
  border-radius:10px;
  font-weight:500;
  color:#374151;
}
.admin-tabs .nav-link.active{
  background:#2563eb;
  color:#fff;
}

.admin-table thead th{
  background:#f8fafc;
  position:sticky;
  top:0;
}
.admin-table td{ vertical-align:middle }

.post-garda-card{
  background:#f8fafc;
  border-radius:14px;
}
.post-garda-card .form-control,
.post-garda-card .form-select{
  border-radius:10px;
}
</style>
<div class="page-content">
  <div class="container-fluid">
<div class="card shadow-sm">
<div class="card-body">

<h3 class="mb-3">Управление справочниками</h3>

<ul class="nav nav-pills gap-2 mb-3 admin-tabs">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t_cal">Калории</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t_cat">Категории</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t_ds">DS категории</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t_emp">Сотрудники</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t_pg">Post Garda</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t_sup">Поставщики</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t_cons">Потребитель</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t_wh">Склады</button></li>
</ul>

<div class="tab-content">

<!-- ================= CALORIE ================= -->
<div class="tab-pane fade show active" id="t_cal">
  <div class="row g-2 mb-2">
    <div class="col"><input id="cal_title" class="form-control" placeholder="Название"></div>
    <div class="col"><input id="cal_kpg" class="form-control" placeholder="kcal / gram"></div>
    <div class="col-2">
      <button class="btn btn-primary w-100"
        onclick="addRow('calorie_coefficients',{title:cal_title.value,kcal_per_gram:cal_kpg.value})">
        ➕ Добавить
      </button>
    </div>
  </div>

  <table class="table table-hover table-sm admin-table">
  <?php while($r=$calorie->fetch_assoc()): ?>
    <tr data-table="calorie_coefficients" data-id="<?=$r['coeff_id']?>">
      <td><?=$r['coeff_id']?></td>
      <td><input class="form-control ajax" data-field="title" value="<?=h($r['title'])?>"></td>
      <td><input class="form-control ajax" data-field="kcal_per_gram" value="<?=$r['kcal_per_gram']?>"></td>
      <td><button class="btn btn-outline-danger btn-sm" onclick="delRow(this)">✕</button></td>
    </tr>
  <?php endwhile; ?>
  </table>
</div>

<!-- ================= CATEGORIES ================= -->
<div class="tab-pane fade" id="t_cat">
  <div class="row g-2 mb-2">
    <div class="col"><input id="cat_name" class="form-control" placeholder="Название"></div>
    <div class="col-2">
      <button class="btn btn-primary w-100"
        onclick="addRow('categories',{name:cat_name.value})">
        ➕ Добавить
      </button>
    </div>
  </div>

  <table class="table table-hover table-sm admin-table">
  <?php while($r=$categories->fetch_assoc()): ?>
    <tr data-table="categories" data-id="<?=$r['category_id']?>">
      <td><?=$r['category_id']?></td>
      <td><input class="form-control ajax" data-field="name" value="<?=h($r['name'])?>"></td>
      <td><button class="btn btn-outline-danger btn-sm" onclick="delRow(this)">✕</button></td>
    </tr>
  <?php endwhile; ?>
  </table>
</div>

<!-- ================= CATEGORIES DS ================= -->
<div class="tab-pane fade" id="t_ds">
  <div class="row g-2 mb-2">
    <div class="col"><input id="ds_name" class="form-control" placeholder="Название"></div>
    <div class="col">
      <select id="ds_active" class="form-select">
        <option value="1">Активна</option>
        <option value="0">Неактивна</option>
      </select>
    </div>
    <div class="col-2">
      <button class="btn btn-primary w-100"
        onclick="addRow('categories_ds',{name:ds_name.value,active:ds_active.value})">
        ➕ Добавить
      </button>
    </div>
  </div>

  <table class="table table-hover table-sm admin-table">
  <?php while($r=$categories_ds->fetch_assoc()): ?>
    <tr data-table="categories_ds" data-id="<?=$r['category_ds_id']?>">
      <td><?=$r['category_ds_id']?></td>
      <td><input class="form-control ajax" data-field="name" value="<?=h($r['name'])?>"></td>
      <td>
        <select class="form-select ajax" data-field="active">
          <option value="1" <?=$r['active']?'selected':''?>>Да</option>
          <option value="0" <?=!$r['active']?'selected':''?>>Нет</option>
        </select>
      </td>
      <td><button class="btn btn-outline-danger btn-sm" onclick="delRow(this)">✕</button></td>
    </tr>
  <?php endwhile; ?>
  </table>
</div>

<!-- ================= EMPLOYEES ================= -->
<div class="tab-pane fade" id="t_emp">
  <div class="row g-2 mb-2">
    <div class="col"><input id="emp_name" class="form-control" placeholder="ФИО"></div>
    <div class="col">
      <select id="emp_active" class="form-select">
        <option value="1">Активен</option>
        <option value="0">Неактивен</option>
      </select>
    </div>
    <div class="col-2">
      <button class="btn btn-primary w-100"
        onclick="addRow('employees',{full_name:emp_name.value,active:emp_active.value})">
        ➕ Добавить
      </button>
    </div>
  </div>

  <table class="table table-hover table-sm admin-table">
  <?php while($r=$employees->fetch_assoc()): ?>
    <tr data-table="employees" data-id="<?=$r['employee_id']?>">
      <td><?=$r['employee_id']?></td>
      <td><input class="form-control ajax" data-field="full_name" value="<?=h($r['full_name'])?>"></td>
      <td>
        <select class="form-select ajax" data-field="active">
          <option value="1" <?=$r['active']?'selected':''?>>Да</option>
          <option value="0" <?=!$r['active']?'selected':''?>>Нет</option>
        </select>
      </td>
      <td><button class="btn btn-outline-danger btn-sm" onclick="delRow(this)">✕</button></td>
    </tr>
  <?php endwhile; ?>
  </table>
</div>

<!-- ================= POST GARDA ================= -->
<div class="tab-pane fade" id="t_pg">

  <div class="card mb-3 border-0 shadow-sm post-garda-card">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-6">
          <label class="form-label">Товар</label>
          <select id="pg_product" class="form-select">
            <option value="">— выбрать товар —</option>
            <?php while($p=$products_no_pg->fetch_assoc()): ?>
              <option value="<?=$p['product_id']?>"><?=h($p['name'])?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Грамм / человек</label>
          <input id="pg_grams" class="form-control" placeholder="например: 180">
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary w-100"
            onclick="addRow('product_post_garda_norms',{product_id:pg_product.value,grams_per_person:pg_grams.value})">
            ➕ Добавить норму
          </button>
        </div>
      </div>
    </div>
  </div>

  <table class="table table-hover mb-0">
  <?php while($r=$post_garda->fetch_assoc()): ?>
    <tr data-table="product_post_garda_norms"
        data-id="<?=$r['product_id']?>"
        data-product-id="<?=$r['product_id']?>"
        data-product-name="<?=h($r['name'])?>">
      <td><input class="form-control ajax" data-field="name" value="<?=h($r['name'])?>" readonly></td>
      <td><input class="form-control ajax" data-field="grams_per_person" value="<?=$r['grams_per_person']?>"></td>
      <td><button class="btn btn-outline-danger btn-sm" onclick="delPostGarda(this)">✕</button></td>
    </tr>
  <?php endwhile; ?>
  </table>

</div>

<!-- ================= SUPPLIERS ================= -->
<div class="tab-pane fade" id="t_sup">
  <div class="row g-2 mb-2">
    <div class="col"><input id="sup_name" class="form-control" placeholder="Название"></div>
    <div class="col">
      <select id="sup_active" class="form-select">
        <option value="1">Активен</option>
        <option value="0">Неактивен</option>
      </select>
    </div>
    <div class="col-2">
      <button class="btn btn-primary w-100"
        onclick="addRow('suppliers',{name:sup_name.value,active:sup_active.value})">
        ➕ Добавить
      </button>
    </div>
  </div>

  <table class="table table-hover table-sm admin-table">
  <?php while($r=$suppliers->fetch_assoc()): ?>
    <tr data-table="suppliers" data-id="<?=$r['supplier_id']?>">
      <td><?=$r['supplier_id']?></td>
      <td><input class="form-control ajax" data-field="name" value="<?=h($r['name'])?>"></td>
      <td>
        <select class="form-select ajax" data-field="active">
          <option value="1" <?=$r['active']?'selected':''?>>Да</option>
          <option value="0" <?=!$r['active']?'selected':''?>>Нет</option>
        </select>
      </td>
      <td><button class="btn btn-outline-danger btn-sm" onclick="delRow(this)">✕</button></td>
    </tr>
  <?php endwhile; ?>
  </table>
</div>
<!-- ================= consumers ================= -->
<div class="tab-pane fade" id="t_cons">
  <div class="row g-2 mb-2">
    <div class="col"><input id="cons_name" class="form-control" placeholder="Название"></div>
    <div class="col">
      <select id="cons_active" class="form-select">
        <option value="1">Активен</option>
        <option value="0">Неактивен</option>
      </select>
    </div>
    <div class="col-2">
      <button class="btn btn-primary w-100"
  onclick="addRow('consumers',{name:cons_name.value,active:cons_active.value})">
        ➕ Добавить
      </button>
    </div>
  </div>

  <table class="table table-hover table-sm admin-table">
  <?php while($r=$consumers->fetch_assoc()): ?>
    <tr data-table="consumers" data-id="<?=$r['consumer_id']?>">
      <td><?=$r['consumer_id']?></td>
      <td><input class="form-control ajax" data-field="name" value="<?=h($r['name'])?>"></td>
      <td>
        <select class="form-select ajax" data-field="active">
          <option value="1" <?=$r['active']?'selected':''?>>Да</option>
          <option value="0" <?=!$r['active']?'selected':''?>>Нет</option>
        </select>
      </td>
      <td><button class="btn btn-outline-danger btn-sm" onclick="delRow(this)">✕</button></td>
    </tr>
  <?php endwhile; ?>
  </table>
</div>

<!-- ================= WAREHOUSES ================= -->
<div class="tab-pane fade" id="t_wh">
  <div class="row g-2 mb-2">
    <div class="col"><input id="wh_name" class="form-control" placeholder="Название"></div>
    <div class="col">
      <select id="wh_active" class="form-select">
        <option value="1">Активен</option>
        <option value="0">Неактивен</option>
      </select>
    </div>
    <div class="col-2">
      <button class="btn btn-primary w-100"
        onclick="addRow('warehouses',{name:wh_name.value,active:wh_active.value})">
        ➕ Добавить
      </button>
    </div>
  </div>

  <table class="table table-hover table-sm admin-table">
  <?php while($r=$warehouses->fetch_assoc()): ?>
    <tr data-table="warehouses" data-id="<?=$r['warehouse_id']?>">
      <td><?=$r['warehouse_id']?></td>
      <td><input class="form-control ajax" data-field="name" value="<?=h($r['name'])?>"></td>
      <td>
        <select class="form-select ajax" data-field="active">
          <option value="1" <?=$r['active']?'selected':''?>>Да</option>
          <option value="0" <?=!$r['active']?'selected':''?>>Нет</option>
        </select>
      </td>
      <td><button class="btn btn-outline-danger btn-sm" onclick="delRow(this)">✕</button></td>
    </tr>
  <?php endwhile; ?>
  </table>
</div>

</div>
</div>
</div>
</div>
<script>
const CSRF = '<?=h($_SESSION['csrf'])?>';

async function api(data){
  data.csrf = CSRF;
  const r = await fetch('admin_save.php',{
    method:'POST',
    body:new URLSearchParams(data)
  });
  return r.json();
}

document.addEventListener('change', async e=>{
  if(!e.target.classList.contains('ajax')) return;
  const tr = e.target.closest('tr');
  const res = await api({
    action:'update',
    table:tr.dataset.table,
    id:tr.dataset.id,
    field:e.target.dataset.field,
    value:e.target.value
  });
  e.target.classList.toggle('is-valid',res.ok);
  e.target.classList.toggle('is-invalid',!res.ok);
  if(!res.ok) alert(res.error);
});

async function addRow(table, fields){
  const res = await api(Object.assign({action:'insert',table},fields));
  if(!res.ok){
    alert(res.error);
    return;
  }

  if(table === 'product_post_garda_norms'){
    const select = document.getElementById('pg_product');
    [...select.options].forEach(o=>{
      if(o.value == fields.product_id) o.remove();
    });
    document.getElementById('pg_product').value='';
    document.getElementById('pg_grams').value='';
    return;
  }

  location.reload();
}

async function delRow(btn){
  if(!confirm('Удалить запись?')) return;
  const tr = btn.closest('tr');
  const res = await api({action:'delete',table:tr.dataset.table,id:tr.dataset.id});
  if(res.ok) tr.remove(); else alert(res.error);
}

async function delPostGarda(btn){
  if(!confirm('Удалить норму Post Garda?')) return;

  const tr = btn.closest('tr');
  const pid = tr.dataset.productId;
  const pname = tr.dataset.productName;

  const res = await api({action:'delete',table:'product_post_garda_norms',id:pid});
  if(!res.ok){ alert(res.error); return; }

  tr.remove();
  const select = document.getElementById('pg_product');
  const opt = document.createElement('option');
  opt.value = pid;
  opt.textContent = pname;
  select.appendChild(opt);
}
</script>

<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>

