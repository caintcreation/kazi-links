<?php
// register.php - Register page with Ksh 300 referral bonus indicator
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$refParam = strtoupper(trim($_GET['ref'] ?? ''));
$pageTitle = "Create Free Account — KaziLink";
require_once __DIR__ . '/includes/header.php';
$welcomeBonusRef = (int)getSetting('welcome_reward_referred', '300.00');
$welcomeBonusStandard = (int)getSetting('welcome_reward', '100.00');
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1.25rem;">
  <div class="surface" style="max-width: 440px; width: 100%; padding: 2rem;">
    
    <div style="text-align: center; margin-bottom: 2rem;">
      <a href="<?= BASE_URL ?>/index.php" style="font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; color: var(--foreground);">
        kazi<span class="text-accent">link</span>jobs
      </a>
      <h2 style="font-size: 1.5rem; font-weight: 800; margin-top: 1rem;">Create free account</h2>
      <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.25rem;" id="bonusSubtitle">
        Join with a referral link to get a <strong style="color: var(--primary);">Sh <?= $welcomeBonusRef ?> welcome bonus</strong> (Sh <?= $welcomeBonusStandard ?> standard).
      </p>
    </div>

    <div id="registerAlert" class="alert alert-danger" style="display: none;"></div>

    <form id="registerForm" novalidate>
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="regName">Full name</label>
        <input type="text" id="regName" name="name" class="field" placeholder="e.g. Brian Mwangi" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label" for="regEmail">Email address</label>
        <input type="email" id="regEmail" name="email" class="field" placeholder="example@gmail.com" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="regPhone">M-Pesa phone number</label>
        <input type="tel" id="regPhone" name="phone" class="field" placeholder="07XXXXXXXX or 01XXXXXXXX" required>
        <div class="form-text">Used for direct Safaricom withdrawals</div>
      </div>

      <div class="form-group">
        <label class="form-label" for="regPassword">Password</label>
        <div class="input-password-wrapper">
          <input type="password" id="regPassword" name="password" class="field" placeholder="Min. 6 characters" required>
          <button type="button" class="password-toggle-btn" data-target="regPassword">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="regConfirmPassword">Confirm password</label>
        <div class="input-password-wrapper">
          <input type="password" id="regConfirmPassword" name="confirm_password" class="field" placeholder="Re-enter password" required>
          <button type="button" class="password-toggle-btn" data-target="regConfirmPassword">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="regRef">Referral code (Optional &mdash; gives Sh 300 bonus)</label>
        <input type="text" id="regRef" name="ref" class="field" placeholder="e.g. KZ7A91B" value="<?= e($refParam) ?>">
        <div class="form-text" id="refBonusNote" style="color: #065f46; font-weight: 600;">
          <?= !empty($refParam) ? "✓ Referral code applied! You will receive Sh {$welcomeBonusRef} welcome bonus." : "Enter a referral code to get Sh {$welcomeBonusRef} instead of Sh {$welcomeBonusStandard}." ?>
        </div>
      </div>

      <div style="font-size: 0.8rem; color: var(--muted-foreground); margin: 1rem 0; line-height: 1.5;">
        By creating an account, you agree to our 
        <a href="<?= BASE_URL ?>/terms.php" target="_blank" style="color: var(--primary); font-weight: 700; text-decoration: underline;">Terms &amp; Conditions</a> 
        and <a href="<?= BASE_URL ?>/privacy.php" target="_blank" style="color: var(--primary); font-weight: 700; text-decoration: underline;">Privacy Policy</a>.
      </div>

      <button type="submit" id="regSubmitBtn" class="btn btn-gold btn-block" style="margin-top: 1rem; padding: 0.85rem;">
        <span>Create free account</span>
      </button>

      <div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: var(--muted-foreground);">
        Already have an account? <a href="<?= BASE_URL ?>/login.php" style="color: var(--primary); font-weight: 700;">Sign in here</a>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const refInput = document.getElementById('regRef');
  const refNote = document.getElementById('refBonusNote');

  refInput.addEventListener('input', () => {
    if (refInput.value.trim().length > 0) {
      refNote.textContent = '✓ Referral code active! You will receive Sh <?= $welcomeBonusRef ?> welcome bonus.';
      refNote.style.color = '#065f46';
    } else {
      refNote.textContent = 'Enter a referral code to get Sh <?= $welcomeBonusRef ?> instead of Sh <?= $welcomeBonusStandard ?>.';
      refNote.style.color = 'var(--muted-foreground)';
    }
  });

  const form = document.getElementById('registerForm');
  const alertBox = document.getElementById('registerAlert');
  const submitBtn = document.getElementById('regSubmitBtn');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    alertBox.style.display = 'none';

    const email = document.getElementById('regEmail').value.trim();
    const phone = document.getElementById('regPhone').value.trim();
    const pass = document.getElementById('regPassword').value;
    const confirmPass = document.getElementById('regConfirmPassword').value;

    if (!email || !phone || !pass || !confirmPass) {
      alertBox.textContent = 'Please fill in all required fields.';
      alertBox.style.display = 'flex';
      return;
    }

    if (pass !== confirmPass) {
      alertBox.textContent = 'Passwords do not match.';
      alertBox.style.display = 'flex';
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Creating account...';

    const formData = new FormData(form);

    try {
      const res = await fetch('<?= BASE_URL ?>/api/register.php', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();

      if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => {
          window.location.href = data.redirect;
        }, 600);
      } else {
        alertBox.textContent = data.message || 'Registration failed.';
        alertBox.style.display = 'flex';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Create free account</span>';
      }
    } catch (err) {
      alertBox.textContent = 'A network error occurred. Please try again.';
      alertBox.style.display = 'flex';
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<span>Create free account</span>';
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
