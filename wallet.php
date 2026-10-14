<?php
// wallet.php - Wallet & M-Pesa Withdrawal interface with dynamic 8-task completion validation
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$user = getLoggedInUser();
$db = getDB();
$wallet = getUserWallet($user['id']);

$pageTitle = "Wallet & Withdrawals — KaziLink";
require_once __DIR__ . '/includes/header.php';

$minWithdrawal = (float)getSetting('minimum_withdrawal', '2500.00');

// Completed task levels count
$taskCountStmt = $db->prepare("SELECT COUNT(*) FROM user_level_progress WHERE user_id = ? AND status = 'completed'");
$taskCountStmt->execute([$user['id']]);
$userCompletedTasks = (int)$taskCountStmt->fetchColumn();

$wStmt = $db->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$wStmt->execute([$user['id']]);
$userWithdrawals = $wStmt->fetchAll();
?>

<!-- App Navigation Bar -->
<div class="surface" style="border-radius: 0; border-top: none; border-left: none; border-right: none; margin-bottom: 2rem;">
  <div style="max-width: 64rem; margin: 0 auto; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between;">
    <a href="<?= BASE_URL ?>/index.php" style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 800;">
      kazi<span class="text-accent">link</span>jobs
    </a>
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-ghost" style="padding: 0.4rem 1rem; font-size: 0.85rem;">
      &larr; Dashboard
    </a>
  </div>
</div>

<div style="max-width: 64rem; margin: 0 auto; padding: 0 1.25rem 4rem;">
  
  <div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; font-weight: 800;">Wallet & M-Pesa Cashout</h1>
    <p style="color: var(--muted-foreground); font-size: 0.95rem;">
      Withdraw directly to your Safaricom M-Pesa line once you reach Sh <?= number_format($minWithdrawal) ?>.
    </p>
  </div>

  <!-- Wallet Grid -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2.5rem;">
    <div class="surface" style="padding: 1.5rem; background: var(--gradient-hero); color: var(--ink-foreground); border: none;">
      <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; opacity: 0.85;">Available Balance</div>
      <div style="font-size: 2rem; font-weight: 800; margin-top: 0.25rem;">Sh <?= number_format($wallet['balance'], 2) ?></div>
      <small style="opacity: 0.85;">Cleared for M-Pesa transfer</small>
    </div>

    <div class="surface" style="padding: 1.5rem;">
      <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; color: var(--muted-foreground);">Pending Cashout</div>
      <div style="font-size: 2rem; font-weight: 800; color: oklch(50% 0.15 85); margin-top: 0.25rem;">Sh <?= number_format($wallet['pending_balance'], 2) ?></div>
      <small style="color: var(--muted-foreground);">Under verification</small>
    </div>

    <div class="surface" style="padding: 1.5rem;">
      <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; color: var(--muted-foreground);">Lifetime Earnings</div>
      <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-top: 0.25rem;">Sh <?= number_format($wallet['total_earned'], 2) ?></div>
      <small style="color: var(--muted-foreground);">Jobs & referrals</small>
    </div>

    <div class="surface" style="padding: 1.5rem;">
      <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; color: var(--muted-foreground);">Total Withdrawn</div>
      <div style="font-size: 2rem; font-weight: 800; margin-top: 0.25rem;">Sh <?= number_format($wallet['total_withdrawn'], 2) ?></div>
      <small style="color: var(--muted-foreground);">Sent to M-Pesa</small>
    </div>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
    
    <!-- Withdrawal Form Surface -->
    <div class="surface" id="withdraw" style="padding: 1.75rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <div>
          <h2 style="font-size: 1.25rem; font-weight: 800;">Withdraw to M-Pesa</h2>
          <small style="color: var(--muted-foreground);">Sent to registered phone number</small>
        </div>
        <span class="badge badge-success">M-Pesa 🇰🇪</span>
      </div>

      <div id="withdrawAlert" class="alert alert-danger" style="display: none;"></div>

      <form id="withdrawalForm" novalidate>
        <?= csrfField() ?>

        <div class="form-group">
          <label class="form-label" for="wdPhone">M-Pesa phone number</label>
          <input type="text" id="wdPhone" name="phone" class="field" value="<?= e($user['phone']) ?>" required>
          <div class="form-text">Must be a valid Safaricom phone line.</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="wdAmount">Amount to withdraw (KES)</label>
          <input type="number" id="wdAmount" name="amount" class="field" min="<?= $minWithdrawal ?>" max="<?= (float)$wallet['balance'] ?>" step="10" placeholder="e.g. 2500" required>
          <div class="form-text">Minimum threshold: <strong>Sh <?= number_format($minWithdrawal) ?></strong></div>
        </div>

        <button type="submit" id="withdrawSubmitBtn" class="btn btn-gold btn-block" style="margin-top: 1.5rem; padding: 0.85rem;" <?= (float)$wallet['balance'] < $minWithdrawal ? 'disabled' : '' ?>>
          Submit M-Pesa Cashout
        </button>

        <?php if ((float)$wallet['balance'] < $minWithdrawal): ?>
          <p style="color: var(--muted-foreground); font-size: 0.85rem; text-align: center; margin-top: 0.75rem;">
            You need at least <strong>Sh <?= number_format($minWithdrawal) ?></strong> to withdraw. Complete more levels to reach this target!
          </p>
        <?php endif; ?>
      </form>
    </div>

    <!-- Payout Requests History -->
    <div class="surface" style="padding: 1.75rem;">
      <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem;">Withdrawal History</h2>

      <?php if (empty($userWithdrawals)): ?>
        <p style="text-align: center; padding: 2rem; color: var(--muted-foreground);">No withdrawal requests yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Reference</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($userWithdrawals as $w): ?>
              <tr>
                <td><code><?= e($w['reference']) ?></code></td>
                <td style="font-weight: 700; color: var(--primary);">Sh <?= number_format($w['net_amount'], 2) ?></td>
                <td>
                  <span class="badge badge-<?= $w['status'] === 'paid' ? 'success' : ($w['status'] === 'pending' ? 'warning' : 'danger') ?>">
                    <?= e($w['status']) ?>
                  </span>
                </td>
                <td><?= formatDate($w['created_at']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const minThreshold = <?= json_encode($minWithdrawal) ?>;
  const userBalance = <?= json_encode((float)$wallet['balance']) ?>;
  const completedTasks = <?= json_encode($userCompletedTasks) ?>;
  const form = document.getElementById('withdrawalForm');
  const alertBox = document.getElementById('withdrawAlert');
  const submitBtn = document.getElementById('withdrawSubmitBtn');

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      alertBox.style.display = 'none';

      // Check the 8 project task levels requirement dynamically upon attempting withdrawal
      if (completedTasks < 8) {
        const remaining = 8 - completedTasks;
        alertBox.innerHTML = `
          <div>
            <strong>Task requirement not met:</strong><br>
            You must complete at least <strong>8 project task levels</strong> before requesting a withdrawal. You have currently completed <strong>${completedTasks} / 8</strong> tasks (${remaining} more task levels required).
            <div style="margin-top: 8px;">
              <a href="<?= BASE_URL ?>/jobs.php" class="btn btn-primary" style="padding: 4px 12px; font-size: 0.8rem; border-radius: 9999px;">Go to Tasks &rarr;</a>
            </div>
          </div>
        `;
        alertBox.style.display = 'flex';
        return;
      }

      const amount = parseFloat(document.getElementById('wdAmount').value);
      if (isNaN(amount) || amount < minThreshold) {
        alertBox.textContent = `Minimum cashout amount is Sh ${minThreshold.toLocaleString()}.`;
        alertBox.style.display = 'flex';
        return;
      }

      if (amount > userBalance) {
        alertBox.textContent = `Amount exceeds your available balance of Sh ${userBalance.toFixed(2)}.`;
        alertBox.style.display = 'flex';
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Processing...';

      const formData = new FormData(form);

      try {
        const res = await fetch('<?= BASE_URL ?>/api/withdrawal.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          showToast(data.message, 'success');
          setTimeout(() => {
            window.location.reload();
          }, 800);
        } else {
          alertBox.innerHTML = data.message || 'Withdrawal failed.';
          alertBox.style.display = 'flex';
          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Submit M-Pesa Cashout';
        }
      } catch (err) {
        alertBox.textContent = 'Network error while requesting withdrawal.';
        alertBox.style.display = 'flex';
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Submit M-Pesa Cashout';
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
