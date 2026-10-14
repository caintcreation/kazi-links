<?php
// 404.php - Not Found Error Page
require_once __DIR__ . '/includes/auth.php';
http_response_code(404);
$pageTitle = "Page Not Found";
require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="text-align: center; padding: 60px 16px;">
  <div style="font-size: 4rem; color: var(--danger); font-weight: 800;">404</div>
  <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--dark); margin: 10px 0;">Page Not Found</h1>
  <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 24px;">The page you are looking for may have been moved or does not exist.</p>
  <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary"><i class="bi bi-house"></i> Return Home</a>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
