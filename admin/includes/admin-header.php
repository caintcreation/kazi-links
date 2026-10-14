<?php
// admin/includes/admin-header.php - Admin Dashboard navigation header
require_once __DIR__ . '/../../includes/admin-auth.php';

$currentAdmin = getLoggedInAdmin();
$siteName = getSetting('site_name', 'KaziLink Kenya');
$adminTitle = $adminTitle ?? 'Administration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($adminTitle) ?> | KaziLink Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    .admin-layout {
      display: flex;
      min-height: 100vh;
    }
    .admin-sidebar {
      width: 250px;
      background: #111827;
      color: #9ca3af;
      padding: 24px 16px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: sticky;
      top: 0;
      height: 100vh;
    }
    .admin-brand {
      color: #fff;
      font-size: 1.25rem;
      font-weight: 800;
      margin-bottom: 24px;
      padding-left: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .admin-nav {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .admin-nav a {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 14px;
      border-radius: var(--radius-md);
      color: #9ca3af;
      font-weight: 500;
      font-size: 0.92rem;
    }
    .admin-nav a:hover, .admin-nav a.active {
      background: #1f2937;
      color: #10b981;
    }
    .admin-main {
      flex: 1;
      padding: 30px;
      background: #f3f4f6;
      overflow-x: hidden;
    }
    .admin-topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
      background: #fff;
      padding: 16px 24px;
      border-radius: var(--radius-lg);
      border: 1px solid var(--border-color);
    }
    @media (max-width: 900px) {
      .admin-layout { flex-direction: column; }
      .admin-sidebar { width: 100%; height: auto; position: static; }
    }
  </style>
</head>
<body>

<div class="admin-layout">
  <!-- Sidebar -->
  <aside class="admin-sidebar">
    <div>
      <div class="admin-brand">
        <span>🇰🇪 KaziLink Admin</span>
      </div>

      <ul class="admin-nav">
        <li>
          <a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Overview
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/admin/users.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'user.php']) ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Users & Wallets
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/admin/withdrawals.php" class="<?= basename($_SERVER['PHP_SELF']) == 'withdrawals.php' ? 'active' : '' ?>">
            <i class="bi bi-cash-coin"></i> Withdrawals
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/admin/jobs.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['jobs.php', 'levels.php']) ? 'active' : '' ?>">
            <i class="bi bi-grid"></i> Tasks & Levels
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/admin/transactions.php" class="<?= basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Ledger Transactions
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/admin/referrals.php" class="<?= basename($_SERVER['PHP_SELF']) == 'referrals.php' ? 'active' : '' ?>">
            <i class="bi bi-diagram-3"></i> Referrals
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/admin/settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i> System Settings
          </a>
        </li>
      </ul>
    </div>

    <div>
      <div style="padding: 10px 14px; font-size: 0.85rem; color: #6b7280; border-top: 1px solid #1f2937; margin-top: 20px;">
        Logged in as: <strong style="color: #fff;"><?= e($currentAdmin['username']) ?></strong>
      </div>
      <a href="<?= BASE_URL ?>/admin/logout.php" class="btn btn-outline btn-sm btn-block" style="color: #ef4444; border-color: #374151; margin-top: 8px;">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
      <a href="<?= BASE_URL ?>/index.php" target="_blank" style="display: block; text-align: center; margin-top: 8px; font-size: 0.82rem; color: #9ca3af;">
        <i class="bi bi-box-arrow-up-right"></i> View Live Site
      </a>
    </div>
  </aside>

  <!-- Main Content Area -->
  <main class="admin-main">
    <div class="admin-topbar">
      <div>
        <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--dark); margin-bottom: 2px;"><?= e($adminTitle) ?></h1>
        <small style="color: var(--text-muted);">Platform Administration Control Center</small>
      </div>

      <div style="display: flex; gap: 12px; align-items: center;">
        <span class="badge badge-success"><i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i> System Live</span>
      </div>
    </div>
