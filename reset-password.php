<?php
// reset-password.php - Reset Password using 6-Digit Code or Link Token
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$pageTitle = "Reset Password — KaziLink Kenya";
$error = '';
$success = '';

$emailParam = trim($_GET['email'] ?? $_SESSION['reset_email'] ?? '');
$tokenParam = trim($_GET['token'] ?? '');

$flashMessage = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// For local testing convenience if SMTP was blocked by local ISP
$localDevCode = null;
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development' && !empty($emailParam)) {
    $db = getDB();
    $s = $db->prepare("SELECT reset_code FROM password_resets WHERE email = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $s->execute([$emailParam]);
    $localDevCode = $s->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $codeOrToken = trim($_POST['code'] ?? $_POST['token'] ?? '');
    $newPass = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = "Security token expired. Please refresh the page.";
    } elseif (empty($codeOrToken)) {
        $error = "Please enter the 6-digit reset code or use the reset link.";
    } elseif (strlen($newPass) < 8) {
        $error = "New password must be at least 8 characters long.";
    } elseif ($newPass !== $confirmPass) {
        $error = "Passwords do not match.";
    } else {
        $db = getDB();
        
        // Find reset record by 6-digit code AND email, or by token
        if (!empty($tokenParam)) {
            $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$tokenParam]);
        } else {
            $stmt = $db->prepare("SELECT * FROM password_resets WHERE email = ? AND reset_code = ? AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$email, $codeOrToken]);
        }
        $resetRow = $stmt->fetch();

        if (!$resetRow) {
            $error = "Invalid or expired reset code. Please request a new code.";
        } else {
            $userEmail = $resetRow['email'];
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);

            // Update user's password
            $update = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $update->execute([$newHash, $userEmail]);

            // Clear reset tokens for this email
            $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$userEmail]);
            unset($_SESSION['reset_email']);

            $_SESSION['flash_success'] = "Password reset successfully! Please log in with your new password.";
            header("Location: " . BASE_URL . "/login.php");
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1.25rem;">
  <div class="surface" style="max-width: 440px; width: 100%; padding: 2rem;">
    
    <div style="text-align: center; margin-bottom: 2rem;">
      <a href="<?= BASE_URL ?>/index.php" style="font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; color: var(--foreground);">
        kazi<span class="text-accent">link</span>jobs
      </a>
      <h2 style="font-size: 1.5rem; font-weight: 800; margin-top: 1rem; color: var(--primary);">Set New Password</h2>
      <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.35rem;">
        Enter your 6-digit verification code and choose a new secure password.
      </p>
    </div>

    <?php if ($flashMessage): ?>
      <div class="alert alert-success" style="margin-bottom: 1.5rem;">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= e($flashMessage) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($localDevCode): ?>
      <div style="background: #f0f9ff; border: 1px dashed var(--primary); padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; font-size: 0.85rem;">
        <i class="bi bi-info-circle text-primary"></i>
        <strong>Active Reset Code:</strong> <code style="font-size: 1.1rem; font-weight: 800; color: var(--primary);"><?= e($localDevCode) ?></code>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/reset-password.php<?= !empty($tokenParam) ? '?token=' . urlencode($tokenParam) : '' ?>">
      <?= csrfField() ?>

      <?php if (!empty($tokenParam)): ?>
        <input type="hidden" name="token" value="<?= e($tokenParam) ?>">
      <?php else: ?>
        <div class="form-group">
          <label class="form-label" for="resetEmail">Email Address</label>
          <input type="email" id="resetEmail" name="email" class="field" value="<?= e($emailParam) ?>" placeholder="Your registered email" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="resetCode">6-Digit Reset Code</label>
          <input type="text" id="resetCode" name="code" class="field" placeholder="e.g. 123456" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required autofocus style="letter-spacing: 4px; font-weight: 700; font-size: 1.15rem; text-align: center;">
        </div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label" for="newPassword">New Password (min 8 characters)</label>
        <div class="input-password-wrapper">
          <input type="password" id="newPassword" name="password" class="field" placeholder="Enter new password" minlength="8" required>
          <button type="button" class="password-toggle-btn" data-target="newPassword">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="confirmPassword">Confirm New Password</label>
        <div class="input-password-wrapper">
          <input type="password" id="confirmPassword" name="confirm_password" class="field" placeholder="Confirm new password" minlength="8" required>
          <button type="button" class="password-toggle-btn" data-target="confirmPassword">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1.25rem; padding: 0.85rem;">
        <i class="bi bi-lock-fill" style="margin-right: 0.35rem;"></i>
        <span>Update Password</span>
      </button>

      <div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: var(--muted-foreground);">
        Remembered your password? <a href="<?= BASE_URL ?>/login.php" style="color: var(--primary); font-weight: 700;">Back to login</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
