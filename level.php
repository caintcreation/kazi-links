<?php
// level.php - Interactive Question Runner matching reference site card aesthetics
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$user = getLoggedInUser();
$levelId = (int)($_GET['id'] ?? 0);

if ($levelId <= 0) {
    header("Location: " . BASE_URL . "/jobs.php");
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT l.*, t.name as task_name, t.slug as task_slug 
                      FROM levels l 
                      JOIN tasks t ON l.task_id = t.id 
                      WHERE l.id = ? AND l.status = 'active'");
$stmt->execute([$levelId]);
$level = $stmt->fetch();

if (!$level) {
    header("Location: " . BASE_URL . "/jobs.php");
    exit;
}

$pStmt = $db->prepare("SELECT * FROM user_level_progress WHERE user_id = ? AND level_id = ?");
$pStmt->execute([$user['id'], $levelId]);
$progress = $pStmt->fetch();

if ((float)$level['price'] > 0 && (!$progress || $progress['status'] === 'locked')) {
    header("Location: " . BASE_URL . "/job.php?slug=" . urlencode($level['task_slug']));
    exit;
}

$qStmt = $db->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d FROM questions WHERE level_id = ? AND status = 'active' ORDER BY id ASC");
$qStmt->execute([$levelId]);
$questions = $qStmt->fetchAll();

$pageTitle = $level['task_name'] . " — Level " . $level['level_number'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- App Navigation Bar -->
<div class="surface" style="border-radius: 0; border-top: none; border-left: none; border-right: none; margin-bottom: 2rem;">
  <div style="max-width: 64rem; margin: 0 auto; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between;">
    <a href="<?= BASE_URL ?>/job.php?slug=<?= urlencode($level['task_slug']) ?>" class="btn btn-ghost" style="padding: 0.4rem 1rem; font-size: 0.85rem;">
      &larr; Exit Level
    </a>
    <span style="font-weight: 700; font-size: 0.9rem;">
      <?= e($level['task_name']) ?> &middot; Level <?= $level['level_number'] ?>
    </span>
  </div>
</div>

<div style="max-width: 44rem; margin: 0 auto; padding: 0 1.25rem 5rem;">
  
  <div class="surface" style="padding: 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
      <span class="badge badge-info" style="margin-bottom: 0.25rem;">10 Questions</span>
      <h1 style="font-size: 1.35rem; font-weight: 800;"><?= e($level['title']) ?></h1>
      <small style="color: var(--muted-foreground);">Passing score: 70% &middot; Reward: KSh 100</small>
    </div>
    <div style="text-align: right;">
      <span id="answeredCounter" style="font-weight: 700; color: var(--primary); font-size: 1.1rem;">0 / <?= count($questions) ?></span>
    </div>
  </div>

  <form id="levelQuestionsForm">
    <?= csrfField() ?>
    <input type="hidden" name="level_id" value="<?= $levelId ?>">

    <?php foreach ($questions as $idx => $q): ?>
      <div class="surface" style="padding: 1.5rem; margin-bottom: 1rem;" id="question_box_<?= $q['id'] ?>">
        <div style="display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 700; color: var(--muted-foreground); margin-bottom: 0.75rem;">
          <span>QUESTION <?= $idx + 1 ?> OF <?= count($questions) ?></span>
          <span class="badge badge-secondary" id="badge_<?= $q['id'] ?>">Pending</span>
        </div>

        <div style="font-weight: 600; font-size: 1.05rem; margin-bottom: 1.25rem;">
          <?= nl2br(e($q['question_text'])) ?>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.65rem;">
          <?php 
          $opts = ['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']];
          foreach ($opts as $key => $optVal): 
          ?>
            <label class="surface question-option-label" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; cursor: pointer; border-radius: var(--radius-lg); transition: all 0.15s; border-color: var(--border);">
              <input type="radio" 
                     name="answers[<?= $q['id'] ?>]" 
                     value="<?= $key ?>" 
                     style="display: none;" 
                     class="question-radio" 
                     data-qid="<?= $q['id'] ?>">
              <span class="option-letter" style="width: 26px; height: 26px; border-radius: 9999px; background: var(--secondary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem;">
                <?= $key ?>
              </span>
              <span style="flex: 1; font-size: 0.95rem;"><?= e($optVal) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div style="margin-top: 2rem;">
      <button type="submit" id="submitLevelBtn" class="btn btn-gold btn-block" style="padding: 0.85rem; font-size: 1.05rem;">
        Submit Level Answers
      </button>
    </div>
  </form>
</div>

<!-- Result Modal -->
<div class="modal-backdrop" id="resultModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 id="resultModalTitle" style="font-size: 1.35rem; font-weight: 800;">Results</h3>
      <button type="button" class="modal-close">&times;</button>
    </div>
    <div id="resultModalBody" style="text-align: center; margin: 1.5rem 0;"></div>
    <div id="resultModalActions" style="display: flex; gap: 0.75rem; justify-content: center;"></div>
  </div>
</div>

<style>
.question-option-label.selected {
  border-color: var(--primary) !important;
  background: var(--muted) !important;
}
.question-option-label.selected .option-letter {
  background: var(--primary) !important;
  color: #fff !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('levelQuestionsForm');
  if (!form) return;

  const totalQuestions = <?= count($questions) ?>;
  const answeredCounter = document.getElementById('answeredCounter');
  const submitBtn = document.getElementById('submitLevelBtn');

  document.querySelectorAll('.question-radio').forEach(radio => {
    radio.addEventListener('change', () => {
      const qid = radio.getAttribute('data-qid');
      const box = document.getElementById(`question_box_${qid}`);

      box.querySelectorAll('.question-option-label').forEach(item => item.classList.remove('selected'));
      radio.closest('.question-option-label').classList.add('selected');

      const badge = document.getElementById(`badge_${qid}`);
      badge.textContent = 'Answered';
      badge.className = 'badge badge-success';

      const checked = form.querySelectorAll('input[type="radio"]:checked').length;
      answeredCounter.textContent = `${checked} / ${totalQuestions}`;
    });
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const checked = form.querySelectorAll('input[type="radio"]:checked').length;
    if (checked < totalQuestions) {
      showToast(`Please answer all ${totalQuestions} questions before submitting.`, 'error');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Grading Answers...';

    const formData = new FormData(form);

    try {
      const res = await fetch('<?= BASE_URL ?>/api/complete-level.php', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();

      const modalTitle = document.getElementById('resultModalTitle');
      const modalBody = document.getElementById('resultModalBody');
      const modalActions = document.getElementById('resultModalActions');

      if (data.passed) {
        modalTitle.textContent = 'Hongera! Level Passed!';
        modalBody.innerHTML = `
          <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎉</div>
          <h3 style="font-size: 1.5rem; font-weight: 800;">Score: ${data.score}%</h3>
          <p style="color: var(--muted-foreground); margin: 0.5rem 0 1rem;">You have passed this level. KSh 100 has been credited to your account.</p>
          <div class="gold-bg" style="display: inline-block; padding: 0.5rem 1.5rem; border-radius: 9999px; font-weight: 800;">
            + KSh 100 credited to your account
          </div>
        `;
        modalActions.innerHTML = `
          <a href="${data.redirect}" class="btn btn-primary btn-block">Back to Job Overview</a>
        `;
      } else {
        modalTitle.textContent = 'Assessment Not Passed';
        modalBody.innerHTML = `
          <div style="font-size: 3rem; margin-bottom: 0.5rem;">❌</div>
          <h3 style="font-size: 1.5rem; font-weight: 800;">Score: ${data.score}%</h3>
          <p style="color: var(--muted-foreground); margin: 0.5rem 0;">You needed 70% to pass this level.</p>
        `;
        modalActions.innerHTML = `
          <button type="button" class="btn btn-primary" onclick="window.location.reload();">Retry Level</button>
          <a href="<?= BASE_URL ?>/job.php?slug=<?= urlencode($level['task_slug']) ?>" class="btn btn-ghost">Back to Job</a>
        `;
      }

      openModal('resultModal');

    } catch (err) {
      showToast('Error communicating with server.', 'error');
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Submit Level Answers';
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
