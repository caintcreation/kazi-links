<?php
// Enable immediate error reporting
ini_set('display_errors', '1');
error_reporting(E_ALL);

// login.php - Login interface matching reference site layout & styling
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$pageTitle = "Log in — KaziLink";
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1.25rem;">
  <div class="surface" style="max-width: 420px; width: 100%; padding: 2rem;">
    
    <div style="text-align: center; margin-bottom: 2rem;">
      <a href="<?= BASE_URL ?>/index.php" style="font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; color: var(--foreground);">
        kazi<span class="text-accent">link</span>jobs
      </a>
      <h2 style="font-size: 1.5rem; font-weight: 800; margin-top: 1rem;">Welcome back</h2>
      <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.25rem;">
        Enter your details to access your account
      </p>
    </div>

    <div id="loginAlert" class="alert alert-danger" style="display: none;"></div>

    <form id="loginForm" novalidate>
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="loginEmail">Email address or M-Pesa number</label>
        <input type="text" id="loginEmail" name="email_or_phone" class="field" placeholder="e.g. 0712345678 or email" required>
      </div>

      <div class="form-group">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
          <label class="form-label" for="loginPassword" style="margin-bottom: 0;">Password</label>
          <a href="<?= BASE_URL ?>/forgot-password.php" style="font-size: 0.8rem; color: var(--primary); font-weight: 600;">Forgot password?</a>
        </div>
        <div class="input-password-wrapper">
          <input type="password" id="loginPassword" name="password" class="field" placeholder="Your password" required>
          <button type="button" class="password-toggle-btn" data-target="loginPassword">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" id="loginSubmitBtn" class="btn btn-primary btn-block" style="margin-top: 1.5rem; padding: 0.85rem;">
        <span>Log in</span>
      </button>

      <div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: var(--muted-foreground);">
        Don't have an account? <a href="<?= BASE_URL ?>/register.php" style="color: var(--primary); font-weight: 700;">Create free account</a>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('loginForm');
  const alertBox = document.getElementById('loginAlert');
  const submitBtn = document.getElementById('loginSubmitBtn');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    alertBox.style.display = 'none';

    const loginId = document.getElementById('loginEmail').value.trim();
    const pass = document.getElementById('loginPassword').value;

    if (!loginId || !pass) {
      alertBox.textContent = 'Please enter your login email or phone and password.';
      alertBox.style.display = 'flex';
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Signing in...';

    const formData = new FormData(form);

    try {
      const res = await fetch('<?= BASE_URL ?>/api/login.php', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();

      if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => {
          window.location.href = data.redirect;
        }, 500);
      } else {
        alertBox.textContent = data.message || 'Login failed.';
        alertBox.style.display = 'flex';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Log in</span>';
      }
    } catch (err) {
      alertBox.textContent = 'A network error occurred. Please try again.';
      alertBox.style.display = 'flex';
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<span>Log in</span>';
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
