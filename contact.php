<?php
// contact.php - Customer-care contact form
require_once __DIR__ . '/includes/auth.php';

$user = getLoggedInUser();
$pageTitle = 'Customer Care — KaziLink';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 760px;">
  <div style="text-align: center; margin-bottom: 30px;">
    <span class="hero-pill"><i class="bi bi-headset"></i> Customer Care</span>
    <h1 style="font-size: 2rem; font-weight: 800; margin-top: 8px;">How can we help?</h1>
    <p style="color: var(--text-muted);">
      Send us a complaint, question, or account-support request. Our team will respond by email.
    </p>
  </div>

  <div class="card">
    <div id="contactAlert" class="alert" style="display: none;"></div>

    <form id="contactForm" novalidate>
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="contactName">Your name</label>
        <input type="text" id="contactName" name="name" class="field" maxlength="100" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="contactEmail">Your email address</label>
        <input type="email" id="contactEmail" name="email" class="field" maxlength="191"
               value="<?= e($user['email'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="contactSubject">Subject</label>
        <input type="text" id="contactSubject" name="subject" class="field" maxlength="150"
               placeholder="e.g. Withdrawal support" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="contactMessage">Message</label>
        <textarea id="contactMessage" name="message" class="field" rows="7" maxlength="5000"
                  placeholder="Describe your issue clearly..." required></textarea>
      </div>

      <button type="submit" id="contactSubmit" class="btn btn-primary btn-block">
        <i class="bi bi-send"></i> Send to Customer Care
      </button>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contactForm');
  const alertBox = document.getElementById('contactAlert');
  const submitButton = document.getElementById('contactSubmit');

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    alertBox.style.display = 'none';
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending...';

    try {
      const response = await fetch('<?= BASE_URL ?>/api/contact.php', {
        method: 'POST',
        body: new FormData(form)
      });
      const data = await response.json();
      alertBox.textContent = data.message || 'Unable to send your message.';
      alertBox.className = data.success ? 'alert alert-success' : 'alert alert-danger';
      alertBox.style.display = 'flex';

      if (data.success) {
        form.reset();
        <?php if ($user): ?>
        document.getElementById('contactEmail').value = <?= json_encode($user['email']) ?>;
        <?php endif; ?>
      }
    } catch (error) {
      alertBox.textContent = 'A network error occurred. Please try again.';
      alertBox.className = 'alert alert-danger';
      alertBox.style.display = 'flex';
    } finally {
      submitButton.disabled = false;
      submitButton.innerHTML = '<i class="bi bi-send"></i> Send to Customer Care';
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
