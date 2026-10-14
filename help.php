<?php
// help.php - FAQ and Help documentation
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Help & Frequently Asked Questions";
require_once __DIR__ . '/includes/header.php';

$welcomeReward = getSetting('welcome_reward', '100.00');
$referralReward = getSetting('referral_reward', '100.00');
$minWithdrawal = getSetting('minimum_withdrawal', '2500.00');
$supportEmail = getSetting('support_email', 'support@kazilink.co.ke');
?>

<div class="container" style="max-width: 800px;">
  <div style="text-align: center; margin-bottom: 30px;">
    <span class="hero-pill">🇰🇪 Support Center</span>
    <h1 style="font-size: 2rem; font-weight: 800; color: var(--dark); margin-top: 8px;">Frequently Asked Questions</h1>
    <p style="color: var(--text-muted);">Everything you need to know about completing tasks and receiving M-Pesa payouts.</p>
    <a href="<?= BASE_URL ?>/contact.php" class="btn btn-primary" style="margin-top: 12px;">
      <i class="bi bi-headset"></i> Contact Customer Care
    </a>
  </div>

  <div style="display: flex; flex-direction: column; gap: 16px;">
    
    <div class="card">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--dark); margin-bottom: 8px;">
        1. How do tasks work on KaziLink?
      </h3>
      <p style="color: var(--text-muted); line-height: 1.6;">
        KaziLink provides structured online microtasks across five major categories: Braintest, House Review, Surveys, Chat Moderator, and Online Tutor. Each category contains 6 levels. Complete the available levels in order, and when you achieve a passing score (70%+), your earned reward is automatically credited into your wallet balance.
      </p>
    </div>

    <div class="card">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--dark); margin-bottom: 8px;">
        2. How do I withdraw my earnings to M-Pesa?
      </h3>
      <p style="color: var(--text-muted); line-height: 1.6;">
        Once your available balance reaches the minimum threshold of <strong><?= formatCurrency($minWithdrawal) ?></strong>, navigate to your Wallet page and click "Request Withdrawal". Enter your registered Safaricom M-Pesa phone number and confirm. Our finance team will review and approve the disbursement directly to your phone.
      </p>
    </div>

    <div class="card">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--dark); margin-bottom: 8px;">
        3. How does the referral program work?
      </h3>
      <p style="color: var(--text-muted); line-height: 1.6;">
        Every member receives a unique referral code and link in their Referrals dashboard. When you share this link and a new member registers, you will receive <strong><?= formatCurrency($referralReward) ?></strong>. Self-referrals and fraudulent accounts are strictly disqualified.
      </p>
    </div>

    <div class="card" id="forgot-password">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--dark); margin-bottom: 8px;">
        4. What if I forget my password or need account recovery?
      </h3>
      <p style="color: var(--text-muted); line-height: 1.6;">
        If you lose access to your account, please contact our support team at <a href="mailto:<?= e($supportEmail) ?>"><strong><?= e($supportEmail) ?></strong></a> with your registered email and M-Pesa phone number. Our team will verify your identity and send a secure reset link.
      </p>
    </div>

    <div class="card">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--dark); margin-bottom: 8px;">
        5. What are the rules regarding multiple accounts?
      </h3>
      <p style="color: var(--text-muted); line-height: 1.6;">
        Each member is strictly permitted one account linked to one valid Kenyan National ID/M-Pesa number. Automated bots, answer sharing scripts, or duplicate account farming will result in immediate permanent account termination and forfeiture of balances.
      </p>
    </div>

  </div>

  <div class="card" style="margin-top: 16px; text-align: center;">
    <strong>Need more help?</strong>
    <p style="color: var(--text-muted); margin: 6px 0 12px;">Send a complaint or support request directly to our customer-care team.</p>
    <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline">Send a message</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
