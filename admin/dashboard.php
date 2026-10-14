<?php
// admin/dashboard.php - Admin Dashboard Overview & Metrics
require_once __DIR__ . '/../includes/admin-auth.php';
requireAdminAuth();

$db = getDB();
$adminTitle = "System Dashboard Overview";
require_once __DIR__ . '/includes/admin-header.php';

// Metrics aggregations
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();

$pendingWdCount = (int)$db->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn();
$pendingWdAmount = (float)$db->query("SELECT COALESCE(SUM(net_amount), 0) FROM withdrawals WHERE status = 'pending'")->fetchColumn();

$totalRewards = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type IN ('task_reward', 'welcome_bonus', 'referral_reward') AND status = 'completed'")->fetchColumn();
$totalWithdrawn = (float)$db->query("SELECT COALESCE(SUM(net_amount), 0) FROM withdrawals WHERE status = 'paid'")->fetchColumn();

$completedLevels = (int)$db->query("SELECT COUNT(*) FROM user_level_progress WHERE status = 'completed'")->fetchColumn();
$referralRewards = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'referral_reward' AND status = 'completed'")->fetchColumn();

// Recent 5 Pending Withdrawals
$recentPendingWd = $db->query("SELECT w.*, u.email FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.status = 'pending' ORDER BY w.created_at ASC LIMIT 5")->fetchAll();

// Recent 5 User registrations
$recentUsers = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>

<!-- Metrics Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px;">
  <div class="card" style="border-left: 4px solid #3b82f6;">
    <div class="wallet-label">Total Registered Users</div>
    <div class="wallet-amount" style="color: #1e40af;"><?= number_format($totalUsers) ?></div>
    <small style="color: var(--text-muted);"><?= number_format($activeUsers) ?> currently active</small>
  </div>

  <div class="card" style="border-left: 4px solid #f59e0b;">
    <div class="wallet-label">Pending Withdrawals</div>
    <div class="wallet-amount" style="color: #b45309;"><?= $pendingWdCount ?></div>
    <small style="color: var(--text-muted);"><?= formatCurrency($pendingWdAmount) ?> queued</small>
  </div>

  <div class="card" style="border-left: 4px solid #10b981;">
    <div class="wallet-label">Total Rewards Issued</div>
    <div class="wallet-amount" style="color: #065f46;"><?= formatCurrency($totalRewards) ?></div>
    <small style="color: var(--text-muted);">Welcome, tasks & referrals</small>
  </div>

  <div class="card" style="border-left: 4px solid #6366f1;">
    <div class="wallet-label">Total Withdrawn</div>
    <div class="wallet-amount" style="color: #4338ca;"><?= formatCurrency($totalWithdrawn) ?></div>
    <small style="color: var(--text-muted);">Paid out to M-Pesa</small>
  </div>

  <div class="card" style="border-left: 4px solid #ec4899;">
    <div class="wallet-label">Level Completions</div>
    <div class="wallet-amount" style="color: #be185d;"><?= number_format($completedLevels) ?></div>
    <small style="color: var(--text-muted);">Passed assessments</small>
  </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px;">
  
  <!-- Pending Withdrawals Queue -->
  <div class="card">
    <div class="card-header">
      <div>
        <h2 class="card-title">Pending Withdrawals Queue</h2>
        <small style="color: var(--text-muted);">Requests awaiting administrative action</small>
      </div>
      <a href="<?= BASE_URL ?>/admin/withdrawals.php" class="btn btn-outline btn-sm">Manage All</a>
    </div>

    <?php if (empty($recentPendingWd)): ?>
      <div style="text-align: center; padding: 30px; color: var(--text-muted);">
        <i class="bi bi-check2-circle" style="font-size: 2rem; color: #10b981;"></i>
        <p style="margin-top: 6px;">All withdrawal requests have been processed!</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>User</th>
              <th>Phone</th>
              <th>Net Amount</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentPendingWd as $w): ?>
            <tr>
              <td><?= e($w['email']) ?></td>
              <td><code><?= e($w['phone']) ?></code></td>
              <td style="font-weight: 700; color: var(--primary);"><?= formatCurrency($w['net_amount']) ?></td>
              <td>
                <a href="<?= BASE_URL ?>/admin/withdrawals.php?id=<?= $w['id'] ?>" class="btn btn-primary btn-sm">
                  Review
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recent User Registrations -->
  <div class="card">
    <div class="card-header">
      <div>
        <h2 class="card-title">Recent User Registrations</h2>
        <small style="color: var(--text-muted);">Latest members joined</small>
      </div>
      <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline btn-sm">All Users</a>
    </div>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Email</th>
            <th>M-Pesa Phone</th>
            <th>Joined</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
          <tr>
            <td>
              <a href="<?= BASE_URL ?>/admin/user.php?id=<?= $u['id'] ?>" style="font-weight: 600;">
                <?= e($u['email']) ?>
              </a>
            </td>
            <td><code><?= e($u['phone']) ?></code></td>
            <td><?= formatDate($u['created_at'], false) ?></td>
            <td>
              <span class="badge badge-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>">
                <?= e($u['status']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
