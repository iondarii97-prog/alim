<?php
$page_title = 'Rezultat FIFO';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

<div class="page-content">
  <div class="wrap">
    <h2>Rezultatul documentului</h2>
    <div class="card">
  <b>Data:</b> <span id="h-date">—</span><br>
  <b>Bon:</b> <span id="h-bon">—</span><br>
  <b>Extras:</b> <span id="h-extra">—</span><br>
  <b>A predat:</b> <span id="h-issued">—</span><br>
  <b>A primit:</b> <span id="h-received">—</span>
</div>

    <table border="1" width="100%" cellpadding="6">
      <thead>
        <tr>
          <th>Produs</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody id="tb"></tbody>
    </table>

    <h3>Total general: <span id="tg">0</span></h3>
  </div>
</div>

<script>
const raw = sessionStorage.getItem('fifo_preview');

const tb = document.getElementById('tb');
const tgEl = document.getElementById('tg');

if(!raw){
  tb.innerHTML = '<tr><td colspan="2">Nu sunt date</td></tr>';
}else{
  try{
    const data = JSON.parse(raw);

    const doc  = data.document;
    const form = data.form;

    /* ===== ПОКАЗЫВАЕМ ШАПКУ ===== */
    document.getElementById('h-date').textContent = form.date;
    document.getElementById('h-bon').textContent  = form.doc_no;
    document.getElementById('h-extra').textContent= form.doc_extra;
    document.getElementById('h-issued').textContent = form.issued_by_text;
    document.getElementById('h-received').textContent = form.received_by_text;

    /* ===== ТАБЛИЦА ===== */
    let tg = 0;

    if(!Array.isArray(doc.items) || !doc.items.length){
      tb.innerHTML = '<tr><td colspan="2">Nu sunt produse</td></tr>';
    }else{
      doc.items.forEach(i=>{
        const total = parseFloat(i.total) || 0;
        tg += total;

        tb.insertAdjacentHTML('beforeend',`
          <tr>
            <td>${i.name}</td>
            <td><b>${total.toFixed(3)}</b></td>
          </tr>
        `);
      });
    }

    tgEl.textContent = tg.toFixed(3);

  }catch(e){
    tb.innerHTML = '<tr><td colspan="2">Eroare date</td></tr>';
  }
}
</script>


<?php include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php'; ?>
