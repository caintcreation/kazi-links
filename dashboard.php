<?php
// dashboard.php - User Dashboard styled with reference site surface cards & typography
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$user = getLoggedInUser();
$db = getDB();

$pageTitle = "Dashboard — KaziLink";
require_once __DIR__ . '/includes/header.php';

$wallet = getUserWallet($user['id']);
$taskStmt = $db->query("SELECT * FROM tasks WHERE status = 'active' ORDER BY order_index ASC");
$tasks = $taskStmt->fetchAll();

$txStmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$txStmt->execute([$user['id']]);
$recentTx = $txStmt->fetchAll();
$registrationNotice = $_SESSION['registration_notice'] ?? null;
unset($_SESSION['registration_notice']);
?>

<!-- App Header -->
<div class="surface" style="border-radius: 0; border-top: none; border-left: none; border-right: none; margin-bottom: 2rem;">
  <div style="max-width: 64rem; margin: 0 auto; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between;">
    <div>
      <a href="<?= BASE_URL ?>/index.php" style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 800; color: var(--foreground);">
        kazi<span class="text-accent">link</span>jobs
      </a>
      <div style="color: var(--muted-foreground); font-size: 0.85rem; margin-top: 0.2rem;">
        Habari, <strong style="color: var(--foreground);"><?= e(!empty($user['name']) ? $user['name'] : explode('@', $user['email'])[0]) ?></strong> 👋
      </div>
    </div>
    <div style="display: flex; align-items: center; gap: 1rem;">
      <a href="<?= BASE_URL ?>/profile.php" style="color: var(--foreground); font-size: 0.85rem; font-weight: 600;">
        <i class="bi bi-gear"></i> Settings
      </a>
      <a href="<?= BASE_URL ?>/contact.php" style="color: var(--primary); font-size: 0.85rem; font-weight: 700;">
        <i class="bi bi-headset"></i> Support
      </a>
      <a href="<?= BASE_URL ?>/logout.php" style="color: var(--destructive); font-size: 0.85rem; font-weight: 600;">Sign out</a>
    </div>
  </div>
</div>

<div style="max-width: 64rem; margin: 0 auto; padding: 0 1.25rem 4rem;">

  <?php if ($registrationNotice): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem;">
      <i class="bi bi-check-circle-fill"></i>
      <?= e($registrationNotice) ?>
    </div>
  <?php endif; ?>
  
  <!-- Wallet Cards -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2.5rem;">
    <div class="surface" style="padding: 1.5rem; background: var(--gradient-hero); color: var(--ink-foreground); border: none;">
      <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; opacity: 0.8;">Available Balance</div>
      <div style="font-size: 2rem; font-weight: 800; margin-top: 0.25rem;">Sh <?= number_format($wallet['balance'], 2) ?></div>
      <div style="margin-top: 1rem;">
        <a href="<?= BASE_URL ?>/wallet.php#withdraw" class="btn btn-gold" style="padding: 0.4rem 1rem; font-size: 0.8rem;">
          Withdraw to M-Pesa
        </a>
      </div>
    </div>

    <div class="surface" style="padding: 1.5rem;">
      <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; color: var(--muted-foreground);">Pending Payouts</div>
      <div style="font-size: 2rem; font-weight: 800; color: oklch(50% 0.15 85); margin-top: 0.25rem;">Sh <?= number_format($wallet['pending_balance'], 2) ?></div>
      <small style="color: var(--muted-foreground);">Under verification</small>
    </div>

    <div class="surface" style="padding: 1.5rem;">
      <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; color: var(--muted-foreground);">Total Earned</div>
      <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-top: 0.25rem;">Sh <?= number_format($wallet['total_earned'], 2) ?></div>
      <small style="color: var(--muted-foreground);">Tasks & referrals</small>
    </div>

    <div class="surface" style="padding: 1.5rem;">
      <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; color: var(--muted-foreground);">Withdrawn</div>
      <div style="font-size: 2rem; font-weight: 800; margin-top: 0.25rem;">Sh <?= number_format($wallet['total_withdrawn'], 2) ?></div>
      <small style="color: var(--muted-foreground);">Sent to M-Pesa</small>
    </div>
  </div>

  <!-- Available Jobs -->
  <div style="margin-bottom: 2.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1rem;">
      <div>
        <h2 style="font-size: 1.5rem; font-weight: 800;">Available Jobs</h2>
        <p style="color: var(--muted-foreground); font-size: 0.875rem;">Complete 10 questions per level to earn Sh 100 per level</p>
      </div>
      <a href="<?= BASE_URL ?>/jobs.php" class="btn btn-ghost" style="padding: 0.4rem 1rem; font-size: 0.8rem;">All Jobs</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
      <?php foreach ($tasks as $task): 
        $userCompStmt = $db->prepare("SELECT COUNT(*) FROM user_level_progress ulp JOIN levels l ON ulp.level_id = l.id WHERE ulp.user_id = ? AND l.task_id = ? AND ulp.status = 'completed'");
        $userCompStmt->execute([$user['id'], $task['id']]);
        $completedCount = (int)$userCompStmt->fetchColumn();
        $pct = round(($completedCount / 6) * 100);
      ?>
      <div class="surface" style="padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
          <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
            <span style="border-radius: 0.75rem; background: var(--secondary); padding: 0.5rem; display: inline-flex;">
              <i class="bi <?= e($task['icon']) ?>" style="color: var(--primary); font-size: 1.25rem;"></i>
            </span>
            <div>
              <div style="font-weight: 700; font-size: 1.05rem;"><?= e($task['name']) ?></div>
              <small style="color: var(--muted-foreground);"><?= $completedCount ?> / 6 levels completed</small>
            </div>
          </div>

          <div style="background: var(--muted); border-radius: 9999px; height: 6px; overflow: hidden; margin: 0.75rem 0;">
            <div style="background: var(--primary); height: 100%; width: <?= $pct ?>%;"></div>
          </div>
        </div>

        <div style="margin-top: 1rem;">
          <a href="<?= BASE_URL ?>/job.php?slug=<?= urlencode($task['slug']) ?>" class="btn btn-primary btn-block" style="padding: 0.5rem;">
            <?= $completedCount > 0 ? 'Continue Job' : 'Start Job' ?> &rarr;
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Recent Ledger Transactions -->
  <div class="surface" style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <div>
        <h3 style="font-size: 1.15rem; font-weight: 700;">Recent Transactions</h3>
        <small style="color: var(--muted-foreground);">Real-time financial audit trail</small>
      </div>
      <a href="<?= BASE_URL ?>/transactions.php" class="btn btn-ghost" style="padding: 0.35rem 0.85rem; font-size: 0.8rem;">View All</a>
    </div>

    <?php if (empty($recentTx)): ?>
      <p style="text-align: center; padding: 2rem; color: var(--muted-foreground);">No transactions recorded yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentTx as $tx): ?>
            <tr>
              <td><code><?= e($tx['reference']) ?></code></td>
              <td><?= e($tx['description']) ?></td>
              <td style="font-weight: 700; color: <?= $tx['direction'] === 'credit' ? 'var(--primary)' : 'var(--destructive)' ?>;">
                <?= $tx['direction'] === 'credit' ? '+' : '-' ?> Sh <?= number_format($tx['amount'], 2) ?>
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
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
