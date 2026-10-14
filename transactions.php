<?php
// transactions.php - Complete Transaction History Page with filters & pagination
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$user = getLoggedInUser();
$db = getDB();

$pageTitle = "Transaction History";
require_once __DIR__ . '/includes/header.php';

// Filtering parameters
$typeFilter = trim($_GET['type'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query
$where = ["user_id = ?"];
$params = [$user['id']];

if (!empty($typeFilter)) {
    $where[] = "type = ?";
    $params[] = $typeFilter;
}

if (!empty($statusFilter)) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $where[] = "(reference LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(" AND ", $where);

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE $whereClause");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch items
$sql = "SELECT * FROM transactions WHERE $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>

<div class="container">
  <div style="margin-bottom: 24px;">
    <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--dark);">Transaction Ledger</h1>
    <p style="color: var(--text-muted); font-size: 0.95rem;">Auditable history of all bonuses, task payouts, level unlock fees, and withdrawals.</p>
  </div>

  <!-- Filter & Search Bar -->
  <div class="card" style="margin-bottom: 24px; padding: 18px;">
    <form method="GET" action="<?= BASE_URL ?>/transactions.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
      
      <div style="flex: 2; min-width: 180px;">
        <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Search Reference / Description</label>
        <input type="text" name="search" class="form-control" placeholder="TX-..., Task name..." value="<?= e($search) ?>">
      </div>

      <div style="flex: 1; min-width: 140px;">
        <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Transaction Type</label>
        <select name="type" class="form-control">
          <option value="">All Types</option>
          <option value="welcome_bonus" <?= $typeFilter === 'welcome_bonus' ? 'selected' : '' ?>>Welcome Bonus</option>
          <option value="task_reward" <?= $typeFilter === 'task_reward' ? 'selected' : '' ?>>Task Reward</option>
          <option value="referral_reward" <?= $typeFilter === 'referral_reward' ? 'selected' : '' ?>>Referral Reward</option>
          <option value="level_unlock" <?= $typeFilter === 'level_unlock' ? 'selected' : '' ?>>Level Unlock</option>
          <option value="withdrawal" <?= $typeFilter === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
          <option value="adjustment" <?= $typeFilter === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
        </select>
      </div>

      <div style="flex: 1; min-width: 130px;">
        <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Status</label>
        <select name="status" class="form-control">
          <option value="">All Statuses</option>
          <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
          <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
      </div>

      <div style="display: flex; gap: 8px;">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
        <a href="<?= BASE_URL ?>/transactions.php" class="btn btn-outline">Reset</a>
      </div>
    </form>
  </div>

  <!-- Transactions Table -->
  <div class="card">
    <?php if (empty($transactions)): ?>
      <div style="text-align: center; padding: 40px 16px; color: var(--text-muted);">
        <i class="bi bi-file-earmark-spreadsheet" style="font-size: 2.5rem; display: block; margin-bottom: 8px;"></i>
        <p>No matching transactions found.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Type</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transactions as $tx): ?>
            <tr>
              <td><code><?= e($tx['reference']) ?></code></td>
              <td>
                <span class="badge badge-<?= in_array($tx['type'], ['welcome_bonus', 'task_reward', 'referral_reward']) ? 'success' : ($tx['type'] === 'withdrawal' ? 'info' : 'secondary') ?>">
                  <?= e(str_replace('_', ' ', $tx['type'])) ?>
                </span>
              </td>
              <td><?= e($tx['description']) ?></td>
              <td style="font-weight: 700; color: <?= $tx['direction'] === 'credit' ? 'var(--primary)' : 'var(--danger)' ?>;">
                <?= $tx['direction'] === 'credit' ? '+' : '-' ?> <?= formatCurrency($tx['amount']) ?>
              </td>
              <td>
                <span class="badge badge-<?= $tx['status'] === 'completed' ? 'success' : ($tx['status'] === 'pending' ? 'warning' : 'danger') ?>">
                  <?= e($tx['status']) ?>
                </span>
              </td>
              <td><?= formatDate($tx['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px;">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?>&type=<?= urlencode($typeFilter) ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>" 
               class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>">
              <?= $p ?>
            </a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
