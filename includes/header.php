<?php
// includes/header.php - Global Navigation matching reference site with Live Payout Ticker
require_once __DIR__ . '/auth.php';

$currentUser = getLoggedInUser();
$siteName = getSetting('site_name', 'KaziLink');
$pageTitle = $pageTitle ?? 'KaziLink — Simple online jobs, paid to M-Pesa';
$bodyClass = $currentUser ? 'has-bottom-nav' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="Join KaziLink: get up to a Sh 300 welcome bonus, complete simple jobs, refer friends and withdraw to M-Pesa."/>
  <meta property="og:title" content="<?= e($pageTitle) ?>"/>
  <meta property="og:description" content="Sh 300 welcome bonus, Sh 100 per completed level, Sh 100 per referral. Withdraw to M-Pesa."/>
  <meta property="og:type" content="website"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body class="<?= $bodyClass ?>">

<!-- Live Withdrawal Ticker Bar (authenticated users only) -->
<?php if ($currentUser): ?>
<div class="live-payout-bar">
  <div class="ticker-content" id="livePayoutTicker">
    <span class="live-pulse-dot"></span>
    <span>Live M-Pesa payouts active</span>
  </div>
</div>
<?php endif; ?>

<!-- Global Authenticated Bottom Navigation Bar on Mobile -->
<?php if ($currentUser): ?>
<nav class="bottom-nav">
  <div class="bottom-nav-inner">
    <a href="<?= BASE_URL ?>/dashboard.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2" style="font-size: 1.25rem;"></i>
      <span>Dashboard</span>
    </a>
    <a href="<?= BASE_URL ?>/jobs.php" class="bottom-nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['jobs.php', 'job.php', 'level.php']) ? 'active' : '' ?>">
      <i class="bi bi-grid" style="font-size: 1.25rem;"></i>
      <span>Jobs</span>
    </a>
    <a href="<?= BASE_URL ?>/wallet.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'wallet.php' ? 'active' : '' ?>">
      <i class="bi bi-wallet2" style="font-size: 1.25rem;"></i>
      <span>Wallet</span>
    </a>
    <a href="<?= BASE_URL ?>/referrals.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'referrals.php' ? 'active' : '' ?>">
      <i class="bi bi-people" style="font-size: 1.25rem;"></i>
      <span>Referrals</span>
    </a>
    <a href="<?= BASE_URL ?>/contact.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '' ?>">
      <i class="bi bi-headset" style="font-size: 1.25rem;"></i>
      <span>Support</span>
    </a>
  </div>
</nav>
<?php endif; ?>
