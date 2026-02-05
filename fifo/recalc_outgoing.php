<?php
// FIFO recalculation for ONE outgoing document

function fifo_recalc_outgoing(mysqli $conn, int $doc_id){

  $conn->begin_transaction();

  try{

    /* =====================================================
       1. ОТКАТ СТАРОГО FIFO
    ===================================================== */
    $res = $conn->prepare("
      SELECT incoming_item_id, qty
      FROM outgoing_items
      WHERE document_id = ?
        AND incoming_item_id IS NOT NULL
    ");
    $res->bind_param("i",$doc_id);
    $res->execute();
    $r = $res->get_result();

    while($row=$r->fetch_assoc()){
      $conn->query("
        UPDATE incoming_items
        SET remaining_qty = remaining_qty + {$row['qty']}
        WHERE item_id = {$row['incoming_item_id']}
      ");
    }

    /* обнуляем связи FIFO */
    $conn->query("
      UPDATE outgoing_items
      SET incoming_item_id = NULL
      WHERE document_id = $doc_id
    ");

    /* =====================================================
       2. НОВОЕ FIFO-СПИСАНИЕ
    ===================================================== */
    $items = $conn->prepare("
      SELECT item_id, product_id, qty
      FROM outgoing_items
      WHERE document_id = ?
      ORDER BY item_id
    ");
    $items->bind_param("i",$doc_id);
    $items->execute();
    $out = $items->get_result();

    while($oi = $out->fetch_assoc()){

      $need = (float)$oi['qty'];
      if($need <= 0) continue;

      // берем приходы FIFO
      $in = $conn->prepare("
        SELECT item_id, remaining_qty
        FROM incoming_items
        WHERE product_id = ?
          AND remaining_qty > 0
        ORDER BY item_id
      ");
      $in->bind_param("i",$oi['product_id']);
      $in->execute();
      $ins = $in->get_result();

      while($need > 0 && $inc = $ins->fetch_assoc()){

        $take = min($need, (float)$inc['remaining_qty']);

        // уменьшаем остаток прихода
        $conn->query("
          UPDATE incoming_items
          SET remaining_qty = remaining_qty - $take
          WHERE item_id = {$inc['item_id']}
        ");

        // фиксируем связь
        $conn->query("
          UPDATE outgoing_items
          SET incoming_item_id = {$inc['item_id']}
          WHERE item_id = {$oi['item_id']}
        ");

        $need -= $take;
      }

      if($need > 0){
        throw new Exception("Недостаточно остатков для продукта ID {$oi['product_id']}");
      }
    }

    /* =====================================================
       3. ПЕРЕСЧЁТ STOCK_BY_PRICE
    ===================================================== */
    $conn->query("TRUNCATE stock_by_price");

    $conn->query("
      INSERT INTO stock_by_price(product_id, price_id, qty)
      SELECT ii.product_id, ii.price_id, SUM(ii.remaining_qty)
      FROM incoming_items ii
      GROUP BY ii.product_id, ii.price_id
    ");

    /* =====================================================
       4. ФЛАГ FIFO
    ===================================================== */
    $conn->query("
      UPDATE outgoing_documents
      SET fifo_done = 1,
          fifo_done_at = NOW()
      WHERE document_id = $doc_id
    ");

    $conn->commit();
    return ['ok'=>true];

  }catch(Throwable $e){
    $conn->rollback();
    return ['ok'=>false,'error'=>$e->getMessage()];
  }
}
