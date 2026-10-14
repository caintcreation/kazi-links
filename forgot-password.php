<?php
// forgot-password.php - Request Password Reset Code
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$pageTitle = "Forgot Password — KaziLink Kenya";
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = "Security token expired. Please refresh the page.";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, email, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['status'] !== 'suspended') {
            // Generate 6-digit verification code and secure token
            $resetCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $token = bin2hex(random_bytes(32));

            // Clean previous resets for this email
            $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

            // Save reset code valid for 1 hour
            $insert = $db->prepare("INSERT INTO password_resets (email, token, reset_code, expires_at) 
                                    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
            $insert->execute([$email, $token, $resetCode]);

            // Send password reset email
            sendPasswordResetEmail($email, $resetCode, $token);

            $_SESSION['reset_email'] = $email;
            $_SESSION['flash_success'] = "A 6-digit reset code has been sent to {$email}. Please check your inbox or spam folder.";
            header("Location: " . BASE_URL . "/reset-password.php?email=" . urlencode($email));
            exit;
        } else {
            // Even if user not found, give safe message
            $_SESSION['reset_email'] = $email;
            $_SESSION['flash_success'] = "If an account with that email exists, a 6-digit reset code has been sent.";
            header("Location: " . BASE_URL . "/reset-password.php?email=" . urlencode($email));
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
      <h2 style="font-size: 1.5rem; font-weight: 800; margin-top: 1rem; color: var(--primary);">Forgot Password?</h2>
      <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.35rem;">
        Enter your registered email address and we'll send you a 6-digit code to reset your password.
      </p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/forgot-password.php">
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="resetEmail">Registered Email Address</label>
        <input type="email" id="resetEmail" name="email" class="field" placeholder="e.g. you@example.com" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1.25rem; padding: 0.85rem;">
        <i class="bi bi-send-fill" style="margin-right: 0.35rem;"></i>
        <span>Send Reset Code</span>
      </button>

      <div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: var(--muted-foreground);">
        Remember your password? <a href="<?= BASE_URL ?>/login.php" style="color: var(--primary); font-weight: 700;">Back to login</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
