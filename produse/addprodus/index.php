<?php
$page_title = 'Добавление прихода';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>


<div class="page-content">
<div class="container-fluid py-4">
  <div class="card">
    <div class="card-body">

      <div class="text-center-title">Добавление прихода</div>

      <?php
        $conn = new mysqli("localhost", "root", "", "alim");
        $conn->set_charset("utf8mb4");
        $suppliers = $conn->query("
          SELECT supplier_id, name
          FROM suppliers
          WHERE active=1
          ORDER BY name
        ");
      ?>

      <form id="incomingForm" enctype="multipart/form-data">

        <!-- TOP -->
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="form-label">Tip document</label>
            <select class="form-select" name="doc_type" required>
              <option value="">Выберите тип документа</option>
              <option value="Factura">Factura</option>
              <option value="Bon">Bon</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Поставщик</label>
            <select class="form-select" name="supplier_id" required>
              <option value="">Выберите поставщика</option>
              <?php while($s = $suppliers->fetch_assoc()): ?>
                <option value="<?= (int)$s['supplier_id'] ?>">
                  <?= htmlspecialchars($s['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Номер документа</label>
            <input type="text" class="form-control" name="doc_number" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Дата документа</label>
            <input type="date" class="form-control" name="doc_date"
                   value="<?= date('Y-m-d') ?>" required>
          </div>

          <div class="col-md-8">
            <label class="form-label">Загрузить документ</label>
            <input type="file" class="form-control"
                   name="doc_file" accept=".jpg,.jpeg,.png,.pdf">
          </div>
        </div>

        <!-- PRODUCTS -->
        <h6 class="mb-2">Товары</h6>
        <div class="table-responsive">
          <table class="table table-bordered align-middle" id="productsTable">
            <thead class="table-light">
              <tr class="text-center">
                <th>Название товара</th>
                <th>Цена / unitate</th>
                <th>Количество</th>
                <th>Unit</th>
                <th>Сумма</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="position:relative">
                  <input type="text" class="form-control product-name" autocomplete="off">
                </td>
                <td><input type="number" step="0.01" min="0" class="form-control price"></td>
                <td><input type="number" step="0.001" min="0" class="form-control qty"></td>
                <td>
                  <input type="text"
                         class="form-control unit unit-input"
                         readonly
                         data-from-db="0">
                </td>
                <td><input type="text" class="form-control total total-input" readonly></td>
                <td class="text-center">
                  <button type="button" class="btn btn-danger btn-sm remove-row">✖</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- TOTAL -->
        <div class="row mt-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Общая сумма</label>
            <input type="text" class="form-control total-input" id="grandTotal" readonly>
          </div>
        </div>

        <!-- ACTIONS -->
        <div class="mt-4">
          <button type="button" class="btn btn-primary me-2" id="addRow">Добавить товар</button>
          <button type="button" class="btn btn-success" id="saveBtn">Сохранить</button>
        </div>

        <div class="mt-3" id="msg"></div>
      </form>

    </div>
  </div>
</div>
</div>

<script>
const tableBody = document.querySelector('#productsTable tbody');
const grandTotalInput = document.getElementById('grandTotal');

/* ===== AUTOCOMPLETE PORTAL ===== */
const portal = document.createElement('div');
portal.className = 'autocomplete-portal';
document.body.appendChild(portal);

/* ===== helpers ===== */
const toNum = v => {
  const n = parseFloat(v);
  return Number.isFinite(n) ? n : 0;
};

function recalc(){
  let total = 0;
  tableBody.querySelectorAll('tr').forEach(tr => {
    const price = toNum(tr.querySelector('.price').value);
    const qty   = toNum(tr.querySelector('.qty').value);
    const sum   = price * qty;
    tr.querySelector('.total').value = sum ? sum.toFixed(2) : '';
    total += sum;
  });
  grandTotalInput.value = total ? total.toFixed(2) : '';
}

/* ===== search ===== */
async function searchProducts(q){
  const res = await fetch('search_products.php?q=' + encodeURIComponent(q));
  return await res.json();
}

/* ===== add row ===== */
document.getElementById('addRow').onclick = () => {
  tableBody.insertAdjacentHTML('beforeend', `
    <tr>
      <td>
        <input type="text" class="form-control product-name" autocomplete="off">
      </td>
      <td><input type="number" step="0.01" class="form-control price"></td>
      <td><input type="number" step="0.001" class="form-control qty"></td>
      <td>
        <input type="text" class="form-control unit unit-input" readonly data-from-db="0">
      </td>
      <td><input type="text" class="form-control total total-input" readonly></td>
      <td class="text-center">
        <button type="button" class="btn btn-danger btn-sm remove-row">✖</button>
      </td>
    </tr>
  `);
};

/* ===== table events ===== */
tableBody.addEventListener('input', async e => {

  if(e.target.classList.contains('price') || e.target.classList.contains('qty')){
    recalc();
    return;
  }

  if(!e.target.classList.contains('product-name')) return;

  const input = e.target;
  const tr    = input.closest('tr');
  const unit  = tr.querySelector('.unit');
  const q     = input.value.trim();

  unit.value = '';
  unit.readOnly = false;
  unit.dataset.fromDb = '0';

  portal.innerHTML = '';
  portal.style.display = 'none';

  if(q.length < 2) return;

  const items = await searchProducts(q);
  if(!items.length) return;

  const r = input.getBoundingClientRect();

  portal.style.left  = r.left + 'px';
  portal.style.top   = (r.bottom + window.scrollY) + 'px';
  portal.style.width = r.width + 'px';

  items.forEach(p => {
    const div = document.createElement('div');
    div.className = 'autocomplete-item';
    div.textContent = `${p.name} (${p.unit})`;
    div.onclick = () => {
      input.value = p.name;
      unit.value = p.unit;
      unit.readOnly = true;
      unit.dataset.fromDb = '1';
      portal.style.display = 'none';
    };
    portal.appendChild(div);
  });

  portal.style.display = 'block';
});

/* ===== hide autocomplete ===== */
document.addEventListener('click', e => {
  if(!portal.contains(e.target) && !e.target.classList.contains('product-name')){
    portal.style.display = 'none';
  }
});

/* ===== remove row ===== */
tableBody.addEventListener('click', e => {
  if(e.target.classList.contains('remove-row')){
    const tr = e.target.closest('tr');
    if(tableBody.children.length === 1){
      tr.querySelectorAll('input').forEach(i => i.value = '');
    } else tr.remove();
    recalc();
  }
});

/* ===== save ===== */
document.getElementById('saveBtn').onclick = async () => {

  const items = [];
  const invalid = [];

  tableBody.querySelectorAll('tr').forEach(tr => {
    const name  = tr.querySelector('.product-name').value.trim();
    const price = toNum(tr.querySelector('.price').value);
    const qty   = toNum(tr.querySelector('.qty').value);
    const unit  = tr.querySelector('.unit').value.trim();

    if(name && !unit) invalid.push(name);

    if(name && price > 0 && qty > 0 && unit){
      items.push({ name, price, qty, unit });
    }
  });

  if(invalid.length){
    document.getElementById('msg').innerHTML =
      `<div class="alert alert-danger">
        Укажите unit для новых товаров: ${invalid.join(', ')}
      </div>`;
    return;
  }

  if(!items.length){
    document.getElementById('msg').innerHTML =
      `<div class="alert alert-warning">Добавь товары</div>`;
    return;
  }

  const fd = new FormData(document.getElementById('incomingForm'));
  fd.append('items_json', JSON.stringify(items));

  const res = await fetch('save_incoming.php', { method:'POST', body: fd });
  document.getElementById('msg').innerHTML = await res.text();
};

recalc();
</script>


<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>

