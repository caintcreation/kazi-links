<?php
// admin/login.php - Admin Login Interface
require_once __DIR__ . '/../includes/admin-auth.php';

if (isAdminLoggedIn()) {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = "Security token mismatch. Please try again.";
    } elseif (empty($usernameOrEmail) || empty($password)) {
        $error = "Please enter your administrative credentials.";
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admins WHERE (username = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];

            // Update last login
            $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
            logAdminAudit($admin['id'], 'admin_login', 'admin', $admin['id'], 'Admin logged in');

            header("Location: " . BASE_URL . "/admin/dashboard.php");
            exit;
        } else {
            $error = "Invalid administrator username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Portal Login | KaziLink</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    body {
      background: #0f172a;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 16px;
    }
  </style>
</head>
<body>

<div style="max-width: 420px; width: 100%;">
  <div class="card" style="padding: 32px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
    <div style="text-align: center; margin-bottom: 24px;">
      <span class="brand-badge" style="background: #10b981; font-size: 1.1rem; padding: 4px 12px; margin-bottom: 12px; display: inline-block;">
        🇰🇪 KaziLink Portal
      </span>
      <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--dark);">Administrator Login</h2>
      <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 4px;">Authorized system operators only</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger" style="margin-bottom: 20px;">
        <i class="bi bi-exclamation-triangle"></i> <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/admin/login.php">
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="adminUser">Username or Email</label>
        <input type="text" id="adminUser" name="username" class="form-control" placeholder="admin" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label" for="adminPass">Password</label>
        <div class="input-password-wrapper">
          <input type="password" id="adminPass" name="password" class="form-control" placeholder="Enter admin password" required>
          <button type="button" class="password-toggle-btn" data-target="adminPass">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 14px;">
        <i class="bi bi-shield-lock"></i> Authenticate
      </button>
    </form>

    <div style="text-align: center; margin-top: 24px;">
      <a href="<?= BASE_URL ?>/index.php" style="font-size: 0.88rem; color: var(--text-muted);">
        <i class="bi bi-arrow-left"></i> Return to Public Site
      </a>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
