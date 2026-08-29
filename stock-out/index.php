<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$activePage = 'stock-out';
$error = '';

$products = $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $date = $_POST['transaction_date'];
    $note = trim($_POST['note']);
    $productIds = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $prices = $_POST['unit_price'] ?? [];

    $lines = [];
    foreach ($productIds as $i => $pid) {
        if ($pid !== '' && (float) $qtys[$i] > 0) {
            $lines[] = ['product_id' => (int) $pid, 'qty' => (float) $qtys[$i], 'price' => (float) $prices[$i]];
        }
    }

    if (!$lines) {
        $error = __('stockout_err_add_product');
    } else {
        // verify enough stock before committing anything
        foreach ($lines as $line) {
            $stmt = $pdo->prepare('SELECT name, current_stock FROM products WHERE id = ?');
            $stmt->execute([$line['product_id']]);
            $p = $stmt->fetch();
            if ($p && $line['qty'] > $p['current_stock']) {
                $error = __('stockout_err_insufficient_prefix') . " {$p['name']} (" . __('stockout_err_insufficient_have') . " {$p['current_stock']}, " . __('stockout_err_insufficient_requested') . " {$line['qty']}).";
                break;
            }
        }

        if (!$error) {
            $pdo->beginTransaction();
            $reference = 'STO-' . str_pad((string) ($pdo->query('SELECT COUNT(*) FROM stock_transactions')->fetchColumn() + 1), 6, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare('INSERT INTO stock_transactions (reference, type, transaction_date, note, supplier_id, user_id) VALUES (?,?,?,?,NULL,?)');
            $stmt->execute([$reference, 'out', $date, $note, $_SESSION['user_id']]);
            $txId = $pdo->lastInsertId();

            foreach ($lines as $line) {
                $subtotal = $line['qty'] * $line['price'];
                $stmt = $pdo->prepare('INSERT INTO stock_transaction_items (transaction_id, product_id, qty, unit_price, subtotal) VALUES (?,?,?,?,?)');
                $stmt->execute([$txId, $line['product_id'], $line['qty'], $line['price'], $subtotal]);

                $stmt = $pdo->prepare('UPDATE products SET current_stock = current_stock - ? WHERE id = ?');
                $stmt->execute([$line['qty'], $line['product_id']]);
            }
            $pdo->commit();
            set_flash('success', __('stockout_recorded_prefix') . " $reference " . __('stockout_recorded_suffix'));
            header('Location: ' . BASE_URL . '/stock-out/index.php');
            exit;
        }
    }
}

$recent = $pdo->query("SELECT t.id, t.reference, t.transaction_date, COUNT(i.id) items, SUM(i.qty) total_qty, SUM(i.subtotal) total_value
                        FROM stock_transactions t
                        LEFT JOIN stock_transaction_items i ON i.transaction_id = t.id
                        WHERE t.type = 'out'
                        GROUP BY t.id, t.reference, t.transaction_date ORDER BY t.id DESC LIMIT 6")->fetchAll();


require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-1"><?= __('nav_stock_out') ?></h4>
    <p class="text-secondary small mb-0">Record outgoing inventory, sales dispatch, and customer dispatches</p>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <form method="post">
      <?= csrf_field() ?>
      <div class="card p-3 mb-3">
        <div class="bracket-label mb-3"><?= __('common_transaction_details') ?></div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label"><?= __('common_transaction_date') ?></label>
            <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label"><?= __('stockout_note_label') ?></label>
            <input type="text" name="note" class="form-control" placeholder="<?= __('stockout_note_placeholder') ?>">
          </div>
        </div>
      </div>

      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="bracket-label mb-0"><?= __('common_line_items') ?></div>
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()"><?= __('common_add_product') ?></button>
        </div>
        <div class="table-responsive mb-3">
          <table class="table align-middle" id="lineTable">
            <thead class="table-light"><tr><th><?= __('common_product') ?></th><th style="width:110px;"><?= __('common_qty') ?></th><th style="width:140px;"><?= __('stockout_unit_price') ?></th><th style="width:40px;"></th></tr></thead>
            <tbody id="lineBody"></tbody>
          </table>
        </div>
        <button class="btn text-white w-100 py-2" style="background:var(--danger);"><i class="bi bi-upload me-1"></i> <?= __('stockout_submit_button') ?></button>
      </div>
    </form>
  </div>

  <div class="col-lg-4">
    <div class="card p-3">
      <div class="bracket-label mb-3" style="color:var(--danger);"><?= __('stockout_recent_title') ?></div>
      <?php if (!$recent): ?><p class="text-secondary small mb-0"><?= __('common_no_transactions') ?></p><?php endif; ?>
      <?php foreach ($recent as $t): ?>
        <div class="border-bottom pb-2 mb-2">
          <div class="d-flex justify-content-between align-items-center small mb-1">
            <span class="mono fw-bold" style="color:var(--danger);"><?= htmlspecialchars($t['reference']) ?></span>
            <span class="mono text-secondary"><?= $t['transaction_date'] ?></span>
          </div>
          <div class="small mt-1 d-flex justify-content-between">
            <span><?= $t['items'] ?> item(s) · <?= (int)$t['total_qty'] ?> unit(s)</span>
            <strong class="mono text-danger">$<?= number_format($t['total_value'], 2) ?></strong>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
const PRODUCTS = <?= json_encode($products) ?>;
const T_CHOOSE_PRODUCT = <?= json_encode(__('common_choose_product_option')) ?>;
const T_NOW = <?= json_encode(__('common_now_label')) ?>;
const T_PCS = <?= json_encode(__('common_pcs')) ?>;

function productOptions(selected) {
  let html = `<option value="">${T_CHOOSE_PRODUCT}</option>`;
  PRODUCTS.forEach(p => {
    html += `<option value="${p.id}" data-price="${p.sale_price}" ${String(p.id)===String(selected)?'selected':''}>${p.name} · ${p.sku} (${T_NOW}: ${p.current_stock} ${T_PCS})</option>`;
  });
  return html;
}

function addRow(productId = '', qty = 1, price = '') {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="product_id[]" class="form-select form-select-sm" onchange="fillPrice(this)" required>${productOptions(productId)}</select></td>
    <td><input type="number" name="qty[]" class="form-control form-control-sm" value="${qty}" min="1" required></td>
    <td><input type="number" name="unit_price[]" class="form-control form-control-sm" value="${price}" step="0.01" required></td>
    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('tr').remove()">✕</button></td>`;
  document.getElementById('lineBody').appendChild(tr);
}
function fillPrice(sel) {
  const opt = sel.selectedOptions[0];
  const row = sel.closest('tr');
  if (opt && opt.dataset.price) {
    row.querySelector('[name="unit_price[]"]').value = opt.dataset.price;
  }
}
addRow();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
