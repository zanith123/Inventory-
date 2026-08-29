<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';
$activePage = 'dashboard';

// KPI Metrics
$totalProducts = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalUnits    = $pdo->query('SELECT COALESCE(SUM(current_stock),0) FROM products')->fetchColumn();
$totalValue    = $pdo->query('SELECT COALESCE(SUM(current_stock * cost_price),0) FROM products')->fetchColumn();
$lowStockCount = $pdo->query('SELECT COUNT(*) FROM products WHERE current_stock <= min_stock')->fetchColumn();

// Low Stock List
$stmtLow = $pdo->query("
    SELECT p.*, c.name as category_name, u.name as unit_name 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN units u ON p.unit_id = u.id
    WHERE p.current_stock <= p.min_stock
    ORDER BY p.current_stock ASC
    LIMIT 5
");
$lowStockItems = $stmtLow->fetchAll();

// Monthly Movements Chart Data (Last 6 Months)
$stmtMovements = $pdo->query("
    SELECT 
        DATE_FORMAT(st.transaction_date, '%b %Y') as month_label,
        SUM(CASE WHEN st.type = 'in' THEN sti.qty ELSE 0 END) as total_in,
        SUM(CASE WHEN st.type = 'out' THEN sti.qty ELSE 0 END) as total_out
    FROM stock_transactions st
    JOIN stock_transaction_items sti ON st.id = sti.transaction_id
    WHERE st.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(st.transaction_date, '%Y-%m'), DATE_FORMAT(st.transaction_date, '%b %Y')
    ORDER BY DATE_FORMAT(st.transaction_date, '%Y-%m') ASC
");
$movementData = $stmtMovements->fetchAll();

$months = array_column($movementData, 'month_label');
$stockInQtys = array_map('intval', array_column($movementData, 'total_in'));
$stockOutQtys = array_map('intval', array_column($movementData, 'total_out'));

// Category Valuation Chart Data
$stmtCatVal = $pdo->query("
    SELECT COALESCE(c.name, 'Uncategorized') as cat_name, COALESCE(SUM(p.current_stock * p.cost_price), 0) as cat_val
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    GROUP BY p.category_id, c.name
    HAVING cat_val > 0
    ORDER BY cat_val DESC
    LIMIT 6
");
$catValData = $stmtCatVal->fetchAll();
$catNames = array_column($catValData, 'cat_name');
$catValues = array_map('floatval', array_column($catValData, 'cat_val'));

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="mb-1"><?= __('dashboard_title') ?> <span class="badge bg-primary text-dark fs-6 ms-2">v2.0</span></h4>
    <p class="text-secondary small mb-0">Overview of inventory health, recent movements, and stock metrics</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/stock-in/index.php" class="btn btn-primary btn-sm"><i class="bi bi-download me-1"></i> <?= __('nav_stock_in') ?></a>
    <a href="<?= BASE_URL ?>/stock-out/index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-upload me-1"></i> <?= __('nav_stock_out') ?></a>
  </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card p-3 card-stat h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="bracket-label"><?= __('dashboard_total_products') ?></div>
        <i class="bi bi-box text-primary fs-5"></i>
      </div>
      <div class="fs-3 mono fw-bold"><?= number_format($totalProducts) ?></div>
      <div class="text-secondary small mt-1"><?= __('common_products_word') ?> catalog</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 card-stat h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="bracket-label"><?= __('dashboard_units_in_stock') ?></div>
        <i class="bi bi-layers text-success fs-5"></i>
      </div>
      <div class="fs-3 mono fw-bold"><?= number_format($totalUnits) ?></div>
      <div class="text-secondary small mt-1"><?= __('common_units_word') ?> available</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 card-stat h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="bracket-label"><?= __('dashboard_inventory_value') ?></div>
        <i class="bi bi-currency-dollar text-info fs-5"></i>
      </div>
      <div class="fs-3 mono fw-bold"><?= format_money($totalValue) ?></div>
      <div class="text-secondary small mt-1">Total valuation at cost</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 card-stat h-100 <?= $lowStockCount > 0 ? 'border-danger' : '' ?>">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="bracket-label text-danger" style="color:var(--danger);"><?= __('dashboard_low_stock') ?></div>
        <i class="bi bi-exclamation-triangle text-danger fs-5"></i>
      </div>
      <div class="fs-3 mono fw-bold text-danger"><?= number_format($lowStockCount) ?></div>
      <div class="text-secondary small mt-1">Requires restock attention</div>
    </div>
  </div>
</div>

<!-- Interactive Charts Row -->
<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card p-3 h-100">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="mb-0 text-uppercase mono fs-7 text-secondary"><i class="bi bi-bar-chart-line me-2 text-primary"></i><?= __('v2_chart_movement') ?></h6>
        <span class="badge bg-dark border border-secondary text-secondary">Last 6 Months</span>
      </div>
      <div style="height: 260px; position: relative;">
        <canvas id="chartMovements"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card p-3 h-100">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="mb-0 text-uppercase mono fs-7 text-secondary"><i class="bi bi-pie-chart me-2 text-info"></i><?= __('v2_chart_category') ?></h6>
        <span class="badge bg-dark border border-secondary text-secondary">Valuation</span>
      </div>
      <div style="height: 260px; position: relative;">
        <canvas id="chartCategory"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Low Stock Watchlist Table -->
<div class="card p-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h6 class="mb-0 text-uppercase mono fs-7 text-danger"><i class="bi bi-shield-exclamation me-2"></i><?= __('v2_low_stock_watch') ?></h6>
    <a href="<?= BASE_URL ?>/product/index.php" class="btn btn-link btn-sm text-decoration-none text-secondary p-0">View all products <i class="bi bi-arrow-right"></i></a>
  </div>
  <?php if (empty($lowStockItems)): ?>
    <div class="text-secondary text-center py-4">
      <i class="bi bi-check-circle fs-3 text-success d-block mb-2"></i>
      All product stock levels are healthy! No items below minimum threshold.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>Category</th>
            <th class="text-end">Min Stock</th>
            <th class="text-end">Current Stock</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lowStockItems as $item): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($item['name']) ?></td>
              <td><span class="slug-pill"><?= htmlspecialchars($item['sku']) ?></span></td>
              <td><?= htmlspecialchars($item['category_name'] ?? '—') ?></td>
              <td class="text-end mono"><?= number_format($item['min_stock']) ?></td>
              <td class="text-end mono text-danger fw-bold"><?= number_format($item['current_stock']) ?> <?= htmlspecialchars($item['unit_name'] ?? '') ?></td>
              <td class="text-center">
                <a href="<?= BASE_URL ?>/stock-in/index.php" class="btn btn-sm btn-outline-primary" title="Restock item">
                  <i class="bi bi-plus-circle me-1"></i> Restock
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const isLight = document.body.classList.contains('theme-light');
  const textColor = isLight ? '#0B1220' : '#8B9AAE';
  const gridColor = isLight ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.06)';

  // Movements Chart
  const ctxMove = document.getElementById('chartMovements').getContext('2d');
  new Chart(ctxMove, {
    type: 'bar',
    data: {
      labels: <?= json_encode(empty($months) ? ['No Data'] : $months) ?>,
      datasets: [
        {
          label: 'Stock In',
          data: <?= json_encode(empty($stockInQtys) ? [0] : $stockInQtys) ?>,
          backgroundColor: '#34D399',
          borderRadius: 4
        },
        {
          label: 'Stock Out',
          data: <?= json_encode(empty($stockOutQtys) ? [0] : $stockOutQtys) ?>,
          backgroundColor: '#F87171',
          borderRadius: 4
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: textColor, font: { family: 'JetBrains Mono' } } }
      },
      scales: {
        x: { ticks: { color: textColor }, grid: { color: gridColor } },
        y: { ticks: { color: textColor }, grid: { color: gridColor }, beginAtZero: true }
      }
    }
  });

  // Category Chart
  const ctxCat = document.getElementById('chartCategory').getContext('2d');
  new Chart(ctxCat, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode(empty($catNames) ? ['No Categories'] : $catNames) ?>,
      datasets: [{
        data: <?= json_encode(empty($catValues) ? [1] : $catValues) ?>,
        backgroundColor: ['#22D3EE', '#34D399', '#FBBF24', '#A855F7', '#EC4899', '#6366F1'],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { color: textColor, font: { family: 'JetBrains Mono', size: 11 } } }
      }
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
