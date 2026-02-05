<?php
// ajax/admin_save.php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
header('Content-Type: application/json');

// ===== DB =====
$conn = new mysqli("localhost","root","","alim");
$conn->set_charset("utf8mb4");

// ===== HELPERS =====
function jerr($m){ echo json_encode(['ok'=>false,'error'=>$m]); exit; }
function jok($d=[]){ echo json_encode(['ok'=>true]+$d); exit; }

// ===== CSRF =====
if(!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')){
  jerr('CSRF');
}

// ===== SCHEMA (WHITELIST) =====
$SCHEMA = [
  'calorie_coefficients'=>[
    'pk'=>'coeff_id',
    'fields'=>['title'=>'s','kcal_per_gram'=>'d'],
    'insert'=>['title','kcal_per_gram']
  ],
  'categories'=>[
    'pk'=>'category_id',
    'fields'=>['name'=>'s'],
    'insert'=>['name']
  ],
  'categories_ds'=>[
    'pk'=>'category_ds_id',
    'fields'=>['name'=>'s','active'=>'i'],
    'insert'=>['name','active']
  ],
  'employees'=>[
    'pk'=>'employee_id',
    'fields'=>['full_name'=>'s','active'=>'i'],
    'insert'=>['full_name','active']
  ],
  'product_post_garda_norms'=>[
    'pk'=>'product_id',
    'fields'=>['grams_per_person'=>'d'],
    'insert'=>['product_id','grams_per_person']
  ],
  'suppliers'=>[
    'pk'=>'supplier_id',
    'fields'=>['name'=>'s','active'=>'i'],
    'insert'=>['name','active']
  ],
  'consumers'=>[
    'pk'=>'consumer_id',
    'fields'=>['name'=>'s','active'=>'i'],
    'insert'=>['name','active']
  ],
  'warehouses'=>[
    'pk'=>'warehouse_id',
    'fields'=>['name'=>'s','active'=>'i'],
    'insert'=>['name','active']
  ],
];

$action = $_POST['action'] ?? '';
$table  = $_POST['table']  ?? '';

if(!isset($SCHEMA[$table])) jerr('Table not allowed');
$cfg = $SCHEMA[$table];

// ===== UPDATE =====
if($action==='update'){
  $id=(int)($_POST['id']??0);
  $field=$_POST['field']??'';
  $value=$_POST['value']??'';
  if($id<=0 || !isset($cfg['fields'][$field])) jerr('Bad update');

  $type=$cfg['fields'][$field];
  if($type==='i') $value=(int)$value;
  if($type==='d'){
    $value=str_replace(',','.',trim($value));
    if(!preg_match('/^\d+(\.\d+)?$/',$value)) jerr('Invalid number');
    $value=(float)$value;
  }
  if($type==='s'){ $value=trim($value); if($value==='') jerr('Empty'); }

  $sql="UPDATE `$table` SET `$field`=? WHERE `{$cfg['pk']}`=?";
  $st=$conn->prepare($sql);
  $st->bind_param($type.'i',$value,$id);
  $st->execute();
  jok();
}

// ===== INSERT =====
if($action==='insert'){
  $cols=[];$qs=[];$types='';$vals=[];
  foreach($cfg['insert'] as $f){
    if(!isset($_POST[$f])) jerr("Missing $f");
    $v=$_POST[$f];
    $t=$cfg['fields'][$f] ?? 's';
    if($t==='i'){ $v=(int)$v; $types.='i'; }
    elseif($t==='d'){
      $v=str_replace(',','.',trim($v));
      if(!preg_match('/^\d+(\.\d+)?$/',$v)) jerr('Invalid number');
      $v=(float)$v; $types.='d';
    } else { $v=trim($v); if($v==='') jerr('Empty'); $types.='s'; }
    $cols[]="`$f`"; $qs[]='?'; $vals[]=$v;
  }
  $sql="INSERT INTO `$table` (".implode(',',$cols).") VALUES(".implode(',',$qs).")";
  $st=$conn->prepare($sql);
  $st->bind_param($types,...$vals);
  $st->execute();
  jok(['id'=>$conn->insert_id]);
}

// ===== DELETE =====
if($action==='delete'){
  $id=(int)($_POST['id']??0);
  if($id<=0) jerr('Bad delete');
  $sql="DELETE FROM `$table` WHERE `{$cfg['pk']}`=?";
  $st=$conn->prepare($sql);
  $st->bind_param('i',$id);
  $st->execute();
  jok();
}

jerr('Unknown action');
