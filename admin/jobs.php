<?php
// admin/jobs.php - Task Categories and Management
require_once __DIR__ . '/../includes/admin-auth.php';
requireAdminAuth();

$admin = getLoggedInAdmin();
$db = getDB();

$message = '';
$error = '';

// Handle Create / Edit Task
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = "CSRF Token mismatch.";
    } elseif ($action === 'create_task') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'bi-clipboard-check');

        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        }

        if (empty($name)) {
            $error = "Task name is required.";
        } else {
            try {
                $ins = $db->prepare("INSERT INTO tasks (name, slug, description, icon, status) VALUES (?, ?, ?, ?, 'active')");
                $ins->execute([$name, $slug, $description, $icon]);
                $taskId = (int)$db->lastInsertId();

                // Generate 6 default levels automatically
                for ($lvl = 1; $lvl <= 6; $lvl++) {
                    $lvlPrice = ($lvl == 1) ? 0.00 : (($lvl - 1) * 50.00);
                    $lvlReward = ($lvl == 1) ? 30.00 : (($lvl - 1) * 80.00);
                    $db->prepare("INSERT INTO levels (task_id, level_number, title, price, reward, passing_score, status) VALUES (?, ?, ?, ?, ?, 70, 'active')")
                       ->execute([$taskId, $lvl, "{$name} Level {$lvl}", $lvlPrice, $lvlReward]);
                }

                logAdminAudit($admin['id'], 'task_created', 'task', $taskId, "Created task $name with 6 levels");
                $message = "Task created successfully with 6 default levels!";
            } catch (Exception $e) {
                $error = "Error creating task (slug may already exist): " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_status') {
        $taskId = (int)$_POST['task_id'];
        $newStatus = $_POST['status'] === 'active' ? 'active' : 'inactive';
        $db->prepare("UPDATE tasks SET status = ? WHERE id = ?")->execute([$newStatus, $taskId]);
        logAdminAudit($admin['id'], 'task_status_toggle', 'task', $taskId, "Task set to $newStatus");
        $message = "Task status updated.";
    }
}

// Fetch all tasks with level counts
$sql = "
    SELECT t.*, 
           COUNT(l.id) as total_levels,
           SUM(l.reward) as total_rewards,
           (SELECT COUNT(*) FROM user_task_progress utp WHERE utp.task_id = t.id) as active_students
    FROM tasks t
    LEFT JOIN levels l ON t.id = l.task_id
    GROUP BY t.id
    ORDER BY t.order_index ASC, t.id ASC
";
$tasks = $db->query($sql)->fetchAll();

$adminTitle = "Task Categories & Tracks";
require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if ($message): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
  <div>
    <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--dark);">All Task Tracks</h2>
    <p style="color: var(--text-muted); font-size: 0.88rem;">Manage categories, levels, questions, and rewards</p>
  </div>
  <button type="button" class="btn btn-primary btn-sm" onclick="openModal('createTaskModal');">
    <i class="bi bi-plus-lg"></i> Create New Task
  </button>
</div>

<!-- Task Categories Grid -->
<div class="tasks-grid">
  <?php foreach ($tasks as $t): ?>
  <div class="task-card">
    <div>
      <div class="task-card-header">
        <div class="task-icon"><i class="bi <?= e($t['icon']) ?>"></i></div>
        <div>
          <div class="task-name"><?= e($t['name']) ?></div>
          <small style="color: var(--text-muted);"><?= (int)$t['total_levels'] ?> Levels &bull; <?= (int)$t['active_students'] ?> active users</small>
        </div>
      </div>

      <p class="task-desc"><?= e($t['description']) ?></p>

      <div style="margin-bottom: 16px; font-size: 0.88rem;">
        <div>Status: 
          <span class="badge badge-<?= $t['status'] === 'active' ? 'success' : 'secondary' ?>">
            <?= e($t['status']) ?>
          </span>
        </div>
        <div style="margin-top: 4px;">Potential Reward: <strong><?= formatCurrency($t['total_rewards'] ?? 0) ?></strong></div>
      </div>
    </div>

    <div>
      <div style="display: flex; gap: 8px;">
        <a href="<?= BASE_URL ?>/admin/levels.php?task_id=<?= $t['id'] ?>" class="btn btn-primary btn-block btn-sm">
          <i class="bi bi-layers"></i> Manage Levels (<?= (int)$t['total_levels'] ?>)
        </a>
        <form method="POST" action="" style="display: inline;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
          <input type="hidden" name="status" value="<?= $t['status'] === 'active' ? 'inactive' : 'active' ?>">
          <button type="submit" class="btn btn-outline btn-sm" title="Toggle active status">
            <i class="bi bi-power"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Create Task Modal -->
<div class="modal-backdrop" id="createTaskModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700;">Create New Task Track</h3>
      <button type="button" class="modal-close">&times;</button>
    </div>

    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create_task">

      <div class="form-group">
        <label class="form-label">Task Name</label>
        <input type="text" name="name" class="form-control" placeholder="e.g. Data Entry Specialist" required>
      </div>

      <div class="form-group">
        <label class="form-label">Slug (Optional URL key)</label>
        <input type="text" name="slug" class="form-control" placeholder="e.g. data-entry">
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Brief explanation of the tasks required"></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Bootstrap Icon Class</label>
        <input type="text" name="icon" class="form-control" value="bi-clipboard-check">
        <div class="form-text">e.g. bi-lightbulb, bi-card-checklist, bi-mortarboard</div>
      </div>

      <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Create Task Track</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
