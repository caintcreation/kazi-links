<?php
// admin/withdrawals.php - Admin Withdrawal Approvals & Disbursement Workflow
require_once __DIR__ . '/../includes/admin-auth.php';
requireAdminAuth();

$admin = getLoggedInAdmin();
$db = getDB();

$message = '';
$error = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wdId = (int)($_POST['withdrawal_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $adminNote = trim($_POST['admin_note'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = "CSRF security mismatch.";
    } elseif ($wdId > 0 && in_array($newStatus, ['processing', 'paid', 'rejected', 'cancelled'])) {
        $stmt = $db->prepare("SELECT * FROM withdrawals WHERE id = ?");
        $stmt->execute([$wdId]);
        $wd = $stmt->fetch();

        if ($wd) {
            $db->beginTransaction();
            try {
                // Update withdrawal record
                $upd = $db->prepare("UPDATE withdrawals SET status = ?, admin_note = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
                $upd->execute([$newStatus, $adminNote, $admin['id'], $wdId]);

                // Update ledger and user wallet
                $userWallet = getUserWallet($wd['user_id']);
                $pendingBalance = (float)$userWallet['pending_balance'];
                $balance = (float)$userWallet['balance'];
                $totalWithdrawn = (float)$userWallet['total_withdrawn'];
                $amount = (float)$wd['amount'];

                if ($newStatus === 'paid') {
                    // Finalize payout: deduct from pending_balance, add to total_withdrawn
                    $newPending = max(0, $pendingBalance - $amount);
                    $newWithdrawn = $totalWithdrawn + $amount;
                    $db->prepare("UPDATE wallets SET pending_balance = ?, total_withdrawn = ? WHERE user_id = ?")
                       ->execute([$newPending, $newWithdrawn, $wd['user_id']]);

                    // Update corresponding transaction to completed
                    $db->prepare("UPDATE transactions SET status = 'completed' WHERE reference = ?")
                       ->execute([$wd['reference']]);

                    logAdminAudit($admin['id'], 'withdrawal_paid', 'withdrawal', $wdId, "Marked Paid KES {$wd['net_amount']} to {$wd['phone']}");
                    $message = "Withdrawal #{$wd['reference']} marked as PAID.";

                } elseif (in_array($newStatus, ['rejected', 'cancelled'])) {
                    // Refund: restore available balance, remove from pending_balance
                    $newPending = max(0, $pendingBalance - $amount);
                    $newBalance = $balance + $amount;
                    $db->prepare("UPDATE wallets SET balance = ?, pending_balance = ? WHERE user_id = ?")
                       ->execute([$newBalance, $newPending, $wd['user_id']]);

                    // Update transaction to rejected
                    $db->prepare("UPDATE transactions SET status = 'rejected', description = CONCAT(description, ' [Rejected: ', ?, ']') WHERE reference = ?")
                       ->execute([$adminNote, $wd['reference']]);

                    logAdminAudit($admin['id'], 'withdrawal_rejected', 'withdrawal', $wdId, "Rejected KES {$wd['amount']} - $adminNote");
                    $message = "Withdrawal #{$wd['reference']} has been rejected and funds returned to user wallet.";

                } elseif ($newStatus === 'processing') {
                    logAdminAudit($admin['id'], 'withdrawal_processing', 'withdrawal', $wdId, "Set to Processing");
                    $message = "Withdrawal #{$wd['reference']} set to Processing.";
                }

                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to update withdrawal status: " . $e->getMessage();
            }
        }
    }
}

// Filters
$statusFilter = trim($_GET['status'] ?? 'pending');
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($statusFilter) && $statusFilter !== 'all') {
    $where[] = "w.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $where[] = "(w.reference LIKE ? OR w.phone LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT w.*, u.email, a.username as processor_name
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    LEFT JOIN admins a ON w.processed_by = a.id
    WHERE $whereClause
    ORDER BY w.created_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$withdrawals = $stmt->fetchAll();

$adminTitle = "Withdrawals Management";
require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if ($message): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<!-- Filter & Status Tabs -->
<div class="card" style="margin-bottom: 24px; padding: 18px;">
  <form method="GET" action="<?= BASE_URL ?>/admin/withdrawals.php" style="display: flex; gap: 12px; flex-wrap: wrap;">
    <div style="flex: 2; min-width: 200px;">
      <input type="text" name="search" class="form-control" placeholder="Search reference, phone, or user email..." value="<?= e($search) ?>">
    </div>
    <div style="flex: 1; min-width: 140px;">
      <select name="status" class="form-control" onchange="this.form.submit();">
        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
        <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid (Completed)</option>
        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
    <a href="<?= BASE_URL ?>/admin/withdrawals.php" class="btn btn-outline">Reset</a>
  </form>
</div>

<!-- Withdrawals Table -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Withdrawal Requests (<?= count($withdrawals) ?>)</h2>
  </div>

  <?php if (empty($withdrawals)): ?>
    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
      <i class="bi bi-wallet" style="font-size: 2.5rem; display: block; margin-bottom: 8px;"></i>
      <p>No withdrawal requests found matching criteria.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Member</th>
            <th>M-Pesa Line</th>
            <th>Amount</th>
            <th>Fee</th>
            <th>Net to Send</th>
            <th>Status</th>
            <th>Requested</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($withdrawals as $w): ?>
          <tr>
            <td><code><?= e($w['reference']) ?></code></td>
            <td>
              <a href="<?= BASE_URL ?>/admin/user.php?id=<?= $w['user_id'] ?>">
                <?= e($w['email']) ?>
              </a>
            </td>
            <td><strong><code><?= e($w['phone']) ?></code></strong></td>
            <td><?= formatCurrency($w['amount']) ?></td>
            <td style="color: var(--danger);"><?= formatCurrency($w['fee']) ?></td>
            <td style="font-weight: 700; color: var(--primary); font-size: 1rem;"><?= formatCurrency($w['net_amount']) ?></td>
            <td>
              <span class="badge badge-<?= $w['status'] === 'paid' ? 'success' : ($w['status'] === 'pending' ? 'warning' : ($w['status'] === 'processing' ? 'info' : 'danger')) ?>">
                <?= e($w['status']) ?>
              </span>
            </td>
            <td><?= formatDate($w['created_at']) ?></td>
            <td>
              <?php if (in_array($w['status'], ['pending', 'processing'])): ?>
                <button type="button" class="btn btn-primary btn-sm action-btn" 
                        data-id="<?= $w['id'] ?>"
                        data-ref="<?= e($w['reference']) ?>"
                        data-phone="<?= e($w['phone']) ?>"
                        data-net="<?= formatCurrency($w['net_amount']) ?>">
                  Process
                </button>
              <?php else: ?>
                <small style="color: var(--text-muted);">
                  <?= e($w['processor_name'] ?? 'Admin') ?>
                </small>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Process Modal -->
<div class="modal-backdrop" id="processWdModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700;">Process M-Pesa Withdrawal</h3>
      <button type="button" class="modal-close">&times;</button>
    </div>

    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="withdrawal_id" id="modalWdId">

      <div style="background: #f8fafc; padding: 14px; border-radius: var(--radius-md); margin-bottom: 16px;">
        <div>Reference: <strong id="modalWdRef"></strong></div>
        <div>M-Pesa Number: <strong id="modalWdPhone"></strong></div>
        <div style="margin-top: 6px; font-size: 1.1rem; color: var(--primary);">Net Payout: <strong id="modalWdNet"></strong></div>
      </div>

      <div class="form-group">
        <label class="form-label">Update Payout Status</label>
        <select name="status" class="form-control" required>
          <option value="paid">Mark PAID (M-Pesa Disbursed)</option>
          <option value="processing">Mark Processing (Queued)</option>
          <option value="rejected">Reject & Refund Balance</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Admin Audit Note</label>
        <textarea name="admin_note" class="form-control" rows="2" placeholder="e.g. Disbursed via Safaricom Daraja B2C / Manual Send"></textarea>
      </div>

      <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save & Apply</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.action-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('modalWdId').value = btn.getAttribute('data-id');
      document.getElementById('modalWdRef').textContent = btn.getAttribute('data-ref');
      document.getElementById('modalWdPhone').textContent = btn.getAttribute('data-phone');
      document.getElementById('modalWdNet').textContent = btn.getAttribute('data-net');
      openModal('processWdModal');
    });
  });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
