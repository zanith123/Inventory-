<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$activePage = 'stock-report';
$tab = $_GET['tab'] ?? 'overview';

$fromDate = $_GET['from_date'] ?? '';
$toDate   = $_GET['to_date'] ?? '';
$typeFilter = $_GET['type'] ?? '';

// Build dynamic WHERE clause
$whereClauses = ['1=1'];
$params = [];

if (!empty($fromDate)) {
    $whereClauses[] = 't.transaction_date >= ?';
    $params[] = $fromDate;
}
if (!empty($toDate)) {
    $whereClauses[] = 't.transaction_date <= ?';
    $params[] = $toDate;
}
if (!empty($typeFilter)) {
    $whereClauses[] = 't.type = ?';
    $params[] = $typeFilter;
}
$whereSql = implode(' AND ', $whereClauses);

// ---------- CSV export ----------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sqlCsv = "SELECT t.reference, t.transaction_date, t.type, COUNT(i.id) items, SUM(i.qty) qty, SUM(i.subtotal) value, t.note
               FROM stock_transactions t
               LEFT JOIN stock_transaction_items i ON i.transaction_id = t.id
               WHERE $whereSql
               GROUP BY t.id, t.reference, t.transaction_date, t.type, t.note ORDER BY t.id DESC";

    $stmtCsv = $pdo->prepare($sqlCsv);
    $stmtCsv->execute($params);
    $rows = $stmtCsv->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stock-report-v2-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Reference', 'Date', 'Type', 'Line Items', 'Units Qty', 'Total Value ($)', 'Note']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['reference'], $r['transaction_date'], strtoupper($r['type']), $r['items'], $r['qty'], number_format((float)$r['value'], 2, '.', ''), $r['note']]);
    }
    fclose($out);
    exit;
}

// KPI Metrics
$stmtIn = $pdo->prepare("SELECT COALESCE(SUM(i.qty),0) FROM stock_transactions t JOIN stock_transaction_items i ON i.transaction_id=t.id WHERE t.type='in' AND $whereSql");
$stmtIn->execute($params);
$unitsIn = (int) $stmtIn->fetchColumn();

$stmtOut = $pdo->prepare("SELECT COALESCE(SUM(i.qty),0) FROM stock_transactions t JOIN stock_transaction_items i ON i.transaction_id=t.id WHERE t.type='out' AND $whereSql");
$stmtOut->execute($params);
$unitsOut = (int) $stmtOut->fetchColumn();

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM stock_transactions t WHERE $whereSql");
$stmtCount->execute($params);
$txCount = (int) $stmtCount->fetchColumn();

$stmtByType = $pdo->prepare("SELECT type, COUNT(*) c FROM stock_transactions t WHERE $whereSql GROUP BY type");
$stmtByType->execute($params);
$byType = $stmtByType->fetchAll(PDO::FETCH_KEY_PAIR);

$typeLabels = ['in' => __('nav_stock_in'), 'out' => __('nav_stock_out'), 'adjustment' => __('common_adjustment_label')];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-1"><?= __('nav_stock_reports') ?></h4>
    <p class="text-secondary small mb-0">Audit logs, inventory movements, and transaction exports</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> <?= __('v2_print') ?></button>
    <a class="btn btn-primary btn-sm" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"><i class="bi bi-download me-1"></i> <?= __('stockreport_export_button') ?></a>
  </div>
</div>

<!-- Date Filter Form -->
<div class="card p-3 mb-4">
  <form method="get" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
    <div class="col-md-3">
      <label class="form-label mb-1"><?= __('v2_date_from') ?></label>
      <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($fromDate) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label mb-1"><?= __('v2_date_to') ?></label>
      <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($toDate) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label mb-1"><?= __('common_type') ?></label>
      <select name="type" class="form-select form-select-sm">
        <option value=""><?= __('v2_all_types') ?></option>
        <option value="in" <?= $typeFilter==='in'?'selected':'' ?>><?= __('nav_stock_in') ?></option>
        <option value="out" <?= $typeFilter==='out'?'selected':'' ?>><?= __('nav_stock_out') ?></option>
        <option value="adjustment" <?= $typeFilter==='adjustment'?'selected':'' ?>><?= __('common_adjustment_label') ?></option>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i> <?= __('v2_filter') ?></button>
      <a href="?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle"></i></a>
    </div>
  </form>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card p-3"><div class="bracket-label mb-2"><?= __('stockreport_units_in') ?></div><div class="fs-3 mono fw-bold" style="color:var(--good);"><?= number_format($unitsIn) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="bracket-label mb-2"><?= __('stockreport_units_out') ?></div><div class="fs-3 mono fw-bold text-danger"><?= number_format($unitsOut) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="bracket-label mb-2"><?= __('stockreport_net_flow') ?></div><div class="fs-3 mono fw-bold"><?= ($unitsIn - $unitsOut >= 0 ? '+' : '') . number_format($unitsIn - $unitsOut) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="bracket-label mb-2"><?= __('stockreport_transactions') ?></div><div class="fs-3 mono fw-bold"><?= number_format($txCount) ?></div></div></div>
</div>

<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link <?= $tab==='overview'?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET, ['tab' => 'overview'])) ?>"><?= __('stockreport_tab_overview') ?></a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='log'?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET, ['tab' => 'log'])) ?>"><?= __('stockreport_tab_log') ?></a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='product'?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET, ['tab' => 'product'])) ?>"><?= __('stockreport_tab_product') ?></a></li>
</ul>

<div class="printable-area">
<?php if ($tab === 'overview'): ?>
  <div class="card p-3">
    <div class="bracket-label mb-3"><?= __('stockreport_by_type_title') ?></div>
    <?php if (!$byType): ?><p class="text-secondary small mb-0"><?= __('common_no_transactions') ?></p><?php endif; ?>
    <?php foreach (['in' => __('nav_stock_in'), 'out' => __('nav_stock_out'), 'adjustment' => __('stockreport_adjustments_label')] as $key => $label): ?>
      <div class="d-flex justify-content-between border-bottom py-2">
        <span class="text-secondary"><?= $label ?></span>
        <span class="mono fw-semibold"><?= number_format($byType[$key] ?? 0) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

<?php elseif ($tab === 'log'):
  $sqlLog = "SELECT t.id, t.reference, t.transaction_date, t.type, t.note, COUNT(i.id) items, SUM(i.qty) qty, SUM(i.subtotal) value
             FROM stock_transactions t
             LEFT JOIN stock_transaction_items i ON i.transaction_id = t.id
             WHERE $whereSql
             GROUP BY t.id, t.reference, t.transaction_date, t.type, t.note ORDER BY t.id DESC";

  $stmtLog = $pdo->prepare($sqlLog);
  $stmtLog->execute($params);
  $rows = $stmtLog->fetchAll();
?>
  <div class="card">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light"><tr><th><?= __('common_reference') ?></th><th><?= __('common_date') ?></th><th><?= __('common_type') ?></th><th><?= __('stockreport_col_products') ?></th><th><?= __('stockreport_col_units') ?></th><th><?= __('common_value') ?></th><th><?= __('common_note') ?></th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-secondary py-4"><?= __('common_no_transactions') ?></td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td class="mono text-primary fw-bold"><?= htmlspecialchars($r['reference']) ?></td>
            <td class="mono"><?= $r['transaction_date'] ?></td>
            <td><span class="badge-stock <?= $r['type']==='out' ? 'badge-low' : 'badge-normal' ?>"><?= htmlspecialchars($typeLabels[$r['type']] ?? $r['type']) ?></span></td>
            <td><?= $r['items'] ?></td>
            <td class="mono"><?= number_format((int) $r['qty']) ?></td>
            <td class="mono fw-semibold">$<?= number_format((float)$r['value'], 2) ?></td>
            <td class="text-secondary small"><?= htmlspecialchars($r['note']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else:
  $rows = $pdo->query('SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.name')->fetchAll();
?>
  <div class="card">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light"><tr><th><?= __('common_product') ?></th><th><?= __('common_category') ?></th><th class="text-end"><?= __('stockreport_col_current_stock') ?></th><th class="text-center"><?= __('stockreport_col_level') ?></th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-secondary py-4"><?= __('product_empty') ?></td></tr><?php endif; ?>
          <?php foreach ($rows as $p): $low = $p['current_stock'] <= $p['min_stock']; ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
            <td><?= $p['category_name'] ? htmlspecialchars($p['category_name']) : '<span class="text-secondary">—</span>' ?></td>
            <td class="text-end mono fw-bold"><?= number_format($p['current_stock']) ?></td>
            <td class="text-center"><span class="badge-stock <?= $low ? 'badge-low' : 'badge-normal' ?>"><?= $low ? __('stockreport_badge_low') : __('stockreport_badge_normal') ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
