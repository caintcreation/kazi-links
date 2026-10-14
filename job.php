<?php
// job.php - Single Task view showing 6 levels with lock/unlock states
require_once __DIR__ . '/includes/auth.php';

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
    header("Location: " . BASE_URL . "/jobs.php");
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM tasks WHERE slug = ? AND status = 'active'");
$stmt->execute([$slug]);
$task = $stmt->fetch();

if (!$task) {
    header("Location: " . BASE_URL . "/jobs.php");
    exit;
}

$pageTitle = $task['name'] . " — KaziLink";
require_once __DIR__ . '/includes/header.php';

$lvlStmt = $db->prepare("SELECT * FROM levels WHERE task_id = ? AND status = 'active' ORDER BY level_number ASC");
$lvlStmt->execute([$task['id']]);
$levels = $lvlStmt->fetchAll();

$userProgress = [];
if ($currentUser) {
    $pStmt = $db->prepare("SELECT level_id, status, score, attempts FROM user_level_progress WHERE user_id = ?");
    $pStmt->execute([$currentUser['id']]);
    while ($r = $pStmt->fetch()) {
        $userProgress[$r['level_id']] = $r;
    }
}

$userWalletBalance = $currentUser ? (float)getUserWallet($currentUser['id'])['balance'] : 0.00;
?>

<!-- App Navigation Bar -->
<div class="surface" style="border-radius: 0; border-top: none; border-left: none; border-right: none; margin-bottom: 2rem;">
  <div style="max-width: 64rem; margin: 0 auto; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between;">
    <a href="<?= BASE_URL ?>/index.php" style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 800;">
      kazi<span class="text-accent">link</span>jobs
    </a>
    <a href="<?= BASE_URL ?>/jobs.php" class="btn btn-ghost" style="padding: 0.4rem 1rem; font-size: 0.85rem;">
      &larr; Back to jobs
    </a>
  </div>
</div>

<div style="max-width: 64rem; margin: 0 auto; padding: 0 1.25rem 4rem;">
  
  <!-- Task Header Surface -->
  <div class="surface" style="padding: 1.75rem; margin-bottom: 2rem;">
    <div style="display: flex; gap: 1rem; align-items: center;">
      <span style="border-radius: 1rem; background: var(--secondary); padding: 0.85rem; display: inline-flex;">
        <i class="bi <?= e($task['icon']) ?>" style="color: var(--primary); font-size: 1.75rem;"></i>
      </span>
      <div>
        <h1 style="font-size: 1.85rem; font-weight: 800;"><?= e($task['name']) ?></h1>
        <p style="color: var(--muted-foreground); margin-top: 0.25rem; font-size: 0.95rem;">
          <?= e($task['description']) ?>
        </p>
      </div>
    </div>
    
    <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px dashed var(--border); display: flex; gap: 2rem; flex-wrap: wrap; font-size: 0.875rem;">
      <div><strong>6 Levels</strong> in total</div>
      <div>Complete each level to continue</div>
      <div>Reward: <strong>KSh 100</strong> per level</div>
    </div>
  </div>

  <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 1rem;">Choose a level</h2>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
    <?php 
    $prevCompleted = true;
    foreach ($levels as $l): 
      $lId = (int)$l['id'];
      $lNum = (int)$l['level_number'];
      $price = (float)$l['price'];
      $prog = $userProgress[$lId] ?? null;

      $isDone = ($prog && $prog['status'] === 'completed');
      $isOpen = ($prog && $prog['status'] === 'unlocked');
      $isFree = ($price <= 0);
      $canUnlock = ($lNum === 1 || $prevCompleted);
      $prevCompleted = $isDone;
    ?>
    <div class="surface" style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; border-left: 4px solid <?= $isDone ? 'var(--primary)' : ($isOpen ? 'var(--accent)' : 'var(--border)') ?>; <?= (!$isDone && !$isOpen && !$canUnlock) ? 'opacity: 0.72; background: var(--muted);' : '' ?>">
      
      <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
          <span style="font-weight: 800; font-size: 1.15rem;">Level <?= $lNum ?></span>
          <?php if ($isDone): ?>
            <span class="badge badge-success">Completed &middot; Sh 100 Earned</span>
          <?php elseif ($isOpen): ?>
            <span class="badge badge-info">Unlocked</span>
          <?php else: ?>
            <span class="badge badge-secondary"><i class="bi bi-lock-fill"></i> Locked</span>
          <?php endif; ?>
        </div>

        <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-bottom: 1rem;">
          10 multiple-choice questions. Pass with 70% or more to receive KSh 100.
        </p>
      </div>

      <div>
        <?php if (!$currentUser): ?>
          <a href="<?= BASE_URL ?>/login.php" class="btn btn-ghost btn-block" style="padding: 0.5rem;">Log in to start</a>
        <?php elseif ($isDone): ?>
          <a href="<?= BASE_URL ?>/level.php?id=<?= $lId ?>" class="btn btn-ghost btn-block" style="padding: 0.5rem;">Review Questions</a>
        <?php elseif ($isOpen): ?>
          <a href="<?= BASE_URL ?>/level.php?id=<?= $lId ?>" class="btn btn-primary btn-block" style="padding: 0.5rem;">Start Level <?= $lNum ?></a>
        <?php elseif ($canUnlock): ?>
          <?php if ($isFree): ?>
          <a href="<?= BASE_URL ?>/level.php?id=<?= $lId ?>" class="btn btn-primary btn-block" style="padding: 0.5rem;">
            Start Level <?= $lNum ?>
          </a>
          <?php else: ?>
          <button type="button" class="btn btn-gold btn-block unlock-btn" 
                  data-level-id="<?= $lId ?>" 
                  data-price="<?= $price ?>" 
                  data-level-num="<?= $lNum ?>" 
                  style="padding: 0.5rem;">
            Unlock Level
          </button>
          <?php endif; ?>
        <?php else: ?>
          <button type="button" class="btn btn-ghost btn-block" disabled style="padding: 0.5rem; opacity: 0.5;">
            <i class="bi bi-lock-fill"></i> Locked
          </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</div>

<!-- Unlock Modal -->
<div class="modal-backdrop" id="unlockModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.25rem; font-weight: 800;">Unlock Level</h3>
      <button type="button" class="modal-close">&times;</button>
    </div>
    <div id="unlockModalContent" style="margin-bottom: 1.5rem; font-size: 0.95rem;"></div>
    <div id="unlockModalActions" style="display: flex; gap: 0.75rem; justify-content: flex-end;">
      <button type="button" class="btn btn-ghost" data-modal-close id="cancelUnlockBtn">Cancel</button>
      <button type="button" class="btn btn-primary" id="confirmUnlockBtn">Proceed</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  let targetLevelId = null;
  let targetPrice = 0;
  let targetLvlNum = 1;
  let pollInterval = null;
  let countdownTimer = null;
  let currentReference = null;

  const content = document.getElementById('unlockModalContent');
  const confirmBtn = document.getElementById('confirmUnlockBtn');
  const cancelBtn = document.getElementById('cancelUnlockBtn');
  const modalActions = document.getElementById('unlockModalActions');

  function cleanupTimers() {
    if (pollInterval) {
      clearInterval(pollInterval);
      pollInterval = null;
    }
    if (countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }
  }

  // Cleanup if modal is closed
  document.querySelectorAll('#unlockModal [data-modal-close], #unlockModal .modal-close').forEach(el => {
    el.addEventListener('click', () => {
      cleanupTimers();
    });
  });

  document.querySelectorAll('.unlock-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      cleanupTimers();
      targetLevelId = btn.getAttribute('data-level-id');
      targetPrice = parseFloat(btn.getAttribute('data-price')) || 0;
      targetLvlNum = btn.getAttribute('data-level-num') || '1';

      modalActions.style.display = 'flex';
      confirmBtn.disabled = false;
      confirmBtn.style.display = 'inline-flex';
      cancelBtn.style.display = 'inline-flex';
      confirmBtn.innerHTML = 'Proceed';

      if (targetPrice <= 0) {
        content.innerHTML = `<p>This level is free to start. Click Proceed to begin immediately.</p>`;
      } else {
        content.innerHTML = `
          <p>Unlocking <strong>Level ${targetLvlNum}</strong> costs <strong>Sh ${targetPrice.toFixed(2)}</strong>.</p>
          <div style="background: var(--muted); padding: 1rem; border-radius: var(--radius-lg); margin-top: 1rem;">
            <div style="font-weight: 700; color: var(--foreground); margin-bottom: 0.25rem;">
              <i class="bi bi-phone"></i> Direct M-Pesa STK Push
            </div>
            <div style="font-size: 0.88rem; color: var(--muted-foreground);">
              An M-Pesa payment prompt for <strong>Sh ${targetPrice.toFixed(2)}</strong> will be sent to your registered phone.
            </div>
            <p style="color: var(--muted-foreground); margin-top: 0.5rem; font-size: 0.85rem;">
              Enter your M-Pesa PIN, then wait here while we unlock your level automatically.
            </p>
          </div>
        `;
      }

      openModal('unlockModal');
    });
  });

  async function checkPaymentStatus(reference, isManual = false) {
    try {
      const url = '<?= BASE_URL ?>/api/payment-status.php?reference=' + encodeURIComponent(reference) + (isManual ? '&manual=1' : '');
      const statusRes = await fetch(url);
      const statusData = await statusRes.json();
      if (statusData.status === 'paid') {
        cleanupTimers();
        content.innerHTML = `
          <div style="text-align: center; padding: 1.5rem 0;">
            <div style="font-size: 2.5rem; color: #10b981; margin-bottom: 0.5rem;">
              <i class="bi bi-check-circle-fill"></i>
            </div>
            <h4 style="font-size: 1.25rem; font-weight: 800; color: #065f46; margin-bottom: 0.25rem;">Payment Confirmed!</h4>
            <p style="color: var(--muted-foreground); font-size: 0.9rem;">Level ${targetLvlNum} has been unlocked successfully. Redirecting you...</p>
          </div>
        `;
        modalActions.style.display = 'none';
        showToast('Payment confirmed! Level unlocked.', 'success');
        setTimeout(() => {
          window.location.href = statusData.redirect || ('<?= BASE_URL ?>/level.php?id=' + targetLevelId);
        }, 1500);
        return true;
      } else if (statusData.status === 'failed') {
        cleanupTimers();
        showFailureView(
          statusData.message || 'Payment was not completed. If you did not enter your PIN or cancelled the prompt, please try again.',
          statusData.title || (statusData.reason === 'cancelled' ? 'Payment Cancelled on Phone' : 'Payment Failed'),
          statusData.reason || 'failed'
        );
        return true;
      }
    } catch (e) {
      console.warn('Status poll error:', e);
    }
    return false;
  }

  function showFailureView(msg, title = 'Payment Failed or Timed Out', reason = 'failed') {
    const isCancel = reason === 'cancelled';
    const iconClass = isCancel ? 'bi-x-circle-fill' : 'bi-exclamation-triangle-fill';
    const bg = isCancel ? '#fff7ed' : '#fef2f2';
    const border = isCancel ? '#ffedd5' : '#fecaca';
    const textTitle = isCancel ? '#9a3412' : '#991b1b';
    const textMsg = isCancel ? '#c2410c' : '#b91c1c';
    const iconColor = isCancel ? '#f97316' : '#ef4444';

    content.innerHTML = `
      <div style="background: ${bg}; border: 1px solid ${border}; border-radius: var(--radius-lg); padding: 1.25rem; margin-bottom: 1rem;">
        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
          <i class="bi ${iconClass}" style="color: ${iconColor}; font-size: 1.35rem; margin-top: 1px; flex-shrink: 0;"></i>
          <div>
            <div style="font-weight: 700; color: ${textTitle}; margin-bottom: 0.25rem; font-size: 1rem;">${title}</div>
            <div style="font-size: 0.88rem; color: ${textMsg}; line-height: 1.5;">${msg}</div>
          </div>
        </div>
      </div>
      <p style="font-size: 0.85rem; color: var(--muted-foreground);">
        Ensure your phone screen is unlocked, then click below to resend the M-Pesa prompt.
      </p>
    `;
    modalActions.style.display = 'flex';
    confirmBtn.disabled = false;
    confirmBtn.style.display = 'inline-flex';
    confirmBtn.innerHTML = '<i class="bi bi-arrow-repeat" style="margin-right: 0.35rem;"></i> Try Again (Resend STK)';
  }

  async function initiateUnlock(isResend = false) {
    if (!targetLevelId) return;

    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Sending prompt...';

    const formData = new FormData();
    formData.append('level_id', targetLevelId);
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
    if (isResend) {
      formData.append('force_resend', '1');
    }

    try {
      const res = await fetch('<?= BASE_URL ?>/api/unlock-level.php', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();

      if (data.success && data.status === 'pending') {
        currentReference = data.reference;
        showToast(data.message || 'M-Pesa prompt sent. Please check your phone.', 'success');

        // Generous humble time: 120 seconds (2 minutes)
        let secondsRemaining = 120;

        content.innerHTML = `
          <div style="text-align: center; padding: 1rem 0;">
            <div class="spinner" style="width: 2.5rem; height: 2.5rem; border: 3px solid rgba(0,0,0,0.1); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1rem;"></div>
            <h4 style="font-weight: 800; font-size: 1.15rem; margin-bottom: 0.25rem;">M-Pesa Prompt Sent</h4>
            <p style="color: var(--muted-foreground); font-size: 0.88rem; margin-bottom: 1rem;">
              An M-Pesa prompt for <strong>Sh ${targetPrice.toFixed(2)}</strong> has been sent to your phone. Enter your <strong>M-Pesa PIN</strong> to complete payment.
            </p>
            <div style="display: inline-block; background: var(--muted); padding: 0.5rem 1rem; border-radius: 999px; font-weight: 700; font-size: 0.88rem; color: var(--foreground); margin-bottom: 1rem;" id="timerBadge">
              <i class="bi bi-stopwatch"></i> Waiting for PIN: <span id="countdownSecs">${secondsRemaining}</span>s
            </div>
            <div>
              <button type="button" class="btn btn-sm btn-ghost" id="manualVerifyBtn" style="border: 1px solid var(--border);">
                <i class="bi bi-check2-circle"></i> I Have Entered PIN (Verify Now)
              </button>
            </div>
          </div>
        `;

        modalActions.style.display = 'flex';
        confirmBtn.style.display = 'none';

        document.getElementById('manualVerifyBtn').addEventListener('click', async () => {
          const btn = document.getElementById('manualVerifyBtn');
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Checking M-Pesa...';
          const paid = await checkPaymentStatus(currentReference, true);
          if (!paid) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle"></i> Still checking... Verify Now';
          }
        });

        // Countdown timer giving ample 120 seconds
        countdownTimer = setInterval(() => {
          secondsRemaining--;
          const el = document.getElementById('countdownSecs');
          if (el) el.textContent = secondsRemaining;

          if (secondsRemaining <= 0) {
            cleanupTimers();
            showFailureView('No payment was detected within 2 minutes. If you did not enter your PIN or cancelled the prompt, click Try Again.', 'Payment Timed Out', 'timeout');
          }
        }, 1000);

        // Polling loop every 3 seconds
        pollInterval = setInterval(async () => {
          await checkPaymentStatus(currentReference, false);
        }, 3000);

      } else if (data.success && data.redirect) {
        showToast(data.message || 'Level unlocked!', 'success');
        window.location.href = data.redirect;
      } else {
        showFailureView(data.message || 'Could not initiate level unlock.', 'Request Failed', 'failed');
      }
    } catch (e) {
      showFailureView('Network or server error while initiating payment. Please try again.', 'Connection Error', 'failed');
    }
  }

  confirmBtn.addEventListener('click', () => {
    initiateUnlock(confirmBtn.textContent.includes('Try Again') || confirmBtn.textContent.includes('Resend'));
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
