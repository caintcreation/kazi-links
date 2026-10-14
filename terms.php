<?php
// terms.php - Terms of Service
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Terms of Service";
require_once __DIR__ . '/includes/header.php';
$siteName = getSetting('site_name', 'KaziLink Kenya');
?>

<div class="container" style="max-width: 800px;">
  <div class="card">
    <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--dark); margin-bottom: 20px;">Terms of Service</h1>
    <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 24px;">Effective Date: <?= date('F j, Y') ?></p>

    <div style="display: flex; flex-direction: column; gap: 16px; line-height: 1.6; color: var(--dark-light);">
      <h3>1. Agreement to Terms</h3>
      <p>By accessing or registering with <?= e($siteName) ?>, you agree to be bound by these Terms of Service and all applicable laws and regulations in the Republic of Kenya.</p>

      <h3>2. Eligibility & Accounts</h3>
      <p>Users must be residents of Kenya possessing a valid Safaricom M-Pesa line registered in their own name. You agree to provide true, accurate, and current information during registration. Maintaining multiple accounts or falsifying contact details is strictly prohibited.</p>

      <h3>3. Task Progression & Rewards</h3>
      <p>Tasks represent micro-evaluations, questionnaires, and assessment activities. Completion rewards are credited only upon achieving the required minimum passing score. <?= e($siteName) ?> does not offer fixed wage employment or guaranteed investment returns; all rewards are performance-based as determined by system rules.</p>

      <h3>4. M-Pesa Withdrawals</h3>
      <p>Withdrawals are subject to admin review, verification of completed levels, and system fee structures. Payouts are made exclusively in Kenya Shillings (KES) to valid M-Pesa lines.</p>

      <h3>5. Termination</h3>
      <p>We reserve the right to suspend or terminate accounts engaging in fraud, automated script bots, answer manipulation, or harassment without prior notice.</p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
