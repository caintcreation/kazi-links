<?php
// profile.php - User Settings: Edit Name, M-Pesa Phone Number, Password, and View Terms
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$user = getLoggedInUser();
$db = getDB();

$message = '';
$error = '';

// Handle POST actions: update_profile and change_password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = "Session expired. Please refresh the page.";
    } elseif ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || strlen($name) < 2) {
            $error = "Please provide a valid full name (at least 2 characters).";
        } else {
            $normalizedPhone = normalizeKenyanPhone($phone);
            if (!$normalizedPhone) {
                $error = "Please enter a valid Kenyan Safaricom phone number (e.g., 0712345678 or 0112345678).";
            } else {
                // Check if phone number is already registered to someone else
                $chk = $db->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
                $chk->execute([$normalizedPhone, $user['id']]);
                if ($chk->fetch()) {
                    $error = "That M-Pesa phone number is already linked to another account.";
                } else {
                    $upd = $db->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                    $upd->execute([$name, $normalizedPhone, $user['id']]);
                    $message = "Your profile information has been successfully updated!";
                    $user['name'] = $name;
                    $user['phone'] = $normalizedPhone;
                }
            }
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_new_password'] ?? '';

        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPass, $row['password_hash'])) {
            $error = "Your current password does not match our records.";
        } elseif (strlen($newPass) < 6) {
            $error = "New password must be at least 6 characters long.";
        } elseif ($newPass !== $confirmPass) {
            $error = "New password and confirmation do not match.";
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $uStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $uStmt->execute([$newHash, $user['id']]);
            $message = "Your password has been successfully updated!";
        }
    }
}

$pageTitle = "Account Settings — KaziLink Kenya";
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 680px; margin: 2rem auto; padding: 0 1.25rem 4rem;">
  
  <div style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--primary);">Account Settings</h1>
    <p style="color: var(--muted-foreground); font-size: 0.95rem;">Manage your personal details, M-Pesa cashout number, and security credentials.</p>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem;">
      <i class="bi bi-check-circle-fill"></i>
      <span><?= e($message) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <!-- 1. Profile Details Card (Editable Name & Phone) -->
  <div class="surface" style="padding: 1.75rem; margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
      <h2 style="font-size: 1.2rem; font-weight: 800; margin: 0; color: var(--primary);">
        <i class="bi bi-person-badge" style="margin-right: 0.35rem;"></i> Personal Information
      </h2>
      <span class="badge badge-success"><i class="bi bi-check-circle"></i> Active</span>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/profile.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update_profile">

      <div class="form-group">
        <label class="form-label" for="profileName">Full Name</label>
        <input type="text" id="profileName" name="name" class="field" value="<?= e($user['name'] ?? '') ?>" placeholder="e.g. Brian Mwangi" required>
        <div class="form-text">Used to address you on your dashboard and official notifications.</div>
      </div>

      <div class="form-group">
        <label class="form-label" for="profilePhone">M-Pesa Phone Number</label>
        <input type="tel" id="profilePhone" name="phone" class="field" value="<?= e($user['phone'] ?? '') ?>" placeholder="07XXXXXXXX or 01XXXXXXXX" required>
        <div class="form-text">All withdrawal disbursements are sent to this registered Safaricom number.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <input type="email" class="field" value="<?= e($user['email']) ?>" readonly style="background: var(--muted); cursor: not-allowed; flex: 1;">
          <?php if (!empty($user['email_verified'])): ?>
            <span class="badge badge-success"><i class="bi bi-check2-all"></i> Verified</span>
          <?php else: ?>
            <a href="<?= BASE_URL ?>/verify.php" class="btn btn-warning btn-sm" style="font-size: 0.8rem;">Activate Now</a>
          <?php endif; ?>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
        <div>
          <label class="form-label">Your Referral Code</label>
          <input type="text" class="field" value="<?= e($user['referral_code']) ?>" readonly style="background: var(--muted); font-weight: 800; letter-spacing: 1px;">
        </div>
        <div>
          <label class="form-label">Member Since</label>
          <input type="text" class="field" value="<?= formatDate($user['created_at'], false) ?>" readonly style="background: var(--muted);">
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem; padding: 0.75rem 1.5rem;">
        <i class="bi bi-save"></i> Save Profile Changes
      </button>
    </form>
  </div>

  <!-- 2. Security & Password Change Card -->
  <div class="surface" style="padding: 1.75rem; margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
      <h2 style="font-size: 1.2rem; font-weight: 800; margin: 0; color: var(--primary);">
        <i class="bi bi-shield-lock" style="margin-right: 0.35rem;"></i> Change Password
      </h2>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/profile.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="change_password">

      <div class="form-group">
        <label class="form-label" for="curPass">Current Password</label>
        <div class="input-password-wrapper">
          <input type="password" id="curPass" name="current_password" class="field" placeholder="Enter current password" required>
          <button type="button" class="password-toggle-btn" data-target="curPass">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="newPass">New Password (min. 6 characters)</label>
        <div class="input-password-wrapper">
          <input type="password" id="newPass" name="new_password" class="field" placeholder="Enter new password" minlength="6" required>
          <button type="button" class="password-toggle-btn" data-target="newPass">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="confPass">Confirm New Password</label>
        <div class="input-password-wrapper">
          <input type="password" id="confPass" name="confirm_new_password" class="field" placeholder="Repeat new password" minlength="6" required>
          <button type="button" class="password-toggle-btn" data-target="confPass">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top: 1rem; padding: 0.75rem 1.5rem;">
        <i class="bi bi-key"></i> Update Password
      </button>
    </form>
  </div>

  <!-- 3. Terms & Conditions and Platform Policies -->
  <div class="surface" style="padding: 1.75rem;">
    <h2 style="font-size: 1.2rem; font-weight: 800; margin: 0 0 1rem; color: var(--primary);">
      <i class="bi bi-file-earmark-text" style="margin-right: 0.35rem;"></i> Terms &amp; Policies
    </h2>
    <p style="color: var(--muted-foreground); font-size: 0.9rem; line-height: 1.6;">
      By using KaziLink Kenya, you agree to our operating guidelines, Safaricom M-Pesa payout rules, and privacy standards.
    </p>

    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem;">
      <a href="<?= BASE_URL ?>/terms.php" class="btn btn-ghost" style="border: 1px solid var(--border);">
        <i class="bi bi-book"></i> Read Terms of Service
      </a>
      <a href="<?= BASE_URL ?>/privacy.php" class="btn btn-ghost" style="border: 1px solid var(--border);">
        <i class="bi bi-shield-check"></i> Privacy Policy
      </a>
      <a href="<?= BASE_URL ?>/help.php" class="btn btn-ghost" style="border: 1px solid var(--border);">
        <i class="bi bi-question-circle"></i> Help &amp; FAQs
      </a>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
