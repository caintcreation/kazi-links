<?php
// admin/user.php - User Deep Dive, Auditable Balance Adjustment & Suspension Controls
require_once __DIR__ . '/../includes/admin-auth.php';
requireAdminAuth();

$admin = getLoggedInAdmin();
$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    header("Location: " . BASE_URL . "/admin/users.php");
    exit;
}

$db = getDB();

// Handle Actions (Status change, balance adjustment, password reset)
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = "CSRF Token mismatch.";
    } elseif ($action === 'toggle_status') {
        $newStatus = ($_POST['status'] === 'suspended') ? 'suspended' : 'active';
        $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $userId]);
        logAdminAudit($admin['id'], 'user_status_change', 'user', $userId, "Status set to $newStatus");
        $message = "User status updated to $newStatus.";
    } elseif ($action === 'adjust_balance') {
        $amount = (float)($_POST['amount'] ?? 0);
        $direction = $_POST['direction'] === 'debit' ? 'debit' : 'credit';
        $reason = trim($_POST['reason'] ?? 'Admin manual ledger adjustment');

        if ($amount <= 0) {
            $error = "Adjustment amount must be greater than zero.";
        } else {
            $desc = "Admin Adjustment: $reason (by {$admin['username']})";
            $ref = recordTransaction($userId, 'adjustment', $amount, $direction, $desc, 'completed', json_encode(['admin_id' => $admin['id'], 'reason' => $reason]));

            if ($ref) {
                logAdminAudit($admin['id'], 'balance_adjustment', 'user', $userId, "$direction of KES $amount: $reason");
                $message = "Successfully recorded adjustment of " . formatCurrency($amount) . " ($direction). Ref: $ref";
            } else {
                $error = "Adjustment failed. Verify sufficient wallet balance if debiting.";
            }
        }
    } elseif ($action === 'reset_password') {
        $newPass = trim($_POST['new_password'] ?? '');
        if (strlen($newPass) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);
            logAdminAudit($admin['id'], 'password_reset', 'user', $userId, "Admin reset user password");
            $message = "User password successfully updated to: $newPass";
        }
    }
}

// Fetch user data
$uStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();
if (!$user) {
    header("Location: " . BASE_URL . "/admin/users.php");
    exit;
}

$wallet = getUserWallet($userId);

// Fetch recent 10 transactions
$txStmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$txStmt->execute([$userId]);
$userTxs = $txStmt->fetchAll();

// Fetch Level Progress
$pStmt = $db->prepare("
    SELECT ulp.*, l.level_number, l.title as level_title, t.name as task_name
    FROM user_level_progress ulp
    JOIN levels l ON ulp.level_id = l.id
    JOIN tasks t ON l.task_id = t.id
    WHERE ulp.user_id = ?
    ORDER BY ulp.completed_at DESC, ulp.created_at DESC
");
$pStmt->execute([$userId]);
$progresses = $pStmt->fetchAll();

$adminTitle = "User Profile: " . $user['email'];
require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="margin-bottom: 20px;">
  <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left"></i> Back to User Directory</a>
</div>

<?php if ($message): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<!-- User Overview Header -->
<div class="card" style="margin-bottom: 24px;">
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
      <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--dark);"><?= e($user['email']) ?></h2>
      <p style="color: var(--text-muted); font-size: 0.9rem;">
        M-Pesa: <strong><?= e($user['phone']) ?></strong> &bull; Ref Code: <code><?= e($user['referral_code']) ?></code> &bull; Joined: <?= formatDate($user['created_at']) ?>
      </p>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
      <span class="badge badge-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>" style="font-size: 0.9rem; padding: 6px 12px;">
        <?= ucfirst(e($user['status'])) ?>
      </span>

      <form method="POST" action="" style="display: inline;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="status" value="<?= $user['status'] === 'active' ? 'suspended' : 'active' ?>">
        <button type="submit" class="btn btn-sm <?= $user['status'] === 'active' ? 'btn-danger' : 'btn-primary' ?>" onclick="return confirm('Change user account status?');">
          <?= $user['status'] === 'active' ? 'Suspend Account' : 'Reactivate Account' ?>
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Wallet Snapshot -->
<div class="wallet-grid">
  <div class="wallet-card highlight">
    <div class="wallet-label">Available Balance</div>
    <div class="wallet-amount"><?= formatCurrency($wallet['balance']) ?></div>
  </div>
  <div class="wallet-card">
    <div class="wallet-label">Pending Balance</div>
    <div class="wallet-amount" style="color: var(--warning);"><?= formatCurrency($wallet['pending_balance']) ?></div>
  </div>
  <div class="wallet-card">
    <div class="wallet-label">Total Earned</div>
    <div class="wallet-amount" style="color: var(--primary);"><?= formatCurrency($wallet['total_earned']) ?></div>
  </div>
  <div class="wallet-card">
    <div class="wallet-label">Total Withdrawn</div>
    <div class="wallet-amount"><?= formatCurrency($wallet['total_withdrawn']) ?></div>
  </div>
</div>

<!-- Admin Adjustment & Password Reset Actions -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 24px; margin-top: 24px;">
  
  <!-- Auditable Balance Adjustment -->
  <div class="card">
    <div class="card-header">
      <div>
        <h3 class="card-title">Auditable Ledger Adjustment</h3>
        <small style="color: var(--text-muted);">Credit or debit user wallet with an audit trail</small>
      </div>
      <span class="badge badge-warning">Financial Control</span>
    </div>

    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="adjust_balance">

      <div class="form-group">
        <label class="form-label">Adjustment Direction</label>
        <select name="direction" class="form-control" required>
          <option value="credit">Credit (+) Add to balance</option>
          <option value="debit">Debit (-) Deduct from balance</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Amount (KES)</label>
        <input type="number" step="0.01" name="amount" class="form-control" placeholder="100.00" required>
      </div>

      <div class="form-group">
        <label class="form-label">Audit Reason / Description</label>
        <input type="text" name="reason" class="form-control" placeholder="e.g., Goodwill credit, refund, penalty" required>
      </div>

      <button type="submit" class="btn btn-primary btn-block" onclick="return confirm('Confirm ledger modification? This will create an auditable transaction.');">
        <i class="bi bi-wallet-fill"></i> Execute Ledger Adjustment
      </button>
    </form>
  </div>

  <!-- Reset Password Directly -->
  <div class="card">
    <div class="card-header">
      <div>
        <h3 class="card-title">Reset User Password</h3>
        <small style="color: var(--text-muted);">Set a temporary or customer-service password</small>
      </div>
      <span class="badge badge-info">Access Control</span>
    </div>

    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="reset_password">

      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="text" name="new_password" class="form-control" placeholder="Enter new password (min. 6 chars)" required>
      </div>

      <button type="submit" class="btn btn-outline btn-block" onclick="return confirm('Reset this user password?');">
        <i class="bi bi-key-fill"></i> Override Password
      </button>
    </form>
  </div>
</div>

<!-- Task Progress History -->
<div class="card" style="margin-top: 24px;">
  <div class="card-header">
    <h3 class="card-title">Completed & Active Tasks</h3>
  </div>

  <?php if (empty($progresses)): ?>
    <p style="color: var(--text-muted); text-align: center; padding: 20px;">No task levels attempted yet by this user.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Task</th>
            <th>Level</th>
            <th>Title</th>
            <th>Status</th>
            <th>Score</th>
            <th>Attempts</th>
            <th>Completed At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($progresses as $p): ?>
          <tr>
            <td><strong><?= e($p['task_name']) ?></strong></td>
            <td>Level <?= $p['level_number'] ?></td>
            <td><?= e($p['level_title']) ?></td>
            <td>
              <span class="badge badge-<?= $p['status'] === 'completed' ? 'success' : 'info' ?>">
                <?= e($p['status']) ?>
              </span>
            </td>
            <td><strong><?= (int)$p['score'] ?>%</strong></td>
            <td><?= (int)$p['attempts'] ?></td>
            <td><?= formatDate($p['completed_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Recent Transactions -->
<div class="card" style="margin-top: 24px;">
  <div class="card-header">
    <h3 class="card-title">Recent Transactions Ledger</h3>
  </div>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Reference</th>
          <th>Type</th>
          <th>Description</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($userTxs as $tx): ?>
        <tr>
          <td><code><?= e($tx['reference']) ?></code></td>
          <td><?= e(str_replace('_', ' ', $tx['type'])) ?></td>
          <td><?= e($tx['description']) ?></td>
          <td style="font-weight: 700; color: <?= $tx['direction'] === 'credit' ? 'var(--primary)' : 'var(--danger)' ?>;">
            <?= $tx['direction'] === 'credit' ? '+' : '-' ?> <?= formatCurrency($tx['amount']) ?>
          </td>
          <td>
            <span class="badge badge-<?= $tx['status'] === 'completed' ? 'success' : 'warning' ?>">
              <?= e($tx['status']) ?>
            </span>
          </td>
          <td><?= formatDate($tx['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
