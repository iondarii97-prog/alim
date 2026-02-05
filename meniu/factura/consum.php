<?php
$page_title = 'Consum / FIFO';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

  <style>
:root{
  --grid:#000;
  --head:#bfbfbf;
  --subhead:#d9d9d9;
  --border:#d1d5e1;
  --bg:#f3f4f6;
  --white:#fff;
  --blue:#2563eb;
  --blue2:#1d4ed8;
}
*{box-sizing:border-box}

/* FORM */
.form-top{
  display:grid;
  grid-template-columns:240px minmax(300px,1.2fr) minmax(300px,1.2fr) minmax(260px,1fr);
  gap:12px;margin-bottom:12px
}
.card{background:#fff;border:1px solid var(--border);border-radius:10px;padding:10px}
label{font-size:11px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:2px}
input,select{width:100%;height:34px;border-radius:6px;border:1px solid #cbd5e1;padding:6px 8px}
.grid-row{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}

/* TABLES */
table{width:100%;border-collapse:collapse;background:#fff}
td,th{border:1px solid var(--grid);padding:4px 6px;font-size:12px}
.center{text-align:center}
.bold{font-weight:700}
.head{background:var(--head)}
.subhead{background:var(--subhead)}

.doc-header td,.doc-header th{border:2px solid #000;font-size:13px}
.doc-header .big{font-size:15px;font-weight:700}
.doc-header .total-row{background:#cfcfcf}

/* BUTTONS */
.actions{margin-top:14px;display:flex;justify-content:flex-end;gap:10px}
.btn{
  height:40px;padding:0 16px;border-radius:8px;
  border:1px solid var(--blue2);background:var(--blue);
  color:#fff;font-weight:800;cursor:pointer
}
.btn:disabled{background:#999}

/* PRINT */
@media print {

  /* === СТРАНИЦА === */
  @page {
    size: A4 portrait;   /* ВЕРТИКАЛЬНО */
    margin: 8mm;
  }

  /* === СКРЫТЬ ВСЁ, ЧТО НЕ ДОЛЖНО ПЕЧАТАТЬСЯ === */
  nav,
  .topbar,
  .form-top,
  .actions,
  button,
  .btn {
    display: none !important;
  }

  body,
  .wrap {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  /* === ТАБЛИЦЫ === */
  table {
    width: 100% !important;
    border-collapse: collapse !important;
    page-break-before: auto !important;
    page-break-after: auto !important;
    page-break-inside: auto !important;
    margin: 0 !important;
  }

  /* ❗ НЕ РАЗРЫВАТЬ между таблицами */
  table + table {
    page-break-before: avoid !important;
  }

  /* === ПОВТОР ЗАГОЛОВКА ТАБЛИЦЫ === */
  thead {
    display: table-header-group;
  }

  /* === СТРОКИ === */
  tr {
    page-break-inside: avoid !important;
  }

  /* === ШРИФТ (под вертикаль) === */
  th, td {
    font-size: 10px !important;
    padding: 3px 4px !important;
  }

  /* === ЦВЕТА (обязательно для серых шапок) === */
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}

</style>
<div class="page-content">
  <div class="wrap">

<!-- FORM -->
<div class="form-top">

  <div class="card">
    <label>Data meniului</label>
    <input id="date" type="date" value="<?=date('Y-m-d')?>">
  </div>

  <div class="card">
    <div class="grid-row">
      <div><label>Dejun</label><input id="people_morning" type="number" value="0"></div>
      <div><label>Prînz</label><input id="people_lunch" type="number" value="0"></div>
      <div><label>Cină</label><input id="people_evening" type="number" value="0"></div>
    </div>
  </div>

  <div class="card">
    <div class="grid-row">
      <div><label>Gardă</label><input id="people_garda" type="number" value="0"></div>
      <div><label>Post</label><input id="people_post" type="number" value="0"></div>
      <div>
        <label>Zile</label>
        <select id="days_multiplier">
          <option value="0">0 zile</option>
          <option value="1" selected>1 zi</option>
          <option value="2">2 zile</option>
          <option value="3">3 zile</option>
          <option value="5">5 zile</option>
        </select>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="grid-row" style="grid-template-columns:1fr 1fr">
      <div><label>Bon</label><input id="doc_no"></div>
      <div><label>Extras</label><input id="doc_extra"></div>
    </div>

    <div class="grid-row" style="margin-top:8px;grid-template-columns:1fr 1fr">
      <div>
        <label>Cine a predat</label>
        <select id="issued_by"><option value="">— selectează —</option></select>
      </div>
      <div>
        <label>Cine a primit</label>
        <select id="received_by"><option value="">— selectează —</option></select>
      </div>
    </div>
  </div>

</div>

<!-- HEADER -->
<!-- ===== BON HEADER BLOCK ===== -->
<table style="width:100%;border-collapse:collapse;margin-bottom:10px">
  <tr>
    <td colspan="6" style="border:1px solid #000;padding:6px;font-weight:bold">
      Valabil pînă : <span id="valid_until">—</span>
    </td>
    <td colspan="3" style="border:1px solid #000;padding:6px;font-weight:bold;text-align:right">
      extras din ord. nr. <span id="h-extra-top">—</span>
    </td>
  </tr>

  <tr>
    <td colspan="6" style="border:1px solid #000;padding:6px;font-weight:bold;text-align:center">
      BON DE LIVRARE Nr <span id="h-bon-top">—</span>
    </td>
    <td colspan="3" style="border:1px solid #000"></td>
  </tr>

  <tr style="background:#cfcfcf;font-weight:bold;text-align:center">
    <td style="border:1px solid #000"></td>
    <td style="border:1px solid #000">DEJUN</td>
    <td style="border:1px solid #000">PRÎNZ</td>
    <td style="border:1px solid #000">CINĂ</td>
    <td style="border:1px solid #000">GARDĂ</td>
    <td style="border:1px solid #000">POST</td>
    <td style="border:1px solid #000"></td>
    <td style="border:1px solid #000"></td>
    <td style="border:1px solid #000"></td>
  </tr>

  <tr style="text-align:center">
    <td style="border:1px solid #000;font-weight:bold">Bt. / FMP</td>
    <td style="border:1px solid #000" id="bt-dejun">0</td>
    <td style="border:1px solid #000" id="bt-prinz">0</td>
    <td style="border:1px solid #000" id="bt-cina">0</td>
    <td style="border:1px solid #000" id="bt-garda">0</td>
    <td style="border:1px solid #000" id="bt-post">0</td>
    <td style="border:1px solid #000">-</td>
    <td style="border:1px solid #000">-</td>
    <td style="border:1px solid #000">-</td>
  </tr>

  <tr style="background:#e0e0e0;font-weight:bold;text-align:center">
    <td style="border:1px solid #000">TOTAL</td>
    <td style="border:1px solid #000" id="tot-dejun">0</td>
    <td style="border:1px solid #000" id="tot-prinz">0</td>
    <td style="border:1px solid #000" id="tot-cina">0</td>
    <td style="border:1px solid #000" id="tot-garda">0</td>
    <td style="border:1px solid #000" id="tot-post">0</td>
    <td style="border:1px solid #000">-</td>
    <td style="border:1px solid #000">-</td>
    <td style="border:1px solid #000">-</td>
  </tr>
</table>


<!-- MENU TABLE (ONLY PREVIEW) -->
<table>
<thead>
<tr class="head"><th colspan="9" class="center">MENIU (PREVIEW)</th></tr>
<tr>
  <th rowspan="2">Nr</th><th rowspan="2">Produs</th><th rowspan="2">u/m</th>
  <th rowspan="2">gr</th><th colspan="3">Livrare</th>
  <th rowspan="2">G/P</th><th rowspan="2">Total</th>
</tr>
<tr>
  <th class="subhead">dejun</th><th class="subhead">prînz</th><th class="subhead">cina</th>
</tr>
</thead>
<tbody id="tbody">
<tr><td colspan="9" class="center bold">Se încarcă…</td></tr>
</tbody>
</table>

<!-- SIGNATURES -->
<table style="margin-top:25px;width:100%;border:none">
<tr>
<td style="border:none;width:50%">
<strong>Responsabil (a predat):</strong><br><br>
<span id="sig-issued">____________________________</span>
</td>
<td style="border:none;width:50%">
<strong>Am primit:</strong><br><br>
<span id="sig-received">____________________________</span>
</td>
</tr>
</table>

<!-- BUTTONS -->
<div class="actions">
  <button class="btn" id="btn-generate">⚙️ Generează FIFO</button>
  <button class="btn" onclick="window.print()">🖨️ Tipărește</button>
</div>

</div>
</div>
<script>
const el = id => document.getElementById(id);
let fifoLocked = false;

/* ================= HELPERS ================= */
function roDate(v){
  if(!v) return '—';
  const [y,m,d] = v.split('-');
  return d+'.'+m+'.'+y;
}

/* ================= API ================= */
async function apiPost(url, data){
  const r = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(data)
  });
  return await r.json();
}

/* ================= MENU PREVIEW ================= */
async function loadMenu(){
  const r = await apiPost('api_menu.php',{
    date: el('date').value,
    people_morning: +el('people_morning').value || 0,
    people_lunch:   +el('people_lunch').value   || 0,
    people_evening: +el('people_evening').value || 0,
    people_garda:   +el('people_garda').value   || 0,
    people_post:    +el('people_post').value    || 0,
    days_multiplier:+el('days_multiplier').value|| 1
  });

  const tb = el('tbody');
  tb.innerHTML = '';

  if(!r.ok || !r.data?.groups?.[0]?.items){
    tb.innerHTML = '<tr><td colspan="9" class="center">Nu sunt date</td></tr>';
    return;
  }

  let i = 0;

  r.data.groups[0].items.forEach(p=>{
    if(+p.total <= 0) return;
    i++;

    tb.insertAdjacentHTML('beforeend',`
      <tr>
        <td class="center">${i}</td>
        <td>${p.name}</td>
        <td class="center">${p.um}</td>
        <td class="center bold">${p.portion}</td>
        <td class="center">${p.dejun}</td>
        <td class="center">${p.prinz}</td>
        <td class="center">${p.cina}</td>
        <td class="center">${p.gp}</td>
        <td class="center bold">${p.total}</td>
      </tr>
    `);
  });
}

/* ================= EMPLOYEES ================= */
async function loadEmployees(){
  const r = await fetch('api_employees.php');
  const j = await r.json();

  if(!j.data) return;

  j.data.forEach(e=>{
    ['issued_by','received_by'].forEach(id=>{
      const o = document.createElement('option');
      o.value = e.employee_id;
      o.textContent = e.full_name;
      el(id).appendChild(o);
    });
  });
}

/* ================= COLLECT TABLE ================= */
function collectTableItems(){
  const rows = document.querySelectorAll('#tbody tr');
  const items = [];

  rows.forEach(r=>{
    const td = r.querySelectorAll('td');
    if(td.length < 9) return;

    items.push({
      name:    td[1].innerText.trim(),
      um:      td[2].innerText.trim(),
      portion: td[3].innerText.trim(),
      dejun:   td[4].innerText.trim(),
      prinz:   td[5].innerText.trim(),
      cina:    td[6].innerText.trim(),
      gp:      td[7].innerText.trim(),
      total:   td[8].innerText.trim()
    });
  });

  return items;
}

/* ================= SYNC HEADER ================= */
function syncBonHeader(){
  const dej = +el('people_morning').value || 0;
  const pr  = +el('people_lunch').value   || 0;
  const ci  = +el('people_evening').value || 0;
  const ga  = +el('people_garda').value   || 0;
  const po  = +el('people_post').value    || 0;

  el('bt-dejun').textContent = dej;
  el('bt-prinz').textContent = pr;
  el('bt-cina').textContent  = ci;
  el('bt-garda').textContent = ga;
  el('bt-post').textContent  = po;

  el('tot-dejun').textContent = dej;
  el('tot-prinz').textContent = pr;
  el('tot-cina').textContent  = ci;
  el('tot-garda').textContent = ga;
  el('tot-post').textContent  = po;

  el('h-bon-top').textContent   = el('doc_no').value || '—';
  el('h-extra-top').textContent = el('doc_extra').value || '—';

  if(el('date').value){
    const d = new Date(el('date').value);
    d.setDate(d.getDate() );
    el('valid_until').textContent = d.toLocaleDateString('ro-RO');
  }
}

/* ================= SIGNATURES ================= */
function syncSignatures(){
  const issuedSel = el('issued_by');
  const receivedSel = el('received_by');

  el('sig-issued').textContent =
    issuedSel.value
      ? issuedSel.selectedOptions[0].text
      : '____________________________';

  el('sig-received').textContent =
    receivedSel.value
      ? receivedSel.selectedOptions[0].text
      : '____________________________';
}

/* ================= FIFO GENERATE ================= */
el('btn-generate').onclick = async ()=>{

  if(fifoLocked){
    alert('FIFO deja executat');
    return;
  }

  if(!el('doc_no').value.trim()){
    alert('Bon lipsă');
    return;
  }

  const payload = {
    date: el('date').value,

    people_morning: +el('people_morning').value || 0,
    people_lunch:   +el('people_lunch').value   || 0,
    people_evening: +el('people_evening').value || 0,
    people_garda:   +el('people_garda').value   || 0,
    people_post:    +el('people_post').value    || 0,
    days_multiplier:+el('days_multiplier').value|| 1,

    doc_no: el('doc_no').value,
    doc_extra: el('doc_extra').value,

    issued_by: el('issued_by').value || null,
    received_by: el('received_by').value || null,

    items: collectTableItems()
  };

  const res = await apiPost('api_generate.php', payload);

  if(!res.ok){
    if(res.shortages){
      let msg = res.message + "\n\n";
      res.shortages.forEach(s=>{
        msg += `• ${s.name}: lipsă ${s.missing} ${s.um}\n`;
      });
      alert(msg);
    } else {
      alert(res.message || 'Eroare server');
    }
    return;
  }

  fifoLocked = true;
  el('btn-generate').disabled = true;

  alert('FIFO executat cu succes!\nDocument № ' + res.document_id);

  // остаёмся на этой же странице
};


/* ================= INIT ================= */
[
 'date','people_morning','people_lunch','people_evening',
 'people_garda','people_post','days_multiplier',
 'doc_no','doc_extra','issued_by','received_by'
].forEach(id =>
  el(id).addEventListener('change', () => {
    syncBonHeader();
    loadMenu();
  })
);

el('issued_by').addEventListener('change', syncSignatures);
el('received_by').addEventListener('change', syncSignatures);

document.addEventListener('DOMContentLoaded', ()=>{
  syncSignatures();
  syncBonHeader();
  loadMenu();
  loadEmployees();
});
</script>

<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>
