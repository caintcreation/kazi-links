<?php
// admin/settings.php - System & Financial Configuration Hub
require_once __DIR__ . '/../includes/admin-auth.php';
requireAdminAuth();

$admin = getLoggedInAdmin();
$db = getDB();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        $error = "CSRF Token mismatch.";
    } else {
        $submittedSettings = $_POST['settings'] ?? [];
        foreach ($submittedSettings as $key => $val) {
            updateSetting($key, trim($val));
        }

        // Toggles (checkboxes not present when unchecked)
        $checkboxKeys = ['referrals_enabled', 'withdrawals_enabled', 'registration_enabled', 'maintenance_mode'];
        foreach ($checkboxKeys as $cKey) {
            $val = isset($_POST['settings'][$cKey]) ? '1' : '0';
            updateSetting($cKey, $val);
        }

        logAdminAudit($admin['id'], 'settings_updated', 'settings', null, "System settings modified");
        $message = "Configuration settings successfully updated!";
    }
}

// Fetch all settings
$settings = $db->query("SELECT * FROM settings ORDER BY setting_group ASC, id ASC")->fetchAll();
$grouped = [];
foreach ($settings as $s) {
    $grouped[$s['setting_group']][] = $s;
}

$adminTitle = "System & Financial Settings";
require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if ($message): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/admin/settings.php">
  <?= csrfField() ?>

  <!-- Financial Configuration Card -->
  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
      <div>
        <h2 class="card-title">Financial & Reward Rules</h2>
        <small style="color: var(--text-muted);">All reward values and withdrawal thresholds are controlled dynamically from here</small>
      </div>
      <span class="badge badge-success"><i class="bi bi-cash"></i> Ledger Controls</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
      
      <div class="form-group">
        <label class="form-label">Welcome Reward (KES)</label>
        <input type="number" step="0.01" name="settings[welcome_reward]" class="form-control" value="<?= e(getSetting('welcome_reward', '100.00')) ?>" required>
        <div class="form-text">Instant credit for registration without a referral code</div>
      </div>

      <div class="form-group">
        <label class="form-label">Referred Welcome Reward (KES)</label>
        <input type="number" step="0.01" name="settings[welcome_reward_referred]" class="form-control" value="<?= e(getSetting('welcome_reward_referred', '300.00')) ?>" required>
        <div class="form-text">Instant credit for registration with a valid referral code</div>
      </div>

      <div class="form-group">
        <label class="form-label">Referral Reward (KES)</label>
        <input type="number" step="0.01" name="settings[referral_reward]" class="form-control" value="<?= e(getSetting('referral_reward', '100.00')) ?>" required>
        <div class="form-text">Paid to referrer immediately after the referred account is created</div>
      </div>

      <div class="form-group">
        <label class="form-label">Minimum Withdrawal Amount (KES)</label>
        <input type="number" step="0.01" name="settings[minimum_withdrawal]" class="form-control" value="<?= e(getSetting('minimum_withdrawal', '2500.00')) ?>" required>
        <div class="form-text">Minimum balance required to initiate payout request</div>
      </div>

      <div class="form-group">
        <label class="form-label">Withdrawal Percentage Fee (%)</label>
        <input type="number" step="0.01" name="settings[withdrawal_fee_percent]" class="form-control" value="<?= e(getSetting('withdrawal_fee_percent', '2.50')) ?>" required>
        <div class="form-text">Variable processing commission percentage</div>
      </div>

      <div class="form-group">
        <label class="form-label">Withdrawal Fixed Fee (KES)</label>
        <input type="number" step="0.01" name="settings[withdrawal_fee_fixed]" class="form-control" value="<?= e(getSetting('withdrawal_fee_fixed', '10.00')) ?>" required>
        <div class="form-text">Fixed network fee added per transaction</div>
      </div>

    </div>
  </div>

  <!-- General & Platform Info -->
  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
      <div>
        <h2 class="card-title">General Platform Identity</h2>
        <small style="color: var(--text-muted);">Branding and support contact points</small>
      </div>
      <span class="badge badge-info"><i class="bi bi-globe"></i> Identity</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
      
      <div class="form-group">
        <label class="form-label">Site Name</label>
        <input type="text" name="settings[site_name]" class="form-control" value="<?= e(getSetting('site_name', 'KaziLink Kenya')) ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Site Tagline</label>
        <input type="text" name="settings[site_tagline]" class="form-control" value="<?= e(getSetting('site_tagline', 'Simple Online Microtasks & Flexible Rewards in Kenya')) ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Currency Symbol</label>
        <input type="text" name="settings[currency_symbol]" class="form-control" value="<?= e(getSetting('currency_symbol', 'KSh')) ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Support Email</label>
        <input type="email" name="settings[support_email]" class="form-control" value="<?= e(getSetting('support_email', 'support@kazilink.co.ke')) ?>" required>
      </div>

    </div>
  </div>

  <!-- System Switches -->
  <div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
      <div>
        <h2 class="card-title">System Operational Toggles</h2>
        <small style="color: var(--text-muted);">Safely enable or pause system modules</small>
      </div>
      <span class="badge badge-warning"><i class="bi bi-toggle-on"></i> Switches</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
      
      <label style="display: flex; align-items: center; gap: 12px; padding: 14px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer;">
        <input type="checkbox" name="settings[registration_enabled]" value="1" <?= getSetting('registration_enabled', '1') === '1' ? 'checked' : '' ?> style="transform: scale(1.3);">
        <div>
          <div style="font-weight: 600;">Allow Registrations</div>
          <small style="color: var(--text-muted);">Accept new accounts</small>
        </div>
      </label>

      <label style="display: flex; align-items: center; gap: 12px; padding: 14px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer;">
        <input type="checkbox" name="settings[withdrawals_enabled]" value="1" <?= getSetting('withdrawals_enabled', '1') === '1' ? 'checked' : '' ?> style="transform: scale(1.3);">
        <div>
          <div style="font-weight: 600;">Enable Withdrawals</div>
          <small style="color: var(--text-muted);">Permit user payout requests</small>
        </div>
      </label>

      <label style="display: flex; align-items: center; gap: 12px; padding: 14px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer;">
        <input type="checkbox" name="settings[referrals_enabled]" value="1" <?= getSetting('referrals_enabled', '1') === '1' ? 'checked' : '' ?> style="transform: scale(1.3);">
        <div>
          <div style="font-weight: 600;">Enable Referral Program</div>
          <small style="color: var(--text-muted);">Issue affiliate rewards</small>
        </div>
      </label>

      <label style="display: flex; align-items: center; gap: 12px; padding: 14px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer;">
        <input type="checkbox" name="settings[maintenance_mode]" value="1" <?= getSetting('maintenance_mode', '0') === '1' ? 'checked' : '' ?> style="transform: scale(1.3);">
        <div>
          <div style="font-weight: 600; color: var(--danger);">Maintenance Mode</div>
          <small style="color: var(--text-muted);">Lock site for maintenance</small>
        </div>
      </label>

    </div>
  </div>

  <button type="submit" class="btn btn-primary btn-lg" style="margin-bottom: 40px;">
    <i class="bi bi-save"></i> Save All Settings
  </button>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
