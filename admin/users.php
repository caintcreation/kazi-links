<?php
// admin/users.php - User Management Directory
require_once __DIR__ . '/../includes/admin-auth.php';
requireAdminAuth();

$db = getDB();
$adminTitle = "User & Wallet Management";
require_once __DIR__ . '/includes/admin-header.php';

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(u.email LIKE ? OR u.phone LIKE ? OR u.referral_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $where[] = "u.status = ?";
    $params[] = $status;
}

$whereClause = implode(" AND ", $where);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereClause");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch
$sql = "
    SELECT u.*, w.balance, w.pending_balance, w.total_earned, w.total_withdrawn,
           (SELECT COUNT(*) FROM user_level_progress ulp WHERE ulp.user_id = u.id AND ulp.status = 'completed') as completed_levels
    FROM users u
    LEFT JOIN wallets w ON u.id = w.user_id
    WHERE $whereClause
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!-- Search Bar -->
<div class="card" style="margin-bottom: 24px; padding: 18px;">
  <form method="GET" action="<?= BASE_URL ?>/admin/users.php" style="display: flex; gap: 12px; flex-wrap: wrap;">
    <div style="flex: 2; min-width: 200px;">
      <input type="text" name="search" class="form-control" placeholder="Search email, phone (07...), referral code..." value="<?= e($search) ?>">
    </div>
    <div style="flex: 1; min-width: 140px;">
      <select name="status" class="form-control">
        <option value="">All Statuses</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline">Reset</a>
  </form>
</div>

<!-- Users Table -->
<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>User Account</th>
          <th>M-Pesa Phone</th>
          <th>Ref Code</th>
          <th>Balance</th>
          <th>Total Earned</th>
          <th>Tasks Done</th>
          <th>Status</th>
          <th>Joined</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>#<?= $u['id'] ?></td>
          <td>
            <strong><?= e($u['email']) ?></strong>
          </td>
          <td><code><?= e($u['phone']) ?></code></td>
          <td><span class="badge badge-secondary"><?= e($u['referral_code']) ?></span></td>
          <td style="font-weight: 700; color: var(--primary);"><?= formatCurrency($u['balance'] ?? 0) ?></td>
          <td><?= formatCurrency($u['total_earned'] ?? 0) ?></td>
          <td><strong><?= (int)$u['completed_levels'] ?></strong> levels</td>
          <td>
            <span class="badge badge-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>">
              <?= e($u['status']) ?>
            </span>
          </td>
          <td><?= formatDate($u['created_at'], false) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/admin/user.php?id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">
              <i class="bi bi-person-gear"></i> Manage
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px;">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>" 
           class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
