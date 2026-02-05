<?php
$page_title = 'Списания (Consum)';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

<!-- DataTables -->
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<style>
/* ❌ скрываем лишнее */
.dataTables_length,
.dataTables_paginate,
.dataTables_info{
  display:none !important;
}

/* поиск справа */
.dataTables_filter{
  float:right;
  margin-bottom:12px;
}
.dataTables_filter input{
  width:260px;
  padding:8px 12px;
  border-radius:10px;
  border:1px solid #d1d5db;
  outline:none;
}
.dataTables_filter input:focus{
  border-color:#2563eb;
  box-shadow:0 0 0 3px rgba(37,99,235,.15);
}

/* убрать стрелки сортировки */
table.dataTable thead th::before,
table.dataTable thead th::after{
  display:none !important;
}
table.dataTable thead th.sorting,
table.dataTable thead th.sorting_asc,
table.dataTable thead th.sorting_desc{
  background-image:none !important;
}

/* полосы */
table.dataTable tbody tr:nth-child(odd){
  background:#efefef;
}

/* ссылки */
.id-link,
.factura-link{
  color:#0d6efd;
  font-weight:600;
  text-decoration:none;
}

/* Destinație */
.livrator{
  display:flex;
  align-items:center;
  gap:8px;
}
.livrator-dot{
  width:12px;
  height:12px;
  border-radius:50%;
  background:#f6d38b;
  display:inline-block;
  flex:0 0 12px;
}
</style>

<?php
$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($dbDate){
  if(!$dbDate) return '';
  $t = strtotime($dbDate);
  return $t ? date('d.m.Y', $t) : h($dbDate);
}

/*
 * OUTGOING / CONSUM
 * supplier_id отсутствует — используем note как destinatie
 */
$sql = "
  SELECT
    d.document_id,
    d.doc_type,
    d.doc_number,
    d.doc_date,
    d.note,
    COALESCE(t.total_sum, 0) AS total_sum
  FROM outgoing_documents d
  LEFT JOIN (
    SELECT
      oi.document_id,
      SUM(oi.qty * pp.price) AS total_sum
    FROM outgoing_items oi
    JOIN product_prices pp ON pp.price_id = oi.price_id
    GROUP BY oi.document_id
  ) t ON t.document_id = d.document_id
  ORDER BY d.doc_date DESC, d.document_id DESC
";

$res = $conn->query($sql);
if(!$res){
  die("SQL ERROR: " . $conn->error);
}
?>

<div class="page-content">
  <div class="container-fluid py-4">
    <div class="card">
      <div class="card-body">

        <table id="facturiTable" class="table table-hover w-100">
          <thead>
            <tr>
              <th>ID</th>
              <th>Număr document</th>
              <th>Data</th>
              <th>Suma</th>
              <th>Destinație</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $res->fetch_assoc()): ?>
              <?php
                $id   = (int)$row['document_id'];
                $name = trim(($row['doc_type'] ?? 'Consum') . ' ' . ($row['doc_number'] ?? ''));
                if($name === '' || $name === 'Consum') $name = 'Consum #' . $id;

                $link = "/produse/facturi/consum/factura/?id=" . $id;
                $note = $row['note'] ?: '-';
              ?>
              <tr>
                <td><a href="<?= h($link) ?>" class="id-link"><?= $id ?></a></td>
                <td><a href="<?= h($link) ?>" class="factura-link"><?= h($name) ?></a></td>
                <td><?= fmtDate($row['doc_date']) ?></td>
                <td><?= number_format((float)$row['total_sum'], 2, '.', ' ') ?></td>
                <td>
                  <div class="livrator">
                    <span class="livrator-dot"></span>
                    <?= h($note) ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>

      </div>
    </div>
  </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
$(function(){
  const table = $('#facturiTable').DataTable({
    paging:false,
    lengthChange:false,
    info:false,
    searching:true,
    ordering:true,
    language:{
      search:"",
      searchPlaceholder:"Поиск document / destinatie..."
    }
  });

  const input = $('#facturiTable_filter input');
  input.off('keyup.DT input.DT');

  let t=null;
  input.on('input',function(){
    clearTimeout(t);
    const v=this.value;
    t=setTimeout(()=>table.search(v).draw(),300);
  });
});
</script>

<?php include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php'; ?>
