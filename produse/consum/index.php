<?php
$page_title = 'Списание товара (FIFO)';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>
<?php
        $conn = new mysqli("localhost", "root", "", "alim");
        $conn->set_charset("utf8mb4");
        $consumers = $conn->query("
  SELECT consumer_id, name
  FROM consumers
  WHERE active = 1
  ORDER BY name
");
      ?>
<div class="page-content fifo-page">
  <div class="container-fluid py-4">
    <div class="card shadow-sm">
      <div class="card-body">

        <div class="text-center-title">Списание товара (FIFO)</div>

        <form id="outgoingForm" enctype="multipart/form-data">

          <!-- TOP FORM -->
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label">Tip document</label>
              <select class="form-select" name="doc_type" id="doc_type" required>
                <option value="Consum">Consum</option>
              </select>
            </div>

            <div class="col-md-4">
             <label class="form-label">Потребитель</label>
              <select class="form-select" name="consumer_id" required>
                <option value="">Выберите потребителя</option>
                <?php while($s = $consumers->fetch_assoc()): ?>
                  <option value="<?= (int)$s['consumer_id'] ?>">
                    <?= htmlspecialchars($s['name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>



            <div class="col-md-4">
              <label class="form-label">Номер документа</label>
              <input type="text" class="form-control" name="doc_number" id="doc_number" placeholder="CON-001" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Дата документа</label>
              <input type="date" class="form-control" name="doc_date" id="doc_date" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-md-12">
              <label class="form-label">Примечание</label>
              <input type="text" class="form-control" name="note" id="note" placeholder="Напр. списание на кухню">
            </div>
          </div>

          <!-- PRODUCTS TABLE -->
          <h6 class="mb-2">Товары</h6>
          <div class="table-responsive">
            <table class="table table-bordered align-middle" id="productsTable">
              <thead>
                <tr class="text-center">
                  <th>Товар</th>
                  <th style="width:160px;">Доступно</th>
                  <th style="width:180px;">Количество</th>
                  <th style="width:120px;">Действие</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <select class="form-select product-select" style="width:100%"></select>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-secondary avail">—</span>
                  </td>
                  <td>
                    <input type="number" step="0.001" min="0" class="form-control qty" placeholder="0.000">
                    <div class="invalid-feedback">Слишком много (больше чем доступно)</div>
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-row">Удалить</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- TOTAL QTY -->
          <div class="row mt-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Всего количество:</label>
              <input type="text" class="form-control total-input" id="grandQty" readonly>
            </div>
          </div>

          <!-- ACTIONS -->
          <div class="mt-4">
            <button type="button" class="btn btn-primary me-2" id="addRow">Добавить товар</button>
            <button type="button" class="btn btn-success" id="saveBtn">Сохранить (FIFO)</button>
          </div>

          <div class="mt-3" id="msg"></div>
        </form>

      </div>
    </div>
  </div>
</div>

<!-- jQuery + Select2 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(function(){

  const tableBody = document.querySelector('#productsTable tbody');
  const grandQtyInput = document.getElementById('grandQty');

  function toNumber(v){ const n=parseFloat(v); return Number.isFinite(n)?n:0; }
  function formatQty(n){ return (Math.round(n*1000)/1000).toFixed(3); }

  function initSelect2(selectEl){
    $(selectEl).select2({
      placeholder: 'Выберите товар...',
      allowClear: true,
      ajax: {
        url: 'api_products_search.php',
        dataType: 'json',
        delay: 200,
        data: params => ({ q: params.term || '' }),
        processResults: data => data
      },
      width: '100%'
    });
  }

  async function loadAvailForRow(tr){
    const select = tr.querySelector('.product-select');
    const pid = parseInt(select.value || '0', 10);
    const badge = tr.querySelector('.avail');

    if (!pid){
      badge.textContent = '—';
      badge.className = 'badge bg-secondary avail';
      return 0;
    }

    const res = await fetch(`api_product_stock.php?product_id=${pid}`);
    const data = await res.json();
    const qty = toNumber(data?.qty);

    badge.textContent = formatQty(qty) + ' kg';
    badge.className = 'badge bg-info text-dark avail';
    return qty;
  }

  function recalcGrandQty(){
    let sum = 0;
    tableBody.querySelectorAll('tr').forEach(tr => {
      sum += toNumber(tr.querySelector('.qty')?.value);
    });
    grandQtyInput.value = sum > 0 ? formatQty(sum) : '';
  }

  function validateRowQty(tr){
    const qtyInput = tr.querySelector('.qty');
    const need = toNumber(qtyInput.value);

    const availText = tr.querySelector('.avail')?.textContent || '0';
    const avail = toNumber(availText.replace('kg',''));

    if (avail > 0 && need > avail + 1e-9){
      qtyInput.classList.add('is-invalid');
      return false;
    }
    qtyInput.classList.remove('is-invalid');
    return true;
  }

  function addRow(){
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><select class="form-select product-select" style="width:100%"></select></td>
      <td class="text-center"><span class="badge bg-secondary avail">—</span></td>
      <td>
        <input type="number" step="0.001" min="0" class="form-control qty" placeholder="0.000">
        <div class="invalid-feedback">Слишком много (больше чем доступно)</div>
      </td>
      <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove-row">Удалить</button></td>
    `;
    tableBody.appendChild(tr);
    initSelect2(tr.querySelector('.product-select'));
  }

  // init first row
  initSelect2(document.querySelector('.product-select'));


  // change product -> load avail
  $(document).on('change', '.product-select', function(){
    const tr = this.closest('tr');
    loadAvailForRow(tr).then(() => validateRowQty(tr));
  });

  // qty input -> validate + recalc
  tableBody.addEventListener('input', (e) => {
    if(e.target.classList.contains('qty')){
      const tr = e.target.closest('tr');
      validateRowQty(tr);
      recalcGrandQty();
    }
  });

  // remove row
  tableBody.addEventListener('click', (e) => {
    if(e.target.classList.contains('remove-row')){
      const rows = tableBody.querySelectorAll('tr').length;
      const tr = e.target.closest('tr');

      if(rows === 1){
        $(tr.querySelector('.product-select')).val(null).trigger('change');
        tr.querySelector('.qty').value = '';
        tr.querySelector('.avail').textContent = '—';
        tr.querySelector('.avail').className = 'badge bg-secondary avail';
        tr.querySelector('.qty').classList.remove('is-invalid');
      } else {
        tr.remove();
      }
      recalcGrandQty();
    }
  });

  $('#addRow').on('click', addRow);

  $('#saveBtn').on('click', async () => {

    const msg = $('#msg');
    msg.html('');

    const items = [];
    let hasInvalid = false;

    $('#productsTable tbody tr').each(function(){
      const tr = this;
      const pid = parseInt($(tr).find('.product-select').val() || '0', 10);
      const qty = toNumber($(tr).find('.qty').val());

      if(pid > 0 && qty > 0){
        if(!validateRowQty(tr)) hasInvalid = true;
        items.push({ product_id: pid, qty });
      }
    });

    if (!items.length){
      msg.html(`<div class="alert alert-warning mb-0">Добавь хотя бы один товар.</div>`);
      return;
    }
    if (hasInvalid){
      msg.html(`<div class="alert alert-danger mb-0">Есть строки где количество больше доступного.</div>`);
      return;
    }

    const form = document.getElementById('outgoingForm');
    const fd = new FormData(form);
    fd.append('items_json', JSON.stringify(items));

    try {
      const res = await fetch('save_outgoing.php', { method:'POST', body: fd });
      const text = await res.text();
      msg.html(`<div class="alert alert-${res.ok ? 'success':'danger'} mb-0">${text}</div>`);

      if(res.ok){
        form.reset();

        tableBody.innerHTML = `
          <tr>
            <td><select class="form-select product-select" style="width:100%"></select></td>
            <td class="text-center"><span class="badge bg-secondary avail">—</span></td>
            <td>
              <input type="number" step="0.001" min="0" class="form-control qty" placeholder="0.000">
              <div class="invalid-feedback">Слишком много (больше чем доступно)</div>
            </td>
            <td class="text-center">
              <button type="button" class="btn btn-danger btn-sm remove-row">Удалить</button>
            </td>
          </tr>
        `;
        initSelect2(document.querySelector('.product-select'));
        $('#doc_date').val(new Date().toISOString().slice(0,10));
        recalcGrandQty();
      }
    } catch (e) {
      msg.html(`<div class="alert alert-danger mb-0">Ошибка: ${e.message}</div>`);
    }
  });

  recalcGrandQty();

});
</script>


<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>

