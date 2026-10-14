<?php
// verify.php - Account Email Verification Page with Resend Code & Edit Email features
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$token = trim($_GET['token'] ?? '');
$emailParam = strtolower(trim($_GET['email'] ?? $_SESSION['pending_verification_email'] ?? ''));

$db = getDB();
try {
    ensureRegistrationSchema();
} catch (Throwable $e) {
    error_log("Verification Schema Error: " . $e->getMessage());
}

$message = '';
$error = '';
$user = getLoggedInUser();
$activeEmail = $user['email'] ?? $emailParam;

// 1. Handle direct link token verification
if (!empty($token)) {
    $stmt = $db->prepare("SELECT id, name, email, email_verified, (verification_expires_at IS NOT NULL AND verification_expires_at < NOW()) AS is_expired FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $matchedUser = $stmt->fetch();

    if ($matchedUser) {
        if ($matchedUser['email_verified']) {
            $message = "Your email address is already verified! You can proceed to your dashboard.";
        } elseif (!empty($matchedUser['is_expired'])) {
            $error = "This verification link has expired. Please request a new code below.";
        } else {
            $upd = $db->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, verification_code = NULL WHERE id = ?");
            $upd->execute([$matchedUser['id']]);
            $message = "Success! Your email ({$matchedUser['email']}) has been verified. Welcome to KaziLink!";
            $_SESSION['user_id'] = (int)$matchedUser['id'];
            $_SESSION['user_email'] = $matchedUser['email'];
            $activeEmail = $matchedUser['email'];
        }
    } else {
        $error = "Invalid or expired verification link.";
    }
}

// 2. Handle POST Actions: verify_code, resend_code, edit_email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? 'verify_code';

    if (!verifyCsrfToken($csrf)) {
        $error = "Your verification session has expired. Please refresh the page.";
    } elseif ($action === 'verify_code') {
        $submittedCode = trim($_POST['verification_code'] ?? '');
        $targetEmail = strtolower(trim($_POST['email'] ?? $activeEmail));

        if (!preg_match('/^\d{6}$/', $submittedCode) || empty($targetEmail)) {
            $error = "Please enter both your email address and the 6-digit verification code.";
        } else {
            $stmt = $db->prepare("SELECT id, name, email, email_verified, verification_code, (verification_expires_at IS NOT NULL AND verification_expires_at < NOW()) AS is_expired FROM users WHERE email = ?");
            $stmt->execute([$targetEmail]);
            $row = $stmt->fetch();

            $codeExpired = $row && !empty($row['is_expired']);
            if ($row && !$codeExpired && hash_equals((string)$row['verification_code'], $submittedCode)) {
                $upd = $db->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, verification_code = NULL WHERE id = ?");
                $upd->execute([$row['id']]);
                $message = "Success! Your email address has been verified. Your account is fully active!";
                $_SESSION['user_id'] = (int)$row['id'];
                $_SESSION['user_email'] = $row['email'];
                $activeEmail = $row['email'];
            } else {
                $error = $codeExpired
                    ? "This verification code has expired. Click 'Resend Code' below to get a fresh code."
                    : "Incorrect 6-digit verification code. Please check your inbox or spam folder, or click 'Resend Code'.";
            }
        }
    } elseif ($action === 'resend_code') {
        $targetEmail = strtolower(trim($_POST['email'] ?? $activeEmail));

        if (empty($targetEmail) || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Please provide a valid email address to resend the code.";
        } else {
            $stmt = $db->prepare("SELECT id, name, email, referral_code, email_verified FROM users WHERE email = ?");
            $stmt->execute([$targetEmail]);
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                $error = "No account found with email {$targetEmail}.";
            } elseif ($targetUser['email_verified']) {
                $message = "This email is already verified! You can proceed to your dashboard.";
            } else {
                $newCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $newToken = bin2hex(random_bytes(32));

                $db->prepare("UPDATE users SET verification_code = ?, verification_token = ?, verification_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?")
                   ->execute([$newCode, $newToken, $targetUser['id']]);

                sendAccountVerificationEmail($targetEmail, $newCode, $newToken, $targetUser['referral_code'], 300.00, $targetUser['name'] ?? '');
                $activeEmail = $targetEmail;
                $_SESSION['flash_verify'] = "A fresh 6-digit code has been sent to {$targetEmail}. Please check your inbox and spam folder.";
                header("Location: " . BASE_URL . "/verify.php?email=" . urlencode($targetEmail));
                exit;
            }
        }
    } elseif ($action === 'edit_email') {
        $oldEmail = strtolower(trim($_POST['old_email'] ?? $activeEmail));
        $newEmail = strtolower(trim($_POST['new_email'] ?? ''));

        if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid new email address.";
        } elseif ($newEmail === $oldEmail) {
            $error = "The new email address must be different from your current one.";
        } else {
            // Check uniqueness
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$newEmail]);
            if ($check->fetch()) {
                $error = "That email address is already in use by another account.";
            } else {
                $stmt = $db->prepare("SELECT id, name, referral_code FROM users WHERE email = ?");
                $stmt->execute([$oldEmail]);
                $targetUser = $stmt->fetch();

                if (!$targetUser) {
                    $error = "Account not found for {$oldEmail}.";
                } else {
                    $newCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $newToken = bin2hex(random_bytes(32));

                    $upd = $db->prepare("UPDATE users SET email = ?, verification_code = ?, verification_token = ?, verification_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?");
                    $upd->execute([$newEmail, $newCode, $newToken, $targetUser['id']]);

                    sendAccountVerificationEmail($newEmail, $newCode, $newToken, $targetUser['referral_code'], 300.00, $targetUser['name'] ?? '');

                    $_SESSION['user_email'] = $newEmail;
                    $_SESSION['pending_verification_email'] = $newEmail;
                    $_SESSION['flash_verify'] = "Email updated to {$newEmail}! A fresh activation code has been sent to your new email. Please check your inbox and spam folder.";
                    header("Location: " . BASE_URL . "/verify.php?email=" . urlencode($newEmail));
                    exit;
                }
            }
        }
    }
}

$flashMessage = $_SESSION['flash_verify'] ?? '';
unset($_SESSION['flash_verify']);

$pageTitle = "Activate Your Account — KaziLink Kenya";
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1.25rem;">
  <div class="surface" style="max-width: 460px; width: 100%; padding: 2rem; text-align: center;">
    
    <div style="margin-bottom: 1.5rem;">
      <span style="border-radius: 1rem; background: var(--secondary); padding: 0.85rem; display: inline-flex; margin-bottom: 1rem;">
        <i class="bi bi-shield-check" style="color: var(--primary); font-size: 2rem;"></i>
      </span>
      <h1 style="font-size: 1.65rem; font-weight: 800; color: var(--primary);">Activate Your Account</h1>
      <p style="color: var(--muted-foreground); font-size: 0.9rem; margin-top: 0.35rem;">
        Enter the 6-digit code sent to:<br/>
        <strong style="color: var(--foreground); font-size: 1rem;"><?= e($activeEmail ?: 'your registered email') ?></strong>
      </p>
    </div>

    <?php if ($flashMessage): ?>
      <div class="alert alert-success" style="text-align: left; margin-bottom: 1.25rem;">
        <i class="bi bi-info-circle-fill"></i> <?= e($flashMessage) ?>
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="alert alert-success" style="text-align: left; margin-bottom: 1.25rem;">
        <i class="bi bi-check-circle-fill"></i> <?= e($message) ?>
      </div>
      <div style="margin-top: 1.5rem;">
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-gold btn-block">Continue to Dashboard &rarr;</a>
      </div>
    <?php else: ?>

      <?php if ($error): ?>
        <div class="alert alert-danger" style="text-align: left; margin-bottom: 1.25rem;">
          <i class="bi bi-exclamation-triangle-fill"></i> <?= e($error) ?>
        </div>
      <?php endif; ?>

      <!-- Spam Folder Reminder Box -->
      <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-lg); padding: 0.85rem 1rem; margin-top: 1rem; margin-bottom: 1.25rem; text-align: left; display: flex; align-items: flex-start; gap: 0.75rem;">
        <i class="bi bi-envelope-exclamation" style="color: #d97706; font-size: 1.35rem; margin-top: 1px; flex-shrink: 0;"></i>
        <div style="font-size: 0.88rem; color: #92400e; line-height: 1.5;">
          <strong>Can't see the code in your inbox?</strong> Please also check your <strong>Spam / Junk folder</strong>. If you find our email there, click <em>"Report not spam"</em> so you never miss withdrawal updates or task payouts!
        </div>
      </div>

      <!-- 6-Digit Code Submission Form -->
      <form method="POST" action="<?= BASE_URL ?>/verify.php" style="margin-top: 1.25rem;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="verify_code">
        <input type="hidden" name="email" value="<?= e($activeEmail) ?>">

        <?php if (empty($activeEmail)): ?>
          <div class="form-group" style="text-align: left;">
            <label class="form-label" for="verifyEmail">Your registered email</label>
            <input type="email" id="verifyEmail" name="email" class="field" placeholder="example@gmail.com" required>
          </div>
        <?php endif; ?>

        <div class="form-group" style="text-align: left;">
          <label class="form-label" for="verifyCode">Enter 6-Digit Code</label>
          <input type="text" id="verifyCode" name="verification_code" class="field" placeholder="123456" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" style="font-size: 1.6rem; text-align: center; letter-spacing: 8px; font-weight: 800; color: var(--primary);" required autofocus>
          <div class="form-text" style="text-align: center; margin-top: 0.5rem; font-size: 0.85rem; color: var(--muted-foreground);">
            <i class="bi bi-info-circle"></i> Check your <strong>Inbox</strong> or <strong>Spam / Junk folder</strong> for an email from <strong>growflow56@gmail.com</strong>
          </div>
        </div>

        <button type="submit" class="btn btn-gold btn-block" style="margin-top: 1.25rem; padding: 0.85rem; font-size: 1rem;">
          Activate Account Now
        </button>
      </form>

      <!-- Action Row: Resend Code & Edit Email -->
      <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed var(--border); display: flex; flex-direction: column; gap: 0.75rem;">
        
        <!-- Resend Code Form -->
        <form method="POST" action="<?= BASE_URL ?>/verify.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="resend_code">
          <input type="hidden" name="email" value="<?= e($activeEmail) ?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="font-size: 0.85rem; width: 100%;">
            <i class="bi bi-arrow-repeat"></i> Didn't receive code? Resend Code
          </button>
        </form>

        <!-- Toggle Edit Email Modal / Section -->
        <button type="button" id="toggleEditEmailBtn" class="btn btn-ghost btn-sm" style="font-size: 0.85rem; color: var(--primary);">
          <i class="bi bi-pencil-square"></i> Wrong email address? Change Email
        </button>

      </div>

      <!-- Edit Email Form (collapsible) -->
      <div id="editEmailSection" style="display: none; margin-top: 1.25rem; background: var(--muted); padding: 1.25rem; border-radius: var(--radius-lg); text-align: left;">
        <h4 style="font-size: 0.95rem; font-weight: 700; margin-top: 0; color: var(--primary);">Correct Your Email Address</h4>
        <form method="POST" action="<?= BASE_URL ?>/verify.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="edit_email">
          <input type="hidden" name="old_email" value="<?= e($activeEmail) ?>">

          <div class="form-group" style="margin-bottom: 0.75rem;">
            <label class="form-label" for="newEmailInput">New correct email address</label>
            <input type="email" id="newEmailInput" name="new_email" class="field" placeholder="correct@example.com" required>
          </div>

          <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
            <button type="button" id="cancelEditEmailBtn" class="btn btn-ghost btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm">Update &amp; Send Code</button>
          </div>
        </form>
      </div>

    <?php endif; ?>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggleBtn = document.getElementById('toggleEditEmailBtn');
  const cancelBtn = document.getElementById('cancelEditEmailBtn');
  const section = document.getElementById('editEmailSection');

  if (toggleBtn && section) {
    toggleBtn.addEventListener('click', () => {
      section.style.display = section.style.display === 'none' ? 'block' : 'none';
    });
  }
  if (cancelBtn && section) {
    cancelBtn.addEventListener('click', () => {
      section.style.display = 'none';
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
