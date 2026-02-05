<?php
$page_title = 'Facturi venit';
include $_SERVER['DOCUMENT_ROOT'].'/includ/header.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/navbar.php';
?>

<style>
  
.dataTables_length,
.dataTables_paginate,
.dataTables_info{ display:none!important; }

.dataTables_filter{ float:right; margin-bottom:12px; }
.dataTables_filter input{
  width:260px;
  padding:8px 12px;
  border-radius:10px;
  border:1px solid #d1d5db;
}
.dataTables_filter input:focus{
  border-color:#2563eb;
  box-shadow:0 0 0 3px rgba(37,99,235,.15);
  outline:none;
}

table.dataTable thead th::before,
table.dataTable thead th::after{ display:none!important; }

table.dataTable tbody tr:nth-child(odd){ background:#efefef; }

.id-link,.factura-link{
  color:#0d6efd;
  font-weight:600;
  text-decoration:none;
}

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
}

.btn-delete{ padding:4px 8px; }
</style>
<?php
$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

function fmtDate($d){
  if(!$d) return '';
  $t=strtotime($d);
  return $t?date('d.m.Y',$t):$d;
}

$sql="
SELECT
 d.document_id,
 d.doc_number,
 d.doc_date,
 s.name supplier,
 COALESCE(SUM(ii.qty*pp.price),0) total_sum
FROM incoming_documents d
LEFT JOIN suppliers s ON s.supplier_id=d.supplier_id
LEFT JOIN incoming_items ii ON ii.document_id=d.document_id
LEFT JOIN product_prices pp ON pp.price_id=ii.price_id
GROUP BY d.document_id
ORDER BY d.doc_date DESC, d.document_id DESC
";
$res=$conn->query($sql);
?>

<div class="page-content">
<div class="container-fluid py-4">
<div class="card">
<div class="card-body">

<table id="facturiTable" class="table table-hover w-100">
<thead>
<tr>
<th>ID</th>
<th>Factura</th>
<th>Data</th>
<th>Suma</th>
<th>Livrator</th>
</tr>
</thead>
<tbody>

<?php while($r=$res->fetch_assoc()): 
$id=(int)$r['document_id'];
$doc=$r['doc_number'];
?>
<tr>
<td>
<a class="id-link" href="/produse/facturi/venit/factura/?id=<?=$id?>">
<?=$id?>
</a>
</td>

<td>
<a class="factura-link" href="/produse/facturi/venit/factura/?id=<?=$id?>">
<?=htmlspecialchars($doc?("Factura ".$doc):("Factura #".$id))?>
</a>
</td>

<td><?=fmtDate($r['doc_date'])?></td>
<td><?=number_format($r['total_sum'],2,'.',' ')?></td>

<td>
<div class="livrator">
<span class="livrator-dot"></span>
<?=htmlspecialchars($r['supplier']??'-')?>
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


<!-- ================= CORE ================= -->

<!-- jQuery (PRIMUL) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- ================= PAGE SCRIPT ================= -->
<script>
$(function(){

  const table = $('#facturiTable').DataTable({
    paging:false,
    searching:true,
    ordering:true,
    language:{
      search:"",
      searchPlaceholder:"Поиск factura / livrator..."
    }
  });

  const input = $('#facturiTable_filter input');
  input.off('keyup.DT input.DT');

  let t = null;
  input.on('input', function(){
    clearTimeout(t);
    const v = this.value;
    t = setTimeout(() => table.search(v).draw(), 300);
  });

});
</script>


<?php
include $_SERVER['DOCUMENT_ROOT'].'/includ/scrypt.php';
include $_SERVER['DOCUMENT_ROOT'].'/includ/footer.php';
?>
