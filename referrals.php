<?php
// referrals.php - Referrals page matching reference site (Sh 100 per referral)
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$user = getLoggedInUser();
$db = getDB();

$pageTitle = "Referrals — KaziLink";
require_once __DIR__ . '/includes/header.php';

$referralReward = (float)getSetting('referral_reward', '100.00');

$stmt = $db->prepare("
    SELECT r.*, u.name, u.email, u.phone, u.created_at as registered_date,
           (SELECT COUNT(*) FROM user_level_progress ulp WHERE ulp.user_id = u.id AND ulp.status = 'completed') as completed_levels
    FROM referrals r 
    JOIN users u ON r.referred_id = u.id 
    WHERE r.referrer_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$user['id']]);
$referrals = $stmt->fetchAll();

$totalInvited = count($referrals);
$totalRewarded = 0;
$totalEarnings = 0.00;

foreach ($referrals as $r) {
    if ($r['status'] === 'rewarded') {
        $totalRewarded++;
        $totalEarnings += (float)$r['reward_amount'];
    }
}

$refLink = BASE_URL . "/register.php?ref=" . urlencode($user['referral_code']);
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
    <h1 style="font-size: 2rem; font-weight: 800;">Sh <?= (int)$referralReward ?> per referral</h1>
    <p style="color: var(--muted-foreground); font-size: 0.95rem;">
      Share your personal link and earn for every friend who joins.
    </p>
  </div>

  <!-- Referral Link Surface -->
  <div class="surface" style="padding: 1.75rem; background: var(--gradient-hero); color: var(--ink-foreground); border: none; margin-bottom: 2rem;">
    <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem;">Your Referral Link</h3>
    <p style="opacity: 0.8; font-size: 0.875rem; margin-bottom: 1rem;">
      Friends get a Sh 300 welcome bonus and you get Sh 100.
    </p>

    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
      <input type="text" id="refLinkInput" class="field" value="<?= e($refLink) ?>" readonly style="background: rgba(255,255,255,0.95); font-weight: 700; color: var(--foreground); flex: 1; min-width: 250px;">
      <button type="button" class="btn btn-gold" data-copy="<?= e($refLink) ?>">
        Copy link
      </button>
    </div>

    <div style="margin-top: 1rem; font-size: 0.875rem; opacity: 0.9;">
      Your code: <strong><?= e($user['referral_code']) ?></strong>
    </div>
  </div>

  <!-- Stats Grid -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2.5rem;">
    <div class="surface" style="padding: 1.5rem;">
      <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; color: var(--muted-foreground);">Friends Joined</div>
      <div style="font-size: 2rem; font-weight: 800; margin-top: 0.25rem;"><?= $totalInvited ?></div>
    </div>

    <div class="surface" style="padding: 1.5rem;">
      <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; color: var(--muted-foreground);">Eligible Referrals</div>
      <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-top: 0.25rem;"><?= $totalRewarded ?></div>
    </div>

    <div class="surface" style="padding: 1.5rem;">
      <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; color: var(--muted-foreground);">Referral Earnings</div>
      <div style="font-size: 2rem; font-weight: 800; color: oklch(50% 0.15 85); margin-top: 0.25rem;">Sh <?= number_format($totalEarnings, 2) ?></div>
    </div>
  </div>

  <!-- Referrals List Surface -->
  <div class="surface" style="padding: 1.5rem;">
    <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">Referred Friends</h2>

    <?php if (empty($referrals)): ?>
      <p style="text-align: center; padding: 2rem; color: var(--muted-foreground);">You haven't referred anyone yet. Share your link above!</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Friend's Name</th>
              <th>Email</th>
              <th>Joined</th>
              <th>Status</th>
              <th>Bonus</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($referrals as $ref): ?>
            <tr>
              <td>
                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--foreground);">
                  <i class="bi bi-person-fill" style="color: var(--primary);"></i>
                  <span><?= e(!empty($ref['name']) ? $ref['name'] : (!empty($ref['email']) ? explode('@', $ref['email'])[0] : 'KaziLink Member')) ?></span>
                </div>
              </td>
              <td><?= e(substr($ref['email'], 0, 3) . '***@' . explode('@', $ref['email'])[1]) ?></td>
              <td><?= formatDate($ref['registered_date'], false) ?></td>
              <td>
                <span class="badge badge-<?= $ref['status'] === 'rewarded' ? 'success' : 'warning' ?>">
                  <?= e($ref['status']) ?>
                </span>
              </td>
              <td style="font-weight: 700; color: var(--primary);">
                <?= $ref['status'] === 'rewarded' ? ('Sh ' . number_format($ref['reward_amount'], 2)) : 'Pending' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
