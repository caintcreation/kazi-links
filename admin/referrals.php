<?php
// admin/referrals.php - Referral Network and Reward Administration
require_once __DIR__ . '/../includes/admin-auth.php';
requireAdminAuth();

$db = getDB();
$adminTitle = "Affiliate & Referral Tracking";
require_once __DIR__ . '/includes/admin-header.php';

// Aggregations
$totRef = (int)$db->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
$totEligible = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE status IN ('eligible', 'rewarded')")->fetchColumn();
$totRewardedAmount = (float)$db->query("SELECT COALESCE(SUM(reward_amount), 0) FROM referrals WHERE status = 'rewarded'")->fetchColumn();

// List of referrals
$sql = "
    SELECT r.*, 
           u1.name as referrer_name, u1.email as referrer_email, u1.phone as referrer_phone,
           u2.name as referred_name, u2.email as referred_email, u2.phone as referred_phone,
           (SELECT COUNT(*) FROM user_level_progress ulp WHERE ulp.user_id = u2.id AND ulp.status = 'completed') as referred_completed_levels
    FROM referrals r
    JOIN users u1 ON r.referrer_id = u1.id
    JOIN users u2 ON r.referred_id = u2.id
    ORDER BY r.created_at DESC
    LIMIT 50
";
$referrals = $db->query($sql)->fetchAll();
?>

<!-- Referral Metrics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
  <div class="card">
    <div class="wallet-label">Total Invitations</div>
    <div class="wallet-amount"><?= number_format($totRef) ?></div>
    <small style="color: var(--text-muted);">Registered via affiliate links</small>
  </div>
  <div class="card">
    <div class="wallet-label">Eligible / Rewarded</div>
    <div class="wallet-amount" style="color: var(--primary);"><?= number_format($totEligible) ?></div>
    <small style="color: var(--text-muted);">Met task level criteria</small>
  </div>
  <div class="card">
    <div class="wallet-label">Total Commission Paid</div>
    <div class="wallet-amount" style="color: #10b981;"><?= formatCurrency($totRewardedAmount) ?></div>
    <small style="color: var(--text-muted);">Disbursed to referrers</small>
  </div>
</div>

<!-- Referrals Table -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Referral Connections (Latest 50)</h2>
  </div>

  <?php if (empty($referrals)): ?>
    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
      <i class="bi bi-diagram-3" style="font-size: 2.5rem; display: block; margin-bottom: 8px;"></i>
      <p>No referral linkages recorded yet.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Referrer (Inviter)</th>
            <th>Referred User (New Member)</th>
            <th>Levels Completed</th>
            <th>Status</th>
            <th>Commission</th>
            <th>Joined Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($referrals as $ref): ?>
          <tr>
            <td>
              <div style="font-weight: 700; color: var(--dark);"><?= e(!empty($ref['referrer_name']) ? $ref['referrer_name'] : 'Member') ?></div>
              <a href="<?= BASE_URL ?>/admin/user.php?id=<?= $ref['referrer_id'] ?>" style="font-size: 0.88rem;">
                <?= e($ref['referrer_email']) ?>
              </a>
              <div style="font-size: 0.8rem; color: var(--text-muted);"><?= e($ref['referrer_phone']) ?></div>
            </td>
            <td>
              <div style="font-weight: 700; color: var(--dark);"><?= e(!empty($ref['referred_name']) ? $ref['referred_name'] : 'New Member') ?></div>
              <a href="<?= BASE_URL ?>/admin/user.php?id=<?= $ref['referred_id'] ?>" style="font-size: 0.88rem;">
                <?= e($ref['referred_email']) ?>
              </a>
              <div style="font-size: 0.8rem; color: var(--text-muted);"><?= e($ref['referred_phone']) ?></div>
            </td>
            <td>
              <strong><?= (int)$ref['referred_completed_levels'] ?></strong> levels
            </td>
            <td>
              <span class="badge badge-<?= $ref['status'] === 'rewarded' ? 'success' : 'warning' ?>">
                <?= e($ref['status']) ?>
              </span>
            </td>
            <td style="font-weight: 700; color: <?= $ref['status'] === 'rewarded' ? 'var(--primary)' : 'var(--text-muted)' ?>;">
              <?= $ref['status'] === 'rewarded' ? formatCurrency($ref['reward_amount']) : 'Pending' ?>
            </td>
            <td><?= formatDate($ref['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
