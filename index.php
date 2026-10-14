<?php
// Enable immediate error reporting
ini_set('display_errors', '1');
error_reporting(E_ALL);

// index.php - Homepage matching Deep Blue palette, Sh 300 referral bonus, and live withdrawal ticker
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "KaziLink — Simple online jobs, paid to M-Pesa";
require_once __DIR__ . '/includes/header.php';

$welcomeBonusRef = (int)getSetting('welcome_reward_referred', '300.00');
$referralReward = (int)getSetting('referral_reward', '100.00');
?>

<div style="min-height: 100vh;">
  <!-- Hero Section with Deep Blue Gradient -->
  <section class="hero-bg">
    <div style="max-width: 64rem; margin: 0 auto; padding: 1.5rem 1.25rem 5rem;">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <a href="<?= BASE_URL ?>/index.php" style="font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; letter-spacing: -0.02em; color: var(--ink-foreground);">
          kazi<span class="text-accent">link</span>jobs
        </a>
        <?php if ($currentUser): ?>
          <a class="btn btn-ghost-light" style="padding: 0.5rem 1.25rem; font-size: 0.875rem;" href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
        <?php else: ?>
          <a class="btn btn-ghost-light" style="padding: 0.5rem 1.25rem; font-size: 0.875rem;" href="<?= BASE_URL ?>/login.php">Log in</a>
        <?php endif; ?>
      </div>

      <div style="margin-top: 4rem; max-width: 42rem;">
        <span class="gold-bg" style="display: inline-block; border-radius: 9999px; padding: 0.25rem 0.75rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
          Kenya 🇰🇪 &middot; Paid to M-Pesa
        </span>

        <h1 style="margin-top: 1.25rem; font-size: 3rem; line-height: 1.02; font-weight: 800;" class="md-heading">
          Kazi rahisi.<br/>Pesa halisi.
        </h1>

        <p style="margin-top: 1.25rem; max-width: 32rem; font-size: 1.125rem; opacity: 0.85;">
          Complete short online jobs from your phone, earn Sh 100 per level, and withdraw straight to your M-Pesa.
        </p>

        <div style="margin-top: 2rem; display: flex; flex-wrap: wrap; gap: 0.75rem;">
          <?php if ($currentUser): ?>
            <a class="btn btn-gold" style="font-size: 1rem;" href="<?= BASE_URL ?>/dashboard.php">Go to Dashboard</a>
            <a class="btn btn-ghost-light" style="font-size: 1rem;" href="<?= BASE_URL ?>/jobs.php">View Available Jobs</a>
          <?php else: ?>
            <a class="btn btn-gold" style="font-size: 1rem;" href="<?= BASE_URL ?>/register.php">Create free account</a>
            <a class="btn btn-ghost-light" style="font-size: 1rem;" href="<?= BASE_URL ?>/login.php">I have an account</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- 3 Benefit Cards Section -->
  <section style="max-width: 64rem; margin: -2.5rem auto 0; padding: 0 1.25rem; display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
    <div class="surface" style="padding: 1.5rem;">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M12 7v14"></path><path d="M20 11v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8"></path><path d="M7.5 7a1 1 0 0 1 0-5A4.8 8 0 0 1 12 7a4.8 8 0 0 1 4.5-5 1 1 0 0 1 0 5"></path><rect x="3" y="7" width="18" height="4" rx="1"></rect></svg>
      <h3 style="margin-top: 0.75rem; font-size: 1.125rem; font-weight: 700;">Sh <?= $welcomeBonusRef ?> welcome bonus</h3>
      <p style="margin-top: 0.25rem; font-size: 0.875rem; color: var(--muted-foreground);">Join with a referral link to get Sh <?= $welcomeBonusRef ?> bonus credited instantly.</p>
    </div>

    <div class="surface" style="padding: 1.5rem;">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><path d="M16 3.128a4 4 0 0 1 0 7.744"></path><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><circle cx="9" cy="7" r="4"></circle></svg>
      <h3 style="margin-top: 0.75rem; font-size: 1.125rem; font-weight: 700;">Sh <?= $referralReward ?> per referral</h3>
      <p style="margin-top: 0.25rem; font-size: 0.875rem; color: var(--muted-foreground);">Every user receives a referral link. Share and earn Sh 100 for every friend who joins.</p>
    </div>

    <div class="surface" style="padding: 1.5rem;">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"></rect><path d="M12 18h.01"></path></svg>
      <h3 style="margin-top: 0.75rem; font-size: 1.125rem; font-weight: 700;">Withdraw to M-Pesa</h3>
      <p style="margin-top: 0.25rem; font-size: 0.875rem; color: var(--muted-foreground);">Direct disbursement straight to your Safaricom M-Pesa phone number.</p>
    </div>
  </section>

  <!-- 5 Jobs 6 Levels Each Section -->
  <section style="max-width: 64rem; margin: 0 auto; padding: 5rem 1.25rem;">
    <h2 style="font-size: 2rem; font-weight: 800;">Five jobs. Six levels each.</h2>
    <p style="margin-top: 0.5rem; max-width: 36rem; color: var(--muted-foreground);">Answer 10 simple questions per level, progress through each project, and earn rewards for successful completion.</p>

    <div style="margin-top: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;">
      
      <!-- Braintest -->
      <a href="<?= BASE_URL ?>/job.php?slug=braintest" class="surface" style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.75rem; padding: 1.25rem; transition: transform 0.15s;">
        <span style="border-radius: 0.75rem; background: var(--secondary); padding: 0.625rem; display: inline-flex;">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary" style="width: 1.25rem; height: 1.25rem;"><path d="M12 18V5"></path><path d="M15 13a4.17 4.17 0 0 1-3-4 4.17 4.17 0 0 1-3 4"></path><path d="M17.598 6.5A3 3 0 1 0 12 5a3 3 0 1 0-5.598 1.5"></path><path d="M17.997 5.125a4 4 0 0 1 2.526 5.77"></path><path d="M18 18a4 4 0 0 0 2-7.464"></path><path d="M19.967 17.483A4 4 0 1 1 12 18a4 4 0 1 1-7.967-.517"></path><path d="M6 18a4 4 0 0 1-2-7.464"></path><path d="M6.003 5.125a4 4 0 0 0-2.526 5.77"></path></svg>
        </span>
        <span style="font-weight: 600; font-size: 1.05rem;">Braintest</span>
        <small style="color: var(--muted-foreground);">6 Levels &middot; Earn Sh 100/lvl</small>
      </a>

      <!-- House Review -->
      <a href="<?= BASE_URL ?>/job.php?slug=house-review" class="surface" style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.75rem; padding: 1.25rem; transition: transform 0.15s;">
        <span style="border-radius: 0.75rem; background: var(--secondary); padding: 0.625rem; display: inline-flex;">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary" style="width: 1.25rem; height: 1.25rem;"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
        </span>
        <span style="font-weight: 600; font-size: 1.05rem;">House Review</span>
        <small style="color: var(--muted-foreground);">6 Levels &middot; Earn Sh 100/lvl</small>
      </a>

      <!-- Surveys -->
      <a href="<?= BASE_URL ?>/job.php?slug=surveys" class="surface" style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.75rem; padding: 1.25rem; transition: transform 0.15s;">
        <span style="border-radius: 0.75rem; background: var(--secondary); padding: 0.625rem; display: inline-flex;">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary" style="width: 1.25rem; height: 1.25rem;"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>
        </span>
        <span style="font-weight: 600; font-size: 1.05rem;">Surveys</span>
        <small style="color: var(--muted-foreground);">6 Levels &middot; Earn Sh 100/lvl</small>
      </a>

      <!-- Chat Moderator -->
      <a href="<?= BASE_URL ?>/job.php?slug=chat-moderator" class="surface" style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.75rem; padding: 1.25rem; transition: transform 0.15s;">
        <span style="border-radius: 0.75rem; background: var(--secondary); padding: 0.625rem; display: inline-flex;">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary" style="width: 1.25rem; height: 1.25rem;"><path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"></path></svg>
        </span>
        <span style="font-weight: 600; font-size: 1.05rem;">Chat Moderator</span>
        <small style="color: var(--muted-foreground);">6 Levels &middot; Earn Sh 100/lvl</small>
      </a>

      <!-- Online Tutor -->
      <a href="<?= BASE_URL ?>/job.php?slug=online-tutor" class="surface" style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.75rem; padding: 1.25rem; transition: transform 0.15s;">
        <span style="border-radius: 0.75rem; background: var(--secondary); padding: 0.625rem; display: inline-flex;">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary" style="width: 1.25rem; height: 1.25rem;"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"></path><path d="M22 10v6"></path><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"></path></svg>
        </span>
        <span style="font-weight: 600; font-size: 1.05rem;">Online Tutor</span>
        <small style="color: var(--muted-foreground);">6 Levels &middot; Earn Sh 100/lvl</small>
      </a>

    </div>
  </section>
</div>

<style>
@media (min-width: 768px) {
  .md-heading {
    font-size: 4.5rem !important;
  }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
