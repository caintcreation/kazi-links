<?php
// privacy.php - Privacy Policy
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Privacy Policy";
require_once __DIR__ . '/includes/header.php';
$siteName = getSetting('site_name', 'KaziLink Kenya');
?>

<div class="container" style="max-width: 800px;">
  <div class="card">
    <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--dark); margin-bottom: 20px;">Privacy Policy</h1>
    <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 24px;">Effective Date: <?= date('F j, Y') ?></p>

    <div style="display: flex; flex-direction: column; gap: 16px; line-height: 1.6; color: var(--dark-light);">
      <h3>1. Information We Collect</h3>
      <p>We collect information provided directly by you, including your email address, phone number (M-Pesa), account credentials, and task completion responses.</p>

      <h3>2. How We Use Information</h3>
      <p>We use your information to facilitate account access, disburse M-Pesa withdrawal payouts, process task grading, prevent spam and duplicate accounts, and comply with Kenyan data regulations.</p>

      <h3>3. Data Protection & Security</h3>
      <p>We implement secure cryptographic password hashing (bcrypt), PDO prepared statements to defend against unauthorized injections, and encrypted session handling. We never sell your personal contact numbers to third-party telemarketers.</p>

      <h3>4. Contact Us</h3>
      <p>For data privacy queries or account deletion requests, please contact our support team at <?= e(getSetting('support_email', 'support@kazilink.co.ke')) ?>.</p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
