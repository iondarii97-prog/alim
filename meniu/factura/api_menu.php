<?php
require __DIR__.'/db.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

/* ===== READ JSON ===== */
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  echo json_encode(['ok'=>false,'message'=>'Invalid JSON']);
  exit;
}

/* ===== INPUT ===== */
$date  = $data['date'] ?? '';
$pm    = (int)($data['people_morning'] ?? 0);
$pl    = (int)($data['people_lunch'] ?? 0);
$pe    = (int)($data['people_evening'] ?? 0);
$pg    = (int)($data['people_garda'] ?? 0);
$pp    = (int)($data['people_post'] ?? 0);
$days  = (int)($data['days_multiplier'] ?? 1);

function fmt($n, $maxDecimals = 6){
  $s = number_format((float)$n, $maxDecimals, '.', '');
  // убираем лишние нули и точку если она стала последней
  return rtrim(rtrim($s, '0'), '.');
}

/* ===== MENU DAY ===== */
$stmt = $conn->prepare("
  SELECT menu_id, day_name
  FROM menu_days
  WHERE menu_date = ?
  LIMIT 1
");
$stmt->bind_param("s", $date);
$stmt->execute();
$menuDay = $stmt->get_result()->fetch_assoc();

$menu_id = (int)($menuDay['menu_id'] ?? 0);
$dayName = $menuDay['day_name'] ?? '—';

/* ===== PRODUCTS MAP ===== */
$products = [];

/* =========================================================
   1. MENU ITEMS (dejun / prinz / cina)
   ========================================================= */
if ($menu_id > 0) {
  $stmt = $conn->prepare("
    SELECT
      mi.product_id,
      mi.col_index,
      mi.grams,              -- grame per persoană / masă
      p.name,
      p.unit,
      p.category_id
    FROM menu_items mi
    JOIN products p ON p.product_id = mi.product_id
    WHERE mi.menu_id = ?
    ORDER BY p.category_id ASC, p.name ASC
  ");
  $stmt->bind_param("i", $menu_id);
  $stmt->execute();
  $res = $stmt->get_result();

  while ($r = $res->fetch_assoc()) {

    $pid  = (int)$r['product_id'];
    $unit = $r['unit'];

    /* --- grams per meal (1 persoană) --- */
    $gramPerMeal = (float)$r['grams']; // в граммах

    /* --- convert grams → unit for totals --- */
    switch ($unit) {
      case 'kg':
        $portionUnit = $gramPerMeal / 1000; // kg
        break;
      case 'l':
        $portionUnit = $gramPerMeal / 1000; // ml → l
        break;
      case 'buc':
        $portionUnit = $gramPerMeal;        // buc
        break;
      default:
        $portionUnit = $gramPerMeal / 1000;
    }

    if (!isset($products[$pid])) {
      $products[$pid] = [
        'name'    => $r['name'],
        'um'      => $unit,

        // для расчёта gr pe zi
        'gram_per_meal' => $gramPerMeal,   // грамм на 1 приём

        // значения на всё количество людей
        'dejun'   => 0,
        'prinz'   => 0,
        'cina'    => 0,
        'gp'      => 0,

        // итог
        'total'   => 0
      ];
    }

    /* --- распределение по колонкам --- */
    if ($r['col_index'] <= 2) {
      $products[$pid]['dejun'] += $portionUnit * $pm;
    } elseif ($r['col_index'] <= 7) {
      $products[$pid]['prinz'] += $portionUnit * $pl;
    } else {
      $products[$pid]['cina']  += $portionUnit * $pe;
    }
  }
}

/* =========================================================
   2. POST + GARDA NORMS
   ========================================================= */
$res = $conn->query("
  SELECT
    n.product_id,
    n.grams_per_person,
    p.name,
    p.unit,
    p.category_id
  FROM product_post_garda_norms n
  JOIN products p ON p.product_id = n.product_id
  ORDER BY p.category_id ASC, p.name ASC
");

while ($r = $res->fetch_assoc()) {

  $pid  = (int)$r['product_id'];
  $unit = $r['unit'];

  $gramPerDayGP = (float)$r['grams_per_person']; // gr / persoană / zi

  switch ($unit) {
    case 'kg':
      $portionUnit = $gramPerDayGP / 1000;
      break;
    case 'l':
      $portionUnit = $gramPerDayGP / 1000;
      break;
    case 'buc':
      $portionUnit = $gramPerDayGP;
      break;
    default:
      $portionUnit = $gramPerDayGP / 1000;
  }

  if (!isset($products[$pid])) {
    $products[$pid] = [
      'name'    => $r['name'],
      'um'      => $unit,
      'gram_per_meal' => 0, // не участвует в меню
      'dejun'   => 0,
      'prinz'   => 0,
      'cina'    => 0,
      'gp'      => 0,
      'total'   => 0
    ];
  }

  // garda + post (учёт дней)
  $products[$pid]['gp'] +=
    ($pg + $pp * $days) * $portionUnit;
}

/* =========================================================
   3. FINAL FORMAT
   ========================================================= */
$items = [];

foreach ($products as $p) {

  $total =
    $p['dejun'] +
    $p['prinz'] +
    $p['cina'] +
    $p['gp'];

  if ($total <= 0) continue;

  /* ===== gr pe persoană pe zi ===== */
  // считаем сколько приёмов реально используется
  $mealsCount = 0;
  if ($p['dejun'] > 0) $mealsCount++;
  if ($p['prinz'] > 0) $mealsCount++;
  if ($p['cina']  > 0) $mealsCount++;
  if ($p['gp']    > 0) $mealsCount++; // garda/post

  // gr total pe zi / persoană
  $grPerDay = 0;
  if ($p['gram_per_meal'] > 0 && $mealsCount > 0) {
    $grPerDay = ($p['gram_per_meal'] * $mealsCount) / 1000; // → kg
  }

  $items[] = [
  'name'    => $p['name'],
  'um'      => $p['um'],

  'portion' => fmt($grPerDay, 6),

  'dejun'   => fmt($p['dejun'], 4),
  'prinz'   => fmt($p['prinz'], 4),
  'cina'    => fmt($p['cina'], 4),
  'gp'      => fmt($p['gp'], 4),
  'total'   => fmt($total, 6)
];

}

/* ===== RESPONSE ===== */
echo json_encode([
  'ok' => true,
  'data' => [
    'menu_id' => $menu_id,
    'day_name'=> $dayName,
    'groups'  => [
      [
        'warehouse' => '',
        'items' => $items
      ]
    ]
  ]
]);
exit;
