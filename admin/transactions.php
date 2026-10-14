<?php
// admin/transactions.php - Global Ledger Audit Viewer
require_once __DIR__ . '/../includes/admin-auth.php';
requireAdminAuth();

$db = getDB();
$adminTitle = "Global Ledger Transactions";
require_once __DIR__ . '/includes/admin-header.php';

$search = trim($_GET['search'] ?? '');
$type = trim($_GET['type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(t.reference LIKE ? OR u.email LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type)) {
    $where[] = "t.type = ?";
    $params[] = $type;
}

$whereClause = implode(" AND ", $where);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM transactions t JOIN users u ON t.user_id = u.id WHERE $whereClause");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Query
$sql = "
    SELECT t.*, u.email, u.phone 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE $whereClause 
    ORDER BY t.created_at DESC 
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>

<!-- Search & Filter -->
<div class="card" style="margin-bottom: 24px; padding: 18px;">
  <form method="GET" action="<?= BASE_URL ?>/admin/transactions.php" style="display: flex; gap: 12px; flex-wrap: wrap;">
    <div style="flex: 2; min-width: 200px;">
      <input type="text" name="search" class="form-control" placeholder="Search reference, email, description..." value="<?= e($search) ?>">
    </div>
    <div style="flex: 1; min-width: 140px;">
      <select name="type" class="form-control">
        <option value="">All Types</option>
        <option value="welcome_bonus" <?= $type === 'welcome_bonus' ? 'selected' : '' ?>>Welcome Bonus</option>
        <option value="task_reward" <?= $type === 'task_reward' ? 'selected' : '' ?>>Task Reward</option>
        <option value="referral_reward" <?= $type === 'referral_reward' ? 'selected' : '' ?>>Referral Reward</option>
        <option value="level_unlock" <?= $type === 'level_unlock' ? 'selected' : '' ?>>Level Unlock</option>
        <option value="withdrawal" <?= $type === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
        <option value="adjustment" <?= $type === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
    <a href="<?= BASE_URL ?>/admin/transactions.php" class="btn btn-outline">Reset</a>
  </form>
</div>

<!-- Transactions Table -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">All Ledger Transactions (<?= $totalRows ?>)</h2>
  </div>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Reference</th>
          <th>User</th>
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
            <a href="<?= BASE_URL ?>/admin/user.php?id=<?= $tx['user_id'] ?>">
              <?= e($tx['email']) ?>
            </a>
          </td>
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

  <?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px;">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>&type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>" 
           class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
