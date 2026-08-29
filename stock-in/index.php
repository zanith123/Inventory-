<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$activePage = 'stock-in';
$error = '';

$suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();
$products  = $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $supplierId = $_POST['supplier_id'] !== '' ? (int) $_POST['supplier_id'] : null;
    $date = $_POST['transaction_date'];
    $note = trim($_POST['note']);
    $productIds = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $costs = $_POST['unit_cost'] ?? [];

    $lines = [];
    foreach ($productIds as $i => $pid) {
        if ($pid !== '' && (float) $qtys[$i] > 0) {
            $lines[] = ['product_id' => (int) $pid, 'qty' => (float) $qtys[$i], 'cost' => (float) $costs[$i]];
        }
    }

    if (!$lines) {
        $error = __('stockin_err_add_product');
    } else {
        $pdo->beginTransaction();
        $reference = 'STI-' . str_pad((string) ($pdo->query('SELECT COUNT(*) FROM stock_transactions')->fetchColumn() + 1), 6, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare('INSERT INTO stock_transactions (reference, type, transaction_date, note, supplier_id, user_id) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$reference, 'in', $date, $note, $supplierId, $_SESSION['user_id']]);
        $txId = $pdo->lastInsertId();

        foreach ($lines as $line) {
            $subtotal = $line['qty'] * $line['cost'];
            $stmt = $pdo->prepare('INSERT INTO stock_transaction_items (transaction_id, product_id, qty, unit_price, subtotal) VALUES (?,?,?,?,?)');
            $stmt->execute([$txId, $line['product_id'], $line['qty'], $line['cost'], $subtotal]);

            $stmt = $pdo->prepare('UPDATE products SET current_stock = current_stock + ? WHERE id = ?');
            $stmt->execute([$line['qty'], $line['product_id']]);
        }
        $pdo->commit();
        set_flash('success', __('stockin_recorded_prefix') . " $reference " . __('stockin_recorded_suffix'));
        header('Location: ' . BASE_URL . '/stock-in/index.php');
        exit;
    }
}

$recent = $pdo->query("SELECT t.id, t.reference, t.transaction_date, COUNT(i.id) items, SUM(i.qty) total_qty, SUM(i.subtotal) total_value, MAX(s.name) as supplier_name
                        FROM stock_transactions t
                        LEFT JOIN stock_transaction_items i ON i.transaction_id = t.id
                        LEFT JOIN suppliers s ON s.id = t.supplier_id
                        WHERE t.type = 'in'
                        GROUP BY t.id, t.reference, t.transaction_date ORDER BY t.id DESC LIMIT 6")->fetchAll();


require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-1"><?= __('nav_stock_in') ?></h4>
    <p class="text-secondary small mb-0">Record incoming inventory, supplier deliveries, and stock receipts</p>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <form method="post" id="stockInForm">
      <?= csrf_field() ?>
      <div class="card p-3 mb-3">
        <div class="bracket-label mb-3"><?= __('common_transaction_details') ?></div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label"><?= __('common_supplier') ?></label>
            <select name="supplier_id" class="form-select">
              <option value=""><?= __('stockin_select_supplier') ?></option>
              <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label"><?= __('common_transaction_date') ?></label>
            <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="mb-0">
          <label class="form-label"><?= __('common_note') ?></label>
          <input type="text" name="note" class="form-control" placeholder="<?= __('stockin_note_placeholder') ?>">
        </div>
      </div>

      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="bracket-label mb-0"><?= __('common_line_items') ?></div>
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()"><?= __('common_add_product') ?></button>
        </div>
        <div class="table-responsive mb-3">
          <table class="table align-middle" id="lineTable">
            <thead class="table-light"><tr><th><?= __('common_product') ?></th><th style="width:110px;"><?= __('common_qty') ?></th><th style="width:140px;"><?= __('stockin_unit_cost') ?></th><th style="width:40px;"></th></tr></thead>
            <tbody id="lineBody"></tbody>
          </table>
        </div>
        <button class="btn btn-primary w-100 py-2"><i class="bi bi-download me-1"></i> <?= __('stockin_submit_button') ?></button>
      </div>
    </form>
  </div>

  <div class="col-lg-4">
    <div class="card p-3">
      <div class="bracket-label mb-3"><?= __('stockin_recent_title') ?></div>
      <?php if (!$recent): ?><p class="text-secondary small mb-0"><?= __('common_no_transactions') ?></p><?php endif; ?>
      <?php foreach ($recent as $t): ?>
        <div class="border-bottom pb-2 mb-2">
          <div class="d-flex justify-content-between align-items-center small mb-1">
            <span class="mono text-primary fw-bold"><?= htmlspecialchars($t['reference']) ?></span>
            <span class="mono text-secondary"><?= $t['transaction_date'] ?></span>
          </div>
          <div class="small text-secondary"><?= htmlspecialchars($t['supplier_name'] ?? 'Direct Receive') ?></div>
          <div class="small mt-1 d-flex justify-content-between">
            <span><?= $t['items'] ?> item(s) · <?= (int)$t['total_qty'] ?> unit(s)</span>
            <strong class="mono text-success">$<?= number_format($t['total_value'], 2) ?></strong>
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
    html += `<option value="${p.id}" data-cost="${p.cost_price}" ${String(p.id)===String(selected)?'selected':''}>${p.name} · ${p.sku} (${T_NOW}: ${p.current_stock} ${T_PCS})</option>`;
  });
  return html;
}

function addRow(productId = '', qty = 1, cost = '') {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="product_id[]" class="form-select form-select-sm" onchange="fillCost(this)" required>${productOptions(productId)}</select></td>
    <td><input type="number" name="qty[]" class="form-control form-control-sm" value="${qty}" min="1" required></td>
    <td><input type="number" name="unit_cost[]" class="form-control form-control-sm" value="${cost}" step="0.01" required></td>
    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('tr').remove()">✕</button></td>`;
  document.getElementById('lineBody').appendChild(tr);
}
function fillCost(sel) {
  const opt = sel.selectedOptions[0];
  const row = sel.closest('tr');
  if (opt && opt.dataset.cost) {
    row.querySelector('[name="unit_cost[]"]').value = opt.dataset.cost;
  }
}
addRow();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
