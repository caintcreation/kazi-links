<?php
// jobs.php - All Tasks listing page matching reference site
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Jobs — KaziLink";
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$tasks = $db->query("SELECT * FROM tasks WHERE status = 'active' ORDER BY order_index ASC")->fetchAll();
?>

<!-- App Navigation Bar -->
<div class="surface" style="border-radius: 0; border-top: none; border-left: none; border-right: none; margin-bottom: 2rem;">
  <div style="max-width: 64rem; margin: 0 auto; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between;">
    <a href="<?= BASE_URL ?>/index.php" style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 800;">
      kazi<span class="text-accent">link</span>jobs
    </a>
    <?php if ($currentUser): ?>
      <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-ghost" style="padding: 0.4rem 1rem; font-size: 0.85rem;">Dashboard</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/login.php" class="btn btn-ghost" style="padding: 0.4rem 1rem; font-size: 0.85rem;">Log in</a>
    <?php endif; ?>
  </div>
</div>

<div style="max-width: 64rem; margin: 0 auto; padding: 0 1.25rem 4rem;">
  
  <div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; font-weight: 800;">Five jobs. Six levels each.</h1>
    <p style="color: var(--muted-foreground); margin-top: 0.5rem; max-width: 36rem;">
      Answer 10 simple questions per level and earn rewards for successful completion.
    </p>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
    <?php foreach ($tasks as $task): 
      $userCompLevels = 0;
      if ($currentUser) {
        $uStmt = $db->prepare("SELECT COUNT(*) FROM user_level_progress ulp JOIN levels l ON ulp.level_id = l.id WHERE ulp.user_id = ? AND l.task_id = ? AND ulp.status = 'completed'");
        $uStmt->execute([$currentUser['id'], $task['id']]);
        $userCompLevels = (int)$uStmt->fetchColumn();
      }
      $pct = round(($userCompLevels / 6) * 100);
    ?>
    <div class="surface" style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
      <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
          <span style="border-radius: 0.75rem; background: var(--secondary); padding: 0.625rem; display: inline-flex;">
            <i class="bi <?= e($task['icon']) ?>" style="color: var(--primary); font-size: 1.5rem;"></i>
          </span>
          <div>
            <div style="font-weight: 700; font-size: 1.15rem;"><?= e($task['name']) ?></div>
            <small style="color: var(--muted-foreground);">6 Levels &middot; Earn Sh 100/lvl</small>
          </div>
        </div>

        <p style="color: var(--muted-foreground); font-size: 0.9rem; margin-bottom: 1rem;">
          <?= e($task['description']) ?>
        </p>

        <?php if ($currentUser): ?>
          <div style="display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 600; color: var(--muted-foreground);">
            <span>Progress: <?= $userCompLevels ?> / 6</span>
            <span><?= $pct ?>%</span>
          </div>
          <div style="background: var(--muted); border-radius: 9999px; height: 6px; overflow: hidden; margin: 0.5rem 0 1.25rem;">
            <div style="background: var(--primary); height: 100%; width: <?= $pct ?>%;"></div>
          </div>
        <?php endif; ?>
      </div>

      <div>
        <a href="<?= BASE_URL ?>/job.php?slug=<?= urlencode($task['slug']) ?>" class="btn btn-primary btn-block" style="padding: 0.65rem;">
          <?= $userCompLevels > 0 ? 'Continue Job' : 'View Job' ?> &rarr;
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($currentUser): ?>
    <div class="surface" style="margin-top: 1.5rem; padding: 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
      <div>
        <strong><i class="bi bi-headset"></i> Need help with a job?</strong>
        <div style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.25rem;">Contact customer care for assistance.</div>
      </div>
      <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline">Contact Support</a>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
