<?php
// admin/levels.php - Level Management & Question Editor
require_once __DIR__ . '/../includes/admin-auth.php';
requireAdminAuth();

$admin = getLoggedInAdmin();
$db = getDB();

$taskId = (int)($_GET['task_id'] ?? 0);
if ($taskId <= 0) {
    header("Location: " . BASE_URL . "/admin/jobs.php");
    exit;
}

$task = $db->prepare("SELECT * FROM tasks WHERE id = ?");
$task->execute([$taskId]);
$task = $task->fetch();

if (!$task) {
    header("Location: " . BASE_URL . "/admin/jobs.php");
    exit;
}

$message = '';
$error = '';

// Handle Level Edit & Question Add
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = "CSRF security check failed.";
    } elseif ($action === 'edit_level') {
        $levelId = (int)$_POST['level_id'];
        $title = trim($_POST['title']);
        $price = (float)$_POST['price'];
        $reward = (float)$_POST['reward'];
        $passingScore = (int)$_POST['passing_score'];
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';

        $db->prepare("UPDATE levels SET title = ?, price = ?, reward = ?, passing_score = ?, status = ? WHERE id = ?")
           ->execute([$title, $price, $reward, $passingScore, $status, $levelId]);

        logAdminAudit($admin['id'], 'level_updated', 'level', $levelId, "Updated price=$price, reward=$reward");
        $message = "Level settings updated successfully.";

    } elseif ($action === 'add_question') {
        $levelId = (int)$_POST['level_id'];
        $qText = trim($_POST['question_text'] ?? '');
        $optA = trim($_POST['option_a'] ?? '');
        $optB = trim($_POST['option_b'] ?? '');
        $optC = trim($_POST['option_c'] ?? '');
        $optD = trim($_POST['option_d'] ?? '');
        $correct = strtoupper(trim($_POST['correct_option'] ?? 'A'));
        $explanation = trim($_POST['explanation'] ?? '');
        $points = (int)($_POST['points'] ?? 10);

        if (empty($qText) || empty($optA) || empty($optB)) {
            $error = "Question text and at least Options A and B are required.";
        } else {
            $insQ = $db->prepare("INSERT INTO questions (level_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insQ->execute([$levelId, $qText, $optA, $optB, $optC, $optD, $correct, $explanation, $points]);
            logAdminAudit($admin['id'], 'question_added', 'level', $levelId, "Added question to level $levelId");
            $message = "New question added to Level!";
        }
    } elseif ($action === 'delete_question') {
        $qId = (int)$_POST['question_id'];
        $db->prepare("DELETE FROM questions WHERE id = ?")->execute([$qId]);
        logAdminAudit($admin['id'], 'question_deleted', 'question', $qId, "Deleted question #$qId");
        $message = "Question deleted.";
    }
}

// Fetch Levels with question counts
$lvlStmt = $db->prepare("
    SELECT l.*, COUNT(q.id) as question_count 
    FROM levels l 
    LEFT JOIN questions q ON l.id = q.level_id 
    WHERE l.task_id = ? 
    GROUP BY l.id 
    ORDER BY l.level_number ASC
");
$lvlStmt->execute([$taskId]);
$levels = $lvlStmt->fetchAll();

// Active Level for Question inspection
$activeLevelId = (int)($_GET['level_id'] ?? ($levels[0]['id'] ?? 0));
$currentQuestions = [];
if ($activeLevelId > 0) {
    $qStmt = $db->prepare("SELECT * FROM questions WHERE level_id = ? ORDER BY id ASC");
    $qStmt->execute([$activeLevelId]);
    $currentQuestions = $qStmt->fetchAll();
}

$adminTitle = "Levels & Questions: " . $task['name'];
require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="margin-bottom: 20px;">
  <a href="<?= BASE_URL ?>/admin/jobs.php" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left"></i> Back to Task Tracks</a>
</div>

<?php if ($message): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<!-- Levels Overview Table -->
<div class="card" style="margin-bottom: 30px;">
  <div class="card-header">
    <h2 class="card-title"><?= e($task['name']) ?> &mdash; Levels Configuration</h2>
  </div>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Level</th>
          <th>Title</th>
          <th>Unlock Price</th>
          <th>Reward</th>
          <th>Passing Mark</th>
          <th>Questions</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($levels as $l): ?>
        <tr style="<?= $l['id'] == $activeLevelId ? 'background: #eff6ff;' : '' ?>">
          <td><strong>Level <?= $l['level_number'] ?></strong></td>
          <td><?= e($l['title']) ?></td>
          <td><?= (float)$l['price'] == 0 ? '<span class="badge badge-warning">FREE</span>' : formatCurrency($l['price']) ?></td>
          <td style="color: var(--primary); font-weight: 700;"><?= formatCurrency($l['reward']) ?></td>
          <td><?= (int)$l['passing_score'] ?>%</td>
          <td><strong><?= (int)$l['question_count'] ?></strong> questions</td>
          <td>
            <span class="badge badge-<?= $l['status'] === 'active' ? 'success' : 'secondary' ?>">
              <?= e($l['status']) ?>
            </span>
          </td>
          <td>
            <div style="display: flex; gap: 6px;">
              <a href="?task_id=<?= $taskId ?>&level_id=<?= $l['id'] ?>" class="btn btn-sm <?= $l['id'] == $activeLevelId ? 'btn-primary' : 'btn-outline' ?>">
                <i class="bi bi-question-square"></i> Questions
              </a>
              <button type="button" class="btn btn-sm btn-outline edit-level-btn"
                      data-id="<?= $l['id'] ?>"
                      data-num="<?= $l['level_number'] ?>"
                      data-title="<?= e($l['title']) ?>"
                      data-price="<?= $l['price'] ?>"
                      data-reward="<?= $l['reward'] ?>"
                      data-score="<?= $l['passing_score'] ?>"
                      data-status="<?= $l['status'] ?>">
                <i class="bi bi-pencil"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Questions Management for Selected Level -->
<?php if ($activeLevelId > 0): ?>
<div class="card">
  <div class="card-header">
    <div>
      <h2 class="card-title">Questions for Level ID #<?= $activeLevelId ?> (<?= count($currentQuestions) ?>)</h2>
      <small style="color: var(--text-muted);">Ensure at least 5-10 questions per active level for balanced user assessment</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addQuestionModal');">
      <i class="bi bi-plus-lg"></i> Add New Question
    </button>
  </div>

  <?php if (empty($currentQuestions)): ?>
    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
      <i class="bi bi-patch-question" style="font-size: 2.5rem; display: block; margin-bottom: 8px;"></i>
      <p>No questions added to this level yet. Click "Add New Question" above.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Question Prompt</th>
            <th>Options</th>
            <th>Correct</th>
            <th>Points</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($currentQuestions as $idx => $q): ?>
          <tr>
            <td><?= $idx + 1 ?></td>
            <td style="max-width: 320px;">
              <strong><?= e($q['question_text']) ?></strong>
              <?php if (!empty($q['explanation'])): ?>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Explanation: <?= e($q['explanation']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size: 0.82rem;">
              <div>A: <?= e($q['option_a']) ?></div>
              <div>B: <?= e($q['option_b']) ?></div>
              <div>C: <?= e($q['option_c']) ?></div>
              <div>D: <?= e($q['option_d']) ?></div>
            </td>
            <td>
              <span class="badge badge-success" style="font-size: 0.95rem; font-weight: 700;">
                <?= e($q['correct_option']) ?>
              </span>
            </td>
            <td><?= (int)$q['points'] ?> pts</td>
            <td>
              <form method="POST" action="" onsubmit="return confirm('Delete this question permanently?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_question">
                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Edit Level Modal -->
<div class="modal-backdrop" id="editLevelModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700;">Edit Level Settings</h3>
      <button type="button" class="modal-close">&times;</button>
    </div>

    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="edit_level">
      <input type="hidden" name="level_id" id="editLvlId">

      <div class="form-group">
        <label class="form-label">Level Title</label>
        <input type="text" name="title" id="editLvlTitle" class="form-control" required>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label class="form-label">Unlock Price (KES)</label>
          <input type="number" step="0.01" name="price" id="editLvlPrice" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Completion Reward (KES)</label>
          <input type="number" step="0.01" name="reward" id="editLvlReward" class="form-control" required>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label class="form-label">Passing Score (%)</label>
          <input type="number" name="passing_score" id="editLvlScore" class="form-control" min="10" max="100" required>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" id="editLvlStatus" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>

      <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save Level Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Question Modal -->
<div class="modal-backdrop" id="addQuestionModal">
  <div class="modal-card" style="max-width: 600px;">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700;">Add Question to Level</h3>
      <button type="button" class="modal-close">&times;</button>
    </div>

    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_question">
      <input type="hidden" name="level_id" value="<?= $activeLevelId ?>">

      <div class="form-group">
        <label class="form-label">Question Text</label>
        <textarea name="question_text" class="form-control" rows="2" placeholder="e.g. What is the standard tenant refundable deposit?" required></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Option A</label>
        <input type="text" name="option_a" class="form-control" placeholder="Option A text" required>
      </div>

      <div class="form-group">
        <label class="form-label">Option B</label>
        <input type="text" name="option_b" class="form-control" placeholder="Option B text" required>
      </div>

      <div class="form-group">
        <label class="form-label">Option C</label>
        <input type="text" name="option_c" class="form-control" placeholder="Option C text" required>
      </div>

      <div class="form-group">
        <label class="form-label">Option D</label>
        <input type="text" name="option_d" class="form-control" placeholder="Option D text" required>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label class="form-label">Correct Option</label>
          <select name="correct_option" class="form-control" required>
            <option value="A">Option A</option>
            <option value="B">Option B</option>
            <option value="C">Option C</option>
            <option value="D">Option D</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Points</label>
          <input type="number" name="points" class="form-control" value="10" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Explanation (Optional)</label>
        <input type="text" name="explanation" class="form-control" placeholder="Short reason why the selected option is correct">
      </div>

      <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save Question</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.edit-level-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('editLvlId').value = btn.getAttribute('data-id');
      document.getElementById('editLvlTitle').value = btn.getAttribute('data-title');
      document.getElementById('editLvlPrice').value = btn.getAttribute('data-price');
      document.getElementById('editLvlReward').value = btn.getAttribute('data-reward');
      document.getElementById('editLvlScore').value = btn.getAttribute('data-score');
      document.getElementById('editLvlStatus').value = btn.getAttribute('data-status');
      openModal('editLevelModal');
    });
  });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
