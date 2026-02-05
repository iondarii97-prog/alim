<?php
$page_title = 'Добавление прихода';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>
<style>
.card{ border-radius:14px; }
.table input{ width:100%; }
.total-input{ background:#eef1f4; }
.text-center-title{
  text-align:center;
  font-size:22px;
  font-weight:500;
  margin-bottom:20px;
}
</style>

<div class="page-content">

<div class="container-fluid py-4">
  <div class="card">
    <div class="card-body">

      <div class="text-center-title">Добавление прихода</div>

      <!-- PRODUCTS TABLE -->
      <h6 class="mb-2">Товары</h6>
      <div class="table-responsive">
        <table class="table table-bordered align-middle" id="productsTable">
          <thead class="table-light">
<tr class="text-center">
  <th style="min-width:260px;">Название товара</th>
  <th style="min-width:100px;">Ед.</th>
  <th style="min-width:140px;">Цена</th>
  <th style="min-width:140px;">Количество</th>
  <th style="min-width:140px;">Сумма</th>
  <th style="min-width:120px;">Действие</th>
</tr>
</thead>
          <tbody>
            <tr>
  <td>
    <input type="text" class="form-control product-name">
  </td>

  <td>
    <select class="form-select unit">
      <option value="kg">kg</option>
      <option value="l">l</option>
      <option value="buc">buc</option>
    </select>
  </td>

  <td>
    <input type="number" step="0.01" min="0" class="form-control price" placeholder="0.00">
  </td>

  <td>
    <input type="number" step="0.001" min="0" class="form-control qty" placeholder="0.000">
  </td>

  <td>
    <input type="text" class="form-control total total-input" readonly>
  </td>

  <td class="text-center">
    <button type="button" class="btn btn-danger btn-sm remove-row">Удалить</button>
  </td>
</tr>
          </tbody>
        </table>
      </div>

      <!-- TOTAL -->
      <div class="row mt-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Общая сумма:</label>
          <input type="text" class="form-control total-input" id="grandTotal" readonly>
        </div>
      </div>

      <!-- ACTIONS -->
      <div class="mt-4 d-flex gap-2">
        <button type="button" class="btn btn-primary" id="addRow">Добавить товар</button>
        <button type="button" class="btn btn-success" id="saveBtn">Сохранить</button>
      </div>

      <div class="mt-3" id="msg"></div>

    </div>
  </div>
</div>

</div>

<!-- Bootstrap JS -->
<?php include '../../includ/scrypt.php' ?>
<script>
  const tbody = document.querySelector("#productsTable tbody");
  const grandTotalEl = document.querySelector("#grandTotal");
  const msgEl = document.querySelector("#msg");

  function toNumber(v) {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
  }

  function formatMoney(n) {
    return (Math.round(n * 100) / 100).toFixed(2);
  }

  function recalcRow(tr) {
    const price = toNumber(tr.querySelector(".price").value);
    const qty   = toNumber(tr.querySelector(".qty").value);
    const total = price * qty;
    tr.querySelector(".total").value = total > 0 ? formatMoney(total) : "";
  }

  function recalcGrandTotal() {
    let sum = 0;
    tbody.querySelectorAll("tr").forEach(tr => {
      const price = toNumber(tr.querySelector(".price").value);
      const qty   = toNumber(tr.querySelector(".qty").value);
      sum += price * qty;
    });
    grandTotalEl.value = sum > 0 ? formatMoney(sum) : "";
  }

  function recalcAll() {
    tbody.querySelectorAll("tr").forEach(recalcRow);
    recalcGrandTotal();
  }

  // события ввода (делегирование)
  tbody.addEventListener("input", (e) => {
    if (e.target.classList.contains("price") || e.target.classList.contains("qty")) {
      const tr = e.target.closest("tr");
      recalcRow(tr);
      recalcGrandTotal();
    }
  });

  // удалить строку
  tbody.addEventListener("click", (e) => {
    if (e.target.classList.contains("remove-row")) {
      const tr = e.target.closest("tr");
      const rowsCount = tbody.querySelectorAll("tr").length;

      if (rowsCount === 1) {
        // если одна строка — просто очистим
        tr.querySelector(".product-name").value = "";
        tr.querySelector(".price").value = "";
        tr.querySelector(".qty").value = "";
        tr.querySelector(".total").value = "";
      } else {
        tr.remove();
      }
      recalcGrandTotal();
    }
  });

  // добавить строку
  document.querySelector("#addRow").addEventListener("click", () => {
  const tr = document.createElement("tr");
  tr.innerHTML = `
    <td><input type="text" class="form-control product-name"></td>

    <td>
      <select class="form-select unit">
        <option value="kg">kg</option>
        <option value="l">l</option>
        <option value="buc">buc</option>
      </select>
    </td>

    <td><input type="number" step="0.01" min="0" class="form-control price" placeholder="0.00"></td>
    <td><input type="number" step="0.001" min="0" class="form-control qty" placeholder="0.000"></td>
    <td><input type="text" class="form-control total total-input" readonly></td>

    <td class="text-center">
      <button type="button" class="btn btn-danger btn-sm remove-row">Удалить</button>
    </td>
  `;
  tbody.appendChild(tr);
});

  // сохранить (отправка на сервер)
  document.querySelector("#saveBtn").addEventListener("click", async () => {
    msgEl.innerHTML = "";

    // собрать строки
    const items = [];
    tbody.querySelectorAll("tr").forEach(tr => {
  const name  = tr.querySelector(".product-name").value.trim();
  const unit  = tr.querySelector(".unit").value;
  const price = toNumber(tr.querySelector(".price").value);
  const qty   = toNumber(tr.querySelector(".qty").value);

  if (name && unit && price > 0 && qty > 0) {
    items.push({ name, unit, price, qty });
  }
});


    if (items.length === 0) {
      msgEl.innerHTML = `<div class="alert alert-warning mb-0">Добавь хотя бы один товар (название + цена + количество).</div>`;
      return;
    }

    const payload = {
      items,
      grandTotal: toNumber(grandTotalEl.value)
    };

    try {
      const res = await fetch("save_incoming.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });

      const text = await res.text();
      msgEl.innerHTML = `<div class="alert alert-${res.ok ? "success" : "danger"} mb-0">${text}</div>`;
    } catch (err) {
      msgEl.innerHTML = `<div class="alert alert-danger mb-0">Ошибка сети: ${err.message}</div>`;
    }
  });

  // первичный пересчёт
  recalcAll();
</script>
<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>
